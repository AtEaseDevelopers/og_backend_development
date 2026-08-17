@php
    $destinationCount = count($destinations);
    $columns = 'minmax(0, 1fr) 2.5rem'.str_repeat(' 5.5rem', $destinationCount);
@endphp

<div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
    <div class="mb-2 font-semibold text-gray-950 dark:text-white">
        Default Price for All Measurement
    </div>
    <div
        class="rounded-md border border-gray-100 dark:border-gray-800"
        style="max-height: 8rem; overflow-y: auto; font-size: 0.75rem; line-height: 1.5;"
    >
        <div
            style="display: grid; grid-template-columns: {{ $columns }}; position: sticky; top: 0; z-index: 2; border-bottom: 1px solid #e5e7eb; background-color: #ffffff; font-weight: 500;"
        >
            <div style="padding: 0.5rem;">Measurement</div>
            <div style="padding: 0.5rem;">Qty</div>
            @foreach ($destinations as $destination)
                <div style="padding: 0.5rem; text-align: right;">{{ $destination }}</div>
            @endforeach
        </div>

        @forelse($rows as $row)
            <div style="display: grid; grid-template-columns: {{ $columns }}; border-bottom: 1px solid #f3f4f6;">
                <div style="padding: 0.5rem; word-break: break-word;">{{ $row['measurement'] }}</div>
                <div style="padding: 0.5rem;">{{ number_format($row['qty'], 0) }}</div>
                @foreach ($destinations as $destination)
                    <div style="padding: 0.5rem; text-align: right; white-space: nowrap;">
                        @php $price = $row['prices'][$destination] ?? null @endphp
                        @if($price !== null)
                            RM {{ number_format($price, 2) }}
                        @else
                            —
                        @endif
                    </div>
                @endforeach
            </div>
        @empty
            <div style="padding: 0.75rem 0.5rem; color: #6b7280;">No default master prices found.</div>
        @endforelse
    </div>
</div>
