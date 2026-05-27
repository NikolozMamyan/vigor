import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'surface', 'minified', 'expanded', 'display', 'miniDisplay', 'canvas'];
    static values = {
        soundUrl: String,
    };

    connect() {
        this.totalTime = 90;
        this.timeLeft = 90;
        this.interval = null;
        this.expanded = false;
        this.active = false;
        this.particles = [];
        this.particleFrame = null;
        this.canvasContext = this.hasCanvasTarget ? this.canvasTarget.getContext('2d') : null;
        this.sound = this.hasSoundUrlValue ? new Audio(this.soundUrlValue) : null;

        if (this.sound) {
            this.sound.preload = 'auto';
        }

        this.boundWorkoutSetTimerStart = (event) => this.startFromSet(event);
        this.boundWorkoutSetTimerClose = () => this.close();
        this.boundResizeCanvas = () => this.resizeCanvas();

        document.addEventListener('workout-set:timer-start', this.boundWorkoutSetTimerStart);
        document.addEventListener('workout-set:timer-close', this.boundWorkoutSetTimerClose);
        window.addEventListener('resize', this.boundResizeCanvas);

        this.resizeCanvas();
        this.update();
        this.minify();
    }

    disconnect() {
        window.clearInterval(this.interval);
        window.cancelAnimationFrame(this.particleFrame);
        document.removeEventListener('workout-set:timer-start', this.boundWorkoutSetTimerStart);
        document.removeEventListener('workout-set:timer-close', this.boundWorkoutSetTimerClose);
        window.removeEventListener('resize', this.boundResizeCanvas);
    }

    startFromSet(event = null) {
        if (navigator.vibrate) {
            navigator.vibrate(50);
        }

        const detail = event?.detail || {};
        this.notificationContext = {
            title: detail.notificationTitle || 'Repos termine',
            body: detail.notificationBody || 'Ton prochain set est pret.',
            url: detail.url || '/app/workout',
        };

        this.requestNotificationPermission();
        this.start(detail.restSeconds || 90);
    }

    start(seconds = 90) {
        window.clearInterval(this.interval);
        this.totalTime = Number.parseInt(seconds, 10) || 90;
        this.timeLeft = this.totalTime;
        this.endsAt = Date.now() + this.totalTime * 1000;
        this.notified = false;
        this.active = true;
        this.update();
        this.show();
        this.expand();

        this.interval = window.setInterval(() => {
            this.timeLeft = Math.ceil((this.endsAt - Date.now()) / 1000);
            this.update();

            if (this.timeLeft <= 0) {
                this.finish();
            }
        }, 1000);
    }

    show() {
        this.modalTarget.classList.remove('opacity-0', 'pointer-events-none', '-translate-y-12');
        this.modalTarget.classList.add('opacity-100', 'translate-y-0');
    }

    close() {
        window.clearInterval(this.interval);
        this.active = false;

        if (!this.hasModalTarget) {
            return;
        }

        this.modalTarget.classList.remove('opacity-100', 'translate-y-0', 'is-triggered');
        this.modalTarget.classList.add('opacity-0', '-translate-y-12', 'pointer-events-none');
        this.minify();
    }

    expand() {
        if (!this.hasSurfaceTarget) {
            return;
        }

        this.expanded = true;
        this.surfaceTarget.style.width = '330px';
        this.surfaceTarget.style.height = '230px';
        this.surfaceTarget.style.borderRadius = '32px';

        this.minifiedTarget.classList.add('opacity-0', 'pointer-events-none');
        this.minifiedTarget.classList.remove('pointer-events-auto');

        window.setTimeout(() => {
            if (!this.expanded) {
                return;
            }

            this.expandedTarget.classList.remove('opacity-0', 'pointer-events-none');
            this.expandedTarget.classList.add('pointer-events-auto');
        }, 150);
    }

    minify() {
        if (!this.hasSurfaceTarget) {
            return;
        }

        this.expanded = false;
        this.expandedTarget.classList.add('opacity-0', 'pointer-events-none');
        this.expandedTarget.classList.remove('pointer-events-auto');

        this.surfaceTarget.style.width = '130px';
        this.surfaceTarget.style.height = '44px';
        this.surfaceTarget.style.borderRadius = '999px';

        window.setTimeout(() => {
            if (this.expanded) {
                return;
            }

            this.minifiedTarget.classList.remove('opacity-0', 'pointer-events-none');
            this.minifiedTarget.classList.add('pointer-events-auto');
        }, 150);
    }

    skip() {
        this.timeLeft = 0;
        this.finish(false);
    }

    addTime() {
        this.adjustTime(10);
    }

    removeTime() {
        this.adjustTime(-10);
    }

    adjustTime(seconds) {
        this.timeLeft = Math.max(0, this.timeLeft + seconds);
        this.totalTime = Math.max(this.totalTime, this.timeLeft);
        this.endsAt = Date.now() + this.timeLeft * 1000;
        this.update();

        if (navigator.vibrate) {
            navigator.vibrate(25);
        }

        if (this.timeLeft <= 0) {
            this.finish(false);
        }
    }

    finish(shouldNotify = true) {
        if (!this.active) {
            return;
        }

        window.clearInterval(this.interval);
        this.active = false;
        this.timeLeft = 0;
        this.update();
        this.modalTarget.classList.add('is-triggered');
        this.playFinishSound();

        if (shouldNotify) {
            this.notifyRestFinished();
        }

        if (navigator.vibrate) {
            navigator.vibrate([150, 80, 250, 100, 650]);
        }

        this.explode();

        window.setTimeout(() => {
            this.close();
        }, 2000);
    }

    async requestNotificationPermission() {
        if (!('Notification' in window) || Notification.permission !== 'default') {
            return;
        }

        try {
            await Notification.requestPermission();
        } catch {
        }
    }

    async notifyRestFinished() {
        if (this.notified || !('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        this.notified = true;

        const notification = this.notificationContext || {
            title: 'Repos termine',
            body: 'Ton prochain set est pret.',
            url: '/app/workout',
        };

        const options = {
            body: notification.body,
            tag: 'vigor-rest-timer',
            renotify: true,
            silent: false,
            icon: '/icons/vigor-notification-192.png',
            badge: '/icons/vigor-notification-96.png',
            data: { url: notification.url },
        };

        try {
            const registration = await navigator.serviceWorker?.ready;

            if (registration?.showNotification) {
                await registration.showNotification(notification.title, options);
                return;
            }
        } catch {
        }

        try {
            new Notification(notification.title, options);
        } catch {
        }
    }

    playFinishSound() {
        if (!this.sound) {
            return;
        }

        this.sound.currentTime = 0;
        this.sound.play().catch(() => {});
    }

    update() {
        if (!this.hasDisplayTarget || !this.hasMiniDisplayTarget) {
            return;
        }

        const minutes = Math.floor(Math.max(0, this.timeLeft) / 60).toString().padStart(2, '0');
        const seconds = (Math.max(0, this.timeLeft) % 60).toString().padStart(2, '0');
        const formatted = `${minutes}:${seconds}`;

        this.displayTarget.textContent = formatted;
        this.miniDisplayTarget.textContent = formatted;
    }

    resizeCanvas() {
        if (!this.hasCanvasTarget) {
            return;
        }

        this.canvasTarget.width = window.innerWidth;
        this.canvasTarget.height = window.innerHeight;
    }

    explode() {
        if (!this.canvasContext) {
            return;
        }

        this.resizeCanvas();

        const originX = window.innerWidth / 2;
        const originY = 76;
        const colors = ['#ccff00', '#34d399', '#ffffff', '#a7f3d0', '#a3e635'];

        for (let index = 0; index < 100; index += 1) {
            this.particles.push({
                x: originX,
                y: originY,
                vx: (Math.random() - 0.5) * 30,
                vy: (Math.random() - 0.5) * 18 - 10,
                radius: Math.random() * 6 + 3,
                color: colors[Math.floor(Math.random() * colors.length)],
                life: 1,
                decay: Math.random() * 0.012 + 0.007,
                gravity: 0.55,
                bounce: 0.68,
            });
        }

        if (!this.particleFrame) {
            this.animateParticles();
        }
    }

    animateParticles() {
        const context = this.canvasContext;

        context.clearRect(0, 0, this.canvasTarget.width, this.canvasTarget.height);

        let activeParticles = false;

        this.particles.forEach((particle) => {
            if (particle.life <= 0) {
                return;
            }

            activeParticles = true;
            particle.x += particle.vx;
            particle.y += particle.vy;
            particle.vy += particle.gravity;
            particle.life -= particle.decay;

            if (particle.y + particle.radius > window.innerHeight) {
                particle.y = window.innerHeight - particle.radius;
                particle.vy = -particle.vy * particle.bounce;
            }

            if (particle.x + particle.radius > window.innerWidth || particle.x - particle.radius < 0) {
                particle.vx = -particle.vx * particle.bounce;
            }

            context.globalAlpha = Math.max(0, particle.life);
            context.shadowBlur = particle.color === '#ccff00' ? 12 : 0;
            context.shadowColor = particle.color;
            context.beginPath();
            context.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
            context.fillStyle = particle.color;
            context.fill();
        });

        context.globalAlpha = 1;
        context.shadowBlur = 0;

        if (activeParticles) {
            this.particleFrame = window.requestAnimationFrame(() => this.animateParticles());
            return;
        }

        context.clearRect(0, 0, this.canvasTarget.width, this.canvasTarget.height);
        this.particles = [];
        this.particleFrame = null;
    }
}
