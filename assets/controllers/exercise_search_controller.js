import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'results', 'empty', 'modal', 'name', 'muscleGroup', 'equipment', 'imageUrl'];

    connect() {
        this.timeout = null;
        this.abortController = null;
    }

    disconnect() {
        window.clearTimeout(this.timeout);
        this.abortController?.abort();
    }

    search() {
        window.clearTimeout(this.timeout);
        this.timeout = window.setTimeout(() => this.fetchResults(), 180);
    }

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

    async createCustom(event) {
        const button = event.currentTarget;
        const payload = {
            name: this.nameTarget.value.trim(),
            muscleGroup: this.muscleGroupTarget.value.trim(),
            equipment: this.equipmentTarget.value.trim(),
            imageUrl: this.imageUrlTarget.value.trim(),
        };

        if (!payload.name || !payload.muscleGroup || !payload.equipment) {
            this.markCreateInvalid(true);
            return;
        }

        this.markCreateInvalid(false);
        button.disabled = true;
        button.classList.add('opacity-70');

        try {
            const response = await fetch('/api/exercises/custom', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (response.ok) {
                const exercise = await response.json();
                this.resultsTarget.insertAdjacentHTML('afterbegin', this.card(exercise));
                this.closeCreate();
                this.clearCreateForm();

                if (window.lucide) {
                    window.lucide.createIcons();
                }

                return;
            }
        } catch {
        }

        button.disabled = false;
        button.classList.remove('opacity-70');
    }

    async fetchResults() {
        const query = this.inputTarget.value.trim();
        this.abortController?.abort();
        this.abortController = new AbortController();

        try {
            const response = await fetch(`/api/exercises?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                signal: this.abortController.signal,
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            this.render(payload.exercises || []);
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.render([]);
            }
        }
    }

    render(exercises) {
        this.resultsTarget.innerHTML = exercises.map((exercise) => this.card(exercise)).join('');

        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.toggle('hidden', exercises.length > 0);
        }

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    card(exercise) {
        const customBadge = exercise.isCustom
            ? '<span class="bg-app-accent text-black text-[9px] font-extrabold px-2 py-0.5 rounded uppercase ml-1">Custom</span>'
            : '';

        return `
            <article class="glass-panel rounded-[1.5rem] overflow-hidden group cursor-pointer hover:border-app-accent/50 transition-colors p-1">
                <div class="h-32 rounded-t-[1.2rem] rounded-b-xl bg-zinc-900 relative overflow-hidden">
                    <img src="${this.escapeAttribute(exercise.image)}" alt="${this.escapeAttribute(exercise.name)}" class="w-full h-full object-cover opacity-60 group-hover:opacity-100 transition-opacity duration-500 group-hover:scale-110">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                    <div class="absolute bottom-2 left-3">
                        <span class="bg-white/20 backdrop-blur-md text-[9px] font-bold text-white px-2 py-0.5 rounded uppercase">${this.escapeHtml(exercise.tag)}</span>
                        ${customBadge}
                    </div>
                </div>
                <div class="p-3">
                    <h4 class="font-extrabold text-sm text-white mb-0.5">${this.escapeHtml(exercise.name)}</h4>
                    <p class="text-[10px] text-app-muted uppercase font-semibold">${this.escapeHtml(exercise.category)}</p>
                </div>
            </article>
        `;
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

    markCreateInvalid(invalid) {
        [this.nameTarget, this.muscleGroupTarget, this.equipmentTarget].forEach((input) => {
            input.classList.toggle('border-rose-500/50', invalid && !input.value.trim());
        });
    }

    clearCreateForm() {
        this.nameTarget.value = '';
        this.muscleGroupTarget.value = '';
        this.equipmentTarget.value = '';
        this.imageUrlTarget.value = '';
    }
}
