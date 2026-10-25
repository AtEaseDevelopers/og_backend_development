{{-- The customer's special prices and every earlier order line, any product (history button beside Line total on Create / Edit order). Information only. --}}
@php
    $rows = $rows ?? [];
    $special = $special ?? [];
    $money = fn ($value) => $value !== null ? 'RM '.number_format((float) $value, 2) : '—';
@endphp
<div class="ow-history" x-data="{ tab: 'special' }">
    <style>
        .ow-history { --oh-line: var(--ow-line, #e5e7eb); --oh-line-2: var(--ow-line-2, #eef0f3); --oh-muted: var(--ow-muted, #64748b); --oh-link: var(--ow-link, #1d4ed8); font-size: .8125rem; }
        .dark .ow-history { --oh-line: var(--ow-line, #374151); --oh-line-2: var(--ow-line-2, #1f2937); --oh-muted: var(--ow-muted, #94a3b8); --oh-link: var(--ow-link, #93c5fd); }
        .ow-history .oh-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; max-height: 60vh; }
        .ow-history table { width: 100%; border-collapse: collapse; min-width: 44rem; }
        .ow-history th { position: sticky; top: 0; z-index: 1; background: #fff; box-shadow: inset 0 -1px 0 var(--oh-line); font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--oh-muted); text-align: left; padding: .45rem .5rem; border-bottom: 1px solid var(--oh-line); white-space: nowrap; }
        .ow-history td { padding: .5rem; border-bottom: 1px solid var(--oh-line-2); vertical-align: top; }
        .ow-history .oh-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .ow-history .oh-sub { font-size: .7rem; color: var(--oh-muted); }
        .ow-history a.oh-link { color: var(--oh-link); font-weight: 600; }
        .ow-history .oh-empty { padding: 1.25rem .5rem; text-align: center; color: var(--oh-muted); }
        /* two tabs, one table at a time (user, 26 Oct 2026) */
        .ow-history .oh-tabs { display: flex; gap: .25rem; border-bottom: 1px solid var(--oh-line); margin-bottom: .75rem; }
        .ow-history .oh-tab { display: inline-flex; align-items: center; gap: .4rem; padding: .55rem .9rem; font-size: .9rem; font-weight: 600; color: var(--oh-muted); border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .ow-history .oh-tab:hover { color: rgb(15 23 42); }
        .ow-history .oh-tab.oh-tab-on { color: rgb(15 23 42); border-bottom-color: rgb(var(--primary-600, 37 99 235)); }
        .ow-history .oh-count { padding: 0 .45rem; border-radius: 999px; font-size: .72rem; line-height: 1.4rem; background: rgb(241 245 249); color: rgb(71 85 105); }
        .ow-history .oh-hint { font-size: .75rem; color: var(--oh-muted); margin: 0 0 .5rem; }
        .dark .ow-history .oh-tab:hover, .dark .ow-history .oh-tab.oh-tab-on { color: rgb(241 245 249); }
        .dark .ow-history .oh-tab.oh-tab-on { border-bottom-color: rgb(var(--primary-400, 96 165 250)); }
        .dark .ow-history .oh-count { background: rgb(55 65 81); color: rgb(203 213 225); }
        .ow-history .oh-range { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .ow-history .oh-wrap.oh-short { max-height: 40vh; }
    </style>

    <div class="oh-tabs" role="tablist">
        <button type="button" role="tab" class="oh-tab" :class="{ 'oh-tab-on': tab === 'special' }" x-on:click="tab = 'special'" :aria-selected="tab === 'special'">Special price <span class="oh-count">{{ count($special) }}</span></button>
        <button type="button" role="tab" class="oh-tab" :class="{ 'oh-tab-on': tab === 'orders' }" x-on:click="tab = 'orders'" :aria-selected="tab === 'orders'">Earlier orders <span class="oh-count">{{ count($rows) }}</span></button>
    </div>

    <div x-show="tab === 'special'">
        <p class="oh-hint">Used instead of the default price for this customer.</p>
        @if ($special === [])
            <div class="oh-empty">No special price for {{ $customer ?: 'this customer' }} · the default price applies.</div>
        @else
            <div class="oh-wrap">
                <table>
                    <thead><tr><th>Type</th><th>Product</th><th>Drop-off location</th><th>Drop-off type</th><th>Quantity → price per unit</th><th>Minimum charge</th></tr></thead>
                    <tbody>
                        @foreach ($special as $row)
                            <tr>
                                <td style="white-space:nowrap">{{ $row['category_label'] }}</td>
                                <td>{{ $row['product'] ?? '—' }}</td>
                                <td>{{ $row['location'] ?? '—' }}@if ($row['pickup_location'] ?? null)<div class="oh-sub">Pick-up: {{ $row['pickup_location'] }}</div>@endif</td>
                                <td style="white-space:nowrap">{{ $row['drop_off'] }}</td>
                                <td>@foreach ($row['ranges'] as $line)<div class="oh-range">{{ $line }}</div>@endforeach</td>
                                <td>{{ $row['minimums'] === [] ? '—' : implode(' · ', $row['minimums']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div x-show="tab === 'orders'" x-cloak>
    <p class="oh-hint">Every product, newest first.</p>
    @if ($rows === [])
        <div class="oh-empty">No earlier orders for {{ $customer ?: 'this customer' }}.</div>
    @else
        <div class="oh-wrap">
            <table>
                <thead><tr><th>Date</th><th>Order</th><th>Product</th><th>Destination</th><th class="oh-num">Qty</th><th class="oh-num">Unit price</th><th class="oh-num">Line total</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td style="white-space:nowrap">{{ $row['date'] ?? '—' }}</td>
                            <td>
                                @if ($row['view_url'])<a href="{{ $row['view_url'] }}" target="_blank" rel="noopener" class="oh-link">{{ $row['order'] }}</a>@else{{ $row['order'] }}@endif
                                @if ($row['status'])<div class="oh-sub">{{ $row['status'] }}</div>@endif
                            </td>
                            <td>{{ $row['product'] }}</td>
                            <td>{{ $row['destination'] }}</td>
                            <td class="oh-num">{{ rtrim(rtrim(number_format($row['qty'], 2), '0'), '.') }} {{ $row['uom'] }}</td>
                            <td class="oh-num">{{ $money($row['unit_price']) }}</td>
                            <td class="oh-num">{{ $money($row['line_total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    </div>
</div>
