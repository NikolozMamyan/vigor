import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'name', 'search', 'exerciseOptions', 'selectedList', 'emptySelection', 'counter'];
    static values = {
        exercises: Array,
    };

    connect() {
        this.selectedExercises = new Map();
        this.renderExerciseOptions();
        this.renderSelectedExercises();
    }

    openCreate() {
        this.modalTarget.classList.remove('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.add('opacity-100');
        document.body.classList.add('overflow-hidden');
        window.setTimeout(() => this.nameTarget?.focus(), 80);
        this.renderExerciseOptions();
    }

    closeCreate() {
        this.modalTarget.classList.add('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.remove('opacity-100');
        document.body.classList.remove('overflow-hidden');
    }

    async create(event) {
        const button = event.currentTarget;
        const name = this.nameTarget.value.trim();
        const exercises = this.payloadExercises();

        if (name.length < 2) {
            this.nameTarget.classList.add('border-rose-500/50');
            return;
        }

        this.nameTarget.classList.remove('border-rose-500/50');

        if (exercises.length === 0) {
            this.selectedListTarget.classList.add('border', 'border-rose-500/50', 'rounded-2xl');
            return;
        }

        this.selectedListTarget.classList.remove('border', 'border-rose-500/50', 'rounded-2xl');
        this.setBusy(button, true);

        const program = await this.request('/api/workout-programs', 'POST', { name, exercises });

        if (program) {
            window.location.reload();
            return;
        }

        this.setBusy(button, false);
    }

    async delete(event) {
        const button = event.currentTarget;
        const programId = button.dataset.programId;

        if (!programId) {
            return;
        }

        this.setBusy(button, true);

        const deleted = await this.request(`/api/workout-programs/${programId}`, 'DELETE');

        if (deleted) {
            this.element.querySelector(`[data-program-row-id="${programId}"]`)?.remove();
            return;
        }

        this.setBusy(button, false);
    }

    async start(event) {
        const button = event.currentTarget;
        const programId = button.dataset.programId;

        if (!programId) {
            return;
        }

        this.setBusy(button, true);
        const session = await this.request(`/api/workout-programs/${programId}/start`, 'POST');

        if (session) {
            window.location.href = '/app/workout';
            return;
        }

        this.setBusy(button, false);
    }

    async startFree(event) {
        const button = event.currentTarget;
        this.setBusy(button, true);

        const session = await this.request('/api/workout-sessions/free', 'POST');

        if (session) {
            window.location.href = '/app/workout';
            return;
        }

        this.setBusy(button, false);
    }

    setBusy(button, busy) {
        button.disabled = busy;
        button.classList.toggle('opacity-70', busy);
    }

    filterExercises() {
        this.renderExerciseOptions();
    }

    toggleExercise(event) {
        const id = Number.parseInt(event.currentTarget.dataset.exerciseId, 10);
        const exercise = this.exercisesValue.find((item) => item.id === id);

        if (!exercise) {
            return;
        }

        if (this.selectedExercises.has(id)) {
            this.selectedExercises.delete(id);
        } else {
            this.selectedExercises.set(id, {
                exercise,
                targetWeight: '',
                targetSets: 3,
                targetRepsMin: 8,
                targetRepsMax: 10,
                restSeconds: 90,
            });
        }

        this.renderExerciseOptions();
        this.renderSelectedExercises();
    }

    updateSelected(event) {
        const row = event.currentTarget.closest('[data-selected-exercise-id]');
        const id = Number.parseInt(row?.dataset.selectedExerciseId, 10);
        const selected = this.selectedExercises.get(id);

        if (!selected) {
            return;
        }

        selected[event.currentTarget.dataset.field] = event.currentTarget.value;
    }

    removeSelected(event) {
        const id = Number.parseInt(event.currentTarget.dataset.exerciseId, 10);
        this.selectedExercises.delete(id);
        this.renderExerciseOptions();
        this.renderSelectedExercises();
    }

    renderExerciseOptions() {
        if (!this.hasExerciseOptionsTarget) {
            return;
        }

        const query = this.hasSearchTarget ? this.searchTarget.value.trim().toLowerCase() : '';
        const exercises = this.exercisesValue
            .filter((exercise) => Number.isFinite(exercise.id))
            .filter((exercise) => {
                if (!query) {
                    return true;
                }

                return `${exercise.name} ${exercise.category} ${exercise.tag}`.toLowerCase().includes(query);
            })
            .slice(0, 30);

        this.exerciseOptionsTarget.innerHTML = exercises.map((exercise) => this.exerciseOption(exercise)).join('');

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    exerciseOption(exercise) {
        const selected = this.selectedExercises.has(exercise.id);

        return `
            <button type="button" class="w-full rounded-2xl ${selected ? 'bg-app-accent text-black border-app-accent shadow-neon' : 'bg-black/20 text-white border-white/10'} border p-3 flex items-center gap-3 text-left transition-colors" data-action="workout-programs#toggleExercise" data-exercise-id="${exercise.id}">
                <span class="w-10 h-10 rounded-xl ${selected ? 'bg-black/10' : 'bg-white/[0.06] border border-white/10'} flex items-center justify-center">
                    <i data-lucide="${selected ? 'check' : 'plus'}" class="w-4 h-4"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-extrabold truncate">${this.escapeHtml(exercise.name)}</span>
                    <span class="block text-[10px] ${selected ? 'text-black/60' : 'text-app-muted'} uppercase font-bold tracking-wider">${this.escapeHtml(exercise.category)} - ${this.escapeHtml(exercise.tag)}</span>
                </span>
            </button>
        `;
    }

    renderSelectedExercises() {
        if (!this.hasSelectedListTarget) {
            return;
        }

        const selected = Array.from(this.selectedExercises.values());
        this.emptySelectionTarget.classList.toggle('hidden', selected.length > 0);
        this.counterTarget.textContent = `${selected.length} selection${selected.length > 1 ? 's' : ''}`;

        this.selectedListTarget.querySelectorAll('[data-selected-exercise-id]').forEach((row) => row.remove());
        this.selectedListTarget.insertAdjacentHTML('beforeend', selected.map((item) => this.selectedTemplate(item)).join(''));

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    selectedTemplate(item) {
        const exercise = item.exercise;

        return `
            <div class="rounded-3xl bg-black/20 border border-white/10 p-4 space-y-4" data-selected-exercise-id="${exercise.id}">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl bg-app-accent/10 border border-app-accent/20 flex items-center justify-center">
                        <i data-lucide="dumbbell" class="w-4 h-4 text-app-accent"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-extrabold text-white truncate">${this.escapeHtml(exercise.name)}</span>
                        <span class="block text-[10px] text-app-muted uppercase font-bold tracking-wider">${this.escapeHtml(exercise.category)}</span>
                    </span>
                    <button type="button" class="w-8 h-8 rounded-xl bg-white/5 text-app-muted hover:text-rose-400 flex items-center justify-center" data-action="workout-programs#removeSelected" data-exercise-id="${exercise.id}" aria-label="Retirer ${this.escapeAttribute(exercise.name)}">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <div class="grid grid-cols-4 gap-2">
                    ${this.numberInput('kg', 'targetWeight', item.targetWeight, '0')}
                    ${this.numberInput('sets', 'targetSets', item.targetSets, '3')}
                    ${this.numberInput('min', 'targetRepsMin', item.targetRepsMin, '8')}
                    ${this.numberInput('max', 'targetRepsMax', item.targetRepsMax, '10')}
                </div>
                <label class="block">
                    <span class="block text-[9px] text-app-muted uppercase font-bold tracking-wider mb-1">Repos secondes</span>
                    <input type="number" min="1" value="${item.restSeconds}" class="w-full rounded-2xl bg-white/[0.06] border border-white/10 px-3 py-3 text-sm font-bold text-white focus:outline-none focus:border-app-accent" data-field="restSeconds" data-action="input->workout-programs#updateSelected">
                </label>
            </div>
        `;
    }

    numberInput(label, field, value, placeholder) {
        return `
            <label class="block">
                <span class="block text-[9px] text-app-muted uppercase font-bold tracking-wider mb-1">${label}</span>
                <input type="number" min="0" value="${this.escapeAttribute(value)}" placeholder="${placeholder}" class="w-full rounded-2xl bg-white/[0.06] border border-white/10 px-2 py-3 text-sm font-bold text-white text-center focus:outline-none focus:border-app-accent" data-field="${field}" data-action="input->workout-programs#updateSelected">
            </label>
        `;
    }

    payloadExercises() {
        return Array.from(this.selectedExercises.values()).map((item) => ({
            exerciseId: item.exercise.id,
            targetWeight: item.targetWeight,
            targetSets: Number.parseInt(item.targetSets, 10),
            targetRepsMin: Number.parseInt(item.targetRepsMin, 10),
            targetRepsMax: Number.parseInt(item.targetRepsMax, 10),
            restSeconds: Number.parseInt(item.restSeconds, 10),
        }));
    }

    async request(url, method, payload = null) {
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: payload ? JSON.stringify(payload) : null,
            });

            if (!response.ok) {
                return null;
            }

            if (response.status === 204) {
                return {};
            }

            return response.json();
        } catch {
            return null;
        }
    }

    escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    escapeAttribute(value) {
        return this.escapeHtml(value);
    }
}
