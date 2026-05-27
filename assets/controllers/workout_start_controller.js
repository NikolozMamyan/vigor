import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'startButton', 'programButton'];

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
                this.navigateWithRefresh('workout', ['workout', 'home', 'stats']);
                return;
            }
        } catch {
        }

        this.startButtonTarget.disabled = false;
        this.startButtonTarget.classList.remove('opacity-70');
    }

    async startProgram(event) {
        const button = event.currentTarget;
        const programId = button.dataset.programId;

        if (!programId) {
            return;
        }

        button.disabled = true;
        button.classList.add('opacity-70');

        try {
            const response = await fetch(`/api/workout-sessions/from-program/${programId}`, {
                method: 'POST',
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                this.navigateWithRefresh('workout', ['workout', 'home', 'stats']);
                return;
            }
        } catch {
        }

        button.disabled = false;
        button.classList.remove('opacity-70');
    }

    navigateWithRefresh(nextView, views) {
        this.close();
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
}
