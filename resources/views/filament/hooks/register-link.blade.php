{{-- Admin header, beside the user icon (user, 11 Nov 2026): copies the customer registration link. A salesperson's
     link selects them on the register page, so the customer's orders are theirs; other staff copy the public link. --}}
@php
    $ogUser = auth()->user();
    $ogIsSalesperson = (bool) $ogUser?->hasRole('salesperson');

    if ($ogIsSalesperson) {
        $ogUser->ensureOrderingToken();
    }

    $ogRegisterLink = $ogIsSalesperson ? $ogUser->orderingLink() : route('portal.register');
@endphp
@if ($ogUser)
    <div
        x-data="{
            copied: false,
            url: @js($ogRegisterLink),
            async copy() {
                try {
                    await navigator.clipboard.writeText(this.url);
                } catch (e) {
                    // not a secure page (http): the old way
                    const box = document.createElement('textarea');
                    box.value = this.url;
                    box.style.position = 'fixed';
                    box.style.opacity = '0';
                    document.body.appendChild(box);
                    box.select();
                    document.execCommand('copy');
                    box.remove();
                }
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            },
        }"
        class="og-register-link"
        style="display:flex;align-items:center;margin-inline-end:.25rem"
    >
        <button
            type="button"
            x-on:click="copy()"
            title="{{ $ogIsSalesperson ? 'Copy your customer registration link. A customer who registers with it has you as their salesperson.' : 'Copy the public customer registration link.' }}"
            style="display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .75rem;border:1px solid rgb(209 213 219);border-radius:.5rem;font-size:.8125rem;font-weight:600;line-height:1.25rem;background:#fff;color:rgb(55 65 81);white-space:nowrap;cursor:pointer"
        >
            <x-filament::icon icon="heroicon-m-link" class="h-4 w-4" />
            <span x-text="copied ? 'Link copied' : 'Register Link'">Register Link</span>
        </button>
    </div>
@endif
