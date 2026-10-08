/*
 * Date range picker: one field, pick the start day then the end day on a calendar.
 *
 * Markup (resources/views/components/og/date-range.blade.php):
 *   <div data-og-daterange>
 *     <button data-og-daterange-trigger> … <span data-og-daterange-text data-placeholder="Any date">…</span></button>
 *     <button data-og-daterange-clear> (optional)
 *     <input type="hidden" data-og-daterange-from>  <input type="hidden" data-og-daterange-to>   (Y-m-d)
 *   </div>
 * With data-og-daterange-mode="single" on the root it picks one day (only the "from" input is used).
 *
 * The page renders the trigger text itself, so Livewire re-renders need nothing from this script: it only
 * opens the calendar and writes the two hidden inputs, dispatching input + change so wire:model / x-model
 * and plain forms pick the new values up. No dependencies.
 */
(() => {
    if (window.OgDateRange) {
        return;
    }

    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const SHORT_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

    const pad = (n) => String(n).padStart(2, '0');
    const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const parse = (value) => {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
    };
    const today = () => {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), now.getDate());
    };
    const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
    const monthStart = (d) => new Date(d.getFullYear(), d.getMonth(), 1);
    const addMonths = (d, n) => new Date(d.getFullYear(), d.getMonth() + n, 1);
    const monthEnd = (d) => new Date(d.getFullYear(), d.getMonth() + 1, 0);
    const same = (a, b) => Boolean(a && b) && a.getTime() === b.getTime();
    const label = (d) => `${d.getDate()} ${SHORT_MONTHS[d.getMonth()]} ${d.getFullYear()}`;
    const rangeText = (start, end) => {
        if (start && end) {
            return same(start, end) ? label(start) : `${label(start)} – ${label(end)}`;
        }
        if (start) {
            return `From ${label(start)}`;
        }
        return end ? `Until ${label(end)}` : '';
    };

    const SINGLE_PRESETS = [
        ['Today', (t) => [t, null]],
        ['Yesterday', (t) => [addDays(t, -1), null]],
        ['Tomorrow', (t) => [addDays(t, 1), null]],
    ];

    const PRESETS = [
        ['Today', (t) => [t, t]],
        ['Yesterday', (t) => [addDays(t, -1), addDays(t, -1)]],
        ['Last 7 days', (t) => [addDays(t, -6), t]],
        ['Last 30 days', (t) => [addDays(t, -29), t]],
        ['This month', (t) => [monthStart(t), monthEnd(t)]],
        ['Last month', (t) => [addMonths(t, -1), monthEnd(addMonths(t, -1))]],
        ['This year', (t) => [new Date(t.getFullYear(), 0, 1), new Date(t.getFullYear(), 11, 31)]],
    ];

    /** @type {null | {root: HTMLElement, trigger: HTMLElement, panel: HTMLElement, start: ?Date, end: ?Date, picking: boolean, hover: ?Date, view: Date, months: number}} */
    let state = null;

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    };

    const button = (className, text) => {
        const node = el('button', className, text);
        node.type = 'button';
        return node;
    };

    const inputsOf = (root) => [root.querySelector('[data-og-daterange-from]'), root.querySelector('[data-og-daterange-to]')];

    /** Write both ends (null clears) and tell wire:model / x-model / forms about it. */
    function write(root, start, end) {
        const [from, to] = inputsOf(root);

        [[from, start], [to, end]].forEach(([input, date]) => {
            if (!input) {
                return;
            }
            const value = date ? iso(date) : '';
            if (input.value === value) {
                return;
            }
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Plain pages (no Livewire re-render) show the new range straight away
        const text = root.querySelector('[data-og-daterange-text]');
        if (text) {
            const value = isSingle(root) ? (start ? label(start) : '') : rangeText(start, end);
            text.textContent = value || text.dataset.placeholder || '';
            text.classList.toggle('is-placeholder', !value);
        }
        root.classList.toggle('has-value', Boolean(start || end));
    }

    const isSingle = (root) => root.dataset.ogDaterangeMode === 'single';

    function monthsToShow() {
        return window.innerWidth < 640 || (state && isSingle(state.root)) ? 1 : 2;
    }

    function open(root) {
        close();

        const trigger = root.querySelector('[data-og-daterange-trigger]');
        if (!trigger || trigger.disabled) {
            return;
        }

        const [from, to] = inputsOf(root);
        const start = parse(from?.value);
        const end = parse(to?.value);
        const panel = el('div', 'og-dr-panel');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', root.dataset.label || 'Choose a date range');
        panel.tabIndex = -1;

        // Inside a modal the calendar has to live in the dialog, or its focus trap fights the calendar
        const host = trigger.closest('[role="dialog"], dialog, .fi-modal-window') || document.body;
        host.appendChild(panel);

        state = { root, trigger, panel, start, end: isSingle(root) ? null : end, picking: false, hover: null, view: monthStart(start || end || today()), months: 1 };
        state.months = monthsToShow();

        render();
        position();
        trigger.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');

        document.addEventListener('pointerdown', onOutside, true);
        document.addEventListener('keydown', onKeydown, true);
        window.addEventListener('resize', onResize);
        window.addEventListener('scroll', onScroll, true);

        (panel.querySelector('.og-dr-day.is-start') || panel.querySelector('.og-dr-day.is-today') || panel.querySelector('.og-dr-day'))?.focus({ preventScroll: true });
    }

    function close(focusTrigger = false) {
        if (!state) {
            return;
        }

        const { panel, trigger, root } = state;
        state = null;

        panel.remove();
        trigger.setAttribute('aria-expanded', 'false');
        root.classList.remove('is-open');

        document.removeEventListener('pointerdown', onOutside, true);
        document.removeEventListener('keydown', onKeydown, true);
        window.removeEventListener('resize', onResize);
        window.removeEventListener('scroll', onScroll, true);

        if (focusTrigger && trigger.isConnected) {
            trigger.focus({ preventScroll: true });
        }
    }

    function finish(start, end) {
        const root = state?.root;
        close(true);
        if (root) {
            write(root, start, end);
        }
    }

    function render() {
        const { panel } = state;
        panel.textContent = '';

        const presets = el('div', 'og-dr-presets');
        (isSingle(state.root) ? SINGLE_PRESETS : PRESETS).forEach(([name, range]) => {
            const preset = button('og-dr-preset', name);
            preset.addEventListener('click', () => {
                const [start, end] = range(today());
                finish(start, end);
            });
            presets.append(preset);
        });
        const clear = button('og-dr-preset og-dr-preset-clear', isSingle(state.root) ? 'Clear date' : 'Clear dates');
        clear.addEventListener('click', () => finish(null, null));
        presets.append(clear);

        const calendar = el('div', 'og-dr-calendar');
        const months = el('div', 'og-dr-months');

        for (let i = 0; i < state.months; i++) {
            months.append(monthGrid(addMonths(state.view, i), i === 0, i === state.months - 1));
        }

        const hint = el('div', 'og-dr-hint');
        hint.setAttribute('aria-live', 'polite');
        calendar.append(months, hint);
        panel.append(presets, calendar);

        paint();
    }

    function monthGrid(month, first, last) {
        const box = el('div', 'og-dr-month');
        const head = el('div', 'og-dr-month-head');

        const prev = button('og-dr-nav', '‹');
        prev.setAttribute('aria-label', 'Previous month');
        prev.addEventListener('click', () => shift(-1));
        const next = button('og-dr-nav', '›');
        next.setAttribute('aria-label', 'Next month');
        next.addEventListener('click', () => shift(1));
        prev.style.visibility = first ? '' : 'hidden';
        next.style.visibility = last ? '' : 'hidden';

        head.append(prev, el('div', 'og-dr-title', `${MONTHS[month.getMonth()]} ${month.getFullYear()}`), next);

        const grid = el('div', 'og-dr-grid');
        WEEKDAYS.forEach((day) => grid.append(el('div', 'og-dr-weekday', day)));

        const offset = (month.getDay() + 6) % 7; // weeks start on Monday
        for (let i = 0; i < offset; i++) {
            grid.append(el('div', 'og-dr-blank'));
        }

        const days = monthEnd(month).getDate();
        for (let d = 1; d <= days; d++) {
            const date = new Date(month.getFullYear(), month.getMonth(), d);
            const day = button('og-dr-day', String(d));
            day.dataset.date = iso(date);
            day.setAttribute('aria-label', label(date));
            day.classList.toggle('is-today', same(date, today()));
            day.addEventListener('click', () => choose(date));
            day.addEventListener('mouseenter', () => {
                if (state?.picking) {
                    state.hover = date;
                    paint();
                }
            });
            day.addEventListener('focus', () => {
                if (state?.picking) {
                    state.hover = date;
                    paint();
                }
            });
            grid.append(day);
        }

        box.append(head, grid);
        return box;
    }

    function shift(months) {
        state.view = addMonths(state.view, months);
        const focused = document.activeElement;
        const wasNav = focused?.classList?.contains('og-dr-nav');
        render();
        if (wasNav) {
            state.panel.querySelector(months < 0 ? '.og-dr-nav' : '.og-dr-month:last-child .og-dr-nav:last-child')?.focus({ preventScroll: true });
        }
    }

    function choose(date) {
        if (isSingle(state.root)) {
            finish(date, null);
            return;
        }

        if (!state.picking) {
            state.start = date;
            state.end = null;
            state.hover = date;
            state.picking = true;
            paint();
            return;
        }

        let start = state.start;
        let end = date;
        if (end < start) {
            [start, end] = [end, start];
        }
        finish(start, end);
    }

    /** Mark start / end / in-between days and update the hint, without rebuilding the calendar. */
    function paint() {
        const { panel, picking } = state;
        let lo = state.start;
        let hi = picking ? state.hover : state.end;
        if (lo && hi && hi < lo) {
            [lo, hi] = [hi, lo];
        }

        panel.querySelectorAll('.og-dr-day').forEach((day) => {
            const date = parse(day.dataset.date);
            const isStart = same(date, lo);
            const isEnd = same(date, hi);
            day.classList.toggle('is-start', isStart);
            day.classList.toggle('is-end', isEnd);
            day.classList.toggle('is-in-range', Boolean(lo && hi && date > lo && date < hi));
            day.setAttribute('aria-pressed', isStart || isEnd ? 'true' : 'false');
        });

        const hint = panel.querySelector('.og-dr-hint');
        if (hint && isSingle(state.root)) {
            hint.textContent = state.start ? label(state.start) : 'Pick a date';
        } else if (hint) {
            hint.textContent = picking
                ? `${label(state.start)} – pick the end date`
                : (rangeText(state.start, state.end) || 'Pick the start date');
        }
    }

    function position() {
        const { trigger, panel } = state;
        const rect = trigger.getBoundingClientRect();
        const gap = 6;
        const margin = 8;

        panel.style.left = '0px';
        panel.style.top = '0px';
        const width = panel.offsetWidth;
        const height = panel.offsetHeight;

        let left = Math.min(rect.left, window.innerWidth - width - margin);
        left = Math.max(margin, left);

        const below = window.innerHeight - rect.bottom - gap;
        const above = rect.top - gap;
        let top = below >= height || below >= above ? rect.bottom + gap : rect.top - gap - height;
        top = Math.max(margin, Math.min(top, window.innerHeight - height - margin));

        panel.style.left = `${left}px`;
        panel.style.top = `${top}px`;

        // A transformed ancestor (modal animation) shifts position:fixed boxes: correct by the measured offset
        const placed = panel.getBoundingClientRect();
        if (Math.abs(placed.left - left) > 1 || Math.abs(placed.top - top) > 1) {
            panel.style.left = `${left - (placed.left - left)}px`;
            panel.style.top = `${top - (placed.top - top)}px`;
        }
    }

    function onOutside(event) {
        if (!state) {
            return;
        }
        if (state.panel.contains(event.target) || state.trigger.contains(event.target)) {
            return;
        }
        close();
    }

    function onKeydown(event) {
        if (!state) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close(true);
            return;
        }

        const day = event.target.closest?.('.og-dr-day');
        const moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
        if (!day || !(event.key in moves) || !state.panel.contains(day)) {
            return;
        }

        event.preventDefault();
        const target = addDays(parse(day.dataset.date), moves[event.key]);
        let next = state.panel.querySelector(`.og-dr-day[data-date="${iso(target)}"]`);
        if (!next) {
            state.view = monthStart(event.key === 'ArrowLeft' || event.key === 'ArrowUp' ? target : addMonths(target, 1 - state.months));
            render();
            next = state.panel.querySelector(`.og-dr-day[data-date="${iso(target)}"]`);
        }
        next?.focus({ preventScroll: true });
    }

    function onResize() {
        if (!state) {
            return;
        }
        if (!state.root.isConnected) {
            close();
            return;
        }
        if (monthsToShow() !== state.months) {
            state.months = monthsToShow();
            render();
        }
        position();
    }

    function onScroll(event) {
        if (!state || state.panel.contains(event.target)) {
            return;
        }
        if (!state.root.isConnected) {
            close();
            return;
        }
        position();
    }

    document.addEventListener('click', (event) => {
        const clear = event.target.closest?.('[data-og-daterange-clear]');
        if (clear) {
            const root = clear.closest('[data-og-daterange]');
            if (root) {
                event.preventDefault();
                close();
                write(root, null, null);
            }
            return;
        }

        const trigger = event.target.closest?.('[data-og-daterange-trigger]');
        if (!trigger) {
            return;
        }
        const root = trigger.closest('[data-og-daterange]');
        if (!root) {
            return;
        }
        event.preventDefault();
        if (state && state.root === root) {
            close(true);
        } else {
            open(root);
        }
    });

    window.OgDateRange = {
        open: (root) => open(root.closest?.('[data-og-daterange]') || root),
        close: () => close(),
    };
})();
