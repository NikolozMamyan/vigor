import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'startButton'];

    open() {
        this.modalTarget.classList.remove('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.add('opacity-100');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.modalTarget.classList.add('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.remove('opacity-100');
        document.body.classList.remove('overflow-hidden');
    }

    async startFree() {
        this.startButtonTarget.disabled = true;
        this.startButtonTarget.classList.add('opacity-70');

        try {
            const response = await fetch('/api/workout-sessions/free', {
                method: 'POST',
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                window.location.href = '/app/workout';
                return;
            }
        } catch {
        }

        this.startButtonTarget.disabled = false;
        this.startButtonTarget.classList.remove('opacity-70');
    }
}
