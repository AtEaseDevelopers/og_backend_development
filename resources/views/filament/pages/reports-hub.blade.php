<x-filament-panels::page>
    {{-- one card per report: it opens the report on its own page (user, 21 Oct 2026) --}}
    <style>
        .rh-group + .rh-group { margin-top: 1.5rem; }
        .rh-group-title { font-size: .78rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: rgb(100 116 139); margin-bottom: .6rem; }
        .rh-grid { display: grid; gap: .9rem; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); }
        .rh-card { display: flex; gap: .85rem; align-items: flex-start; padding: 1rem 1.1rem; border: 1px solid rgb(229 231 235); border-radius: .9rem; background: #fff; text-decoration: none; color: inherit; transition: border-color .15s, box-shadow .15s, transform .15s; }
        .rh-card:hover, .rh-card:focus-visible { border-color: rgb(var(--primary-500)); box-shadow: 0 6px 18px -10px rgb(15 23 42 / .35); transform: translateY(-1px); outline: none; }
        .rh-icon { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 2.6rem; height: 2.6rem; border-radius: .7rem; background: rgb(var(--primary-50)); color: rgb(var(--primary-600)); }
        .rh-icon svg { width: 1.35rem; height: 1.35rem; }
        .rh-title { font-weight: 600; font-size: .98rem; color: rgb(15 23 42); }
        .rh-desc { margin-top: .2rem; font-size: .82rem; color: rgb(100 116 139); line-height: 1.35; }
        .rh-open { margin-top: .45rem; font-size: .8rem; font-weight: 600; color: rgb(var(--primary-600)); }
        .dark .rh-group-title { color: rgb(148 163 184); }
        .dark .rh-card { background: rgb(17 24 39); border-color: rgb(55 65 81); }
        .dark .rh-icon { background: rgb(var(--primary-500) / .15); color: rgb(var(--primary-400)); }
        .dark .rh-title { color: rgb(241 245 249); }
        .dark .rh-desc { color: rgb(148 163 184); }
        .dark .rh-open { color: rgb(var(--primary-400)); }
    </style>

    @foreach (collect(\App\Filament\Pages\ReportsHub::REPORTS)->groupBy('group', preserveKeys: true) as $group => $reports)
        <section class="rh-group">
            <div class="rh-group-title">{{ $group }}</div>
            <div class="rh-grid">
                @foreach ($reports as $key => $report)
                    <a href="{{ \App\Filament\Pages\ReportsHub::cardUrl($key) }}" class="rh-card">
                        <span class="rh-icon">@svg($report['icon'])</span>
                        <span>
                            <span class="rh-title">{{ $report['label'] }}</span>
                            <span class="rh-desc" style="display:block">{{ $report['description'] }}</span>
                            <span class="rh-open" style="display:block">Open report →</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</x-filament-panels::page>
