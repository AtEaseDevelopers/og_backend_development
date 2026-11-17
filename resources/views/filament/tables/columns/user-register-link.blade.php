{{-- Master Data -> Users, "Register link" (user, 17 Nov 2026): a button that copies the salesperson's customer
     registration link; nothing for other staff or when the link is disabled for the user. --}}
@php
    $record = $getRecord();
    $link = $record->hasRole('salesperson') && $record->registerLinkAllowed() ? $record->registerLink() : null;
@endphp
<div class="px-3 py-2">
    @if ($link)
        <button
            type="button"
            x-data="{
                copied: false,
                url: @js($link),
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
            x-on:click.stop.prevent="copy()"
            title="{{ $link }}"
            style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .55rem;border:1px solid rgb(209 213 219);border-radius:.4rem;font-size:.75rem;font-weight:600;background:#fff;color:rgb(55 65 81);white-space:nowrap;cursor:pointer"
        >
            <x-filament::icon icon="heroicon-m-link" class="h-3.5 w-3.5" />
            <span x-text="copied ? 'Copied' : 'Copy link'">Copy link</span>
        </button>
    @else
        <span class="text-sm text-gray-400">—</span>
    @endif
</div>
