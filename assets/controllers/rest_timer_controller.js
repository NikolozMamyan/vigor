import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'display', 'circle'];

    connect() {
        this.totalTime = 90;
        this.timeLeft = 90;
        this.interval = null;
        this.boundCloseOnNavigate = (event) => {
            if (event.detail.view !== 'workout') {
                this.close();
            }
        };
        this.boundWorkoutSetTimerStart = () => this.startFromSet();
        this.boundWorkoutSetTimerClose = () => this.close();

        this.element.addEventListener('vigor:navigate', this.boundCloseOnNavigate);
        this.element.addEventListener('workout-set:timer-start', this.boundWorkoutSetTimerStart);
        this.element.addEventListener('workout-set:timer-close', this.boundWorkoutSetTimerClose);
        this.update();
    }

    disconnect() {
        window.clearInterval(this.interval);
        this.element.removeEventListener('vigor:navigate', this.boundCloseOnNavigate);
        this.element.removeEventListener('workout-set:timer-start', this.boundWorkoutSetTimerStart);
        this.element.removeEventListener('workout-set:timer-close', this.boundWorkoutSetTimerClose);
    }

    toggleSet(event) {
        const button = event.currentTarget;
        this.setChecked(button, !button.classList.contains('checked'));
    }

    startFromSet() {
        if (navigator.vibrate) {
            navigator.vibrate(50);
        }

        this.start();
    }

    setChecked(button, checked) {
        const row = button.closest('.set-row');

        button.classList.toggle('checked', checked);
        row?.classList.toggle('checked', checked);

        if (!checked) {
            this.close();
            return;
        }

        if (navigator.vibrate) {
            navigator.vibrate(50);
        }

        this.start();
    }

    start() {
        window.clearInterval(this.interval);
        this.totalTime = 90;
        this.timeLeft = 90;
        this.update();

        this.modalTarget.classList.remove('translate-y-10', 'opacity-0', 'pointer-events-none');
        this.modalTarget.classList.add('translate-y-0', 'opacity-100');

        this.interval = window.setInterval(() => {
            this.timeLeft -= 1;
            this.update();

            if (this.timeLeft <= 0) {
                this.close();
                if (navigator.vibrate) {
                    navigator.vibrate([100, 50, 100]);
                }
            }
        }, 1000);
    }

    close() {
        window.clearInterval(this.interval);

        if (!this.hasModalTarget) {
            return;
        }

        this.modalTarget.classList.remove('translate-y-0', 'opacity-100');
        this.modalTarget.classList.add('translate-y-10', 'opacity-0', 'pointer-events-none');
    }

    addTime() {
        this.timeLeft = Math.max(0, this.timeLeft + 15);
        this.totalTime = Math.max(this.totalTime, this.timeLeft);
        this.update();
    }

    update() {
        if (!this.hasDisplayTarget || !this.hasCircleTarget) {
            return;
        }

        const minutes = Math.floor(this.timeLeft / 60).toString().padStart(2, '0');
        const seconds = (this.timeLeft % 60).toString().padStart(2, '0');
        this.displayTarget.textContent = `${minutes}:${seconds}`;

        const percentage = (this.timeLeft / this.totalTime) * 100;
        this.circleTarget.style.strokeDashoffset = 100 - percentage;
    }
}
