{{-- UOM price list: quantity range × location matrix (fits on one screen). --}}
<style>
    .uom-matrix { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .875rem; }
    .uom-matrix th, .uom-matrix td { padding: .55rem .9rem; border-bottom: 1px solid rgb(229 231 235); text-align: right; white-space: nowrap; }
    .uom-matrix th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: rgb(100 116 139); background: rgb(248 250 252); font-weight: 600; }
    .uom-matrix th:first-child, .uom-matrix td:first-child { text-align: left; }
    .uom-matrix td:first-child { font-weight: 600; color: rgb(15 23 42); }
    .uom-matrix td { font-variant-numeric: tabular-nums; }
    .uom-matrix tbody tr:last-child td { border-bottom: 0; }
    .uom-matrix tbody tr:hover td { background: rgb(248 250 252); }
    .uom-matrix .uom-empty { color: rgb(148 163 184); }
    .uom-wrap { border: 1px solid rgb(229 231 235); border-radius: .75rem; overflow: hidden; overflow-x: auto; }
    .uom-cards { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); }
    .uom-card-title { font-weight: 600; padding: .6rem .9rem; border-bottom: 1px solid rgb(229 231 235); background: rgb(248 250 252); }
    .uom-legend { font-size: .75rem; color: rgb(100 116 139); margin-top: .5rem; }
    .dark .uom-matrix th, .dark .uom-card-title { background: rgb(17 24 39); color: rgb(148 163 184); }
    .dark .uom-matrix th, .dark .uom-matrix td, .dark .uom-wrap, .dark .uom-card-title { border-color: rgb(55 65 81); }
    .dark .uom-matrix td:first-child { color: rgb(241 245 249); }
    .dark .uom-matrix tbody tr:hover td { background: rgb(31 41 55); }
</style>

@if ($matrix['locations'] === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">No rate tiers yet. Use Edit to add a price per location and quantity range.</p>
@elseif ($matrix['shared'])
    <div class="uom-wrap">
        <table class="uom-matrix">
            <thead>
                <tr>
                    <th>Quantity</th>
                    @foreach ($matrix['locations'] as $location)
                        <th>{{ $location }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($matrix['ranges'] as $range)
                    <tr>
                        <td>{{ $range }}</td>
                        @foreach ($matrix['locations'] as $location)
                            @php $price = $matrix['cells'][$range][$location] ?? null; @endphp
                            <td @class(['uom-empty' => $price === null])>{{ $price !== null ? 'RM '.number_format($price, 2) : '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="uom-legend">{{ count($matrix['locations']) }} {{ \Illuminate\Support\Str::plural('location', count($matrix['locations'])) }} · {{ count($matrix['ranges']) }} quantity {{ \Illuminate\Support\Str::plural('range', count($matrix['ranges'])) }} · "+" means that quantity and above.</div>
@else
    <div class="uom-cards">
        @foreach ($matrix['byLocation'] as $location => $rows)
            <div class="uom-wrap">
                <div class="uom-card-title">{{ $location }}</div>
                <table class="uom-matrix">
                    <thead><tr><th>Quantity</th><th>Unit price</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr><td>{{ $row['range'] }}</td><td>RM {{ number_format($row['price'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
    <div class="uom-legend">Locations use different quantity ranges, so each location is shown separately. "+" means that quantity and above.</div>
@endif
