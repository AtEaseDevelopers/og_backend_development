{{-- Commission batch: the commission per lorry (one PO per lorry) and what each of its drivers earned, filtered by
     lorry group or lorry company (the driver list's columns, on the lorry's default driver; user, 21 Oct 2026) --}}
@php
    $batch = $getRecord();
    $lines = \App\Domains\Commission\Models\CommissionLineItem::query()
        ->whereIn('commission_slip_id', $batch->slips()->select('id'))
        ->where('is_hidden', false)
        ->with(['driver:id,name,lorry_company_code,lorry_company,lorry_group', 'lorry:id,registration_no,branch_id,default_driver_id', 'lorry.branch:id,name', 'lorry.defaultDriver:id,lorry_company_code,lorry_company,lorry_group', 'deliveryOrder:id,job_sheet_id', 'consignmentNote:id,total_amount', 'slip:id,status'])
        ->get();
    $byLorry = $lines->groupBy(fn ($line) => (int) $line->lorry_id);
    // the lorry's company and group: its default driver's (the driver list), else a driver on its lines
    $companyOf = fn ($group) => $group->first()->lorry?->defaultDriver ?? $group->pluck('driver')->filter(fn ($d) => filled($d?->lorry_company) || filled($d?->lorry_group))->first();
    $groups = $byLorry->map(fn ($g) => $companyOf($g)?->lorry_group)->filter()->unique()->sort()->values();
    $companies = $byLorry->map(fn ($g) => $companyOf($g)?->lorry_company)->filter()->unique()->sort()->values();
@endphp
<style>
    .cbl-wrap { border: 1px solid rgb(229 231 235); border-radius: .75rem; overflow: hidden; overflow-x: auto; }
    .cbl-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .875rem; }
    .cbl-table th, .cbl-table td { padding: .55rem .9rem; border-bottom: 1px solid rgb(229 231 235); text-align: left; vertical-align: top; }
    .cbl-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: rgb(100 116 139); background: rgb(248 250 252); font-weight: 600; white-space: nowrap; }
    .cbl-table .cbl-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .cbl-table tbody tr { cursor: pointer; }
    .cbl-table tbody tr:hover td { background: rgb(248 250 252); }
    .cbl-sub { font-size: .75rem; color: rgb(100 116 139); }
    .cbl-filters { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1.25rem; margin-bottom: .6rem; font-size: .8rem; color: rgb(100 116 139); }
    .cbl-select { margin-left: .35rem; font-size: .8rem; padding: .25rem 1.75rem .25rem .5rem; border: 1px solid rgb(209 213 219); border-radius: .4rem; background-color: #fff; color: inherit; }
    .dark .cbl-wrap, .dark .cbl-table th, .dark .cbl-table td { border-color: rgb(55 65 81); }
    .dark .cbl-table th { background: rgb(17 24 39); color: rgb(148 163 184); }
    .dark .cbl-table tbody tr:hover td { background: rgb(31 41 55); }
    .dark .cbl-sub, .dark .cbl-filters { color: rgb(148 163 184); }
    .dark .cbl-select { background-color: rgb(17 24 39); border-color: rgb(55 65 81); color: rgb(229 231 235); }
</style>
@if ($byLorry->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No commission line yet: the returned CSNs' deliveries have no driver, or no CSN was returned before the cutoff.</p>
@else
    <div class="cbl-panel">
        @if ($groups->isNotEmpty() || $companies->isNotEmpty())
            <div class="cbl-filters">
                <label>Lorry group
                    <select class="cbl-select" data-cbl-filter="group" aria-label="Filter by lorry group">
                        <option value="">All</option>
                        @foreach ($groups as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach
                    </select>
                </label>
                <label>Lorry company
                    <select class="cbl-select" data-cbl-filter="company" aria-label="Filter by lorry company">
                        <option value="">All</option>
                        @foreach ($companies as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                    </select>
                </label>
                <span data-cbl-count></span>
            </div>
        @endif
        <div class="cbl-wrap">
            <table class="cbl-table">
                <thead>
                    <tr>
                        <th>Lorry</th>
                        <th>Lorry company</th>
                        <th>Company</th>
                        <th>Drivers (earned)</th>
                        <th class="cbl-num">Job sheets</th>
                        <th class="cbl-num">CSNs</th>
                        <th class="cbl-num">Sales amount</th>
                        <th class="cbl-num">Commission</th>
                        <th>PO</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byLorry as $lorryId => $group)
                        @php
                            $lorry = $group->first()->lorry;
                            $url = $lorryId ? \App\Filament\Resources\CommissionBatchResource::getUrl('lorry', ['record' => $batch, 'lorry' => $lorryId]) : null;
                            $drivers = $group->groupBy(fn ($line) => $line->driver?->name ?? 'Driver removed')->map(fn ($l) => (float) $l->sum('amount'));
                            $poStatus = $group->pluck('slip.status')->unique()->implode(', ');
                            // the lorry's sales: its CSNs' totals
                            $sales = (float) $group->unique('consignment_note_id')->sum(fn ($line) => (float) ($line->consignmentNote?->total_amount ?? 0));
                            $commission = (float) $group->sum('amount');
                            $company = $companyOf($group);
                            $totals = ($totals ?? ['sales' => 0, 'commission' => 0]);
                            $totals['sales'] += $sales;
                            $totals['commission'] += $commission;
                        @endphp
                        <tr data-group="{{ $company?->lorry_group }}" data-company="{{ $company?->lorry_company }}" @if ($url) onclick="window.location='{{ $url }}'" title="Open lorry {{ $lorry?->registration_no }}" @endif>
                            <td><strong>{{ $lorry?->registration_no ?? 'No lorry' }}</strong></td>
                            <td>
                                {{ $company?->lorry_company ?: '—' }}
                                @if ($company?->lorry_company_code)<div class="cbl-sub">{{ $company->lorry_company_code }}</div>@endif
                                @if ($company?->lorry_group)<div class="cbl-sub">{{ $company->lorry_group }}</div>@endif
                            </td>
                            <td>{{ $lorry?->branch?->name ?? '—' }}</td>
                            <td>
                                @foreach ($drivers as $name => $amount)
                                    <div>{{ $name }} <span class="cbl-sub">· RM {{ number_format($amount, 2) }}</span></div>
                                @endforeach
                            </td>
                            <td class="cbl-num">{{ $group->pluck('deliveryOrder.job_sheet_id')->filter()->unique()->count() }}</td>
                            <td class="cbl-num">{{ $group->pluck('consignment_note_id')->filter()->unique()->count() }}</td>
                            <td class="cbl-num">RM {{ number_format($sales, 2) }}</td>
                            <td class="cbl-num"><strong>RM {{ number_format($commission, 2) }}</strong></td>
                            <td><span class="cbl-sub">{{ $poStatus === 'draft' ? 'Not saved yet' : ucfirst(str_replace('_', ' ', $poStatus)) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6"><strong>{{ $byLorry->count() }} lorr{{ $byLorry->count() === 1 ? 'y' : 'ies' }}</strong></td>
                        <td class="cbl-num"><strong>RM {{ number_format($totals['sales'] ?? 0, 2) }}</strong></td>
                        <td class="cbl-num"><strong>RM {{ number_format($totals['commission'] ?? 0, 2) }}</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <script>
        // the lorry group / company filters hide the other lorries' rows (the footer keeps the batch's totals)
        document.querySelectorAll('.cbl-panel').forEach((panel) => {
            const selects = panel.querySelectorAll('[data-cbl-filter]');
            const rows = panel.querySelectorAll('tbody tr');
            const count = panel.querySelector('[data-cbl-count]');
            const apply = () => {
                let shown = 0;
                rows.forEach((row) => {
                    const ok = Array.from(selects).every((s) => ! s.value || row.dataset[s.dataset.cblFilter] === s.value);
                    row.style.display = ok ? '' : 'none';
                    shown += ok ? 1 : 0;
                });
                if (count) count.textContent = shown === rows.length ? '' : shown + ' of ' + rows.length + ' lorries shown';
            };
            selects.forEach((s) => s.addEventListener('change', apply));
        });
    </script>
@endif
