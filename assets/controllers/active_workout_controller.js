import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sets'];
    static values = {
        sessionId: Number,
        sessionExerciseId: Number,
    };

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
}
