@php
    $letterhead = $letterhead ?? [];
    $meta = $meta ?? [];
    $consignor = $consignor ?? [];
    $consignee = $consignee ?? [];
    $route = $route ?? [];
    $lines = $lines ?? [];
    $dropOff = $drop_off ?? [];
    $totals = $totals ?? [];
    $footer = $footer ?? [];
    $forPdf = $forPdf ?? false;
    $documentTitle = $document_title ?? 'Consignment Note';
    $hidePricing = $hide_pricing ?? false;

    $logoSrc = $forPdf
        ? ($letterhead['logo_src'] ?? null)
        : ($letterhead['logo_url'] ?? null);

    $nl = fn (?string $value): string => filled($value) ? nl2br(e($value)) : '—';
@endphp

<div @class(['csn-document', 'csn-document--pdf' => $forPdf, 'csn-document--no-pricing' => $hidePricing])>
    {{-- Letterhead --}}
    <table class="csn-doc-header">
        <tr>
            <td class="csn-doc-logo-cell">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="O&amp;G Transport" class="csn-doc-logo">
                @endif
            </td>
            <td class="csn-doc-company-cell">
                <div class="csn-doc-company-name">{{ $letterhead['company_name'] ?? 'O&G Transport' }}</div>
                @if(filled($letterhead['reg_no'] ?? null))
                    <div>Co. Reg. {{ $letterhead['reg_no'] }}</div>
                @endif
                @if(filled($letterhead['address'] ?? null))
                    <div>{!! $nl($letterhead['address']) !!}</div>
                @endif
                <div>
                    @if(filled($letterhead['phone'] ?? null))
                        Tel: {{ $letterhead['phone'] }}
                    @endif
                    @if(filled($letterhead['email'] ?? null))
                        @if(filled($letterhead['phone'] ?? null)) &nbsp; @endif
                        E-mail: {{ $letterhead['email'] }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Parties + meta --}}
    <table class="csn-doc-parties">
        <tr>
            <td class="csn-doc-party-col">
                <div class="csn-doc-label">Consignor:</div>
                <div class="csn-doc-party-name">{{ $consignor['name'] ?? '—' }}</div>
                <div class="csn-doc-party-address">{!! $nl($consignor['address'] ?? null) !!}</div>

                <div class="csn-doc-label csn-doc-label-spaced">Consignee:</div>
                <div class="csn-doc-party-name">{{ $consignee['name'] ?? '—' }}</div>
                <div class="csn-doc-party-address">{!! $nl($consignee['address'] ?? null) !!}</div>
            </td>
            <td class="csn-doc-meta-col">
                <div class="csn-doc-title">{{ $documentTitle }}</div>
                <table class="csn-doc-meta-table">
                    <tr><td class="csn-doc-meta-label">NO</td><td>{{ $meta['number'] ?? '—' }}</td></tr>
                    <tr><td class="csn-doc-meta-label">Date</td><td>{{ $meta['issued_at'] ?? '—' }}</td></tr>
                    <tr><td class="csn-doc-meta-label">{{ $meta['reference_label'] ?? 'D/O No' }}</td><td>{{ $meta['reference_number'] ?? ($meta['do_number'] ?? '—') }}</td></tr>
                    @if(! $hidePricing)
                        <tr><td class="csn-doc-meta-label">Invoice No</td><td>{{ $meta['invoice_number'] ?? '—' }}</td></tr>
                    @endif
                    <tr><td class="csn-doc-meta-label">Lorry No</td><td>{{ $meta['lorry_number'] ?? '—' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="csn-doc-route">
        <strong>From:</strong> {{ $route['from'] ?? '—' }}
        &nbsp;&nbsp;&nbsp;
        <strong>To:</strong> {{ $route['to'] ?? '—' }}
    </div>

    {{-- Line items --}}
    <table class="csn-doc-items">
        <thead>
            <tr>
                <th class="csn-doc-qty">Quantity</th>
                <th class="csn-doc-desc">Description</th>
                @unless($hidePricing)
                    <th class="csn-doc-amt">Amount<br>(SGD)</th>
                    <th class="csn-doc-amt">Amount<br>(MYR)</th>
                    <th class="csn-doc-amt">SST<br>(MYR)</th>
                    <th class="csn-doc-amt">Total<br>(MYR)</th>
                @endunless
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $index => $line)
                <tr>
                    <td class="csn-doc-qty">{{ $line['quantity'] ?? '—' }}</td>
                    @if($index === 0)
                        <td class="csn-doc-desc" rowspan="{{ max(count($lines), 1) }}">
                            @foreach($lines as $item)
                                <div class="csn-doc-line-item">{{ $item['description'] ?? '—' }}</div>
                            @endforeach

                            <div class="csn-doc-dropoff">
                                <div class="csn-doc-dropoff-title">Drop off Location</div>
                                <div>{!! $nl($dropOff['address'] ?? null) !!}</div>
                                @if(filled($dropOff['contact'] ?? null))
                                    <div>{{ $dropOff['contact'] }}</div>
                                @endif
                            </div>
                        </td>
                    @endif
                    @unless($hidePricing)
                        <td class="csn-doc-amt csn-doc-amt-value">{{ $line['amount_sgd'] ?? '' }}</td>
                        <td class="csn-doc-amt csn-doc-amt-value">{{ $line['amount_myr'] ?? '' }}</td>
                        <td class="csn-doc-amt csn-doc-amt-value">{{ $line['sst_myr'] ?? '' }}</td>
                        <td class="csn-doc-amt csn-doc-amt-value">{{ $line['total_myr'] ?? '' }}</td>
                    @endunless
                </tr>
            @empty
                <tr>
                    <td class="csn-doc-qty">—</td>
                    <td class="csn-doc-desc">
                        <div class="csn-doc-muted">{{ $hidePricing ? 'No cargo details.' : 'Enter cargo details to preview line items.' }}</div>
                        <div class="csn-doc-dropoff">
                            <div class="csn-doc-dropoff-title">Drop off Location</div>
                            <div>{!! $nl($dropOff['address'] ?? null) !!}</div>
                            @if(filled($dropOff['contact'] ?? null))
                                <div>{{ $dropOff['contact'] }}</div>
                            @endif
                        </div>
                    </td>
                    @unless($hidePricing)
                        <td class="csn-doc-amt"></td>
                        <td class="csn-doc-amt"></td>
                        <td class="csn-doc-amt"></td>
                        <td class="csn-doc-amt"></td>
                    @endunless
                </tr>
            @endforelse
            @unless($hidePricing)
                <tr class="csn-doc-total-row">
                    <td></td>
                    <td class="csn-doc-desc csn-doc-total-label">Total</td>
                    <td class="csn-doc-amt csn-doc-amt-value">{{ $totals['amount_sgd'] ?? '' }}</td>
                    <td class="csn-doc-amt csn-doc-amt-value">{{ $totals['amount_myr'] ?? '' }}</td>
                    <td class="csn-doc-amt csn-doc-amt-value">{{ $totals['sst_myr'] ?? '' }}</td>
                    <td class="csn-doc-amt csn-doc-amt-value">{{ $totals['total_myr'] ?? '' }}</td>
                </tr>
            @endunless
        </tbody>
    </table>

    {{-- Footer --}}
    <table class="csn-doc-footer">
        <tr>
            <td class="csn-doc-footer-left">
                <div class="csn-doc-footer-field">
                    <span class="csn-doc-label">Remark:</span>
                    {{ filled($footer['remarks'] ?? null) ? $footer['remarks'] : '' }}
                </div>
                @if(filled($footer['marking'] ?? null))
                    <div class="csn-doc-footer-field">
                        <span class="csn-doc-label">Marking:</span>
                        {{ $footer['marking'] }}
                    </div>
                @endif
                <div class="csn-doc-footer-field"><span class="csn-doc-label">CCP No:</span> {{ $footer['ccp_no'] ?? '' }}</div>
                <div class="csn-doc-footer-field"><span class="csn-doc-label">K2 No:</span> {{ $footer['k2_no'] ?? '' }}</div>
                <div class="csn-doc-footer-field"><span class="csn-doc-label">Declaration Receipt No:</span> {{ $footer['declaration_receipt_no'] ?? '' }}</div>
            </td>
            <td class="csn-doc-footer-right">
                <div class="csn-doc-sign-line"></div>
                <div class="csn-doc-sign-line"></div>
                <div class="csn-doc-sign-note">Please Chop, Sign &amp; Return.</div>
                <div class="csn-doc-sign-date">Date</div>
            </td>
        </tr>
    </table>
</div>
