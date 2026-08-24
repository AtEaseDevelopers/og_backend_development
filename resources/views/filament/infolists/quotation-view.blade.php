@php
    $summary = $summary ?? [];
    $information = $information ?? [];
    $consignor = $consignor ?? [];
    $pricing = $pricing ?? [];
    $details = $details ?? [];
    $matrix = $details['matrix'] ?? ['destinations' => [], 'rows' => []];
    $destinations = $matrix['destinations'] ?? [];
    $rows = $matrix['rows'] ?? [];

    $display = fn ($value): string => filled($value) ? (string) $value : '—';
@endphp

<div class="quotation-view">
    {{-- Summary --}}
    <div class="qv-card qv-summary-card">
        <div class="qv-grid-6">
            <div class="qv-field">
                <div class="qv-label">Ref No.</div>
                <div class="qv-value qv-value-strong">{{ $display($summary['number'] ?? null) }}</div>
            </div>
            <div class="qv-field">
                <div class="qv-label">Quotation Date</div>
                <div class="qv-value">{{ $display($summary['quoted_at'] ?? null) }}</div>
            </div>
            <div class="qv-field">
                <div class="qv-label">Valid Until</div>
                <div class="qv-value">{{ $display($summary['valid_until'] ?? null) }}</div>
            </div>
            <div class="qv-field">
                <div class="qv-label">Expected Delivery</div>
                <div class="qv-value">{{ $display($summary['expected_delivery_date'] ?? null) }}</div>
            </div>
            <div class="qv-field">
                <div class="qv-label">Salesperson</div>
                <div class="qv-value">{{ $display($summary['salesperson'] ?? null) }}</div>
            </div>
            <div class="qv-field">
                <div class="qv-label">Active Status</div>
                <div class="qv-value qv-status">
                    <span @class(['qv-status-dot', 'qv-status-dot-active' => $summary['is_active'] ?? false])></span>
                    <span>{{ ($summary['is_active'] ?? false) ? 'Active' : 'Inactive' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Quotation Information --}}
    <div class="qv-card">
        <div class="qv-card-title">Quotation Information</div>
        <div class="qv-grid-3">
            <div class="qv-field-stack">
                <div class="qv-field">
                    <div class="qv-label">Title</div>
                    <div class="qv-value">{{ $display($information['title'] ?? null) }}</div>
                </div>
                <div class="qv-field">
                    <div class="qv-label">Salesperson</div>
                    <div class="qv-value">{{ $display($information['salesperson'] ?? null) }}</div>
                </div>
                <div class="qv-field">
                    <div class="qv-label">Attn</div>
                    <div class="qv-value">{{ $display($information['attention'] ?? null) }}</div>
                </div>
            </div>
            <div class="qv-field-stack">
                <div class="qv-field">
                    <div class="qv-label">Terms</div>
                    <div class="qv-value">{{ $display($information['terms_of_payment'] ?? null) }}</div>
                </div>
                <div class="qv-field">
                    <div class="qv-label">Tel (alt)</div>
                    <div class="qv-value">{{ $display($information['customer_phone_alt'] ?? null) }}</div>
                </div>
            </div>
            <div class="qv-field-stack">
                <div class="qv-field">
                    <div class="qv-label">Issued by</div>
                    <div class="qv-value">{{ $display($information['issued_by_name'] ?? null) }}</div>
                </div>
                <div class="qv-field">
                    <div class="qv-label">Fax</div>
                    <div class="qv-value">{{ $display($information['customer_fax'] ?? null) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Consignor / Pickup --}}
    <div class="qv-card">
        <div class="qv-card-title qv-card-title-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M3 9h18M3 13.5h18M3 18h18" />
            </svg>
            Consignor / Pickup
        </div>
        <div class="qv-split">
            <div class="qv-field-stack">
                <div class="qv-field">
                    <div class="qv-label">Consignor</div>
                    <div class="qv-value">
                        @if (! empty($consignor['consignor_url']))
                            <a href="{{ $consignor['consignor_url'] }}" class="qv-link" wire:navigate>{{ $display($consignor['consignor'] ?? null) }}</a>
                        @else
                            {{ $display($consignor['consignor'] ?? null) }}
                        @endif
                    </div>
                </div>
                <div class="qv-field">
                    <div class="qv-label">FROM (Region/City)</div>
                    <div class="qv-value">{{ $display($consignor['from'] ?? null) }}</div>
                </div>
                <div class="qv-field">
                    <div class="qv-label">Company Number</div>
                    <div class="qv-value">{{ $display($consignor['company_number'] ?? null) }}</div>
                </div>
                <div class="qv-field">
                    <div class="qv-label">Billing Address</div>
                    <div class="qv-value qv-value-multiline">{{ $display($consignor['billing_address'] ?? null) }}</div>
                </div>
            </div>
            <div class="qv-field-stack">
                <div class="qv-field">
                    <div class="qv-label">Pickup Location</div>
                    <div class="qv-value">{{ $display($consignor['pickup_location'] ?? null) }}</div>
                </div>
                <div class="qv-field">
                    <div class="qv-label">Pickup Location Detail</div>
                    <div class="qv-value qv-value-multiline">{{ $display($consignor['pickup_location_detail'] ?? null) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pricing Reference --}}
    <div class="qv-card">
        <div class="qv-card-title qv-card-title-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
            </svg>
            Pricing Reference
        </div>

        <div class="qv-pricing-block">
            @include('filament.forms.quotation-history-other', ['rows' => $pricing['history'] ?? []])
        </div>

        <div class="qv-grid-2 qv-pricing-grid">
            <div class="qv-pricing-block">
                @include('filament.forms.quotation-history-special', ['rows' => $pricing['special'] ?? []])
            </div>
            <div class="qv-pricing-block">
                @include('filament.forms.quotation-history-master', [
                    'destinations' => $pricing['master']['destinations'] ?? [],
                    'rows' => $pricing['master']['rows'] ?? [],
                ])
            </div>
        </div>
    </div>

    {{-- Quotation Details --}}
    <div class="qv-card">
        <div class="qv-card-title">Quotation Details</div>

        <div class="qv-section">
            <div class="qv-label">Destinations</div>
            @if (($details['destinations'] ?? []) !== [])
                <div class="qv-tags">
                    @foreach ($details['destinations'] as $destination)
                        <span class="qv-tag">{{ $destination }}</span>
                    @endforeach
                </div>
            @else
                <div class="qv-empty-state">No destinations added.</div>
            @endif
        </div>

        <div class="qv-section">
            <div class="qv-label">Transport Charges</div>
            @if ($rows !== [])
                <div class="qv-table-wrap">
                    <table class="qv-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="qv-text-right">Qty</th>
                                @foreach ($destinations as $destination)
                                    <th class="qv-text-right">{{ $destination }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($details['rows'] ?? [] as $row)
                                <tr>
                                    <td>
                                        <div class="qv-table-item">{{ $row['item_name'] ?? '—' }}</div>
                                        @if (($row['line_type'] ?? '') === 'uom')
                                            <div class="qv-table-sub">UOM · tier pricing</div>
                                        @endif
                                    </td>
                                    <td class="qv-text-right">{{ number_format((float) ($row['quantity'] ?? 1), 0) }}</td>
                                    @foreach ($destinations as $destination)
                                        @php $unit = $row['prices'][$destination] ?? null; @endphp
                                        <td class="qv-text-right">
                                            @if ($unit !== null && $unit !== '')
                                                @if (($row['line_type'] ?? '') === 'uom' && (float) ($row['quantity'] ?? 1) !== 1.0)
                                                    RM {{ number_format((float) $unit * (float) $row['quantity'], 2) }}
                                                    <div class="qv-table-sub">{{ number_format((float) $row['quantity'], 0) }} × RM {{ number_format((float) $unit, 2) }}</div>
                                                @else
                                                    RM {{ number_format((float) $unit, 2) }}
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="qv-empty-state">No transport charges added.</div>
            @endif
        </div>

        <div class="qv-total-bar">
            <span class="qv-total-label">Total Quotation Amount</span>
            <span class="qv-total-value">RM {{ number_format((float) ($details['total_amount'] ?? 0), 2) }}</span>
        </div>

        <div class="qv-section qv-section-last">
            <div class="qv-label">Footnotes</div>
            <div class="qv-value qv-value-multiline">{{ $display($details['footnotes'] ?? null) }}</div>
        </div>
    </div>
</div>
