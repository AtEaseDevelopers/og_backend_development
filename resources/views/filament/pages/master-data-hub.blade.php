<x-filament-panels::page>
    {{-- one card per master data page (user, 21 Oct 2026) --}}
    <style>
        .mdh-group + .mdh-group { margin-top: 1.5rem; }
        .mdh-group-title { font-size: .78rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: rgb(100 116 139); margin-bottom: .6rem; }
        .mdh-grid { display: grid; gap: .9rem; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); }
        .mdh-card { display: flex; gap: .85rem; align-items: flex-start; padding: 1rem 1.1rem; border: 1px solid rgb(229 231 235); border-radius: .9rem; background: #fff; text-decoration: none; color: inherit; transition: border-color .15s, box-shadow .15s, transform .15s; }
        .mdh-card:hover, .mdh-card:focus-visible { border-color: rgb(var(--primary-500)); box-shadow: 0 6px 18px -10px rgb(15 23 42 / .35); transform: translateY(-1px); outline: none; }
        .mdh-icon { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 2.6rem; height: 2.6rem; border-radius: .7rem; background: rgb(var(--primary-50)); color: rgb(var(--primary-600)); }
        .mdh-icon svg { width: 1.35rem; height: 1.35rem; }
        .mdh-title { display: block; font-weight: 600; font-size: .98rem; color: rgb(15 23 42); }
        .mdh-desc { display: block; margin-top: .2rem; font-size: .82rem; color: rgb(100 116 139); line-height: 1.35; }
        .dark .mdh-group-title { color: rgb(148 163 184); }
        .dark .mdh-card { background: rgb(17 24 39); border-color: rgb(55 65 81); }
        .dark .mdh-icon { background: rgb(var(--primary-500) / .15); color: rgb(var(--primary-400)); }
        .dark .mdh-title { color: rgb(241 245 249); }
        .dark .mdh-desc { color: rgb(148 163 184); }
    </style>

    @foreach ($this->groups() as $group => $items)
        <section class="mdh-group">
            <div class="mdh-group-title">{{ $group }}</div>
            <div class="mdh-grid">
                @foreach ($items as $item)
                    <a href="{{ \App\Filament\Pages\MasterDataHub::url($item['class']) }}" class="mdh-card">
                        <span class="mdh-icon">@svg($item['icon'])</span>
                        <span>
                            <span class="mdh-title">{{ $item['label'] }}</span>
                            <span class="mdh-desc">{{ $item['description'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</x-filament-panels::page>
