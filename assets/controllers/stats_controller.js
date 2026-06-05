import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['shareButton', 'shareIcon', 'status'];
    static values = {
        period: String,
        volume: Number,
        trend: Number,
    };

    connect() {
        this.statusTimer = null;
    }

    disconnect() {
        window.clearTimeout(this.statusTimer);
    }

    async share() {
        const text = this.shareText();

        if (navigator.share) {
            try {
                await navigator.share({
                    title: 'Mes statistiques VIGOR',
                    text,
                });
                this.showStatus('Statistiques partagees');
                return;
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }
            }
        }

        try {
            await navigator.clipboard.writeText(text);
            this.showStatus('Resume copie');
        } catch {
            this.showStatus('Partage indisponible', true);
        }
    }

    shareText() {
        const labels = {
            week: 'cette semaine',
            month: 'ce mois-ci',
            quarter: 'ce trimestre',
        };
        const period = labels[this.periodValue] || 'sur la periode';
        const trend = this.trendValue >= 0 ? `+${this.trendValue}` : this.trendValue;

        return `VIGOR : ${this.volumeValue} T soulevees ${period}, tendance ${trend}%.`;
    }

    showStatus(message, error = false) {
        window.clearTimeout(this.statusTimer);
        this.statusTarget.textContent = message;
        this.statusTarget.classList.toggle('text-rose-300', error);
        this.statusTarget.classList.remove('opacity-0', 'translate-y-1', 'pointer-events-none');
        this.shareButtonTarget.setAttribute('aria-label', message);
        this.shareIconTarget.setAttribute('data-lucide', error ? 'circle-alert' : 'check');

        if (window.lucide) {
            window.lucide.createIcons();
        }

        this.statusTimer = window.setTimeout(() => {
            this.statusTarget.classList.add('opacity-0', 'translate-y-1', 'pointer-events-none');
            this.shareButtonTarget.setAttribute('aria-label', 'Partager les statistiques');
            this.shareIconTarget.setAttribute('data-lucide', 'share-2');

            if (window.lucide) {
                window.lucide.createIcons();
            }
        }, 2200);
    }
}
