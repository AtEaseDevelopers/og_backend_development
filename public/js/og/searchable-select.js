/**
 * OG searchable select: turns every plain single-choice <select> into a select2-style
 * "type to filter, then pick" control, in the admin panel and on the customer portal.
 *
 * Progressive enhancement only. The native <select> stays in the DOM, visible and authoritative
 * (it keeps showing the chosen label, Livewire morphs keep updating it, form validation still
 * applies). Clicking it, tapping it or opening it from the keyboard shows a floating search panel
 * instead of the browser popup; picking writes the option back to the select and fires `input`
 * and `change`, so wire:model(.live), x-model and inline onchange handlers behave as before.
 *
 * Everything is wired through document-level listeners, so selects rendered later or replaced by a
 * Livewire morph need no init. Skipped: [multiple], size > 1, disabled, hidden, Filament's own
 * Choices.js selects, and anything carrying (or inside) [data-native-select].
 */
(() => {
    'use strict';

    if (window.OgSearchableSelect) {
        return;
    }

    const CHUNK = 300;            // rows built per batch; further batches are appended while scrolling
    const GAP = 4;                // px between the select and the panel
    const EDGE = 8;               // px kept clear of the viewport edges
    const MIN_WIDTH = 220;
    const MAX_WIDTH = 480;        // the panel may grow past the select up to this width for long labels
    const MIN_LIST_HEIGHT = 120;  // never squeeze the list below this, even with no room on either side
    const TOUCH_SLOP = 10;        // px of finger travel that turns a tap into a scroll
    const TOUCH_KEYBOARD_MIN = 10; // on touch, only raise the on-screen keyboard for lists longer than this

    const ICON_SEARCH = '<svg class="og-ss-search-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>';
    const ICON_CHECK = '<svg class="og-ss-check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>';

    let seq = 0;
    let state = null;       // the open panel, if any (only one at a time)
    let touch = null;       // tap tracking between touchstart and touchend
    let lastTouchAt = 0;    // a handled tap swallows the emulated mousedown that may follow it
    let lastPointerType = ''; // pointerType of the latest pointerdown anywhere ('mouse' / 'touch' / 'pen')

    // Case- and accent-insensitive form used for matching ("Café" matches "cafe").
    const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

    const closestSelect = (target) => (target instanceof Element ? target.closest('select') : null);

    // Finger and stylus input. Focusing a <select> from such a gesture opens the native picker on iOS
    // (as focusing an input raises the keyboard), so after a tap the select does not get focus back.
    const isTouchLike = (pointerType) => pointerType === 'touch' || pointerType === 'pen';

    function isShown(el) {
        return el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
    }

    function isEligible(select) {
        return select instanceof HTMLSelectElement
            && select.isConnected
            && !select.multiple
            && !(select.size > 1)
            && !select.matches(':disabled')
            && !select.closest('[data-native-select]')
            && !select.classList.contains('choices__input')
            && !select.closest('.choices')
            && isShown(select);
    }

    // The panel lives inside the select's dialog when there is one: Filament's x-trap focus trap would
    // pull focus back out of a body-level panel, a modal <dialog> paints in the top layer, and the
    // click-away handlers of modals / dropdown panels (x-float marks those role="dialog") would treat a
    // click in the panel as "outside".
    function hostFor(select) {
        return select.closest('dialog, [role="dialog"]') || select.closest('.fi-modal-window') || document.body;
    }

    function readItems(select) {
        const items = [];
        const options = select.options;

        for (let i = 0; i < options.length; i++) {
            const opt = options[i];

            if (opt.hidden) {
                continue;
            }

            const group = opt.parentElement && opt.parentElement.tagName === 'OPTGROUP' ? opt.parentElement : null;

            items.push({
                opt,
                value: opt.value,
                label: (opt.label || opt.text || '').replace(/\s+/g, ' ').trim(),
                group,
                groupLabel: group ? (group.label || '').trim() : '',
                disabled: opt.disabled || (group !== null && group.disabled),
                // optional extras: a tag beside the label (data-og-tag / data-og-tag-tone) and a second line (data-og-sub)
                tag: (opt.dataset.ogTag || '').trim(),
                tagTone: (opt.dataset.ogTagTone || '').trim(),
                sub: (opt.dataset.ogSub || '').replace(/\s+/g, ' ').trim(),
                key: null, // folded label (and second line), computed on the first search
            });
        }

        return items;
    }

    // Every whitespace-separated term must appear somewhere in the label.
    function filterItems(items, query) {
        const terms = fold(query).split(/\s+/).filter(Boolean);

        if (!terms.length) {
            return items;
        }

        return items.filter((item) => {
            if (item.key === null) {
                item.key = fold(item.sub ? item.label + ' ' + item.sub : item.label);
            }

            return terms.every((term) => item.key.includes(term));
        });
    }

    function viewport() {
        const doc = document.documentElement;

        return {
            width: doc.clientWidth || window.innerWidth,
            height: Math.min(window.innerHeight, doc.clientHeight || window.innerHeight),
        };
    }

    // Name for the panel's listbox, in accessible-name order: aria-labelledby, aria-label, <label>.
    function accessibleName(select) {
        const referenced = (select.getAttribute('aria-labelledby') || '').split(/\s+/)
            .map((ref) => (ref ? document.getElementById(ref) : null))
            .filter(Boolean);

        const name = joinText(referenced)
            || (select.getAttribute('aria-label') || '').replace(/\s+/g, ' ').trim()
            || joinText(select.labels ? Array.from(select.labels) : []);

        return name || 'Options';
    }

    // Text of the labelling elements without any select inside them: a <label> wrapping its select
    // (Filament's table "Group by" / "Sort by") would otherwise contribute every option label.
    function joinText(elements) {
        return elements
            .map((el) => {
                if (el instanceof HTMLSelectElement) {
                    return '';
                }

                const clone = el.cloneNode(true);

                clone.querySelectorAll('select, datalist, script, style, template').forEach((node) => node.remove());

                return clone.textContent.replace(/\s+/g, ' ').trim();
            })
            .filter(Boolean)
            .join(' ');
    }

    /* ------------------------------------------------------------------ open / close */

    function open(select, initialText = '', viaTouch = false) {
        if (state) {
            close(false);
        }

        if (!isEligible(select)) {
            return false;
        }

        const id = 'og-ss-' + (++seq);
        const panel = document.createElement('div');

        panel.className = 'og-ss-panel';
        panel.tabIndex = -1;
        panel.innerHTML =
            '<div class="og-ss-search-wrap">' + ICON_SEARCH +
                '<input type="text" class="og-ss-search" placeholder="Type to search…" autocomplete="off"' +
                ' autocapitalize="off" autocorrect="off" spellcheck="false" role="combobox" aria-autocomplete="list"' +
                ' aria-expanded="true" aria-label="Search options">' +
            '</div>' +
            '<div class="og-ss-list" role="listbox"></div>' +
            '<div class="og-ss-footer" hidden></div>';

        const input = panel.querySelector('.og-ss-search');
        const list = panel.querySelector('.og-ss-list');

        list.id = id + '-list';
        list.setAttribute('aria-label', accessibleName(select));
        input.setAttribute('aria-controls', list.id);

        const s = state = {
            select,
            panel,
            input,
            list,
            footer: panel.querySelector('.og-ss-footer'),
            id,
            items: readItems(select),
            current: select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null,
            query: '',
            matches: [],
            rows: [],
            rowEls: [],
            lastGroup: undefined,
            active: -1,
            side: null,
            width: 0,
            listMax: 0,
            rect: null,
            pointer: null,
            raf: 0,
            timer: 0,
            observer: null,
            prevExpanded: select.getAttribute('aria-expanded'),
            prevControls: select.getAttribute('aria-controls'),
        };

        panel.style.visibility = 'hidden';
        hostFor(select).appendChild(panel);
        s.listMax = parseFloat(getComputedStyle(list).maxHeight) || 256;

        // Size the panel once from the unfiltered list so it does not change width while typing.
        s.matches = s.items;
        render();
        measureWidth();

        if (initialText) {
            input.value = initialText;
            applyQuery();
        }

        place(true);
        if (s.active >= 0) {
            reveal(s.active, true);
        }
        panel.style.visibility = '';

        select.setAttribute('aria-expanded', 'true');
        select.setAttribute('aria-controls', list.id);
        select.setAttribute('data-og-ss-open', '');

        input.addEventListener('input', applyQuery);
        panel.addEventListener('mousedown', onPanelMouseDown);
        panel.addEventListener('focusout', onPanelFocusOut);
        // Clicks in the panel stay in the panel: picking removes it mid-click, and click-away handlers
        // (Filament's dropdown panels, Alpine @click.outside) would then close whatever holds the select.
        panel.addEventListener('click', (event) => event.stopPropagation());
        list.addEventListener('mousemove', onListMouseMove);
        list.addEventListener('click', onListClick);
        list.addEventListener('scroll', onListScroll, { passive: true });

        document.addEventListener('pointerdown', onOutsidePointer, true);
        window.addEventListener('resize', onViewportChange);
        window.addEventListener('scroll', onViewportChange, true);

        // Livewire morphs: refresh when the options change, re-attach the panel if a morph removed it,
        // close when the select goes away or gets disabled.
        s.observer = new MutationObserver(onMutations);
        s.observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['disabled', 'hidden', 'label', 'value'],
        });

        // Catches what no event reports: x-show hiding the select, layout shifts moving it.
        s.timer = window.setInterval(watch, 200);

        // On touch a short list is quicker to tap than to search: keep the on-screen keyboard down.
        const focusTarget = viaTouch && !initialText && s.items.length <= TOUCH_KEYBOARD_MIN ? panel : input;
        focusTarget.focus({ preventScroll: true });
        if (initialText) {
            input.setSelectionRange(input.value.length, input.value.length);
        }

        return true;
    }

    function close(refocus = false) {
        const s = state;

        if (!s) {
            return;
        }

        state = null;

        document.removeEventListener('pointerdown', onOutsidePointer, true);
        window.removeEventListener('resize', onViewportChange);
        window.removeEventListener('scroll', onViewportChange, true);
        s.observer.disconnect();
        window.clearInterval(s.timer);
        if (s.raf) {
            cancelAnimationFrame(s.raf);
        }

        s.panel.remove();

        const select = s.select;

        restoreAttribute(select, 'aria-expanded', s.prevExpanded);
        restoreAttribute(select, 'aria-controls', s.prevControls);
        select.removeAttribute('data-og-ss-open');

        if (refocus && select.isConnected && isShown(select)) {
            select.focus({ preventScroll: true });
        }
    }

    function restoreAttribute(el, name, previous) {
        if (previous === null) {
            el.removeAttribute(name);
        } else {
            el.setAttribute(name, previous);
        }
    }

    function pick(item, refocus = true) {
        const select = state.select;

        // Close first so focus is back on the select when change handlers run, as with the native popup
        // (except after a tap, see isTouchLike).
        close(refocus);

        if (!select.isConnected) {
            return;
        }

        let opt = item.opt;

        if (opt.parentElement === null || !select.contains(opt)) {
            opt = Array.prototype.find.call(select.options, (candidate) => candidate.value === item.value) || null;
        }

        if (!opt || opt.selected) {
            return;
        }

        opt.selected = true;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /* ------------------------------------------------------------------ rendering */

    function applyQuery() {
        const s = state;

        s.query = s.input.value;
        s.matches = filterItems(s.items, s.query);
        render();
        s.list.scrollTop = 0;
        place(false);
        if (s.active >= 0) {
            reveal(s.active, false);
        }
    }

    function render() {
        const s = state;

        s.list.textContent = '';
        s.rows = [];
        s.rowEls = [];
        s.lastGroup = undefined;
        s.active = -1;
        s.input.removeAttribute('aria-activedescendant');

        if (!s.matches.length) {
            const empty = document.createElement('div');

            empty.className = 'og-ss-empty';
            empty.setAttribute('role', 'option');
            empty.setAttribute('aria-disabled', 'true');
            empty.textContent = s.items.length ? 'No results' : 'No options';
            s.list.appendChild(empty);
            updateFooter();

            return;
        }

        // Without a query, start on the current option, rendering far enough to include it.
        let start = -1;

        if (!s.query && s.current) {
            start = s.matches.findIndex((item) => item.opt === s.current);
        }

        appendRows(Math.min(s.matches.length, Math.max(CHUNK, start + 50)));
        updateFooter();

        if (start < 0 || s.rows[start].disabled) {
            start = findEnabled(0, 1);
        }

        if (start >= 0) {
            setActive(start, 'none');
        }
    }

    function appendRows(upTo) {
        const s = state;
        const fragment = document.createDocumentFragment();

        for (let k = s.rows.length; k < upTo; k++) {
            const item = s.matches[k];

            if (item.group !== s.lastGroup) {
                s.lastGroup = item.group;

                if (item.group && item.groupLabel) {
                    const header = document.createElement('div');

                    header.className = 'og-ss-group';
                    header.setAttribute('role', 'presentation');
                    header.textContent = item.groupLabel;
                    fragment.appendChild(header);
                }
            }

            const row = document.createElement('div');
            const label = document.createElement('span');
            const selected = item.opt === s.current;

            row.className = 'og-ss-option';
            row.id = s.id + '-' + k;
            row.dataset.k = k;
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', selected ? 'true' : 'false');

            if (item.value === '') {
                row.classList.add('is-placeholder');
            }

            if (item.disabled) {
                row.classList.add('is-disabled');
                row.setAttribute('aria-disabled', 'true');
            }

            label.className = 'og-ss-label';
            label.textContent = item.label || '—';

            if (item.tag || item.sub) {
                const body = document.createElement('span');
                const top = document.createElement('span');

                body.className = 'og-ss-body';
                top.className = 'og-ss-top';
                top.appendChild(label);

                if (item.tag) {
                    const tag = document.createElement('span');

                    tag.className = 'og-ss-tag' + (item.tagTone ? ' og-ss-tag-' + item.tagTone : '');
                    tag.textContent = item.tag;
                    top.appendChild(tag);
                }

                body.appendChild(top);

                if (item.sub) {
                    const sub = document.createElement('span');

                    sub.className = 'og-ss-sub';
                    sub.textContent = item.sub;
                    body.appendChild(sub);
                }

                row.appendChild(body);
            } else {
                row.appendChild(label);
            }

            if (selected) {
                row.classList.add('is-selected');
                row.insertAdjacentHTML('beforeend', ICON_CHECK);
            }

            s.rows.push(item);
            s.rowEls.push(row);
            fragment.appendChild(row);
        }

        s.list.appendChild(fragment);
    }

    // Rendering is capped; this builds the next batch once the user scrolls or arrows towards the end.
    function ensureRendered(k) {
        const s = state;

        if (k >= s.rows.length && s.rows.length < s.matches.length) {
            appendRows(Math.min(s.matches.length, Math.max(k + 1, s.rows.length + CHUNK)));
            updateFooter();
        }
    }

    function updateFooter() {
        const s = state;
        const total = s.matches.length;
        const shown = s.rows.length;

        if (shown < total) {
            s.footer.textContent = 'Showing ' + shown.toLocaleString() + ' of ' + total.toLocaleString() + ' — keep typing to narrow down';
            s.footer.hidden = false;
        } else {
            s.footer.hidden = true;
        }
    }

    function measureWidth() {
        const s = state;
        const r = s.select.getBoundingClientRect();
        const room = viewport().width - EDGE * 2;

        s.panel.style.minWidth = Math.min(Math.max(r.width, MIN_WIDTH), room) + 'px';
        s.panel.style.maxWidth = Math.min(Math.max(r.width, MAX_WIDTH), room) + 'px';
        s.panel.style.width = 'max-content';
        s.width = Math.ceil(s.panel.getBoundingClientRect().width) + 2;
        s.panel.style.minWidth = '';
        s.panel.style.maxWidth = '';
        s.panel.style.width = s.width + 'px';
    }

    // Fixed position under the select; flips above it when there is clearly more room there.
    function place(allowFlip) {
        const s = state;
        const r = s.select.getBoundingClientRect();
        const vp = viewport();

        s.rect = r;

        const width = Math.min(s.width, vp.width - EDGE * 2);
        const left = Math.max(EDGE, Math.min(r.left, vp.width - EDGE - width));

        s.panel.style.width = width + 'px';
        s.list.style.maxHeight = s.listMax + 'px';

        const chrome = s.panel.offsetHeight - s.list.offsetHeight;
        const wanted = chrome + Math.min(s.list.scrollHeight, s.listMax);
        const below = vp.height - r.bottom - GAP - EDGE;
        const above = r.top - GAP - EDGE;

        if (allowFlip || !s.side) {
            s.side = wanted > below && above > below ? 'top' : 'bottom';
        }

        const room = (s.side === 'top' ? above : below) - chrome;

        s.list.style.maxHeight = Math.max(Math.min(s.listMax, room), Math.min(s.listMax, MIN_LIST_HEIGHT)) + 'px';

        const top = s.side === 'top' ? r.top - GAP - s.panel.offsetHeight : r.bottom + GAP;

        s.panel.style.left = left + 'px';
        s.panel.style.top = top + 'px';

        // A transformed ancestor becomes the containing block of position:fixed: measure and cancel the offset.
        const placed = s.panel.getBoundingClientRect();
        const dx = left - placed.left;
        const dy = top - placed.top;

        if (Math.abs(dx) > 0.5 || Math.abs(dy) > 0.5) {
            s.panel.style.left = left + dx + 'px';
            s.panel.style.top = top + dy + 'px';
        }
    }

    /* ------------------------------------------------------------------ active row */

    function setActive(k, scroll) {
        const s = state;
        const previous = s.rowEls[s.active];

        if (previous) {
            previous.classList.remove('is-active');
        }

        s.active = k;

        const row = s.rowEls[k];

        if (!row) {
            s.input.removeAttribute('aria-activedescendant');

            return;
        }

        row.classList.add('is-active');
        s.input.setAttribute('aria-activedescendant', row.id);

        if (scroll !== 'none') {
            reveal(k, scroll === 'center');
        }
    }

    function reveal(k, center) {
        const s = state;
        const row = s.rowEls[k];

        if (!row) {
            return;
        }

        const list = s.list;
        const top = row.offsetTop;
        const bottom = top + row.offsetHeight;

        if (center) {
            list.scrollTop = top - (list.clientHeight - row.offsetHeight) / 2;
        } else if (top < list.scrollTop) {
            list.scrollTop = top;
        } else if (bottom > list.scrollTop + list.clientHeight) {
            list.scrollTop = bottom - list.clientHeight;
        }
    }

    // First enabled row at or after `from` in direction `dir` (+1 / -1), building rows as needed.
    function findEnabled(from, dir) {
        const s = state;

        for (let k = from; k >= 0 && k < s.matches.length; k += dir) {
            ensureRendered(k);

            if (!s.rows[k].disabled) {
                return k;
            }
        }

        return -1;
    }

    function moveTo(target, dir) {
        const s = state;
        const last = s.matches.length - 1;

        if (last < 0) {
            return;
        }

        target = Math.max(0, Math.min(last, target));

        const k = findEnabled(target, dir);
        const fallback = k >= 0 ? k : findEnabled(target, -dir);

        if (fallback >= 0) {
            setActive(fallback, 'nearest');
        }
    }

    function pageSize() {
        const s = state;
        const row = s.rowEls[0];

        return Math.max(1, Math.floor(s.list.clientHeight / (row ? row.offsetHeight || 36 : 36)) - 1);
    }

    /* ------------------------------------------------------------------ panel events */

    function onPanelMouseDown(event) {
        // Keep focus in the search box while clicking rows; the input itself and the list's
        // scrollbar keep their default behaviour.
        if (event.target !== state.input && event.target !== state.list) {
            event.preventDefault();
        }
    }

    function onPanelFocusOut(event) {
        const s = state;

        // Focus moved somewhere else on the page (not the window losing focus, not a re-render).
        if (s && event.relatedTarget && !s.panel.contains(event.relatedTarget)) {
            close(false);
        }
    }

    function rowFromEvent(event) {
        const row = event.target instanceof Element ? event.target.closest('.og-ss-option') : null;

        return row && state.list.contains(row) ? Number(row.dataset.k) : -1;
    }

    function onListMouseMove(event) {
        const s = state;

        // Ignore the synthetic mousemove some browsers fire when the list scrolls under a still pointer.
        if (s.pointer && s.pointer.x === event.clientX && s.pointer.y === event.clientY) {
            return;
        }

        s.pointer = { x: event.clientX, y: event.clientY };

        const k = rowFromEvent(event);

        if (k < 0) {
            return;
        }

        // Long labels are cut with an ellipsis; show the full text as a tooltip on hover.
        const row = s.rowEls[k];
        const label = row.firstChild;

        if (!row.title && label.scrollWidth > label.clientWidth) {
            row.title = s.rows[k].label;
        }

        if (k !== s.active && !s.rows[k].disabled) {
            setActive(k, 'none');
        }
    }

    function onListClick(event) {
        const k = rowFromEvent(event);

        if (k >= 0 && !state.rows[k].disabled) {
            // Safari's click is not a PointerEvent, so fall back to the pointerdown that started it.
            pick(state.rows[k], !isTouchLike(event.pointerType || lastPointerType));
        }
    }

    function onListScroll() {
        const s = state;

        if (s && s.rows.length < s.matches.length
            && s.list.scrollTop + s.list.clientHeight * 3 >= s.list.scrollHeight) {
            ensureRendered(s.rows.length);
        }
    }

    function onPanelKeyDown(event) {
        const s = state;

        switch (event.key) {
            case 'ArrowDown':
                if (event.altKey) {
                    break;
                }
                consume(event);
                moveTo(s.active < 0 ? 0 : s.active + 1, 1);
                return;
            case 'ArrowUp':
                consume(event);
                if (event.altKey) {
                    close(true);
                } else {
                    moveTo(s.active < 0 ? 0 : s.active - 1, -1);
                }
                return;
            case 'Home':
                consume(event);
                moveTo(0, 1);
                return;
            case 'End':
                consume(event);
                moveTo(s.matches.length - 1, -1);
                return;
            case 'PageDown':
                consume(event);
                moveTo(Math.max(s.active, 0) + pageSize(), 1);
                return;
            case 'PageUp':
                consume(event);
                moveTo(Math.max(s.active, 0) - pageSize(), -1);
                return;
            case 'Enter':
                consume(event);
                if (s.active >= 0 && !s.rows[s.active].disabled) {
                    pick(s.rows[s.active]);
                }
                return;
            case 'Tab':
            case 'F4':
                consume(event);
                close(true);
                return;
        }

        // Typing while the panel itself (not the search box) has focus goes to the search box.
        if (event.target !== s.input && isPrintable(event)) {
            s.input.focus({ preventScroll: true });
        }
    }

    function consume(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }

    function isPrintable(event) {
        return !event.ctrlKey && !event.metaKey && !event.altKey && [...event.key].length === 1;
    }

    /* ------------------------------------------------------------------ while open */

    function onOutsidePointer(event) {
        const s = state;

        if (!s || s.panel.contains(event.target) || s.select.contains(event.target)) {
            return; // presses on the select itself are toggled by its mousedown / touchend handler
        }

        close(false);
    }

    function onViewportChange(event) {
        const s = state;

        if (!s || (event.type === 'scroll' && event.target instanceof Node && s.panel.contains(event.target))) {
            return;
        }

        if (!s.raf) {
            s.raf = requestAnimationFrame(() => {
                s.raf = 0;

                if (state === s) {
                    if (isEligible(s.select)) {
                        place(true);
                    } else {
                        close(false);
                    }
                }
            });
        }
    }

    function onMutations(records) {
        const s = state;

        if (!s) {
            return;
        }

        let optionsChanged = false;
        let structureChanged = false;

        for (const record of records) {
            if (s.panel.contains(record.target)) {
                continue; // our own rendering
            }

            if (s.select.contains(record.target)) {
                optionsChanged = true;
            }

            structureChanged = true;
        }

        if (!structureChanged) {
            return;
        }

        if (!isEligible(s.select)) {
            close(s.panel.contains(document.activeElement) && s.select.isConnected);

            return;
        }

        // A morph of the surrounding Livewire component dropped the panel (it is not in the server HTML).
        if (!s.panel.isConnected) {
            hostFor(s.select).appendChild(s.panel);

            if (!document.activeElement || document.activeElement === document.body) {
                s.input.focus({ preventScroll: true });
            }
        }

        if (optionsChanged) {
            refresh();
        }
    }

    function refresh() {
        const s = state;
        const activeItem = s.active >= 0 ? s.rows[s.active] : null;
        const scrollTop = s.list.scrollTop;

        s.items = readItems(s.select);
        s.current = s.select.selectedIndex >= 0 ? s.select.options[s.select.selectedIndex] : null;
        s.matches = filterItems(s.items, s.query);
        render();

        if (activeItem) {
            const k = s.matches.findIndex((item) => item.opt === activeItem.opt || item.value === activeItem.value);

            if (k >= 0 && !s.matches[k].disabled) {
                ensureRendered(k);
                setActive(k, 'none');
            }
        }

        s.list.scrollTop = scrollTop;
        place(false);
    }

    function watch() {
        const s = state;

        if (!s) {
            return;
        }

        if (!isEligible(s.select)) {
            close(false);

            return;
        }

        const r = s.select.getBoundingClientRect();
        const last = s.rect;

        if (!last || r.top !== last.top || r.left !== last.left || r.width !== last.width || r.height !== last.height) {
            place(false);
        }
    }

    /* ------------------------------------------------------------------ opening (document-level) */

    function onMouseDown(event) {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        const select = closestSelect(event.target);

        if (!select) {
            return;
        }

        const isOpenOne = state !== null && state.select === select;

        // Mouse events emulated after a tap we already handled.
        if (Date.now() - lastTouchAt < 800) {
            if (isOpenOne || isEligible(select)) {
                event.preventDefault();
            }

            return;
        }

        if (isOpenOne) {
            event.preventDefault();
            close(!isTouchLike(lastPointerType)); // a tap the touchend handler did not take

            return;
        }

        if (isEligible(select)) {
            event.preventDefault();
            open(select, '', isTouchLike(lastPointerType));
        }
    }

    function onTouchStart(event) {
        const select = event.touches.length === 1 ? closestSelect(event.target) : null;

        touch = select ? { select, x: event.touches[0].clientX, y: event.touches[0].clientY, moved: false } : null;
    }

    function onTouchMove(event) {
        if (touch && !touch.moved) {
            const point = event.touches[0];

            touch.moved = Math.abs(point.clientX - touch.x) > TOUCH_SLOP || Math.abs(point.clientY - touch.y) > TOUCH_SLOP;
        }
    }

    function onTouchEnd(event) {
        const tap = touch;

        touch = null;

        if (!tap || tap.moved || !event.cancelable || closestSelect(event.target) !== tap.select) {
            return;
        }

        const select = tap.select;

        if (state !== null && state.select === select) {
            event.preventDefault();
            lastTouchAt = Date.now();
            close(false); // no refocus from a tap, see isTouchLike

            return;
        }

        if (isEligible(select)) {
            event.preventDefault();
            lastTouchAt = Date.now();
            open(select, '', true);
        }
    }

    function onKeyDown(event) {
        if (event.isComposing || event.keyCode === 229) {
            return;
        }

        if (state) {
            if (event.key === 'Escape') {
                // Handled before Filament's modal / dropdown Escape listeners so only the panel closes.
                consume(event);
                close(true);
            } else if (event.target instanceof Node && state.panel.contains(event.target)) {
                onPanelKeyDown(event);
            }

            return;
        }

        const select = event.target;

        if (!(select instanceof HTMLSelectElement)) {
            return;
        }

        const key = event.key;
        const plain = !event.ctrlKey && !event.metaKey && !event.altKey;
        let text = '';

        if (key === 'Enter' || key === ' ' || key === 'Spacebar') {
            if (!plain) {
                return;
            }
        } else if (key === 'F4' || (event.altKey && (key === 'ArrowDown' || key === 'ArrowUp'))) {
            // open
        } else if (isPrintable(event)) {
            text = key; // type-to-search: the first character lands in the search box
        } else {
            return; // arrows and the rest keep their native behaviour on a closed select
        }

        if (!isEligible(select)) {
            return;
        }

        consume(event);
        open(select, text);
    }

    document.addEventListener('pointerdown', (event) => { lastPointerType = event.pointerType; }, true);
    document.addEventListener('mousedown', onMouseDown, true);
    document.addEventListener('touchstart', onTouchStart, { capture: true, passive: true });
    document.addEventListener('touchmove', onTouchMove, { capture: true, passive: true });
    document.addEventListener('touchend', onTouchEnd, { capture: true, passive: false });
    document.addEventListener('touchcancel', () => { touch = null; }, true);
    // Window + capture: runs before Filament's own Escape / Tab (focus trap) handlers.
    window.addEventListener('keydown', onKeyDown, true);

    window.OgSearchableSelect = {
        open: (select) => open(select),
        close: () => close(true),
        isEligible,
    };
})();
