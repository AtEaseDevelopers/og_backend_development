{{-- "Search tagged customers" under the Customer tagging field (user, 13 Nov 2026): the tags stay in the field as before;
     typing here marks the tags that match and fades the others, with a count, so a long list can be checked without
     reading every tag. Done in the browser only — nothing is sent or saved by it. --}}
<div wire:ignore class="og-tagsearch"
    x-data="{
        q: '', total: 0, hits: 0, observer: null,
        box() { return this.$root.closest('form')?.querySelector('.og-tag-select') ?? document.querySelector('.og-tag-select'); },
        chips() { return [...(this.box()?.querySelectorAll('.choices__list--multiple .choices__item') ?? [])]; },
        mark() {
            const chips = this.chips(), q = this.q.trim().toLowerCase();
            this.total = chips.length;
            this.hits = 0;
            chips.forEach(chip => {
                const hit = q !== '' && chip.textContent.toLowerCase().includes(q);
                chip.classList.toggle('og-tag-hit', hit);
                chip.classList.toggle('og-tag-dim', q !== '' && ! hit);
                if (hit) { this.hits++; }
            });
        },
        init() {
            this.$nextTick(() => {
                this.mark();
                const box = this.box();
                if (box) {
                    // tags added or removed in the field: count and mark again
                    this.observer = new MutationObserver(() => { this.observer.disconnect(); this.mark(); this.observer.observe(box, { childList: true, subtree: true }); });
                    this.observer.observe(box, { childList: true, subtree: true });
                }
            });
        },
    }">
    <span class="og-tagsearch-count" x-text="total + (total === 1 ? ' customer tagged' : ' customers tagged')"></span>
    <input type="search" x-model="q" x-on:input="mark()" x-on:keydown.enter.prevent placeholder="Search tagged customers" class="og-tagsearch-input" autocomplete="off">
    <span class="og-tagsearch-result" x-show="q.trim() !== ''" x-cloak
        x-text="hits === 0 ? 'Not tagged' : hits + (hits === 1 ? ' tag matches' : ' tags match')"
        x-bind:class="hits === 0 ? 'og-tagsearch-none' : 'og-tagsearch-some'"></span>
</div>

@once
    <style>
        .og-tagsearch { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: -.25rem; }
        .og-tagsearch-count { font-size: .8rem; font-weight: 600; color: rgb(71 85 105); }
        .og-tagsearch-input { flex: 1 1 14rem; max-width: 22rem; padding: .3rem .6rem; font-size: .8rem; border: 1px solid rgb(203 213 225); border-radius: .5rem; background: #fff; color: inherit; }
        .og-tagsearch-result { font-size: .8rem; font-weight: 600; }
        .og-tagsearch-some { color: rgb(21 128 61); }
        .og-tagsearch-none { color: rgb(185 28 28); }
        .og-tag-select .choices__item.og-tag-hit { outline: 2px solid rgb(234 179 8); background: rgb(254 249 195) !important; color: rgb(66 32 6) !important; }
        .og-tag-select .choices__item.og-tag-dim { opacity: .3; }
        .dark .og-tagsearch-count { color: rgb(148 163 184); }
        .dark .og-tagsearch-input { background: rgb(31 41 55 / .6); border-color: rgb(75 85 99); }
    </style>
@endonce
