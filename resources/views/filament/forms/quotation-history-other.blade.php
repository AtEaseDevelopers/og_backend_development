@php
    $columns = '5.5rem minmax(0, 1fr) 6rem 2.5rem 5.5rem 3rem';
@endphp

<div class="rounded-lg border border-blue-200 bg-blue-50/40 p-3 dark:border-blue-700 dark:bg-blue-950/20">
    <div class="mb-2 font-semibold text-gray-950 dark:text-white">Pricing History</div>
    <div
        class="rounded-md border border-blue-100 dark:border-blue-900/40"
        style="max-height: 8rem; overflow-y: auto; font-size: 0.75rem; line-height: 1.5;"
    >
        <div
            style="display: grid; grid-template-columns: {{ $columns }}; position: sticky; top: 0; z-index: 2; border-bottom: 1px solid #bfdbfe; background-color: #eff6ff; font-weight: 500;"
        >
            <div style="padding: 0.5rem;">Date</div>
            <div style="padding: 0.5rem;">Measurement</div>
            <div style="padding: 0.5rem;">Destination</div>
            <div style="padding: 0.5rem;">Qty</div>
            <div style="padding: 0.5rem; text-align: right;">Price</div>
            <div style="padding: 0.5rem;">View</div>
        </div>

        @forelse($rows as $row)
            <div style="display: grid; grid-template-columns: {{ $columns }}; border-bottom: 1px solid #dbeafe;">
                <div style="padding: 0.5rem; white-space: nowrap;">{{ $row['date'] ?? '—' }}</div>
                <div style="padding: 0.5rem; word-break: break-word;">{{ $row['measurement'] }}</div>
                <div style="padding: 0.5rem; word-break: break-word;">{{ $row['destination'] ?? '—' }}</div>
                <div style="padding: 0.5rem;">{{ number_format($row['qty'], 0) }}</div>
                <div style="padding: 0.5rem; text-align: right; white-space: nowrap;">RM {{ number_format($row['price'], 2) }}</div>
                <div style="padding: 0.5rem;">
                    @if($row['view_url'] ?? null)
                        <a href="{{ $row['view_url'] }}" target="_blank" style="color: #d97706; text-decoration: none;">View</a>
                    @else
                        {{ $row['quote'] ?? '—' }}
                    @endif
                </div>
            </div>
        @empty
            <div style="padding: 0.75rem 0.5rem; color: #6b7280;">No pricing history found.</div>
        @endforelse
    </div>
</div>
