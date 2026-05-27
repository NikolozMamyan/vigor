import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['value', 'label', 'carousel', 'dot'];

    select(event) {
        const bar = event.currentTarget;
        const isRest = bar.dataset.rest === 'true';

        this.element.querySelectorAll('.weekly-bar').forEach((element) => {
            element.classList.remove('is-active');
        });

        if (!isRest) {
            bar.classList.add('is-active');
            this.valueTarget.textContent = bar.dataset.value;
            this.labelTarget.textContent = bar.dataset.label;
        } else {
            this.valueTarget.textContent = '0';
            this.labelTarget.textContent = `${bar.dataset.label} - jour de repos`;
        }

        if (navigator.vibrate) {
            navigator.vibrate(30);
        }
    }

    syncCarousel() {
        if (!this.hasCarouselTarget || !this.hasDotTarget) {
            return;
        }

        const cardWidth = this.carouselTarget.querySelector('.home-stats-card')?.getBoundingClientRect().width || this.carouselTarget.clientWidth;
        const gap = 16;
        const index = Math.max(0, Math.min(this.dotTargets.length - 1, Math.round(this.carouselTarget.scrollLeft / (cardWidth + gap))));

        this.dotTargets.forEach((dot, dotIndex) => {
            dot.classList.toggle('bg-app-accent', dotIndex === index);
            dot.classList.toggle('bg-white/25', dotIndex !== index);
        });
    }
}
