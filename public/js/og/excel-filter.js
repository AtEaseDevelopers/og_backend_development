/**
 * Excel-style column filters (same look as the Orders table filter: ow-cf-* styles in the order workspace theme).
 *
 * 1. Filament tables on pages using App\Filament\Concerns\HasExcelColumnFilters: every header tagged
 *    data-og-col (AppServiceProvider) gets a funnel; the checklist comes from the page's ogExcelOptions()
 *    and OK / Clear call ogExcelApply(), so the filter runs on the server over every record.
 * 2. Custom-built tables marked data-og-xtable: the funnel filters and sorts the rows shown, in the browser.
 *    A header with data-og-xskip gets no funnel; a column of dates gets a date range instead of a checklist.
 *
 * The menu: Sort A → Z / Z → A, then a checklist of the column's values (search, select all, counts) or a
 * date range (public/js/og/date-range-picker.js), and Clear filter / Cancel / OK.
 */
(() => {
    if (window.OgExcelFilter) return;

    const FUNNEL = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z" clip-rule="evenodd"/></svg>';
    const CALENDAR = '<svg class="og-dr-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4.5" width="14" height="12.5" rx="2"/><path d="M3 8.5h14M7 2.75v3.5M13 2.75v3.5" stroke-linecap="round"/></svg>';
    const BLANK = '(Blank)';
    const MONTHS = { jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5, jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11 };

    let panel = null;
    let panelFor = null;

    const el = (tag, cls, text) => {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const funnelButton = (label, active, onClick) => {
        const button = el('button', 'ow-cf-btn og-xf-btn' + (active ? ' is-active' : ''));
        button.type = 'button';
        button.innerHTML = FUNNEL;
        button.title = 'Filter';
        button.setAttribute('aria-label', 'Filter ' + label);
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation(); // the header itself may sort on click
            if (panel && panelFor === button) { close(); return; }
            onClick(button);
        });
        button.addEventListener('keydown', (event) => event.stopPropagation());
        return button;
    };

    /* ------------------------------------------------------------------ menu */

    function close() {
        if (panel) panel.remove();
        panel = null;
        panelFor = null;
    }

    function place(button) {
        // a button replaced by a Livewire re-render has no position any more: keep the menu where it is
        if (!panel || !button.isConnected) return;
        const r = button.getBoundingClientRect();
        const width = 300;
        const left = Math.max(8, Math.min(r.left - 8, window.innerWidth - width - 8));
        const top = Math.round(r.bottom + 6);
        panel.style.cssText = `top:${top}px;left:${Math.round(left)}px;width:${width}px;max-height:${Math.max(220, window.innerHeight - top - 12)}px`;
    }

    /**
     * @param handlers {load: () => Promise<data>|data, apply: (payload|null) => void, sort: (dir) => void}
     * data: {type: 'list'|'date'|'none', label, sortable, values: [{value, count}], selected, from, to}
     */
    async function openMenu(button, handlers) {
        close();
        panel = el('div', 'ow-cf-panel og-xf-panel');
        panel.setAttribute('role', 'dialog');
        panel.appendChild(el('p', 'ow-cf-none', 'Loading…'));
        panelFor = button;
        document.body.appendChild(panel);
        place(button);

        let data;
        try { data = await handlers.load(); } catch (e) { data = null; }
        if (!panel || panelFor !== button) return;
        panel.innerHTML = '';

        if (!data || data.type === 'none') {
            panel.appendChild(el('p', 'ow-cf-none', 'This column cannot be filtered.'));
            return;
        }

        if (data.sortable) {
            const sort = el('div', 'ow-cf-sort');
            const labels = data.type === 'date' ? ['Sort oldest → newest', 'Sort newest → oldest'] : ['Sort A → Z', 'Sort Z → A'];
            [['asc', labels[0]], ['desc', labels[1]]].forEach(([dir, label]) => {
                const b = el('button', null, label);
                b.type = 'button';
                b.addEventListener('click', () => { close(); handlers.sort(dir); });
                sort.appendChild(b);
            });
            panel.appendChild(sort);
        }

        const field = el('div', 'ow-cf-field');
        field.appendChild(el('div', 'ow-cf-label', data.label || 'Filter'));
        panel.appendChild(field);
        const collect = data.type === 'date' ? dateRange(field, data) : checklist(field, data);

        const foot = el('div', 'ow-cf-foot');
        const clear = el('button', 'ow-btn-link', 'Clear filter');
        clear.type = 'button';
        clear.addEventListener('click', () => { close(); handlers.apply(null); });
        const right = el('span', 'ow-cf-foot-right');
        const cancel = el('button', 'ow-btn ow-btn-sm', 'Cancel');
        cancel.type = 'button';
        cancel.addEventListener('click', close);
        const ok = el('button', 'ow-btn ow-btn-sm ow-btn-primary', 'OK');
        ok.type = 'button';
        ok.addEventListener('click', () => { const payload = collect(); close(); handlers.apply(payload); });
        right.append(cancel, ok);
        foot.append(clear, right);
        panel.appendChild(foot);
        place(button);
    }

    /** Checklist; the collector gives null when every value is ticked. */
    function checklist(field, data) {
        const values = data.values || [];
        const all = values.map((v) => v.value);
        const ticked = new Set(Array.isArray(data.selected) ? data.selected.filter((v) => all.includes(v)) : all);

        const search = el('input', 'ow-cf-search');
        search.type = 'search';
        search.placeholder = 'Search';
        const list = el('div', 'ow-cf-list');
        field.append(search, list);

        const allRow = el('label', 'ow-cf-item ow-cf-all');
        const allBox = el('input');
        allBox.type = 'checkbox';
        allRow.append(allBox, el('span', null, '(Select all)'));
        list.appendChild(allRow);

        const rows = values.map((v) => {
            const row = el('label', 'ow-cf-item');
            const box = el('input');
            box.type = 'checkbox';
            box.checked = ticked.has(v.value);
            box.addEventListener('change', () => { box.checked ? ticked.add(v.value) : ticked.delete(v.value); syncAll(); });
            row.append(box, el('span', 'ow-cf-value', v.value), el('span', 'ow-cf-count', String(v.count)));
            list.appendChild(row);
            return { row, box, value: v.value };
        });

        const none = el('p', 'ow-cf-none', 'No values');
        list.appendChild(none);

        const visible = () => rows.filter((r) => r.row.style.display !== 'none');
        function syncAll() {
            const vis = visible();
            const n = vis.filter((r) => ticked.has(r.value)).length;
            allBox.checked = vis.length > 0 && n === vis.length;
            allBox.indeterminate = n > 0 && n < vis.length;
            none.style.display = vis.length === 0 ? '' : 'none';
        }
        allBox.addEventListener('change', () => {
            visible().forEach((r) => { r.box.checked = allBox.checked; allBox.checked ? ticked.add(r.value) : ticked.delete(r.value); });
            syncAll();
        });
        search.addEventListener('input', () => {
            const q = search.value.toLowerCase();
            rows.forEach((r) => { r.row.style.display = q === '' || r.value.toLowerCase().includes(q) ? '' : 'none'; });
            syncAll();
        });
        syncAll();
        setTimeout(() => search.focus(), 0);

        return () => (ticked.size === all.length ? null : { values: [...ticked] });
    }

    /** Date range with the system date picker; the collector gives {from, to} (Y-m-d) or null. */
    function dateRange(field, data) {
        const root = el('div', 'og-dr' + (data.from || data.to ? ' has-value' : ''));
        root.setAttribute('data-og-daterange', '');
        root.dataset.label = data.label || 'Date';
        const trigger = el('button', 'og-dr-trigger');
        trigger.type = 'button';
        trigger.setAttribute('data-og-daterange-trigger', '');
        trigger.innerHTML = CALENDAR;
        const fmt = (d) => { const [y, m, day] = d.split('-').map(Number); return new Date(y, m - 1, day).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }); };
        const text = el('span', 'og-dr-text' + (data.from || data.to ? '' : ' is-placeholder'));
        text.setAttribute('data-og-daterange-text', '');
        text.dataset.placeholder = 'Any date';
        text.textContent = data.from && data.to ? (data.from === data.to ? fmt(data.from) : fmt(data.from) + ' – ' + fmt(data.to)) : data.from ? 'From ' + fmt(data.from) : data.to ? 'Until ' + fmt(data.to) : 'Any date';
        trigger.appendChild(text);
        const from = el('input');
        from.type = 'hidden';
        from.value = data.from || '';
        from.setAttribute('data-og-daterange-from', '');
        const to = el('input');
        to.type = 'hidden';
        to.value = data.to || '';
        to.setAttribute('data-og-daterange-to', '');
        root.append(trigger, from, to);
        field.append(root, el('p', 'ow-cf-none', 'Pick the start day, then the end day.'));

        return () => (from.value || to.value ? { from: from.value || null, to: to.value || null } : null);
    }

    /* ------------------------------------------------- 1. Filament tables (server) */

    const wireFor = (node) => {
        const root = node.closest('[wire\\:id]');
        // Livewire 3: find() gives the component's $wire
        return root && window.Livewire ? window.Livewire.find(root.getAttribute('wire:id')) : null;
    };

    function decorateFilament() {
        document.querySelectorAll('th[data-og-col]').forEach((th) => {
            if (th.querySelector('.og-xf-btn')) return;
            const wire = wireFor(th);
            let enabled = false;
            try { enabled = !!wire && wire.ogExcelEnabled === true; } catch (e) { enabled = false; }
            if (!enabled) return;

            const column = th.dataset.ogCol;
            let active = false;
            try { active = !!(wire.ogExcelFilters || {})[column]; } catch (e) { active = false; }

            const button = funnelButton((th.textContent || '').trim(), active, (b) => openMenu(b, {
                load: () => wire.ogExcelOptions(column),
                apply: (payload) => wire.ogExcelApply(column, payload),
                sort: (dir) => wire.sortTable(column, dir),
            }));
            (th.querySelector(':scope > span') || th).appendChild(button);
        });
    }

    /* ------------------------------------------- 2. custom tables (in the browser) */

    const tableState = new WeakMap(); // table -> {filters: {index: {values}|{from,to}}, sort: {index, dir}}
    const savedState = {}; // per page + table position, so a Livewire re-render keeps the filters

    const tableKey = (table) => location.pathname + '#' + [...document.querySelectorAll('table[data-og-xtable]')].indexOf(table);

    const stateOf = (table) => {
        if (!tableState.has(table)) tableState.set(table, savedState[tableKey(table)] || { filters: {}, sort: null });
        return tableState.get(table);
    };

    const bodyRows = (table) => [...table.tBodies].flatMap((tb) => [...tb.rows]).filter((row) => !row.hasAttribute('data-og-xskip') && row.cells.length > 1);

    /** The value a cell shows: its first line of text. */
    const cellValue = (row, index) => {
        const cell = row.cells[index];
        if (!cell) return BLANK;
        const first = (cell.innerText || cell.textContent || '').split('\n').map((s) => s.trim()).find((s) => s !== '');
        return first && first !== '—' && first !== '-' ? first : BLANK;
    };

    /** dd/mm/yyyy, d M yyyy (optionally with a weekday / time) or yyyy-mm-dd -> Y-m-d, else null. */
    const parseDate = (text) => {
        let m = text.match(/(\d{1,2})\/(\d{1,2})\/(\d{4})/);
        if (m) return `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
        m = text.match(/(\d{1,2})\s+([A-Za-z]{3})[a-z]*\s+(\d{4})/);
        if (m && MONTHS[m[2].toLowerCase()] !== undefined) return `${m[3]}-${String(MONTHS[m[2].toLowerCase()] + 1).padStart(2, '0')}-${m[1].padStart(2, '0')}`;
        m = text.match(/(\d{4})-(\d{2})-(\d{2})/);
        return m ? `${m[1]}-${m[2]}-${m[3]}` : null;
    };

    const isDateColumn = (table, index) => {
        const values = bodyRows(table).map((r) => cellValue(r, index)).filter((v) => v !== BLANK);
        return values.length > 0 && values.every((v) => parseDate(v) !== null);
    };

    const rowPasses = (row, filters, skip) => Object.entries(filters).every(([index, filter]) => {
        if (Number(index) === skip) return true;
        const value = cellValue(row, Number(index));
        if (filter.values) return filter.values.includes(value);
        const day = parseDate(value);
        return day !== null && (!filter.from || day >= filter.from) && (!filter.to || day <= filter.to);
    });

    function applyTable(table) {
        const state = stateOf(table);
        bodyRows(table).forEach((row) => { row.style.display = rowPasses(row, state.filters, -1) ? '' : 'none'; });

        // remember the page's own row order, so a third header click can bring it back
        [...table.tBodies].forEach((tb) => [...tb.rows].forEach((r, i) => { if (!r.dataset.ogXi) r.dataset.ogXi = String(i); }));

        if (!state.sort) {
            [...table.tBodies].forEach((tb) => [...tb.rows].sort((a, b) => Number(a.dataset.ogXi) - Number(b.dataset.ogXi)).forEach((r) => tb.appendChild(r)));
        } else {
            const { index, dir } = state.sort;
            const date = isDateColumn(table, index);
            const key = (row) => { const v = cellValue(row, index); return date ? (parseDate(v) || '') : v; };
            [...table.tBodies].forEach((tb) => {
                const rows = [...tb.rows].filter((r) => !r.hasAttribute('data-og-xskip') && r.cells.length > 1);
                rows.sort((a, b) => (dir === 'desc' ? -1 : 1) * String(key(a)).localeCompare(String(key(b)), undefined, { numeric: true, sensitivity: 'base' }));
                rows.forEach((r) => tb.appendChild(r));
            });
        }

        const head = table.tHead ? table.tHead.rows[table.tHead.rows.length - 1] : null;
        if (head) [...head.cells].forEach((th, index) => th.querySelector('.og-xf-btn')?.classList.toggle('is-active', !!state.filters[index]));
        savedState[tableKey(table)] = state;
    }

    function decorateTables() {
        document.querySelectorAll('table[data-og-xtable]').forEach((table) => {
            const head = table.tHead ? table.tHead.rows[table.tHead.rows.length - 1] : null;
            if (!head) return;
            const state = stateOf(table);
            let added = false;

            [...head.cells].forEach((th, index) => {
                if (th.querySelector('.og-xf-btn') || th.hasAttribute('data-og-xskip') || (th.textContent || '').trim() === '' || th.colSpan > 1) return;
                const label = (th.textContent || '').trim();
                const button = funnelButton(label, !!state.filters[index], (b) => openMenu(b, {
                    load: () => {
                        if (isDateColumn(table, index)) {
                            const f = stateOf(table).filters[index] || {};
                            return { type: 'date', label, sortable: true, from: f.from || null, to: f.to || null };
                        }
                        const counts = {};
                        bodyRows(table).filter((r) => rowPasses(r, stateOf(table).filters, index)).forEach((r) => {
                            const v = cellValue(r, index);
                            counts[v] = (counts[v] || 0) + 1;
                        });
                        const values = Object.keys(counts)
                            .sort((a, b) => (a === BLANK) - (b === BLANK) || a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }))
                            .map((v) => ({ value: v, count: counts[v] }));
                        const f = stateOf(table).filters[index];
                        return { type: 'list', label, sortable: true, values, selected: f && f.values ? f.values : null };
                    },
                    apply: (payload) => {
                        const s = stateOf(table);
                        if (payload === null) delete s.filters[index]; else s.filters[index] = payload;
                        applyTable(table);
                    },
                    sort: (dir) => { stateOf(table).sort = { index, dir }; applyTable(table); },
                }));
                th.classList.add('og-xf-th');
                th.appendChild(button);
                added = true;
            });

            // rows re-rendered by Livewire keep the filters
            if (added && (Object.keys(state.filters).length || state.sort)) applyTable(table);
        });
    }

    /* ------------------------------- column toggle at the end of the header row */

    const TOGGLE_ICON = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 4.75A.75.75 0 012.75 4h3.5a.75.75 0 01.75.75v10.5a.75.75 0 01-.75.75h-3.5a.75.75 0 01-.75-.75V4.75zM8.25 4a.75.75 0 00-.75.75v10.5c0 .414.336.75.75.75h3.5a.75.75 0 00.75-.75V4.75a.75.75 0 00-.75-.75h-3.5zM13.75 4a.75.75 0 00-.75.75v10.5c0 .414.336.75.75.75h3.5a.75.75 0 00.75-.75V4.75a.75.75 0 00-.75-.75h-3.5z"/></svg>';

    /** The toolbar above a Filament table is hidden while nothing in it is shown (search, filters, bulk actions…). */
    function syncToolbar(toolbar) {
        const shown = [...toolbar.querySelectorAll('input, button, select, h2, h3, [role="button"]')].filter((n) => {
            const hidden = n.closest('[style*="display: none"], [x-cloak], .og-native-toggle');
            return !hidden || !toolbar.contains(hidden);
        });
        const empty = shown.length === 0;
        if (toolbar.classList.contains('og-toolbar-empty') !== empty) toolbar.classList.toggle('og-toolbar-empty', empty);
    }

    /**
     * Filament: the column toggle sits in the last header cell (as on Orders). Our button lists the page's
     * toggleable columns (ogToggleableColumns) and sets Filament's toggledTableColumns; Filament's own toggle
     * button above the table is hidden, and a toolbar left empty is hidden too.
     */
    function decorateFilamentToggles() {
        document.querySelectorAll('.fi-ta').forEach((ta) => {
            const headRow = ta.querySelector('.fi-ta-table > thead > tr');
            const wire = headRow ? wireFor(headRow) : null;
            let enabled = false;
            try { enabled = !!wire && wire.ogExcelEnabled === true; } catch (e) { enabled = false; }
            if (!enabled) return;

            const toolbar = ta.querySelector('.fi-ta-header-ctn');
            const native = toolbar ? toolbar.querySelector('.fi-ta-col-toggle') : null;
            if (native) native.classList.add('og-native-toggle');
            if (toolbar) {
                syncToolbar(toolbar);
                // bulk actions appear in the toolbar once rows are ticked (Alpine x-show): show it again then
                if (!toolbar.dataset.ogWatched) {
                    toolbar.dataset.ogWatched = '1';
                    new MutationObserver(() => syncToolbar(toolbar)).observe(toolbar, { subtree: true, childList: true, attributes: true, attributeFilter: ['style', 'class'] });
                }
            }

            const last = headRow.lastElementChild;
            if (!native || !last || last.querySelector('.og-ft-toggle')) return;
            let anyHidden = false;
            try { anyHidden = Object.values(wire.toggledTableColumns || {}).some((v) => v === false || (v && typeof v === 'object' && Object.values(v).includes(false))); } catch (e) { anyHidden = false; }
            const button = el('button', 'ow-cf-btn og-ft-toggle' + (anyHidden ? ' is-active' : ''));
            button.type = 'button';
            button.innerHTML = TOGGLE_ICON;
            button.title = 'Show / hide columns';
            button.setAttribute('aria-label', 'Show / hide columns');
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (panel && panelFor === button) { close(); return; }
                close();
                panel = el('div', 'ow-cf-panel og-xf-panel');
                panel.setAttribute('role', 'dialog');
                panelFor = button;
                panel.appendChild(el('div', 'ow-cf-label', 'Columns'));
                document.body.appendChild(panel);
                const r = button.getBoundingClientRect();
                panel.style.cssText = `top:${Math.round(r.bottom + 6)}px;left:${Math.round(Math.max(8, r.right - 240))}px;width:240px;max-height:${Math.max(220, window.innerHeight - r.bottom - 18)}px`;
                let columns = [];
                try { columns = await wire.ogToggleableColumns(); } catch (e) { columns = []; }
                if (!panel) return;
                columns.forEach((c) => {
                    const row = el('label', 'ow-cf-item');
                    const box = el('input');
                    box.type = 'checkbox';
                    box.checked = !!c.visible;
                    box.addEventListener('change', () => wire.set('toggledTableColumns.' + c.name, box.checked));
                    row.append(box, el('span', 'ow-cf-value', c.label));
                    panel.appendChild(row);
                });
                if (columns.length === 0) panel.appendChild(el('p', 'ow-cf-none', 'No columns to hide.'));
            });
            last.classList.add('og-col-toggle-cell');
            last.appendChild(button);
        });
    }

    /** Custom tables: click a header to sort (▲ / ▼, a third click goes back to the page's order) and a column toggle. */
    const hiddenKey = (table) => 'og-xt-hidden:' + tableKey(table);
    const hiddenColumns = (table) => { try { return JSON.parse(localStorage.getItem(hiddenKey(table)) || '[]'); } catch (e) { return []; } };

    function applyHidden(table) {
        const hidden = hiddenColumns(table);
        const head = table.tHead ? table.tHead.rows[table.tHead.rows.length - 1] : null;
        if (!head) return;
        const last = head.cells.length - 1;
        [...table.rows].forEach((row) => {
            if (row.cells.length !== head.cells.length) return; // empty-state / grouped rows
            [...row.cells].forEach((cell, index) => { if (index !== last || row.parentNode !== table.tHead) cell.style.display = hidden.includes(index) ? 'none' : ''; });
        });
    }

    function decorateTableHeaders() {
        document.querySelectorAll('table[data-og-xtable]').forEach((table) => {
            const head = table.tHead ? table.tHead.rows[table.tHead.rows.length - 1] : null;
            if (!head) return;
            const state = stateOf(table);

            [...head.cells].forEach((th, index) => {
                if (th.classList.contains('og-xt-sortable') || th.hasAttribute('data-og-xskip') || (th.textContent || '').trim() === '' || th.colSpan > 1) return;
                th.classList.add('og-xt-sortable');
                const mark = el('span', 'og-xt-sort', '↕');
                const funnel = th.querySelector('.og-xf-btn');
                funnel ? th.insertBefore(mark, funnel) : th.appendChild(mark);
                th.addEventListener('click', (event) => {
                    if (event.target.closest('.og-xf-btn, .og-xt-toggle, .og-xf-panel')) return;
                    const s = stateOf(table);
                    s.sort = s.sort && s.sort.index === index ? (s.sort.dir === 'asc' ? { index, dir: 'desc' } : null) : { index, dir: 'asc' };
                    applyTable(table);
                    syncSortMarks(table);
                });
            });
            syncSortMarks(table);

            // column toggle in the last header cell
            const last = head.cells[head.cells.length - 1];
            if (last && !last.querySelector('.og-xt-toggle')) {
                const button = el('button', 'ow-cf-btn og-xt-toggle' + (hiddenColumns(table).length ? ' is-active' : ''));
                button.type = 'button';
                button.innerHTML = TOGGLE_ICON;
                button.title = 'Show / hide columns';
                button.setAttribute('aria-label', 'Show / hide columns');
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    if (panel && panelFor === button) { close(); return; }
                    openToggle(table, button);
                });
                last.classList.add('og-col-toggle-cell');
                last.appendChild(button);
            }
            applyHidden(table);
            if (state.sort) syncSortMarks(table);
        });
    }

    function syncSortMarks(table) {
        const head = table.tHead.rows[table.tHead.rows.length - 1];
        const sort = stateOf(table).sort;
        [...head.cells].forEach((th, index) => {
            const mark = th.querySelector('.og-xt-sort');
            if (!mark) return;
            const on = sort && sort.index === index;
            mark.textContent = on ? (sort.dir === 'desc' ? '▼' : '▲') : '↕';
            mark.classList.toggle('is-sorted', !!on);
            on ? th.setAttribute('aria-sort', sort.dir === 'desc' ? 'descending' : 'ascending') : th.removeAttribute('aria-sort');
        });
    }

    function openToggle(table, button) {
        close();
        panel = el('div', 'ow-cf-panel og-xf-panel');
        panel.setAttribute('role', 'dialog');
        panelFor = button;
        panel.appendChild(el('div', 'ow-cf-label', 'Columns'));
        const head = table.tHead.rows[table.tHead.rows.length - 1];
        const hidden = new Set(hiddenColumns(table));
        [...head.cells].forEach((th, index) => {
            const label = (th.textContent || '').replace(/[↕▲▼]/g, '').trim();
            if (label === '' || index === 0) return; // the first column always stays
            const row = el('label', 'ow-cf-item');
            const box = el('input');
            box.type = 'checkbox';
            box.checked = !hidden.has(index);
            box.addEventListener('change', () => {
                box.checked ? hidden.delete(index) : hidden.add(index);
                try { localStorage.setItem(hiddenKey(table), JSON.stringify([...hidden])); } catch (e) { /* private mode */ }
                button.classList.toggle('is-active', hidden.size > 0);
                applyHidden(table);
            });
            row.append(box, el('span', 'ow-cf-value', label));
            panel.appendChild(row);
        });
        document.body.appendChild(panel);
        const r = button.getBoundingClientRect();
        panel.style.cssText = `top:${Math.round(r.bottom + 6)}px;left:${Math.round(Math.max(8, r.right - 240))}px;width:240px;max-height:${Math.max(220, window.innerHeight - r.bottom - 18)}px`;
    }

    /* ---------------------------------------------------------------- wiring */

    function decorate() {
        decorateFilament();
        decorateTables();
        decorateTableHeaders();
        decorateFilamentToggles();
    }

    // close on a click outside the menu (the date picker's calendar counts as inside)
    document.addEventListener('mousedown', (event) => {
        if (!panel) return;
        const t = event.target;
        if (panel.contains(t) || (panelFor && panelFor.contains(t)) || (t.closest && t.closest('.og-dr-calendar'))) return;
        close();
    }, true);
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && panel && !document.querySelector('.og-dr-calendar')) close(); });
    window.addEventListener('scroll', () => { if (panel && panelFor) place(panelFor); }, true);
    window.addEventListener('resize', () => { if (panel && panelFor) place(panelFor); });

    // Livewire re-renders tables (and drops the funnels): add them again
    let pending = false;
    const schedule = () => {
        if (pending) return;
        pending = true;
        // a timer, not requestAnimationFrame: background tabs pause animation frames
        setTimeout(() => { pending = false; decorate(); }, 30);
    };
    new MutationObserver(schedule).observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('DOMContentLoaded', schedule);
    document.addEventListener('livewire:init', schedule);
    // components only answer once Livewire has started
    document.addEventListener('livewire:initialized', schedule);
    document.addEventListener('livewire:navigated', schedule);
    window.addEventListener('load', () => { schedule(); setTimeout(schedule, 500); });

    window.OgExcelFilter = { decorate, close };
})();
