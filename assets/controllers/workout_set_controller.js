import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['weight', 'reps'];
    static values = {
        id: Number,
        sessionExerciseId: Number,
        position: Number,
    };

    connect() {
        this.saveTimeout = null;
        this.createPromise = null;
        this.removed = false;
    }

    disconnect() {
        window.clearTimeout(this.saveTimeout);
    }

    scheduleSave() {
        window.clearTimeout(this.saveTimeout);
        this.saveTimeout = window.setTimeout(() => this.save(), 350);
    }

    async save() {
        const payload = this.payload();

        if (!payload) {
            return;
        }

        const set = await this.ensureSet(payload);

        if (!set) {
            return;
        }

        await this.request(`/api/workout-sets/${this.idValue}`, 'PATCH', payload);
    }

    async complete(event) {
        const button = event.currentTarget;
        const nextChecked = !button.classList.contains('checked');

        this.applyChecked(button, nextChecked);

        if (!nextChecked) {
            this.element.dispatchEvent(new CustomEvent('workout-set:timer-close', { bubbles: true }));
            return;
        }

        const payload = this.payload();

        if (!payload) {
            this.applyChecked(button, false);
            return;
        }

        this.element.classList.add('is-syncing');
        this.element.dispatchEvent(new CustomEvent('workout-set:timer-start', {
            detail: this.timerDetail(),
            bubbles: true,
        }));

        const set = await this.ensureSet(payload);

        if (!set) {
            this.rollback(button);
            return;
        }

        const completed = await this.request(`/api/workout-sets/${this.idValue}/complete`, 'POST', payload);

        if (!completed) {
            this.rollback(button);
            return;
        }

        this.element.classList.remove('is-syncing', 'border-rose-500/50');
        this.element.classList.toggle('record-created', Boolean(completed.recordCreated));
    }

    async remove() {
        this.removed = true;
        window.clearTimeout(this.saveTimeout);
        const block = this.element.closest('[data-workout-set-block]');
        (block || this.element).remove();

        let setId = this.hasIdValue ? this.idValue : 0;

        if (setId <= 0 && this.createPromise) {
            const created = await this.createPromise;
            setId = created?.id ?? 0;
        }

        if (setId > 0) {
            await this.request(`/api/workout-sets/${setId}`, 'DELETE');
        }
    }

    async ensureSet(payload) {
        if (this.removed) {
            return null;
        }

        if (this.hasIdValue && this.idValue > 0) {
            return { id: this.idValue };
        }

        if (this.createPromise) {
            return this.createPromise;
        }

        if (!this.hasSessionExerciseIdValue || this.sessionExerciseIdValue <= 0) {
            this.element.classList.add('border-rose-500/50');
            return null;
        }

        this.createPromise = this.request(`/api/workout-session-exercises/${this.sessionExerciseIdValue}/sets`, 'POST', {
            ...payload,
            position: this.positionValue,
        });

        let created = null;

        try {
            created = await this.createPromise;
        } finally {
            this.createPromise = null;
        }

        if (!created) {
            return null;
        }

        if (this.removed) {
            return null;
        }

        this.idValue = created.id;
        this.element.dataset.workoutSetIdValue = created.id;

        return created;
    }

    applyChecked(button, checked) {
        button.classList.toggle('checked', checked);
        this.element.classList.toggle('checked', checked);
    }

    rollback(button) {
        this.element.classList.remove('is-syncing');
        this.element.classList.add('border-rose-500/50');
        this.applyChecked(button, false);
        this.element.dispatchEvent(new CustomEvent('workout-set:timer-close', { bubbles: true }));
    }

    payload() {
        const weight = Number.parseFloat(this.weightTarget.value);
        const reps = Number.parseInt(this.repsTarget.value, 10);

        if (!Number.isFinite(weight) || !Number.isFinite(reps) || weight <= 0 || reps <= 0) {
            this.element.classList.add('border-rose-500/50');
            return null;
        }

        this.element.classList.remove('border-rose-500/50');

        return { weight, reps };
    }

    timerDetail() {
        const workoutView = this.element.closest('[data-controller~="active-workout"]');
        const exerciseTitle = workoutView?.querySelector('[data-active-workout-target="headerSubtitle"]')?.textContent?.trim()
            || workoutView?.querySelector('[data-active-workout-target="headerTitle"]')?.textContent?.trim()
            || 'Seance en cours';
        const currentBlock = this.element.closest('[data-workout-set-block]');
        const nextSet = currentBlock?.nextElementSibling?.querySelector('[data-controller~="workout-set"]')
            || (this.element.nextElementSibling?.matches('[data-controller~="workout-set"]') ? this.element.nextElementSibling : null);
        const nextPosition = nextSet?.dataset.workoutSetPositionValue;
        const nextLabel = nextPosition ? `Prochain set : serie ${nextPosition}` : 'Passe a la suite de ta seance';

        return {
            restSeconds: this.restSeconds(),
            notificationTitle: 'Repos termine',
            notificationBody: `${nextLabel} - ${exerciseTitle}`,
            url: '/app/workout',
        };
    }

    restSeconds() {
        const workoutView = this.element.closest('[data-controller~="active-workout"]');
        const seconds = Number.parseInt(workoutView?.dataset.activeWorkoutRestSecondsValue, 10);

        return Number.isFinite(seconds) && seconds > 0 ? seconds : 90;
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
}
