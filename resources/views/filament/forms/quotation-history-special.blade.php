@php
    $columns = 'minmax(0, 1fr) 7rem 4rem 5rem 5.5rem';
@endphp

<div class="rounded-lg border border-amber-200 bg-amber-50/40 p-3 dark:border-amber-700 dark:bg-amber-950/20">
    <div class="mb-2 font-semibold text-gray-950 dark:text-white">Customer Special Price</div>
    <div
        class="rounded-md border border-amber-100 dark:border-amber-900/40"
        style="max-height: 8rem; overflow-y: auto; font-size: 0.75rem; line-height: 1.5;"
    >
        <div
            style="display: grid; grid-template-columns: {{ $columns }}; position: sticky; top: 0; z-index: 2; border-bottom: 1px solid #fcd34d; background-color: #fffbeb; font-weight: 500;"
        >
            <div style="padding: 0.5rem;">Measurement</div>
            <div style="padding: 0.5rem;">Destination</div>
            <div style="padding: 0.5rem;">UOM</div>
            <div style="padding: 0.5rem;">Route</div>
            <div style="padding: 0.5rem; text-align: right;">Price</div>
        </div>

        @forelse($rows as $row)
            <div style="display: grid; grid-template-columns: {{ $columns }}; border-bottom: 1px solid #fde68a;">
                <div style="padding: 0.5rem; word-break: break-word;">{{ $row['measurement'] }}</div>
                <div style="padding: 0.5rem; word-break: break-word;">{{ $row['destination'] }}</div>
                <div style="padding: 0.5rem;">{{ $row['uom'] ?? '—' }}</div>
                <div style="padding: 0.5rem; word-break: break-word;">{{ $row['route'] ?? '—' }}</div>
                <div style="padding: 0.5rem; text-align: right; white-space: nowrap; font-weight: 500;">RM {{ number_format($row['price'], 2) }}</div>
            </div>
        @empty
            <div style="padding: 0.75rem 0.5rem; color: #6b7280;">No customer special prices on file.</div>
        @endforelse
    </div>
</div>
