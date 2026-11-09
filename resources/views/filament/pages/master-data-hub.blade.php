<x-filament-panels::page>
    {{-- one card per master data page, all on one screen (user, 21 and 25 Oct, 3 Nov 2026): groups stacked, compact cards --}}
    <style>
        /* groups stacked, each a full-width grid of compact cards (user, 3 Nov 2026: the column layout left too much
           white space); the cards are small enough for every group to show without scrolling on a laptop */
        /* one wrapper for every group (the page spaces its children out; inside, the groups sit .5rem apart) */
        /* the card side follows the screen height (user, 4 Nov 2026: a 27" screen fits every group, a laptop had to
           scroll): the height left under the topbar, title and group headings, shared by the groups shown — capped at
           7.4rem on big screens and never under 5rem; the icon and label scale with it */
        /* no room kept for the floating Top button here (the page is sized to fit without scrolling) */
        .fi-main .fi-page:has(.mdh-stack) { padding-bottom: 0; }
        .mdh-stack { display: grid; gap: .5rem; }
        /* groups side by side, three a row on a wide screen and two on a laptop (user, 10 Nov 2026: no more one group
           per row) */
        @media (min-width: 1024px) { .mdh-stack { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1536px) { .mdh-stack { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .mdh-group { container-type: inline-size; border: 1px solid rgb(229 231 235); border-radius: .75rem; background: #fff; padding: .55rem .7rem .7rem; }
        .mdh-group-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin: 0 0 .4rem .15rem; }
        .mdh-group-title { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: rgb(100 116 139); }
        /* hidden cards of the group: an eye-slash badge with the count */
        .mdh-hidden { display: inline-flex; align-items: center; gap: .25rem; padding: .1rem .45rem; border-radius: 999px; font-size: .72rem; font-weight: 600; background: rgb(241 245 249); color: rgb(71 85 105); border: 1px solid rgb(226 232 240); }
        .mdh-hidden svg { width: .85rem; height: .85rem; }
        .mdh-none { font-size: .8rem; color: rgb(148 163 184); padding: .4rem .2rem; }
        .dark .mdh-hidden { background: rgb(55 65 81 / .6); color: rgb(203 213 225); border-color: rgb(75 85 99); }
        /* square cards (user, 3 Nov 2026): the icon above the name, about 7.5rem a side */
        /* four square cards a row in every group, filling the group width, the rest wrapping (user, 10 Nov 2026: a fixed
           card height looked odd); the icon and label scale with the card side, a quarter of the group width */
        .mdh-list { display: grid; gap: .5rem; grid-template-columns: repeat(4, minmax(0, 1fr)); --mdh-card: calc((100cqw - 1.5rem) / 4); }
        .mdh-card { display: flex; flex-direction: column; gap: .45rem; align-items: center; justify-content: center; text-align: center; aspect-ratio: 1 / 1; min-width: 0; padding: .5rem .45rem; border: 1px solid rgb(229 231 235); border-radius: .7rem; background: #fff; text-decoration: none; color: inherit; transition: border-color .15s, box-shadow .15s, transform .15s; }
        .mdh-card:hover, .mdh-card:focus-visible { border-color: rgb(var(--primary-500)); box-shadow: 0 6px 18px -10px rgb(15 23 42 / .35); transform: translateY(-1px); outline: none; }
        .mdh-icon { flex: none; display: inline-flex; align-items: center; justify-content: center; width: min(2.6rem, calc(var(--mdh-card) * .32)); height: min(2.6rem, calc(var(--mdh-card) * .32)); border-radius: .6rem; background: rgb(var(--primary-50)); color: rgb(var(--primary-600)); }
        .mdh-icon svg { width: 55%; height: 55%; }
        .mdh-text { min-width: 0; }
        .mdh-title { display: block; font-weight: 600; font-size: clamp(.7rem, calc(var(--mdh-card) * .113), .9rem); color: rgb(15 23 42); line-height: 1.2; }
        .mdh-desc { display: none; }
        .mdh-empty { padding: 2.5rem 1rem; text-align: center; color: rgb(100 116 139); border: 1px dashed rgb(203 213 225); border-radius: .9rem; }
        .dark .mdh-group { background: rgb(17 24 39); border-color: rgb(55 65 81); }
        .dark .mdh-group-title, .dark .mdh-desc, .dark .mdh-empty { color: rgb(148 163 184); }
        .dark .mdh-card { background: rgb(31 41 55 / .6); border-color: rgb(55 65 81); }
        .dark .mdh-icon { background: rgb(var(--primary-500) / .15); color: rgb(var(--primary-400)); }
        .dark .mdh-title { color: rgb(241 245 249); }
        .dark .mdh-empty { border-color: rgb(55 65 81); }
    </style>

    @php($groups = $this->groups())
    @php($hiddenByGroup = $this->hiddenByGroup())

    @if ($hiddenByGroup === [])
        <div class="mdh-empty">No master data pages are open to your account.</div>
    @else
        <div class="mdh-stack" style="--mdh-groups: {{ max(1, count($hiddenByGroup)) }}">
            @foreach ($hiddenByGroup as $group => $hidden)
                <section class="mdh-group">
                    <div class="mdh-group-head">
                        <span class="mdh-group-title">{{ $group }}</span>
                        @if ($hidden !== [])
                            <span class="mdh-hidden" title="Hidden: {{ implode(', ', $hidden) }}">@svg('heroicon-m-eye-slash') {{ count($hidden) }}</span>
                        @endif
                    </div>
                    <div class="mdh-list">
                        @forelse ($groups[$group] ?? [] as $item)
                            <a href="{{ \App\Filament\Pages\MasterDataHub::url($item['class']) }}" class="mdh-card">
                                <span class="mdh-icon">@svg($item['icon'])</span>
                                <span class="mdh-text">
                                    <span class="mdh-title">{{ $item['label'] }}</span>
                                    @if (filled($item['description'] ?? null))<span class="mdh-desc" title="{{ $item['description'] }}">{{ $item['description'] }}</span>@endif
                                </span>
                            </a>
                        @empty
                            <div class="mdh-none">All hidden</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
