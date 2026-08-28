@php
    $overview = $overview ?? [];
    $consignor = $consignor ?? [];
    $consignee = $consignee ?? [];
    $charges = $charges ?? [];
    $rates = $rates ?? ['destinations' => [], 'rows' => []];
    $document = $document ?? [];
    $subsheets = $subsheets ?? [];
    $task = $task ?? [];

    $display = fn ($value): string => filled($value) ? (string) $value : '—';
    $money = fn ($value): string => 'RM '.number_format((float) ($value ?? 0), 2);
    $yesNo = fn ($value): string => ($value ?? false) ? 'Yes' : 'No';
@endphp

<div class="csn-view space-y-4 text-sm">
    {{-- CSN Overview — dense 4-column grid --}}
    <div class="csn-card csn-card-compact">
        <div class="csn-card-title">CSN Overview</div>
        <div class="csn-grid-4">
            <div class="csn-field"><div class="csn-label">Quotation</div><div class="csn-value">{{ $display($overview['quotation'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">Destination</div><div class="csn-value">{{ $display($overview['destination'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">CSN No.</div><div class="csn-value font-semibold">{{ $display($overview['number'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">CSN Date</div><div class="csn-value">{{ $display($overview['issued_at'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">D/O No.</div><div class="csn-value">{{ $display($overview['do_number'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">Job No.</div><div class="csn-value">{{ $display($overview['job_no'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">Reference No.</div><div class="csn-value">{{ $display($overview['customer_reference'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">Job date</div><div class="csn-value">{{ $display($overview['job_date'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">From area</div><div class="csn-value">{{ $display($overview['from_area'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">To area</div><div class="csn-value">{{ $display($overview['to_area'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">Customer</div><div class="csn-value">{{ $display($overview['customer'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">Telephone No.</div><div class="csn-value">{{ $display($overview['customer_phone'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">Tax code</div><div class="csn-value">{{ $display($overview['tax_code'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">Customer Name</div><div class="csn-value">{{ $display($overview['customer_name'] ?? null) }}</div></div>
            <div class="csn-field"><div class="csn-label">Tax description</div><div class="csn-value">{{ $display($overview['tax_description'] ?? null) }}</div></div>
            <div class="csn-field csn-span-2"><div class="csn-label">Customer address</div><div class="csn-value whitespace-pre-line">{{ $display($overview['customer_address'] ?? null) }}</div></div>
            <div class="csn-field csn-span-2"><div class="csn-label">Remark</div><div class="csn-value whitespace-pre-line">{{ $display($overview['remarks'] ?? null) }}</div></div>
        </div>
    </div>

    {{-- Consignor | Consignee --}}
    <div class="csn-grid-2 csn-grid-pair">
        <div class="csn-card csn-card-compact">
            <div class="csn-card-title">Consignor</div>
            <div class="csn-field-stack">
                <div class="csn-field"><div class="csn-label">Consignor</div><div class="csn-value">{{ $display($consignor['name'] ?? null) }}</div></div>
                <div class="csn-field"><div class="csn-label">Consignor address</div><div class="csn-value whitespace-pre-line">{{ $display($consignor['address'] ?? null) }}</div></div>
                <div class="csn-field"><div class="csn-label">Consignor telephone</div><div class="csn-value">{{ $display($consignor['phone'] ?? null) }}</div></div>
            </div>
        </div>
        <div class="csn-card csn-card-compact">
            <div class="csn-card-title">Consignee</div>
            <div class="csn-field-stack">
                <div class="csn-field"><div class="csn-label">Consignee</div><div class="csn-value">{{ $display($consignee['name'] ?? null) }}</div></div>
                <div class="csn-field"><div class="csn-label">Attention / PIC</div><div class="csn-value">{{ $display($consignee['pic'] ?? null) }}</div></div>
                <div class="csn-field"><div class="csn-label">Consignee address</div><div class="csn-value whitespace-pre-line">{{ $display($consignee['address'] ?? null) }}</div></div>
                <div class="csn-field"><div class="csn-label">Consignee telephone</div><div class="csn-value">{{ $display($consignee['phone'] ?? null) }}</div></div>
                <div class="csn-grid-3">
                    <div class="csn-field"><div class="csn-label">Postcode</div><div class="csn-value">{{ $display($consignee['postcode'] ?? null) }}</div></div>
                    <div class="csn-field"><div class="csn-label">City</div><div class="csn-value">{{ $display($consignee['city'] ?? null) }}</div></div>
                    <div class="csn-field"><div class="csn-label">State</div><div class="csn-value">{{ $display($consignee['state'] ?? null) }}</div></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Charges & Billing — 2 columns inside --}}
    <div class="csn-card csn-card-compact">
        <div class="csn-card-title">Charges & Billing</div>
        <div class="csn-grid-2 csn-grid-pair">
            <div class="csn-field-stack csn-charges-stack">
                <div class="csn-field"><div class="csn-label">Transport charges</div><div class="csn-value">{{ $money($charges['transport_charges'] ?? 0) }}</div></div>
                <div class="csn-field"><div class="csn-label">Discount</div><div class="csn-value">{{ $money($charges['discount'] ?? 0) }}</div></div>
                <div class="csn-field"><div class="csn-label">Sub total</div><div class="csn-value">{{ $money($charges['subtotal'] ?? 0) }}</div></div>
                <div class="csn-field"><div class="csn-label">A/C amount</div><div class="csn-value font-semibold">{{ $money($charges['total_amount'] ?? 0) }}</div></div>
                <div class="csn-field"><div class="csn-label">Tax rate (%)</div><div class="csn-value">{{ filled($charges['tax_rate'] ?? null) ? number_format((float) $charges['tax_rate'], 0).'%' : '—' }}</div></div>
                <div class="csn-field"><div class="csn-label">Tax</div><div class="csn-value">{{ $money($charges['tax_amount'] ?? 0) }}</div></div>
                <div class="csn-field"><div class="csn-label">Advance taken</div><div class="csn-value">{{ $yesNo($charges['advance_taken'] ?? false) }}</div></div>
                <div class="csn-field"><div class="csn-label">Issue invoice</div><div class="csn-value">{{ $yesNo($charges['issue_invoice'] ?? false) }}</div></div>
            </div>
            <div class="csn-field-stack">
                <div class="csn-field">
                    <div class="csn-label">Other D/O No.</div>
                    <div class="csn-value">{{ ($charges['other_do_numbers'] ?? []) !== [] ? implode(', ', $charges['other_do_numbers']) : '—' }}</div>
                </div>
                <div class="csn-field"><div class="csn-label">Marking</div><div class="csn-value">{{ $display($charges['marking'] ?? null) }}</div></div>
                <div class="csn-divider"></div>
                <div class="csn-subsection-title">Transport Charges</div>
                <div class="csn-field"><div class="csn-label">Destinations</div><div class="csn-value">{{ ($charges['destinations'] ?? []) !== [] ? implode(', ', $charges['destinations']) : '—' }}</div></div>
                <div class="csn-field"><div class="csn-label">Bill to destination</div><div class="csn-value">{{ $display($charges['charge_column'] ?? null) }}</div></div>
                <div class="csn-field"><div class="csn-label">Additional pickup task</div><div class="csn-value">{{ ($charges['has_additional_task'] ?? false) ? 'Yes' : 'No' }}</div></div>
            </div>
        </div>
    </div>

    {{-- Rates | Document Preview | Subsheets --}}
    <div class="csn-grid-3 csn-grid-equal">
        <div class="csn-card csn-card-compact">
            <div class="csn-card-title">Rates</div>
            <div class="csn-card-body">
                @include('filament.forms.consignment-transport-preview', [
                    'rateMatrix' => $rates,
                    'chargeColumn' => $charge_column ?? null,
                ])
            </div>
        </div>
        <div class="csn-card csn-card-compact">
            <div class="csn-card-title">Document Preview</div>
            <div class="csn-card-body">
                @include('filament.forms.consignment-note-preview', ['document' => $document])
            </div>
        </div>
        <div class="csn-card csn-card-compact">
            <div class="csn-card-title">Subsheets</div>
            <div class="csn-card-body">
                @if ($subsheets === [])
                    <div class="csn-empty-state csn-fill">No subsheets</div>
                @else
                    <div class="csn-fill space-y-2">
                        @foreach ($subsheets as $subsheet)
                            <div class="csn-mini-card">
                                <div class="font-medium">{{ $subsheet['number'] }}</div>
                                <div class="text-xs text-gray-500">DO: {{ $display($subsheet['do_number'] ?? null) }}</div>
                                <div class="text-xs">{{ $display($subsheet['sub_driver'] ?? null) }} · {{ $display($subsheet['sub_lorry'] ?? null) }}</div>
                                @if ($subsheet['segment_route'] ?? null)
                                    <div class="text-xs text-gray-600">{{ $subsheet['segment_route'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Consignment Task --}}
    <div class="csn-task-header">
        <span class="csn-task-badge">{{ $task['number'] ?? '—' }}</span>
        <span class="csn-task-title">Consignment Task</span>
    </div>

    {{-- Overview | Traceability — equal 50/50 --}}
    <div class="csn-grid-2 csn-grid-pair">
        <div class="csn-card csn-card-compact">
            <div class="csn-card-title">Consignment Overview</div>
            <div class="csn-field-stack">
                <div class="csn-field"><div class="csn-label">Consignment number</div><div class="csn-value">{{ $display($task['number'] ?? null) }}</div></div>
                <div class="csn-field"><div class="csn-label">Created date</div><div class="csn-value">{{ $display($task['created_date'] ?? null) }}</div></div>
                <div class="csn-field"><div class="csn-label">Transfer code</div><div class="csn-value">{{ $display($task['transfer_code'] ?? null) }}</div></div>
                <div class="csn-field"><div class="csn-label">Transfer branch</div><div class="csn-value">{{ $display($task['transfer_branch'] ?? null) }}</div></div>
                <div class="csn-field"><div class="csn-label">Delivery segments</div><div class="csn-value">{{ $task['segment_count'] ?? 0 }}</div></div>
                <div class="csn-field"><div class="csn-label">Main driver</div><div class="csn-value">{{ $display($task['main_driver'] ?? null) }}</div></div>
                <div class="csn-field"><div class="csn-label">Sub driver</div><div class="csn-value">{{ $display($task['sub_driver'] ?? null) }}</div></div>
            </div>
        </div>
        <div class="csn-card csn-card-compact csn-trace-card">
            <div class="csn-trace-header">
                <div class="csn-trace-header-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25M9 16.5v.75m6-6v.75m-3-9v.75" />
                    </svg>
                </div>
                <div>
                    <div class="csn-trace-header-title">Source Record Traceability</div>
                    <div class="csn-trace-header-desc">{{ $task['traceability_description'] ?? 'This record remains linked to related source documents.' }}</div>
                </div>
            </div>
            <div class="csn-trace-timeline">
                @foreach ($task['traceability'] ?? [] as $item)
                    <div @class(['csn-trace-step', 'csn-trace-step-active' => $item['active'] ?? false])>
                        <div class="csn-trace-rail">
                            <div class="csn-trace-bubble" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            </div>
                        </div>
                        <div class="csn-trace-content">
                            <div class="csn-trace-label">{{ $item['label'] }}</div>
                            <div class="csn-trace-value">
                                @if (! empty($item['url']))
                                    <a href="{{ $item['url'] }}" class="csn-trace-link" wire:navigate>{{ $item['value'] }}</a>
                                @else
                                    {{ $item['value'] }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Delivery Segment --}}
    <div class="csn-card csn-card-compact csn-segment-card">
        <div class="csn-segment-header">
            <div class="csn-segment-header-left">
                <div class="csn-segment-header-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25M9 16.5v.75m6-6v.75m-3-9v.75" />
                    </svg>
                </div>
                <div class="csn-segment-header-title">Delivery Segment</div>
            </div>
            @if (collect($task['segment_actions'] ?? [])->contains(fn ($action) => $action['visible'] ?? false))
                <div class="csn-segment-header-actions">
                    @foreach ($task['segment_actions'] ?? [] as $action)
                        @if ($action['visible'] ?? false)
                            <button
                                type="button"
                                wire:click="mountAction('{{ $action['action'] }}')"
                                class="csn-segment-btn"
                            >
                                {{ $action['label'] }}
                            </button>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        @if (($task['segments'] ?? []) === [])
            <div class="csn-empty-state">No delivery segments yet. Assign a lorry to create the main segment.</div>
        @else
            <div class="csn-segment-list">
                @foreach ($task['segments'] as $segment)
                    <div @class(['csn-segment-row', 'csn-segment-row-active' => $segment['active'] ?? false])>
                        <div @class(['csn-segment-index', 'csn-segment-index-active' => $segment['active'] ?? false])>
                            {{ $segment['index'] }}
                        </div>
                        <div class="csn-segment-body">
                            @if (! empty($segment['url']))
                                <a href="{{ $segment['url'] }}" class="csn-segment-link" wire:navigate>
                            @endif
                            <div class="csn-segment-title">{{ $segment['label'] }}</div>
                            <div class="csn-segment-meta">
                                {{ $display($segment['driver'] ?? null) }} · {{ $display($segment['lorry'] ?? null) }}
                            </div>
                            @if (! empty($segment['url']))
                                </a>
                            @endif
                        </div>
                        <div class="csn-segment-route">{{ $segment['route'] }}</div>
                        <button type="button" class="csn-segment-delete" disabled aria-label="Remove segment" title="Remove segment">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM6.75 9.25a.75.75 0 0 0 0 1.5h6.5a.75.75 0 0 0 0-1.5h-6.5Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Segment Commission --}}
    <div class="csn-card csn-card-compact">
        <div class="csn-card-title">Segment Commission Allocation</div>
        @if (($task['commissions'] ?? []) === [])
            <div class="csn-empty-state">No commission data available.</div>
        @else
            <div class="overflow-x-auto">
                <table class="csn-table">
                    <thead>
                        <tr>
                            <th>Delivery Segment</th>
                            <th>Driver</th>
                            <th>Lorry</th>
                            <th>Segment Route</th>
                            <th class="text-right">Commission %</th>
                            <th class="text-right">Configured Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($task['commissions'] as $row)
                            <tr>
                                <td>{{ $row['segment'] }}</td>
                                <td>{{ $row['driver'] }}</td>
                                <td>{{ $row['lorry'] }}</td>
                                <td>{{ $row['route'] }}</td>
                                <td class="text-right">{{ filled($row['commission_pct'] ?? null) ? $row['commission_pct'].'%' : '—' }}</td>
                                <td class="text-right whitespace-nowrap">MYR {{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
