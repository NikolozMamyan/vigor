import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sets', 'exerciseModal', 'exerciseSearch', 'exerciseOptions', 'heroImage', 'exerciseMeta', 'headerTitle', 'headerSubtitle', 'targetLabel', 'exerciseStrip'];
    static values = {
        sessionId: Number,
        sessionExerciseId: Number,
        exercises: Array,
    };

    connect() {
        this.renderExerciseOptions();
    }

    addSet() {
        const nextPosition = this.nextPosition();
        this.setsTarget.insertAdjacentHTML('beforeend', this.template(nextPosition));

        const addedRow = this.setsTarget.lastElementChild;
        addedRow?.querySelector('input')?.focus();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    nextPosition() {
        const positions = Array.from(this.setsTarget.querySelectorAll('[data-workout-set-position-value]'))
            .map((row) => Number.parseInt(row.dataset.workoutSetPositionValue, 10))
            .filter((position) => Number.isFinite(position));

        return positions.length > 0 ? Math.max(...positions) + 1 : 1;
    }

    template(position) {
        return `
            <div
                class="set-row glass-panel rounded-2xl p-4 pr-10 flex items-center justify-between group relative"
                data-controller="workout-set"
                data-workout-set-id-value="0"
                data-workout-set-session-exercise-id-value="${this.sessionExerciseIdValue}"
                data-workout-set-position-value="${position}"
            >
                <button type="button" class="absolute top-2 right-2 w-7 h-7 rounded-full text-app-muted/70 hover:text-white hover:bg-white/10 flex items-center justify-center transition-colors" aria-label="Effacer la serie ${position}" data-action="workout-set#remove">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
                <div class="flex items-center gap-4">
                    <div class="w-8 h-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-sm font-bold text-app-muted group-hover:text-white transition-colors">${position}</div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-app-muted uppercase font-semibold mb-1">PRC: A completer</span>
                        <div class="flex items-baseline gap-1">
                            <input type="number" inputmode="decimal" placeholder="80" aria-label="Poids serie ${position}" class="data-input w-14 text-2xl font-extrabold text-white text-center pb-1" data-workout-set-target="weight" data-action="input->workout-set#scheduleSave change->workout-set#save">
                            <span class="text-app-muted font-medium text-sm mr-2">kg</span>
                            <span class="text-white/30 text-lg mx-1">x</span>
                            <input type="number" inputmode="numeric" placeholder="8" aria-label="Repetitions serie ${position}" class="data-input w-12 text-2xl font-extrabold text-white text-center pb-1" data-workout-set-target="reps" data-action="input->workout-set#scheduleSave change->workout-set#save">
                            <span class="text-app-muted font-medium text-sm">reps</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="check-btn w-12 h-12 rounded-2xl border-2 border-white/10 bg-white/5 flex items-center justify-center relative overflow-hidden" data-action="workout-set#complete">
                    <i data-lucide="check" class="check-icon w-6 h-6 text-transparent relative z-10 transition-colors duration-300"></i>
                </button>
            </div>
        `;
    }

    async completeSession(event) {
        await this.updateSession(event.currentTarget, 'complete');
    }

    async cancelSession(event) {
        await this.updateSession(event.currentTarget, 'cancel');
    }

    openExerciseModal() {
        this.exerciseModalTarget.classList.remove('opacity-0', 'pointer-events-none');
        this.exerciseModalTarget.classList.add('opacity-100');
        document.body.classList.add('overflow-hidden');
        this.renderExerciseOptions();
        window.setTimeout(() => this.exerciseSearchTarget?.focus(), 80);
    }

    closeExerciseModal() {
        this.exerciseModalTarget.classList.add('opacity-0', 'pointer-events-none');
        this.exerciseModalTarget.classList.remove('opacity-100');
        document.body.classList.remove('overflow-hidden');
    }

    renderExerciseOptions() {
        if (!this.hasExerciseOptionsTarget) {
            return;
        }

        const query = this.hasExerciseSearchTarget ? this.exerciseSearchTarget.value.trim().toLowerCase() : '';
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
        return `
            <button type="button" class="w-full rounded-2xl bg-white/[0.06] border border-white/10 p-3 flex items-center gap-3 text-left hover:border-app-accent/50 hover:bg-white/10 transition-colors" data-action="active-workout#addExercise" data-exercise-id="${exercise.id}">
                <img src="${this.escapeAttribute(exercise.image)}" alt="" class="w-12 h-12 rounded-2xl object-cover border border-white/10">
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-extrabold text-white truncate">${this.escapeHtml(exercise.name)}</span>
                    <span class="block text-[10px] text-app-muted uppercase font-bold tracking-wider">${this.escapeHtml(exercise.category)} - ${this.escapeHtml(exercise.tag)}</span>
                </span>
                <span class="w-9 h-9 rounded-xl bg-white/5 flex items-center justify-center text-white">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                </span>
            </button>
        `;
    }

    async addExercise(event) {
        const button = event.currentTarget;
        const exerciseId = Number.parseInt(button.dataset.exerciseId, 10);

        if (!this.hasSessionIdValue || this.sessionIdValue <= 0 || !Number.isFinite(exerciseId)) {
            return;
        }

        button.disabled = true;
        button.classList.add('opacity-70');

        try {
            const response = await fetch(`/api/workout-sessions/${this.sessionIdValue}/exercises`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ exerciseId }),
            });

            if (response.ok) {
                const sessionExercise = await response.json();
                this.closeExerciseModal();
                this.appendExercisePill(sessionExercise);
                this.applySessionExercise(sessionExercise);
                return;
            }
        } catch {
        }

        button.disabled = false;
        button.classList.remove('opacity-70');
    }

    async switchExercise(event) {
        const button = event.currentTarget;
        const sessionExerciseId = Number.parseInt(button.dataset.sessionExerciseId, 10);

        if (!Number.isFinite(sessionExerciseId) || sessionExerciseId === this.sessionExerciseIdValue) {
            return;
        }

        button.disabled = true;
        button.classList.add('opacity-70');

        try {
            const response = await fetch(`/api/workout-session-exercises/${sessionExerciseId}`, {
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                this.applySessionExercise(await response.json());
                return;
            }
        } catch {
        } finally {
            button.disabled = false;
            button.classList.remove('opacity-70');
        }
    }

    applySessionExercise(sessionExercise) {
        this.sessionExerciseIdValue = sessionExercise.id;
        this.element.dataset.activeWorkoutSessionExerciseIdValue = sessionExercise.id;
        this.heroImageTarget.src = sessionExercise.image;
        this.exerciseMetaTarget.textContent = `${sessionExercise.muscleGroup} - ${sessionExercise.equipment}`;
        this.headerTitleTarget.textContent = 'Seance libre';
        this.headerSubtitleTarget.textContent = `${sessionExercise.exerciseName} - ${sessionExercise.muscleGroup}`;
        this.targetLabelTarget.textContent = sessionExercise.targetLabel || '3 x 8-10';
        this.renderSets(sessionExercise.sets || []);
        this.markActivePill(sessionExercise.id);
    }

    renderSets(sets) {
        this.setsTarget.innerHTML = sets.map((set) => this.setTemplate(set)).join('');

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    setTemplate(set) {
        return `
            <div
                class="set-row glass-panel rounded-2xl p-4 pr-10 flex items-center justify-between group relative ${set.completed ? 'checked' : ''}"
                data-controller="workout-set"
                data-workout-set-id-value="${set.id || 0}"
                data-workout-set-session-exercise-id-value="${set.sessionExerciseId || this.sessionExerciseIdValue}"
                data-workout-set-position-value="${set.number}"
            >
                <button type="button" class="absolute top-2 right-2 w-7 h-7 rounded-full text-app-muted/70 hover:text-white hover:bg-white/10 flex items-center justify-center transition-colors" aria-label="Effacer la serie ${set.number}" data-action="workout-set#remove">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
                <div class="flex items-center gap-4">
                    <div class="w-8 h-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-sm font-bold text-app-muted group-hover:text-white transition-colors">${set.number}</div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-app-muted uppercase font-semibold mb-1">PRC: ${this.escapeHtml(set.previous || 'A completer')}</span>
                        <div class="flex items-baseline gap-1">
                            <input type="number" inputmode="decimal" value="${set.weight ?? ''}" placeholder="80" aria-label="Poids serie ${set.number}" class="data-input w-14 text-2xl font-extrabold text-white text-center pb-1" data-workout-set-target="weight" data-action="input->workout-set#scheduleSave change->workout-set#save">
                            <span class="text-app-muted font-medium text-sm mr-2">kg</span>
                            <span class="text-white/30 text-lg mx-1">x</span>
                            <input type="number" inputmode="numeric" value="${set.reps ?? ''}" placeholder="8" aria-label="Repetitions serie ${set.number}" class="data-input w-12 text-2xl font-extrabold text-white text-center pb-1" data-workout-set-target="reps" data-action="input->workout-set#scheduleSave change->workout-set#save">
                            <span class="text-app-muted font-medium text-sm">reps</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="check-btn ${set.completed ? 'checked' : ''} w-12 h-12 rounded-2xl border-2 border-white/10 bg-white/5 flex items-center justify-center relative overflow-hidden" data-action="workout-set#complete">
                    <i data-lucide="check" class="check-icon w-6 h-6 text-transparent relative z-10 transition-colors duration-300"></i>
                </button>
            </div>
        `;
    }

    appendExercisePill(sessionExercise) {
        const addButton = this.exerciseStripTarget.querySelector('[data-action="active-workout#openExerciseModal"]');
        addButton.insertAdjacentHTML('beforebegin', `
            <button type="button" class="shrink-0 rounded-2xl p-2 pr-4 text-left border flex items-center gap-2 glass-panel text-white border-white/10" data-action="active-workout#switchExercise" data-session-exercise-id="${sessionExercise.id}">
                <img src="${this.escapeAttribute(sessionExercise.image)}" alt="" class="w-9 h-9 rounded-xl object-cover">
                <span>
                    <span class="block text-xs font-extrabold">${this.escapeHtml(sessionExercise.exerciseName)}</span>
                    <span class="block text-[9px] font-bold uppercase tracking-wider text-app-muted">${this.escapeHtml(sessionExercise.muscleGroup)}</span>
                </span>
            </button>
        `);
    }

    markActivePill(sessionExerciseId) {
        this.exerciseStripTarget.querySelectorAll('[data-session-exercise-id]').forEach((pill) => {
            const active = Number.parseInt(pill.dataset.sessionExerciseId, 10) === sessionExerciseId;
            pill.classList.toggle('bg-app-accent', active);
            pill.classList.toggle('text-black', active);
            pill.classList.toggle('border-app-accent', active);
            pill.classList.toggle('glass-panel', !active);
            pill.classList.toggle('text-white', !active);
            pill.classList.toggle('border-white/10', !active);
        });
    }

    async updateSession(button, action) {
        if (!this.hasSessionIdValue || this.sessionIdValue <= 0) {
            return;
        }

        button.disabled = true;
        button.classList.add('opacity-60');

        try {
            const response = await fetch(`/api/workout-sessions/${this.sessionIdValue}/${action}`, {
                method: 'POST',
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                window.location.href = '/app/home';
                return;
            }
        } catch {
        }

        button.disabled = false;
        button.classList.remove('opacity-60');
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
