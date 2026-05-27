import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'card',
        'curveDot',
        'curvePath',
        'filter',
        'gradientPath',
        'history',
        'legend',
        'modal',
        'modalCategory',
        'modalName',
        'modalOneRepMax',
        'modalVolume',
    ];
    static values = {
        records: Object,
    };

    filter(event) {
        const category = event.params.category || 'all';

        this.filterTargets.forEach((button) => {
            const active = button.dataset.recordsCategoryParam === category;
            button.classList.toggle('is-active', active);
            button.classList.toggle('bg-white', active);
            button.classList.toggle('text-black', active);
            button.classList.toggle('glass-panel', !active);
            button.classList.toggle('text-app-muted', !active);
        });

        this.cardTargets.forEach((card) => {
            const visible = category === 'all' || card.dataset.category === category;
            card.classList.toggle('hidden', !visible);
        });
    }

    open(event) {
        const record = this.recordsValue[event.currentTarget.dataset.recordId];

        if (!record) {
            return;
        }

        if (navigator.vibrate) {
            navigator.vibrate(40);
        }

        this.modalNameTarget.textContent = record.name;
        this.modalCategoryTarget.textContent = record.category;
        this.modalOneRepMaxTarget.textContent = record.estimatedOneRepMaxLabel;
        this.modalVolumeTarget.textContent = record.volume;
        this.renderHistory(record.history || []);
        this.drawCurve(record.history || []);

        this.modalTarget.classList.remove('opacity-0', 'pointer-events-none');
        this.modalTarget.querySelector('.records-modal-box')?.classList.remove('translate-y-full');

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    close() {
        if (navigator.vibrate) {
            navigator.vibrate(20);
        }

        this.modalTarget.classList.add('opacity-0', 'pointer-events-none');
        this.modalTarget.querySelector('.records-modal-box')?.classList.add('translate-y-full');
    }

    async share() {
        const featured = Object.values(this.recordsValue).sort((a, b) => b.estimatedOneRepMax - a.estimatedOneRepMax)[0];
        const text = featured
            ? `Mon meilleur PR: ${featured.name} - ${featured.estimatedOneRepMaxLabel}kg 1RM estime`
            : 'Mes records VIGOR';

        if (navigator.share) {
            try {
                await navigator.share({ title: 'Mes records VIGOR', text });
            } catch {
            }
        }
    }

    renderHistory(history) {
        this.historyTarget.innerHTML = history.slice().reverse().map((session) => `
            <div class="p-3.5 rounded-2xl bg-white/[0.02] border border-white/5 grid grid-cols-[1fr_0.9fr_0.9fr] gap-3 items-center">
                <div class="min-w-0">
                    <span class="text-xs font-black text-white block">${this.escapeHtml(session.date)}</span>
                    <span class="text-[10px] text-app-muted font-bold block">Serie record</span>
                </div>
                <div class="text-center">
                    <span class="text-[8px] uppercase tracking-wider text-app-muted font-black block">Perf Lift</span>
                    <span class="text-sm font-extrabold text-white">${this.escapeHtml(session.weight)}kg x ${this.escapeHtml(String(session.reps))}</span>
                </div>
                <div class="text-right">
                    <span class="text-[8px] uppercase tracking-wider text-app-muted font-black block">1RM Estime</span>
                    <span class="text-sm font-extrabold text-app-accent tabular-nums">${this.escapeHtml(session.estimatedOneRepMax)}kg</span>
                </div>
            </div>
        `).join('');

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    drawCurve(history) {
        const values = history.map((session) => Number.parseFloat(session.estimatedOneRepMax)).filter(Number.isFinite);

        if (values.length === 0) {
            this.curvePathTarget.setAttribute('d', 'M 0 35 L 100 35');
            this.gradientPathTarget.setAttribute('d', 'M 0 35 L 100 35 L 100 40 L 0 40 Z');
            this.legendTarget.innerHTML = '';
            this.curveDotTarget.style.left = 'calc(100% - 0.375rem)';
            this.curveDotTarget.style.top = 'calc(87.5% - 0.375rem)';
            return;
        }

        const min = Math.min(...values) - 2;
        const max = Math.max(...values) + 2;
        const range = Math.max(1, max - min);
        const step = values.length > 1 ? 100 / (values.length - 1) : 100;
        const points = values.map((value, index) => ({
            x: values.length > 1 ? index * step : 100,
            y: 34 - ((value - min) / range) * 26,
        }));
        const path = points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' ');
        const last = points[points.length - 1];

        this.curvePathTarget.setAttribute('d', path);
        this.gradientPathTarget.setAttribute('d', `${path} L 100 40 L 0 40 Z`);
        this.curveDotTarget.style.left = `calc(${last.x}% - 0.375rem)`;
        this.curveDotTarget.style.top = `calc(${(last.y / 40) * 100}% - 0.375rem)`;
        this.legendTarget.innerHTML = history.map((session, index) => {
            const activeClass = index === history.length - 1 ? 'text-app-accent' : '';

            return `<span class="${activeClass}">${this.escapeHtml(session.date)}</span>`;
        }).join('');
    }

    escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;

        return div.innerHTML;
    }
}
