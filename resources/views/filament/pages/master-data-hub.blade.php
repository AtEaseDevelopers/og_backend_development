<x-filament-panels::page>
    {{-- one card per master data page, label only, all on one screen (user, 21 and 25 Oct 2026); each group is a column --}}
    <style>
        /* the groups sit side by side (4 across from a laptop width, 2 on a tablet), so every card shows without scrolling */
        .mdh-wrap { container-type: inline-size; }
        .mdh-board { display: grid; gap: .85rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
        @container (min-width: 32rem) { .mdh-board { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @container (min-width: 50rem) { .mdh-board { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .mdh-group { border: 1px solid rgb(229 231 235); border-radius: .9rem; background: #fff; padding: .85rem; }
        .mdh-group-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin: 0 0 .55rem .2rem; }
        .mdh-group-title { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: rgb(100 116 139); }
        /* hidden cards of the group: an eye-slash badge with the count */
        .mdh-hidden { display: inline-flex; align-items: center; gap: .25rem; padding: .1rem .45rem; border-radius: 999px; font-size: .72rem; font-weight: 600; background: rgb(241 245 249); color: rgb(71 85 105); border: 1px solid rgb(226 232 240); }
        .mdh-hidden svg { width: .85rem; height: .85rem; }
        .mdh-none { font-size: .8rem; color: rgb(148 163 184); padding: .4rem .2rem; }
        .dark .mdh-hidden { background: rgb(55 65 81 / .6); color: rgb(203 213 225); border-color: rgb(75 85 99); }
        .mdh-list { display: grid; gap: .45rem; }
        .mdh-card { display: flex; gap: .7rem; align-items: center; padding: .55rem .7rem; border: 1px solid rgb(229 231 235); border-radius: .7rem; background: #fff; text-decoration: none; color: inherit; transition: border-color .15s, box-shadow .15s, transform .15s; }
        .mdh-card:hover, .mdh-card:focus-visible { border-color: rgb(var(--primary-500)); box-shadow: 0 6px 18px -10px rgb(15 23 42 / .35); transform: translateY(-1px); outline: none; }
        .mdh-icon { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 2.1rem; height: 2.1rem; border-radius: .6rem; background: rgb(var(--primary-50)); color: rgb(var(--primary-600)); }
        .mdh-icon svg { width: 1.15rem; height: 1.15rem; }
        .mdh-title { font-weight: 600; font-size: .9rem; color: rgb(15 23 42); line-height: 1.25; }
        .mdh-empty { padding: 2.5rem 1rem; text-align: center; color: rgb(100 116 139); border: 1px dashed rgb(203 213 225); border-radius: .9rem; }
        .mdh-note { font-size: .8rem; color: rgb(100 116 139); }
        .dark .mdh-group { background: rgb(17 24 39); border-color: rgb(55 65 81); }
        .dark .mdh-group-title, .dark .mdh-note, .dark .mdh-empty { color: rgb(148 163 184); }
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
        <div class="mdh-wrap"><div class="mdh-board">
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
                                <span class="mdh-title">{{ $item['label'] }}</span>
                            </a>
                        @empty
                            <div class="mdh-none">All hidden</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div></div>
    @endif
</x-filament-panels::page>
