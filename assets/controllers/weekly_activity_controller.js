import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['value', 'label'];

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
}
