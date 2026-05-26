import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'name'];

    openCreate() {
        this.modalTarget.classList.remove('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.add('opacity-100');
        document.body.classList.add('overflow-hidden');
        window.setTimeout(() => this.nameTarget?.focus(), 80);
    }

    closeCreate() {
        this.modalTarget.classList.add('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.remove('opacity-100');
        document.body.classList.remove('overflow-hidden');
    }

    async create(event) {
        const button = event.currentTarget;
        const name = this.nameTarget.value.trim();

        if (name.length < 2) {
            this.nameTarget.classList.add('border-rose-500/50');
            return;
        }

        this.nameTarget.classList.remove('border-rose-500/50');
        this.setBusy(button, true);

        const program = await this.request('/api/workout-programs', 'POST', { name });

        if (program) {
            window.location.reload();
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

            return response.json();
        } catch {
            return null;
        }
    }
}
