import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'name', 'search', 'exerciseOptions', 'selectedList', 'emptySelection', 'counter', 'list'];
    static values = {
        exercises: Array,
    };

    connect() {
        this.selectedExercises = new Map();
        this.currentTab = 'library';
        this.renderExerciseOptions();
        this.renderSelectedExercises();
        this.updateBuilderControls();
    }

    disconnect() {
        this.toggleBuilderShell(false);
    }

    openCreate() {
        this.modalTarget.classList.remove('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.add('opacity-100');
        document.body.classList.add('overflow-hidden');
        this.toggleBuilderShell(true);
        window.setTimeout(() => this.nameTarget?.focus(), 80);
        this.switchBuilderTab('library');
        this.renderExerciseOptions();
    }

    closeCreate() {
        this.modalTarget.classList.add('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.remove('opacity-100');
        document.body.classList.remove('overflow-hidden');
        this.toggleBuilderShell(false);
    }

    toggleBuilderShell(open) {
        this.element.closest('.app-container')?.classList.toggle('workout-builder-open', open);
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
            this.prependProgram(program);
            this.closeCreate();
            this.resetCreateForm();
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
            this.navigateWithRefresh('workout', ['workout', 'home', 'stats']);
            return;
        }

        this.setBusy(button, false);
    }

    async startFree(event) {
        const button = event.currentTarget;
        this.setBusy(button, true);

        const session = await this.request('/api/workout-sessions/free', 'POST');

        if (session) {
            this.navigateWithRefresh('workout', ['workout', 'home', 'stats']);
            return;
        }

        this.setBusy(button, false);
    }

    setBusy(button, busy) {
        button.disabled = busy;
        button.classList.toggle('opacity-70', busy);
    }

    showLibrary() {
        this.switchBuilderTab('library');
    }

    showConfig() {
        this.switchBuilderTab('config');
        this.renderSelectedExercises();
    }

    mainAction(event) {
        if (this.currentTab === 'library') {
            this.showConfig();
            return;
        }

        if (this.selectedExercises.size === 0) {
            this.showLibrary();
            return;
        }

        this.create(event);
    }

    switchBuilderTab(tab) {
        this.currentTab = tab;

        this.element.querySelectorAll('[data-builder-tab]').forEach((panel) => {
            panel.classList.toggle('active', panel.dataset.builderTab === tab);
        });

        this.element.querySelectorAll('[data-builder-tab-button]').forEach((button) => {
            const active = button.dataset.builderTabButton === tab;
            button.classList.toggle('text-white', active);
            button.classList.toggle('text-app-muted', !active);
        });

        const indicator = this.element.querySelector('.builder-tab-indicator');

        if (indicator) {
            indicator.style.left = tab === 'library' ? '0.25rem' : 'calc(50% - 0.25rem)';
        }

        this.updateBuilderControls();
    }

    navigateWithRefresh(nextView, views) {
        this.closeCreate();
        this.element.dispatchEvent(new CustomEvent('vigor:refresh-views', {
            detail: {
                views,
                nextView,
                path: `/app/${nextView}`,
            },
            bubbles: true,
        }));
        window.history.pushState({ view: nextView }, '', `/app/${nextView}`);
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
        this.updateBuilderControls();

        if (navigator.vibrate) {
            navigator.vibrate(35);
        }
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
        this.updateBuilderControls();
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
        this.renderFilterChips();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    exerciseOption(exercise) {
        const selected = this.selectedExercises.has(exercise.id);

        return `
            <button type="button" class="builder-exercise-card relative rounded-2xl overflow-hidden text-left group ${selected ? 'ring-2 ring-app-accent ring-offset-2 ring-offset-[#050505]' : ''}" data-action="workout-programs#toggleExercise" data-exercise-id="${exercise.id}" aria-pressed="${selected ? 'true' : 'false'}">
                <img src="${this.escapeAttribute(exercise.image)}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:opacity-80 transition-opacity">
                <span class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/25 to-transparent"></span>
                <span class="absolute top-2 right-2 w-7 h-7 rounded-full flex items-center justify-center transition-all duration-300 ${selected ? 'bg-app-accent text-black scale-110' : 'glass-panel text-white'}">
                    <i data-lucide="${selected ? 'check' : 'plus'}" class="w-4 h-4"></i>
                </span>
                <span class="absolute bottom-3 left-3 right-3">
                    <span class="block text-[10px] font-bold text-app-muted uppercase tracking-wider mb-0.5">${this.escapeHtml(exercise.category)}</span>
                    <span class="block text-sm font-extrabold text-white leading-tight">${this.escapeHtml(exercise.name)}</span>
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
        this.renderFilterChips();

        this.selectedListTarget.innerHTML = selected.map((item) => this.selectedTemplate(item)).join('');
        this.updateBuilderControls();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    selectedTemplate(item) {
        const exercise = item.exercise;

        return `
            <div class="glass-panel rounded-3xl p-4 transition-all relative overflow-hidden" data-selected-exercise-id="${exercise.id}">
                <div class="flex items-center gap-4 mb-4">
                    <img src="${this.escapeAttribute(exercise.image)}" alt="" class="w-12 h-12 rounded-xl object-cover opacity-80 shrink-0">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-extrabold text-white truncate">${this.escapeHtml(exercise.name)}</span>
                        <span class="block text-[10px] text-app-muted uppercase font-bold tracking-wider">${this.escapeHtml(exercise.category)}</span>
                    </span>
                    <button type="button" class="w-8 h-8 rounded-full bg-white/5 text-app-muted hover:text-rose-400 hover:bg-rose-500/10 flex items-center justify-center transition-colors" data-action="workout-programs#removeSelected" data-exercise-id="${exercise.id}" aria-label="Retirer ${this.escapeAttribute(exercise.name)}">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-2 bg-black/40 rounded-2xl p-2 border border-white/5">
                    ${this.numberInput('Series', 'targetSets', item.targetSets, '3')}
                    ${this.repsInput(item)}
                    ${this.numberInput('Repos', 'restSeconds', item.restSeconds, '90', 's')}
                </div>
            </div>
        `;
    }

    numberInput(label, field, value, placeholder, suffix = '') {
        return `
            <label class="block text-center p-2 rounded-xl hover:bg-white/5 transition-colors">
                <span class="block text-[9px] text-app-muted font-bold uppercase tracking-widest mb-1">${label}</span>
                <span class="flex items-baseline justify-center gap-0.5">
                    <input type="number" min="0" value="${this.escapeAttribute(value)}" placeholder="${placeholder}" class="neo-input text-2xl font-extrabold w-12" data-field="${field}" data-action="input->workout-programs#updateSelected">
                    ${suffix ? `<span class="text-[10px] text-app-muted font-bold">${suffix}</span>` : ''}
                </span>
            </label>
        `;
    }

    repsInput(item) {
        return `
            <label class="block text-center p-2 rounded-xl hover:bg-white/5 transition-colors border-x border-white/5">
                <span class="block text-[9px] text-app-muted font-bold uppercase tracking-widest mb-1">Reps</span>
                <span class="flex items-center justify-center gap-1">
                    <input type="number" min="0" value="${this.escapeAttribute(item.targetRepsMin)}" placeholder="8" class="neo-input text-2xl font-extrabold w-8" data-field="targetRepsMin" data-action="input->workout-programs#updateSelected">
                    <span class="text-white/30 font-bold">-</span>
                    <input type="number" min="0" value="${this.escapeAttribute(item.targetRepsMax)}" placeholder="10" class="neo-input text-2xl font-extrabold w-8" data-field="targetRepsMax" data-action="input->workout-programs#updateSelected">
                </span>
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

    prependProgram(program) {
        this.listTarget?.insertAdjacentHTML('afterbegin', this.programCard(program));

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    programCard(program) {
        const exercises = (program.exercises || []).map((exercise) => `
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0 flex items-center gap-2">
                    <img src="${this.escapeAttribute(exercise.image)}" alt="" class="w-8 h-8 rounded-xl object-cover border border-white/10">
                    <span class="min-w-0">
                        <span class="block text-xs font-bold text-white truncate">${this.escapeHtml(exercise.name)}</span>
                        <span class="block text-[10px] text-app-muted uppercase font-bold tracking-wider">${this.escapeHtml(exercise.muscleGroup)}</span>
                    </span>
                </div>
                <span class="text-[10px] font-bold text-app-accent whitespace-nowrap">${this.escapeHtml(exercise.target)}</span>
            </div>
        `).join('');

        return `
            <article class="glass-panel rounded-2xl p-4 space-y-3" data-program-row-id="${program.id}">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-app-accent/10 border border-app-accent/20 flex items-center justify-center">
                        <i data-lucide="calendar-days" class="w-5 h-5 text-app-accent"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-extrabold text-white truncate">${this.escapeHtml(program.name)}</h4>
                        <p class="text-[11px] text-app-muted">${this.escapeHtml(program.description || 'Programme personnalise')} - ${this.escapeHtml(program.meta || '')}</p>
                    </div>
                    <button type="button" class="w-10 h-10 rounded-2xl bg-white/5 text-app-muted hover:text-rose-400 hover:bg-rose-500/10 flex items-center justify-center border border-white/10" data-action="workout-programs#delete" data-program-id="${program.id}" aria-label="Supprimer ${this.escapeAttribute(program.name)}">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                    <button type="button" class="w-11 h-11 rounded-2xl bg-app-accent text-black flex items-center justify-center shadow-neon" data-action="workout-programs#start" data-program-id="${program.id}" aria-label="Demarrer ${this.escapeAttribute(program.name)}">
                        <i data-lucide="play" class="w-5 h-5 fill-current ml-0.5"></i>
                    </button>
                </div>
                <div class="space-y-2 border-t border-white/5 pt-3">${exercises}</div>
            </article>
        `;
    }

    resetCreateForm() {
        this.nameTarget.value = 'Push Day - Force';
        this.searchTarget.value = '';
        this.selectedExercises.clear();
        this.switchBuilderTab('library');
        this.renderExerciseOptions();
        this.renderSelectedExercises();
    }

    renderFilterChips() {
        if (!this.hasCounterTarget) {
            return;
        }

        const count = this.selectedExercises.size;
        const categories = [...new Set(this.exercisesValue.map((exercise) => exercise.category).filter(Boolean))].slice(0, 4);

        this.counterTarget.innerHTML = [
            `<button type="button" class="shrink-0 px-4 py-1.5 rounded-full bg-white/10 text-white text-xs font-bold border border-white/10">${count} selection${count > 1 ? 's' : ''}</button>`,
            ...categories.map((category) => `<button type="button" class="shrink-0 px-4 py-1.5 rounded-full glass-panel text-app-muted text-xs font-bold">${this.escapeHtml(category)}</button>`),
        ].join('');
    }

    updateBuilderControls() {
        const count = this.selectedExercises?.size || 0;
        const badge = this.element.querySelector('.builder-config-badge');
        const floatingCounter = this.element.querySelector('.builder-floating-counter');
        const floatingText = this.element.querySelector('.builder-floating-text');
        const mainAction = this.element.querySelector('.builder-main-action');

        if (badge) {
            badge.textContent = count;
            badge.classList.toggle('hidden', count === 0);
        }

        if (floatingCounter) {
            floatingCounter.textContent = count;
            floatingCounter.classList.toggle('scale-0', count === 0);
            floatingCounter.classList.toggle('scale-100', count > 0);
        }

        if (floatingText) {
            floatingText.textContent = `${count} exercice${count > 1 ? 's' : ''}`;
        }

        if (!mainAction) {
            return;
        }

        mainAction.textContent = this.currentTab === 'library' ? 'Configurer' : 'Enregistrer';
        const active = count > 0;
        mainAction.classList.toggle('bg-app-accent', this.currentTab === 'library' && active);
        mainAction.classList.toggle('text-black', active);
        mainAction.classList.toggle('shadow-neon', this.currentTab === 'library' && active);
        mainAction.classList.toggle('bg-white', this.currentTab === 'config' && active);
        mainAction.classList.toggle('bg-white/10', !active);
        mainAction.classList.toggle('text-white', !active);
    }
}
