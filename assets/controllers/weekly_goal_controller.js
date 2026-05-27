import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'form', 'workouts', 'volume', 'minutes', 'error', 'submit'];

    open() {
        this.errorTarget.textContent = '';
        this.modalTarget.classList.remove('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.add('opacity-100');
        this.modalTarget.querySelector('.weekly-goal-modal-panel')?.classList.remove('translate-y-full', 'scale-95');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.modalTarget.classList.add('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.remove('opacity-100');
        this.modalTarget.querySelector('.weekly-goal-modal-panel')?.classList.add('translate-y-full', 'scale-95');
        document.body.classList.remove('overflow-hidden');
    }

    async save(event) {
        event.preventDefault();
        this.errorTarget.textContent = '';
        this.submitTarget.disabled = true;
        this.submitTarget.classList.add('opacity-70');

        try {
            const response = await fetch('/api/profile/weekly-goal', {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    targetWorkouts: Number.parseInt(this.workoutsTarget.value, 10),
                    targetVolume: Math.round(Number.parseFloat(this.volumeTarget.value) * 1000),
                    targetTrainingMinutes: Number.parseInt(this.minutesTarget.value, 10),
                }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.errorTarget.textContent = payload.error || 'Impossible de mettre a jour les objectifs.';
                return;
            }

            this.close();
            this.element.dispatchEvent(new CustomEvent('vigor:refresh-views', {
                detail: {
                    views: ['profile', 'home', 'stats'],
                    nextView: 'profile',
                    path: '/app/profile',
                },
                bubbles: true,
            }));
        } catch {
            this.errorTarget.textContent = 'Impossible de joindre le serveur.';
        } finally {
            this.submitTarget.disabled = false;
            this.submitTarget.classList.remove('opacity-70');
        }
    }
}
