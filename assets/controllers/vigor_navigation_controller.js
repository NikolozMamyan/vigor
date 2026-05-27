import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['view', 'button', 'indicator', 'outerRing', 'innerRing'];
    static values = { active: String };

    connect() {
        this.views = ['home', 'workout', 'library', 'profile'];
        this.swipe = null;
        this.previewView = null;

        this.boundTouchStart = (event) => this.startTouchSwipe(event);
        this.boundTouchMove = (event) => this.moveTouchSwipe(event);
        this.boundTouchEnd = (event) => this.endTouchSwipe(event);
        this.boundTouchCancel = () => this.cancelSwipe();
        this.boundPointerDown = (event) => this.startPointerSwipe(event);
        this.boundPointerMove = (event) => this.movePointerSwipe(event);
        this.boundPointerUp = (event) => this.endPointerSwipe(event);
        this.boundPointerCancel = () => this.cancelSwipe();
        this.boundPopState = (event) => this.show(event.state?.view || this.viewFromPath() || 'home', false);
        this.boundRefreshViews = (event) => this.refreshViews(event.detail || {});

        this.element.addEventListener('touchstart', this.boundTouchStart, { passive: true });
        this.element.addEventListener('touchmove', this.boundTouchMove, { passive: false });
        this.element.addEventListener('touchend', this.boundTouchEnd, { passive: true });
        this.element.addEventListener('touchcancel', this.boundTouchCancel, { passive: true });
        this.element.addEventListener('pointerdown', this.boundPointerDown);
        this.element.addEventListener('pointermove', this.boundPointerMove);
        this.element.addEventListener('pointerup', this.boundPointerUp);
        this.element.addEventListener('pointercancel', this.boundPointerCancel);
        window.addEventListener('popstate', this.boundPopState);
        this.element.addEventListener('vigor:refresh-views', this.boundRefreshViews);

        this.show(this.activeValue || 'home', false);
        window.setTimeout(() => this.animateRings(), 300);
    }

    disconnect() {
        this.element.removeEventListener('touchstart', this.boundTouchStart);
        this.element.removeEventListener('touchmove', this.boundTouchMove);
        this.element.removeEventListener('touchend', this.boundTouchEnd);
        this.element.removeEventListener('touchcancel', this.boundTouchCancel);
        this.element.removeEventListener('pointerdown', this.boundPointerDown);
        this.element.removeEventListener('pointermove', this.boundPointerMove);
        this.element.removeEventListener('pointerup', this.boundPointerUp);
        this.element.removeEventListener('pointercancel', this.boundPointerCancel);
        window.removeEventListener('popstate', this.boundPopState);
        this.element.removeEventListener('vigor:refresh-views', this.boundRefreshViews);
    }

    navigate(event) {
        const view = event.params.view;

        if (view) {
            this.show(view, true);
        }
    }

    show(view, pushState) {
        const previousView = this.activeValue;
        this.activeValue = view;

        this.viewTargets.forEach((element) => {
            element.classList.toggle('active', element.dataset.view === view);
        });

        this.buttonTargets.forEach((button) => {
            const isActive = button.dataset.vigorNavigationViewParam === view;
            button.classList.toggle('active', isActive);

            const icon = button.querySelector('.nav-icon');
            if (icon) {
                icon.classList.toggle('text-white', isActive && view !== 'workout');
                icon.classList.toggle('text-app-accent', isActive && view === 'workout');
                icon.classList.toggle('fill-app-accent', isActive && view === 'workout');
                icon.classList.toggle('text-app-muted', !isActive);
            }

            if (isActive) {
                this.moveIndicator(button, view);
            }
        });

        if (pushState) {
            window.history.pushState({ view }, '', `/app/${view}`);
        }

        if (view !== previousView) {
            this.resetMainScroll();
        }

        this.element.dispatchEvent(new CustomEvent('vigor:navigate', {
            detail: { view },
            bubbles: true,
        }));
    }

    startTouchSwipe(event) {
        if (event.touches.length !== 1) {
            return;
        }

        const touch = event.touches[0];
        this.beginSwipe(touch.clientX, touch.clientY, event.target, touch.identifier);
    }

    moveTouchSwipe(event) {
        if (!this.swipe || event.touches.length !== 1) {
            return;
        }

        const touch = event.touches[0];
        this.updateSwipe(touch.clientX, touch.clientY, () => event.preventDefault());
    }

    endTouchSwipe(event) {
        if (!this.swipe) {
            return;
        }

        const touch = event.changedTouches[0];
        this.finishSwipe(touch.clientX, touch.clientY);
    }

    startPointerSwipe(event) {
        if (event.pointerType === 'touch' || !event.isPrimary) {
            return;
        }

        this.beginSwipe(event.clientX, event.clientY, event.target, event.pointerId);
    }

    movePointerSwipe(event) {
        if (!this.swipe || event.pointerType === 'touch' || event.pointerId !== this.swipe.id) {
            return;
        }

        this.updateSwipe(event.clientX, event.clientY, () => event.preventDefault());
    }

    endPointerSwipe(event) {
        if (!this.swipe || event.pointerType === 'touch' || event.pointerId !== this.swipe.id) {
            return;
        }

        this.finishSwipe(event.clientX, event.clientY);
    }

    beginSwipe(x, y, target, id) {
        if (this.shouldIgnoreSwipe(target)) {
            this.swipe = null;
            return;
        }

        this.swipe = {
            id,
            startX: x,
            startY: y,
            lastX: x,
            lastY: y,
            startedAt: performance.now(),
            dragging: false,
            lock: null,
        };
    }

    updateSwipe(x, y, preventDefault) {
        if (!this.swipe) {
            return;
        }

        const deltaX = x - this.swipe.startX;
        const deltaY = y - this.swipe.startY;
        const absX = Math.abs(deltaX);
        const absY = Math.abs(deltaY);

        this.swipe.lastX = x;
        this.swipe.lastY = y;

        if (!this.swipe.lock) {
            if (absY > 10 && absY > absX) {
                this.swipe.lock = 'vertical';
                this.cancelSwipe(false);
                return;
            }

            if (absX < 8 || absX < absY * 1.1) {
                return;
            }

            this.swipe.lock = 'horizontal';
            this.swipe.dragging = true;
            this.activeViewElement()?.classList.add('swipe-dragging');
        }

        if (this.swipe.lock !== 'horizontal') {
            return;
        }

        preventDefault();
        this.dragViews(deltaX);
    }

    finishSwipe(x, y) {
        const swipe = this.swipe;

        if (!swipe) {
            return;
        }

        const deltaX = x - swipe.startX;
        const deltaY = y - swipe.startY;
        const elapsed = Math.max(1, performance.now() - swipe.startedAt);
        const velocity = Math.abs(deltaX) / elapsed;
        const width = this.element.clientWidth || window.innerWidth;
        const threshold = Math.min(110, width * 0.24);
        const activeView = this.activeViewElement();
        const previewView = this.previewView;
        const direction = deltaX < 0 ? 1 : -1;
        const shouldCommit = swipe.dragging
            && previewView
            && Math.abs(deltaX) > Math.abs(deltaY) * 1.2
            && (Math.abs(deltaX) >= threshold || velocity > 0.45);

        this.swipe = null;

        if (shouldCommit) {
            this.commitSwipe(activeView, previewView, direction);
            return;
        }

        if (swipe.dragging) {
            this.returnViews(activeView, previewView);
        } else {
            this.clearPreviewView();
        }
    }

    cancelSwipe(animate = true) {
        const activeView = this.activeViewElement();
        const previewView = this.previewView;
        this.swipe = null;

        if (animate) {
            this.returnViews(activeView, previewView);
            return;
        }

        this.resetDraggedViews(activeView, previewView);
    }

    dragViews(deltaX) {
        const activeView = this.activeViewElement();

        if (!activeView) {
            return;
        }

        const currentIndex = this.views.indexOf(this.activeValue);
        const atStart = currentIndex === 0 && deltaX > 0;
        const atEnd = currentIndex === this.views.length - 1 && deltaX < 0;
        const width = this.element.clientWidth || window.innerWidth;

        if (atStart || atEnd) {
            const resistedX = Math.max(-86, Math.min(86, deltaX * 0.24));
            activeView.style.transform = `translate3d(${resistedX}px, 0, 0) scale(${1 - Math.abs(resistedX) / 1800})`;
            activeView.style.opacity = Math.max(0.86, 1 - Math.abs(resistedX) / 460).toString();
            this.clearPreviewView();
            return;
        }

        const direction = deltaX < 0 ? 1 : -1;
        const previewView = this.preparePreviewView(direction);
        const clampedX = Math.max(-width, Math.min(width, deltaX));
        const previewX = direction === 1 ? width + clampedX : -width + clampedX;

        activeView.style.transform = `translate3d(${clampedX}px, 0, 0)`;
        activeView.style.opacity = Math.max(0.72, 1 - Math.abs(clampedX) / width / 2.2).toString();

        if (previewView) {
            previewView.style.transform = `translate3d(${previewX}px, 0, 0)`;
            previewView.style.opacity = Math.min(1, 0.72 + Math.abs(clampedX) / width).toString();
        }
    }

    returnViews(activeView, previewView) {
        if (!activeView) {
            return;
        }

        activeView.classList.remove('swipe-dragging');
        activeView.classList.add('swipe-returning');
        activeView.style.transform = '';
        activeView.style.opacity = '';

        if (previewView) {
            const previewDirection = this.previewDirection || 1;
            const width = this.element.clientWidth || window.innerWidth;
            previewView.classList.add('swipe-returning');
            previewView.style.transform = `translate3d(${previewDirection === 1 ? width : -width}px, 0, 0)`;
            previewView.style.opacity = '0';
        }

        window.setTimeout(() => {
            activeView.classList.remove('swipe-returning');
            this.clearPreviewView();
        }, 230);
    }

    resetDraggedViews(activeView, previewView) {
        if (activeView) {
            activeView.classList.remove('swipe-dragging', 'swipe-returning', 'swipe-committing');
            activeView.style.transform = '';
            activeView.style.opacity = '';
        }

        this.clearPreviewView(previewView);
    }

    commitSwipe(activeView, previewView, direction) {
        const width = this.element.clientWidth || window.innerWidth;
        const nextView = previewView.dataset.view;

        activeView.classList.remove('swipe-dragging');
        activeView.classList.add('swipe-committing');
        previewView.classList.add('swipe-committing');

        activeView.style.transform = `translate3d(${direction === 1 ? -width : width}px, 0, 0)`;
        activeView.style.opacity = '0';
        previewView.style.transform = 'translate3d(0, 0, 0)';
        previewView.style.opacity = '1';

        window.setTimeout(() => {
            this.resetDraggedViews(activeView, previewView);
            this.show(nextView, true);

            if (navigator.vibrate) {
                navigator.vibrate(12);
            }
        }, 210);
    }

    preparePreviewView(direction) {
        const currentIndex = this.views.indexOf(this.activeValue);
        const nextView = this.views[currentIndex + direction];

        if (!nextView) {
            return null;
        }

        if (this.previewView?.dataset.view === nextView) {
            return this.previewView;
        }

        this.clearPreviewView();

        const previewView = this.viewTargets.find((element) => element.dataset.view === nextView);
        const width = this.element.clientWidth || window.innerWidth;

        if (!previewView) {
            return null;
        }

        this.previewView = previewView;
        this.previewDirection = direction;
        previewView.classList.add('swipe-preview', 'swipe-dragging');
        previewView.style.transform = `translate3d(${direction === 1 ? width : -width}px, 0, 0)`;
        previewView.style.opacity = '0.72';

        return previewView;
    }

    clearPreviewView(previewView = this.previewView) {
        if (!previewView) {
            this.previewView = null;
            this.previewDirection = null;
            return;
        }

        previewView.classList.remove('swipe-preview', 'swipe-dragging', 'swipe-returning', 'swipe-committing');
        previewView.style.transform = '';
        previewView.style.opacity = '';
        this.previewView = null;
        this.previewDirection = null;
    }

    activeViewElement() {
        return this.viewTargets.find((element) => element.dataset.view === this.activeValue);
    }

    shouldIgnoreSwipe(target) {
        return Boolean(target.closest('input, textarea, select, button, a, [data-swipe-ignore]'));
    }

    viewFromPath() {
        const match = window.location.pathname.match(/^\/app\/(home|workout|library|profile)$/);

        return match ? match[1] : null;
    }

    moveIndicator(button, view) {
        if (!this.hasIndicatorTarget) {
            return;
        }

        const parentRect = button.parentElement.getBoundingClientRect();
        const buttonRect = button.getBoundingClientRect();
        const offsetLeft = buttonRect.left - parentRect.left;

        this.indicatorTarget.style.transform = `translateX(${offsetLeft - 8}px)`;
        this.indicatorTarget.style.opacity = view === 'workout' ? '0' : '1';
    }

    animateRings() {
        if (this.hasOuterRingTarget) {
            this.outerRingTarget.style.strokeDashoffset = '60';
        }

        if (this.hasInnerRingTarget) {
            this.innerRingTarget.style.strokeDashoffset = '90';
        }
    }

    async refreshViews({ views = [], nextView = null, path = null } = {}) {
        const requestedViews = Array.isArray(views) && views.length > 0 ? views : [this.activeValue];
        const uniqueViews = [...new Set(requestedViews.filter((view) => this.views.includes(view)))];
        const targetView = nextView && this.views.includes(nextView) ? nextView : this.activeValue;

        if (uniqueViews.length === 0) {
            if (targetView) {
                this.show(targetView, false);
            }

            return;
        }

        this.setRefreshing(uniqueViews, true);

        try {
            const response = await fetch(path || `/app/${targetView}`, {
                headers: { Accept: 'text/html' },
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            const html = await response.text();
            const documentFragment = new DOMParser().parseFromString(html, 'text/html');

            uniqueViews.forEach((view) => {
                const incoming = documentFragment.getElementById(`view-${view}`);
                const current = this.viewTargets.find((element) => element.dataset.view === view);

                if (!incoming || !current) {
                    return;
                }

                incoming.classList.toggle('active', view === targetView);
                current.replaceWith(incoming);
            });

            this.show(targetView, false);

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } finally {
            this.setRefreshing(uniqueViews, false);
        }
    }

    setRefreshing(views, refreshing) {
        views.forEach((view) => {
            const element = this.viewTargets.find((target) => target.dataset.view === view);

            if (!element) {
                return;
            }

            element.classList.toggle('view-refreshing', refreshing);
            element.setAttribute('aria-busy', refreshing ? 'true' : 'false');
        });
    }

    resetMainScroll() {
        const scroller = this.element.querySelector('.app-main');

        if (scroller) {
            scroller.scrollTo({ top: 0, left: 0, behavior: 'instant' });
        }
    }
}
