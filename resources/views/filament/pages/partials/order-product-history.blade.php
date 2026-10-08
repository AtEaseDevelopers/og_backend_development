{{-- Previous records of one product for the customer (history icon of a product row on Create / Edit order). Information only. --}}
@php
    $rows = $rows ?? [];
    $sameCount = collect($rows)->where('same_destination', true)->count();
@endphp
<div class="ow-history">
    <style>
        .ow-history { --oh-line: var(--ow-line, #e5e7eb); --oh-line-2: var(--ow-line-2, #eef0f3); --oh-muted: var(--ow-muted, #64748b); --oh-link: var(--ow-link, #1d4ed8); --oh-hl-bg: var(--ow-progress-bg, #eff6ff); --oh-hl-fg: var(--ow-progress-fg, #1d4ed8); --oh-hl-bd: var(--ow-progress-bd, #bfdbfe); font-size: .8125rem; }
        .dark .ow-history { --oh-line: var(--ow-line, #374151); --oh-line-2: var(--ow-line-2, #1f2937); --oh-muted: var(--ow-muted, #94a3b8); --oh-link: var(--ow-link, #93c5fd); --oh-hl-bg: var(--ow-progress-bg, rgb(30 64 175 / .25)); --oh-hl-fg: var(--ow-progress-fg, #93c5fd); --oh-hl-bd: var(--ow-progress-bd, rgb(59 130 246 / .35)); }
        .ow-history .oh-meta { display: flex; flex-wrap: wrap; gap: .25rem 1rem; color: var(--oh-muted); margin-bottom: .6rem; font-size: .75rem; }
        .ow-history .oh-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .ow-history table { width: 100%; border-collapse: collapse; min-width: 40rem; }
        .ow-history th { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--oh-muted); text-align: left; padding: .45rem .5rem; border-bottom: 1px solid var(--oh-line); white-space: nowrap; }
        .ow-history td { padding: .5rem; border-bottom: 1px solid var(--oh-line-2); vertical-align: top; }
        .ow-history .oh-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .ow-history .oh-nowrap { white-space: nowrap; }
        .ow-history .oh-sub { font-size: .7rem; color: var(--oh-muted); }
        .ow-history tr.oh-same td { background: var(--oh-hl-bg); }
        .ow-history .oh-pill { display: inline-block; margin-top: .15rem; padding: 0 .4rem; border-radius: 999px; font-size: .62rem; font-weight: 600; line-height: 1.5; background: var(--oh-hl-bg); color: var(--oh-hl-fg); border: 1px solid var(--oh-hl-bd); }
        .ow-history a.oh-link { color: var(--oh-link); font-weight: 600; }
        .ow-history a.oh-link:hover { text-decoration: underline; }
        .ow-history .oh-empty { padding: 1.25rem .5rem; text-align: center; color: var(--oh-muted); }
    </style>

    <div class="oh-meta">
        <span><strong>Product:</strong> {{ $item_name ?: '—' }}</span>
        @if (filled($customer ?? null))<span><strong>Customer:</strong> {{ $customer }}</span>@endif
        @if (filled($location ?? null))<span><strong>This row's destination:</strong> {{ $location }}{{ $sameCount > 0 ? ' · shown first' : '' }}</span>@endif
    </div>

    <div class="oh-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Order / quote no.</th>
                    <th>Destination</th>
                    <th class="oh-num">Qty</th>
                    <th>UOM</th>
                    <th class="oh-num">Unit price</th>
                    <th class="oh-num">Line total</th>
                    <th>View</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr @class(['oh-same' => $row['same_destination']])>
                        <td class="oh-nowrap">{{ $row['date'] ?? '—' }}</td>
                        <td>
                            <div class="oh-nowrap">{{ $row['order'] }}</div>
                            @if ($row['quote'] !== $row['order'] || filled($row['status']))
                                <div class="oh-sub">{{ collect([$row['quote'] !== $row['order'] ? $row['quote'] : null, $row['status']])->filter()->implode(' · ') }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $row['destination'] }}
                            @if ($row['same_destination'])<div><span class="oh-pill">Same destination</span></div>@endif
                        </td>
                        <td class="oh-num">{{ rtrim(rtrim(number_format((float) $row['qty'], 3, '.', ''), '0'), '.') }}</td>
                        <td>{{ $row['uom'] ?? '—' }}</td>
                        <td class="oh-num">{{ $row['unit_price'] !== null ? 'RM '.number_format((float) $row['unit_price'], 2) : 'No price' }}</td>
                        <td class="oh-num">{{ $row['line_total'] !== null ? 'RM '.number_format((float) $row['line_total'], 2) : '—' }}</td>
                        <td>
                            @if ($row['view_url'] ?? null)
                                <a href="{{ $row['view_url'] }}" target="_blank" rel="noopener" class="oh-link">View</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="oh-empty">No previous records for this product</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
