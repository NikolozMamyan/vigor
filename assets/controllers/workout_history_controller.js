import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'body', 'title', 'meta', 'deleteButton', 'list', 'row', 'empty'];
    static values = {
        exercises: Array,
    };

    connect() {
        this.currentSessionId = null;
        this.currentRow = null;
        this.currentSession = null;
        this.saveTimers = new Map();
    }

    disconnect() {
        this.saveTimers.forEach((timer) => window.clearTimeout(timer));
        this.saveTimers.clear();
        document.body.classList.remove('overflow-hidden');
    }

    async open(event) {
        const row = event.currentTarget;
        const sessionId = Number.parseInt(row.dataset.sessionId, 10);

        if (!Number.isFinite(sessionId)) {
            return;
        }

        this.currentSessionId = sessionId;
        this.currentRow = row;
        this.openModal();
        this.titleTarget.textContent = row.querySelector('[data-history-session-name]')?.textContent?.trim() || 'Seance';
        this.metaTarget.textContent = 'Chargement';
        this.bodyTarget.innerHTML = this.loadingTemplate();
        this.setBusy(row, true);

        const session = await this.request(`/api/workout-sessions/${sessionId}`, 'GET');

        this.setBusy(row, false);

        if (!session) {
            this.bodyTarget.innerHTML = this.emptyTemplate('Seance introuvable');
            return;
        }

        this.renderSession(session);
    }

    close() {
        this.modalTarget.classList.add('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.remove('opacity-100');
        document.body.classList.remove('overflow-hidden');
        this.bodyTarget.innerHTML = '';
        this.currentSession = null;
    }

    async deleteSession(event) {
        if (!this.currentSessionId || !window.confirm('Supprimer cette seance ?')) {
            return;
        }

        const button = event.currentTarget;
        this.setBusy(button, true);

        const deleted = await this.request(`/api/workout-sessions/${this.currentSessionId}`, 'DELETE');

        if (!deleted) {
            this.setBusy(button, false);
            return;
        }

        this.currentRow?.remove();
        this.close();
        this.syncEmptyState();
        this.refreshRelatedViews(['home', 'stats', 'records']);
    }

    scheduleSetSave(event) {
        const row = event.currentTarget.closest('[data-history-set-id]');

        if (!row) {
            return;
        }

        const setId = row.dataset.historySetId;
        window.clearTimeout(this.saveTimers.get(setId));
        this.saveTimers.set(setId, window.setTimeout(() => this.saveSet({ currentTarget: row }), 450));
    }

    async saveSet(event) {
        const row = event.currentTarget.closest?.('[data-history-set-id]') || event.currentTarget;
        const setId = Number.parseInt(row?.dataset.historySetId, 10);
        const payload = this.setPayload(row);

        if (!Number.isFinite(setId) || !payload) {
            return;
        }

        window.clearTimeout(this.saveTimers.get(String(setId)));
        this.setSetState(row, 'Sauvegarde', 'muted');
        row.classList.add('is-syncing');

        const set = await this.request(`/api/workout-sets/${setId}`, 'PATCH', payload);

        row.classList.remove('is-syncing');

        if (!set) {
            row.classList.add('border-rose-500/50');
            this.setSetState(row, 'Erreur', 'error');
            return;
        }

        row.classList.remove('border-rose-500/50');
        this.setSetState(row, 'Enregistre', 'saved');
        this.refreshRelatedViews(['home', 'stats', 'records']);
    }

    async deleteSet(event) {
        const button = event.currentTarget;
        const row = button.closest('[data-history-set-id]');
        const setId = Number.parseInt(row?.dataset.historySetId, 10);

        if (!Number.isFinite(setId)) {
            return;
        }

        this.setBusy(button, true);
        row.classList.add('opacity-60');

        const deleted = await this.request(`/api/workout-sets/${setId}`, 'DELETE');

        if (!deleted) {
            this.setBusy(button, false);
            row.classList.remove('opacity-60');
            return;
        }

        row.remove();
        await this.reloadCurrentSession();
        this.refreshRelatedViews(['home', 'stats', 'records']);
    }

    addSet(event) {
        const button = event.currentTarget;
        const sessionExerciseId = Number.parseInt(button.dataset.sessionExerciseId, 10);
        const section = button.closest('[data-history-exercise-id]');
        const list = section?.querySelector('[data-history-sets-list]');

        if (!Number.isFinite(sessionExerciseId) || !list) {
            return;
        }

        const existingRow = list.querySelector('[data-history-new-set]');

        if (existingRow) {
            existingRow.querySelector('input')?.focus();
            return;
        }

        list.querySelector('[data-history-empty]')?.remove();
        list.insertAdjacentHTML('beforeend', this.newSetTemplate(sessionExerciseId, this.nextSetPosition(section)));
        list.lastElementChild?.querySelector('input')?.focus();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    async createSet(event) {
        const button = event.currentTarget;
        const row = button.closest('[data-history-new-set]');
        const sessionExerciseId = Number.parseInt(row?.dataset.sessionExerciseId, 10);
        const payload = this.setPayload(row);

        if (!Number.isFinite(sessionExerciseId) || !payload) {
            return;
        }

        this.setBusy(button, true);
        row.classList.add('is-syncing');

        const set = await this.request(`/api/workout-session-exercises/${sessionExerciseId}/history-sets`, 'POST', payload);

        if (!set) {
            this.setBusy(button, false);
            row.classList.remove('is-syncing');
            row.classList.add('border-rose-500/50');
            this.setSetState(row, 'Erreur', 'error');
            return;
        }

        await this.reloadCurrentSession();
        this.refreshRelatedViews(['home', 'stats', 'records']);
    }

    cancelNewSet(event) {
        event.currentTarget.closest('[data-history-new-set]')?.remove();
    }

    toggleExercisePicker() {
        const picker = this.bodyTarget.querySelector('[data-history-exercise-picker]');

        if (!picker) {
            return;
        }

        picker.classList.toggle('hidden');

        if (!picker.classList.contains('hidden')) {
            this.renderExerciseOptions();
            picker.querySelector('[data-history-exercise-search]')?.focus();
        }
    }

    filterExercises() {
        this.renderExerciseOptions();
    }

    async addExercise(event) {
        const button = event.currentTarget;
        const exerciseId = Number.parseInt(button.dataset.exerciseId, 10);

        if (!this.currentSessionId || !Number.isFinite(exerciseId)) {
            return;
        }

        this.setBusy(button, true);

        const session = await this.request(`/api/workout-sessions/${this.currentSessionId}/history-exercises`, 'POST', { exerciseId });

        if (!session) {
            this.setBusy(button, false);
            return;
        }

        this.renderSession(session);
        this.refreshRelatedViews(['home', 'stats', 'records']);
    }

    openModal() {
        this.modalTarget.classList.remove('opacity-0', 'pointer-events-none');
        this.modalTarget.classList.add('opacity-100');
        document.body.classList.add('overflow-hidden');
    }

    renderSession(session) {
        this.currentSession = session;
        this.titleTarget.textContent = session.name || 'Seance';
        this.metaTarget.textContent = `${session.dateLabel || ''} - ${session.timeLabel || ''} - ${session.durationLabel || '0 min'}`;
        this.deleteButtonTarget.dataset.sessionId = session.id;
        this.bodyTarget.innerHTML = `
            <div class="grid grid-cols-3 gap-2">
                ${this.metricTemplate('Volume', session.volumeLabel || '0 kg')}
                ${this.metricTemplate('Exos', session.exerciseCount || 0)}
                ${this.metricTemplate('Series', session.setCount || 0)}
            </div>
            ${this.exercisePickerTemplate()}
            <div class="space-y-4">
                ${(session.exercises || []).map((exercise) => this.exerciseTemplate(exercise)).join('') || this.emptyTemplate('Aucun exercice')}
            </div>
        `;

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    metricTemplate(label, value) {
        return `
            <div class="rounded-2xl bg-white/[0.05] border border-white/10 p-3">
                <span class="block text-[9px] text-app-muted font-black uppercase tracking-widest">${this.escapeHtml(label)}</span>
                <span class="block text-sm font-extrabold text-white mt-1 truncate">${this.escapeHtml(value)}</span>
            </div>
        `;
    }

    exerciseTemplate(exercise) {
        const sets = exercise.sets || [];

        return `
            <section class="glass-panel rounded-2xl p-4 space-y-3" data-history-exercise-id="${exercise.id}">
                <div class="flex items-center gap-3">
                    <img src="${this.escapeAttribute(exercise.image)}" alt="" class="w-11 h-11 rounded-2xl object-cover border border-white/10">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-extrabold text-white truncate">${this.escapeHtml(exercise.name)}</span>
                        <span class="block text-[10px] text-app-muted uppercase font-bold tracking-wider">${this.escapeHtml(exercise.muscleGroup)} - ${this.escapeHtml(exercise.targetLabel)}</span>
                    </span>
                </div>
                <div class="space-y-2" data-history-sets-list>
                    ${sets.map((set) => this.setTemplate(set)).join('') || this.emptyTemplate('Aucune serie')}
                </div>
                <button type="button" class="w-full rounded-2xl border border-dashed border-white/15 py-3 text-xs font-black text-app-muted uppercase tracking-wider hover:text-app-accent hover:border-app-accent/40 transition-colors flex items-center justify-center gap-2" data-action="workout-history#addSet" data-session-exercise-id="${exercise.id}">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Ajouter une serie
                </button>
            </section>
        `;
    }

    exercisePickerTemplate() {
        return `
            <section class="rounded-2xl bg-white/[0.04] border border-white/10 p-3 space-y-3">
                <button type="button" class="w-full rounded-xl bg-app-accent/10 border border-app-accent/20 text-app-accent py-3 text-xs font-black uppercase tracking-wider flex items-center justify-center gap-2 hover:bg-app-accent/20 transition-colors" data-action="workout-history#toggleExercisePicker">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Ajouter un exercice
                </button>
                <div class="hidden space-y-2" data-history-exercise-picker>
                    <div class="relative">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-app-muted"></i>
                        <input type="search" placeholder="Chercher un exercice" class="w-full rounded-xl bg-black/30 border border-white/10 pl-10 pr-3 py-3 text-sm font-bold text-white focus:outline-none focus:border-app-accent" data-history-exercise-search data-action="input->workout-history#filterExercises">
                    </div>
                    <div class="max-h-56 overflow-y-auto space-y-2 pr-1 no-scrollbar" data-history-exercise-options></div>
                </div>
            </section>
        `;
    }

    renderExerciseOptions() {
        const options = this.bodyTarget.querySelector('[data-history-exercise-options]');
        const search = this.bodyTarget.querySelector('[data-history-exercise-search]');

        if (!options) {
            return;
        }

        const selectedExerciseIds = new Set((this.currentSession?.exercises || []).map((exercise) => Number.parseInt(exercise.exerciseId, 10)));
        const query = search?.value?.trim().toLowerCase() || '';
        const exercises = this.exercisesValue
            .filter((exercise) => Number.isFinite(Number.parseInt(exercise.id, 10)))
            .filter((exercise) => !selectedExerciseIds.has(Number.parseInt(exercise.id, 10)))
            .filter((exercise) => {
                if (!query) {
                    return true;
                }

                return `${exercise.name} ${exercise.category} ${exercise.tag}`.toLowerCase().includes(query);
            })
            .slice(0, 25);

        options.innerHTML = exercises.map((exercise) => this.exerciseOptionTemplate(exercise)).join('')
            || this.emptyTemplate('Aucun exercice trouve');

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    exerciseOptionTemplate(exercise) {
        return `
            <button type="button" class="w-full rounded-xl bg-black/30 border border-white/10 p-2 flex items-center gap-3 text-left hover:border-app-accent/50 transition-colors" data-action="workout-history#addExercise" data-exercise-id="${exercise.id}">
                <img src="${this.escapeAttribute(exercise.image)}" alt="" class="w-10 h-10 rounded-xl object-cover border border-white/10">
                <span class="min-w-0 flex-1">
                    <span class="block text-xs font-extrabold text-white truncate">${this.escapeHtml(exercise.name)}</span>
                    <span class="block text-[9px] text-app-muted uppercase font-bold tracking-wider">${this.escapeHtml(exercise.category)} - ${this.escapeHtml(exercise.tag)}</span>
                </span>
                <i data-lucide="plus" class="w-4 h-4 text-app-accent shrink-0"></i>
            </button>
        `;
    }

    setTemplate(set) {
        return `
            <div class="rounded-2xl bg-black/30 border border-white/10 p-3 flex items-center gap-3 transition-colors" data-history-set-id="${set.id}">
                <span class="w-8 h-8 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-xs font-black text-app-muted shrink-0" data-history-set-position>${set.position}</span>
                <label class="min-w-0 flex items-baseline justify-center gap-1 flex-1">
                    <input type="number" inputmode="decimal" min="0" value="${this.escapeAttribute(set.weight)}" class="neo-input text-xl font-extrabold text-white" data-field="weight" data-action="input->workout-history#scheduleSetSave change->workout-history#saveSet" aria-label="Poids serie ${set.position}">
                    <span class="text-[10px] text-app-muted font-bold">kg</span>
                </label>
                <span class="text-white/25 font-bold">x</span>
                <label class="min-w-0 flex items-baseline justify-center gap-1 flex-1">
                    <input type="number" inputmode="numeric" min="0" value="${this.escapeAttribute(set.reps)}" class="neo-input text-xl font-extrabold text-white" data-field="reps" data-action="input->workout-history#scheduleSetSave change->workout-history#saveSet" aria-label="Repetitions serie ${set.position}">
                    <span class="text-[10px] text-app-muted font-bold">reps</span>
                </label>
                <span class="w-16 text-[9px] font-black uppercase tracking-wider text-app-muted text-right" data-history-set-status>${set.completed ? 'Validee' : 'Brouillon'}</span>
                <button type="button" class="w-8 h-8 rounded-xl bg-white/5 text-app-muted hover:text-rose-400 hover:bg-rose-500/10 flex items-center justify-center transition-colors shrink-0" data-action="workout-history#deleteSet" aria-label="Supprimer la serie ${set.position}">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
            </div>
        `;
    }

    newSetTemplate(sessionExerciseId, position) {
        return `
            <div class="rounded-2xl bg-app-accent/5 border border-app-accent/20 p-3 flex items-center gap-3 transition-colors" data-history-new-set data-session-exercise-id="${sessionExerciseId}">
                <span class="w-8 h-8 rounded-xl bg-app-accent/10 border border-app-accent/20 flex items-center justify-center text-xs font-black text-app-accent shrink-0">${position}</span>
                <input type="hidden" data-field="position" value="${position}">
                <label class="min-w-0 flex items-baseline justify-center gap-1 flex-1">
                    <input type="number" inputmode="decimal" min="0" placeholder="80" class="neo-input text-xl font-extrabold text-white" data-field="weight" aria-label="Poids nouvelle serie">
                    <span class="text-[10px] text-app-muted font-bold">kg</span>
                </label>
                <span class="text-white/25 font-bold">x</span>
                <label class="min-w-0 flex items-baseline justify-center gap-1 flex-1">
                    <input type="number" inputmode="numeric" min="0" placeholder="8" class="neo-input text-xl font-extrabold text-white" data-field="reps" aria-label="Repetitions nouvelle serie">
                    <span class="text-[10px] text-app-muted font-bold">reps</span>
                </label>
                <span class="hidden" data-history-set-status></span>
                <button type="button" class="w-8 h-8 rounded-xl bg-app-accent text-black flex items-center justify-center shrink-0" data-action="workout-history#createSet" aria-label="Ajouter la serie">
                    <i data-lucide="check" class="w-4 h-4"></i>
                </button>
                <button type="button" class="w-8 h-8 rounded-xl bg-white/5 text-app-muted hover:text-white flex items-center justify-center shrink-0" data-action="workout-history#cancelNewSet" aria-label="Annuler">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        `;
    }

    setPayload(row) {
        const weight = Number.parseFloat(row?.querySelector('[data-field="weight"]')?.value);
        const reps = Number.parseInt(row?.querySelector('[data-field="reps"]')?.value, 10);
        const position = Number.parseInt(row?.querySelector('[data-field="position"]')?.value, 10);

        if (!Number.isFinite(weight) || !Number.isFinite(reps) || weight <= 0 || reps <= 0) {
            row?.classList.add('border-rose-500/50');
            this.setSetState(row, 'Invalide', 'error');
            return null;
        }

        row.classList.remove('border-rose-500/50');

        return Number.isFinite(position) ? { weight, reps, position } : { weight, reps };
    }

    nextSetPosition(section) {
        const positions = Array.from(section?.querySelectorAll('[data-history-set-id]') || [])
            .map((row) => Number.parseInt(row.querySelector('[data-history-set-position]')?.textContent, 10))
            .filter(Number.isFinite);

        return positions.length > 0 ? Math.max(...positions) + 1 : 1;
    }

    setSetState(row, label, state) {
        const status = row?.querySelector('[data-history-set-status]');

        if (!status) {
            return;
        }

        status.textContent = label;
        status.classList.toggle('text-app-accent', state === 'saved');
        status.classList.toggle('text-rose-400', state === 'error');
        status.classList.toggle('text-app-muted', !['saved', 'error'].includes(state));
    }

    syncEmptyState() {
        if (!this.hasListTarget || this.listTarget.querySelector('[data-workout-history-target="row"]')) {
            return;
        }

        this.listTarget.innerHTML = this.emptyHistoryTemplate();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    refreshRelatedViews(views) {
        this.element.dispatchEvent(new CustomEvent('vigor:refresh-views', {
            detail: {
                views,
                nextView: 'workout',
                path: '/app/workout',
                background: true,
            },
            bubbles: true,
        }));
    }

    async reloadCurrentSession() {
        if (!this.currentSessionId) {
            return;
        }

        const session = await this.request(`/api/workout-sessions/${this.currentSessionId}`, 'GET');

        if (session) {
            this.renderSession(session);
        }
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

            if (response.status === 204) {
                return {};
            }

            return response.json();
        } catch {
            return null;
        }
    }

    setBusy(element, busy) {
        element.disabled = busy;
        element.classList.toggle('opacity-70', busy);
    }

    loadingTemplate() {
        return '<div class="glass-panel rounded-2xl p-5 text-center text-sm font-bold text-app-muted">Chargement</div>';
    }

    emptyTemplate(label) {
        return `<div class="rounded-2xl bg-white/[0.04] border border-white/10 p-4 text-center text-xs font-bold text-app-muted" data-history-empty>${this.escapeHtml(label)}</div>`;
    }

    emptyHistoryTemplate() {
        return `
            <div class="glass-panel rounded-2xl p-5 text-center border-dashed" data-workout-history-target="empty">
                <div class="w-12 h-12 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="history" class="w-5 h-5 text-app-muted"></i>
                </div>
                <p class="text-sm font-bold text-white mb-1">Aucun historique</p>
                <p class="text-xs text-app-muted">Tes seances terminees apparaitront ici.</p>
            </div>
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
}
