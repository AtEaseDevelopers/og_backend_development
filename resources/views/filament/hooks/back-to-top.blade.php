{{-- Global "Back to top" button: there whenever the page is scrolled down, gone at the top.
     It was sometimes missing (user, 16 Nov 2026): the scroll listener was throttled, and a throttle drops the last
     event of a scroll — so after a quick scroll the button was still judged from a position near the top; and a page
     that only scrolls a little never passed the old 400px mark. Now every scroll is looked at (once a frame), the
     page is looked at again when it changes height or Livewire redraws it, and 120px of scroll is enough. --}}
<div
    x-data="{
        show: false,
        queued: false,
        update() {
            this.show = (window.scrollY || document.documentElement.scrollTop || 0) > 120;
        },
        queue() {
            if (this.queued) { return }
            this.queued = true;
            requestAnimationFrame(() => { this.queued = false; this.update(); });
        },
        init() {
            this.update();
            window.addEventListener('scroll', () => this.queue(), { passive: true });
            window.addEventListener('scrollend', () => this.update(), { passive: true });
            window.addEventListener('resize', () => this.queue(), { passive: true });
            // the page grows or shrinks without a scroll event (a section opened, rows added, Livewire redrew it)
            if (window.ResizeObserver) { new ResizeObserver(() => this.queue()).observe(document.body); }
            document.addEventListener('livewire:navigated', () => this.queue());
        },
    }"
    class="og-back-to-top-wrap"
>
    <button
        type="button"
        x-cloak
        x-show="show"
        x-transition.opacity.duration.200ms
        x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="og-back-to-top"
        aria-label="Back to top"
        title="Back to top"
    >
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z" clip-rule="evenodd" />
        </svg>
        <span>Top</span>
    </button>
</div>

<style>
    [x-cloak] { display: none !important; }

    .og-back-to-top {
        position: fixed;
        right: 1.25rem;
        bottom: 1.25rem;
        z-index: 40;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.5rem 0.8rem 0.5rem 0.65rem;
        border-radius: 9999px;
        border: 1px solid rgb(226 232 240);
        background: rgb(15 23 42);
        color: #fff;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1;
        box-shadow: 0 6px 18px rgb(15 23 42 / 0.18);
        cursor: pointer;
        transition: transform 120ms ease, background-color 120ms ease;
    }

    .og-back-to-top:hover {
        background: rgb(30 41 59);
        transform: translateY(-2px);
    }

    .og-back-to-top:focus-visible {
        outline: 2px solid rgb(59 130 246);
        outline-offset: 2px;
    }

    .og-back-to-top svg {
        width: 0.95rem;
        height: 0.95rem;
    }

    /* Keep page content (form footer actions, pagination) clear of the floating button. */
    .fi-main .fi-page {
        padding-bottom: 4.5rem;
    }

    @media (max-width: 640px) {
        .og-back-to-top { right: 1rem; bottom: 1rem; }
        .og-back-to-top span { display: none; }
        .og-back-to-top { padding: 0.6rem; }
    }

    @media print {
        .og-back-to-top { display: none !important; }
    }
</style>
