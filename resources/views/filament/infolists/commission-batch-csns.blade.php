{{-- Commission batch: the returned CSNs it takes (returned on or before its cutoff), with each CSN's commission --}}
@php
    $batch = $getRecord();
    $rows = $batch->csns()->with(['consignmentNote.customer:id,company_name', 'consignmentNote.fromLocation:id,name', 'consignmentNote.toLocation:id,name'])->orderBy('returned_at')->get();
    $lines = \App\Domains\Commission\Models\CommissionLineItem::query()
        ->whereIn('commission_slip_id', $batch->slips()->select('id'))
        ->with('driver:id,name')
        ->get()
        ->groupBy('consignment_note_id');
@endphp
<style>
    .cbc-wrap { border: 1px solid rgb(229 231 235); border-radius: .75rem; overflow: hidden; overflow-x: auto; }
    .cbc-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .875rem; }
    .cbc-table th, .cbc-table td { padding: .55rem .9rem; border-bottom: 1px solid rgb(229 231 235); text-align: left; vertical-align: top; }
    .cbc-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: rgb(100 116 139); background: rgb(248 250 252); font-weight: 600; white-space: nowrap; }
    .cbc-table .cbc-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .cbc-table tbody tr:last-child td { border-bottom: 0; }
    .cbc-sub { font-size: .75rem; color: rgb(100 116 139); }
    .dark .cbc-wrap, .dark .cbc-table th, .dark .cbc-table td { border-color: rgb(55 65 81); }
    .dark .cbc-table th { background: rgb(17 24 39); color: rgb(148 163 184); }
    .dark .cbc-sub { color: rgb(148 163 184); }
</style>
@if ($rows->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No CSN returned on or before the cutoff date that is not already in another batch.</p>
@else
    <div class="cbc-wrap">
        <table class="cbc-table">
            <thead>
                <tr>
                    <th>CSN</th>
                    <th>Customer</th>
                    <th>Returned</th>
                    <th class="cbc-num">CSN total</th>
                    <th>Drivers</th>
                    <th class="cbc-num">Commission</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php $csn = $row->consignmentNote; $csnLines = $lines->get($row->consignment_note_id, collect()); @endphp
                    <tr>
                        <td>
                            <strong>{{ $csn?->number ?? '—' }}</strong>
                            <div class="cbc-sub">{{ trim(($csn?->fromLocation?->name ?? '').' → '.($csn?->toLocation?->name ?? ''), ' →') }}</div>
                        </td>
                        <td>{{ $csn?->customer?->company_name ?: ($csn?->customer_name ?: '—') }}</td>
                        <td>{{ $row->returned_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="cbc-num">RM {{ number_format((float) ($csn?->total_amount ?? 0), 2) }}</td>
                        <td>
                            @forelse ($csnLines as $line)
                                <div>{{ $line->driver?->name ?? 'Driver removed' }} <span class="cbc-sub">· RM {{ number_format((float) $line->amount, 2) }}{{ $line->line_type !== 'delivery' ? ' · '.str_replace('_', ' ', $line->line_type) : '' }}</span></div>
                            @empty
                                <span class="cbc-sub">No driver on its delivery (no commission line)</span>
                            @endforelse
                        </td>
                        <td class="cbc-num"><strong>RM {{ number_format((float) $row->amount, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"><strong>{{ $rows->count() }} returned CSN(s)</strong></td>
                    <td class="cbc-num"><strong>RM {{ number_format((float) $rows->sum('amount'), 2) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
