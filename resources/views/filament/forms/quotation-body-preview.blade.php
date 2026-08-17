@php
    $formatRm = function ($amount): string {
        if ($amount === null || $amount === '') {
            return '';
        }

        $formatted = number_format((float) $amount, 2, '.', '');

        return 'RM '.str_replace('.', '-', $formatted);
    };

    $destinations = $rateMatrix['destinations'] ?? [];
    $rows = $rateMatrix['rows'] ?? [];
    $columnHeader = count($destinations) > 1
        ? implode(' / ', $destinations)
        : ($destinations[0] ?? 'Rate');
@endphp

<div style="background:#0abab5;color:#000;padding:16px;border-radius:4px;font-family:Courier New,Courier,monospace;font-size:12px;line-height:1.45;overflow:auto;">
    <div style="font-weight:bold;margin-bottom:8px;">RE : {{ $title }}</div>
    <div style="margin-bottom:10px;">Please find the transportation charges for the following:-</div>

    <div style="display:grid;grid-template-columns:1fr auto;gap:8px;font-weight:bold;border-bottom:1px solid #000;padding-bottom:4px;margin-bottom:6px;">
        <div>Item</div>
        <div style="min-width:160px;text-align:right;">{{ $columnHeader }}</div>
    </div>

    @forelse($rows as $row)
        @php
            $filledPrices = array_values(array_filter($row['prices'] ?? [], fn ($price) => filled($price)));
            $samePrice = count($filledPrices) > 0 && count(array_unique($filledPrices)) === 1;
        @endphp
        <div style="display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:4px;">
            <div>{{ $row['label'] }}</div>
            <div style="text-align:right;min-width:160px;">
                @if($samePrice)
                    {{ $formatRm($filledPrices[0]) }}
                @else
                    @foreach($row['prices'] as $index => $price)
                        @if(filled($price))
                            <div>{{ ($destinations[$index] ?? '').': '.$formatRm($price) }}</div>
                        @endif
                    @endforeach
                @endif
            </div>
        </div>
    @empty
        <div style="opacity:.7;">Add items and rates above to preview the quotation body.</div>
    @endforelse

    <div style="margin-top:14px;">Terms of Payment: {{ $terms }}</div>
</div>
