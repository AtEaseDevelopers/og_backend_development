{{-- Customer → Special price: read only (from the special price list and accepted quotations). --}}
@php $rows = $getState() ?? []; @endphp
<style>
    .csp-wrap { border: 1px solid rgb(229 231 235); border-radius: .75rem; overflow: hidden; overflow-x: auto; }
    .csp-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .875rem; }
    .csp-table th, .csp-table td { padding: .55rem .9rem; border-bottom: 1px solid rgb(229 231 235); text-align: left; vertical-align: top; }
    .csp-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: rgb(100 116 139); background: rgb(248 250 252); font-weight: 600; white-space: nowrap; }
    .csp-table td.csp-num, .csp-table th.csp-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .csp-table td:first-child { min-width: 16rem; }
    .csp-table td:nth-child(4), .csp-table td:nth-child(6) { white-space: nowrap; }
    .csp-range { font-variant-numeric: tabular-nums; color: rgb(4 120 87); font-weight: 600; }
    .csp-group { font-size: .85rem; font-weight: 600; margin: 1rem 0 .5rem; color: rgb(15 23 42); }
    .dark .csp-range { color: rgb(110 231 183); }
    .dark .csp-group { color: rgb(241 245 249); }
    .csp-table tbody tr:last-child td { border-bottom: 0; }
    .csp-table tbody tr:hover td { background: rgb(248 250 252); }
    .csp-product { font-weight: 600; color: rgb(15 23 42); }
    .csp-sub { font-size: .75rem; color: rgb(100 116 139); margin-top: .1rem; }
    .csp-price { font-weight: 600; color: rgb(4 120 87); }
    .csp-note { font-size: .8rem; color: rgb(100 116 139); margin-bottom: .75rem; }
    .dark .csp-wrap, .dark .csp-table th, .dark .csp-table td { border-color: rgb(55 65 81); }
    .dark .csp-table th { background: rgb(17 24 39); color: rgb(148 163 184); }
    .dark .csp-table tbody tr:hover td { background: rgb(31 41 55); }
    .dark .csp-product { color: rgb(241 245 249); }
    .dark .csp-sub, .dark .csp-note { color: rgb(148 163 184); }
    .dark .csp-price { color: rgb(110 231 183); }
</style>

<p class="csp-note">Used instead of the default price when this customer is selected. Special prices come from the special price list and from accepted quotations only; they cannot be added or changed here.</p>

@if ($rows === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">No special price for this customer. The default price applies.</p>
@else
    @foreach (collect($rows)->groupBy('category_label') as $label => $group)
        <div class="csp-group">{{ $label }} · {{ $group->count() }} {{ \Illuminate\Support\Str::plural('price row', $group->count()) }}</div>
        <div class="csp-wrap">
            <table class="csp-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Drop-off location</th>
                        <th>Drop-off type</th>
                        <th>Quantity → price per unit</th>
                        <th>Minimum charge</th>
                        <th>Effective</th>
                        <th>From</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group as $row)
                        <tr>
                            <td><div class="csp-product">{{ $row['product'] ?? '—' }}</div></td>
                            <td>
                                {{ $row['location'] ?? '—' }}
                                @if ($row['pickup_location'])
                                    <div class="csp-sub">Pick-up: {{ $row['pickup_location'] }}</div>
                                @endif
                            </td>
                            <td>{{ $row['drop_off'] }}</td>
                            <td>
                                @foreach ($row['ranges'] as $range)
                                    <div class="csp-range">{{ $range }}</div>
                                @endforeach
                            </td>
                            <td>
                                @forelse ($row['minimums'] as $minimum)
                                    <div class="csp-sub" style="margin-top:0">{{ $minimum }}</div>
                                @empty
                                    <span class="csp-sub">—</span>
                                @endforelse
                            </td>
                            <td>{{ $row['effective'] ?? '—' }}</td>
                            <td>{{ $row['from'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif
