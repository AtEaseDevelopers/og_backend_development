@php
    $data = $this->getListingData();
    $rows = $data['rows'] ?? [];
    $pendingCount = $data['pending_count'] ?? 0;
    $detail = $this->getSelectedDetail();
@endphp

<x-filament-panels::page class="fi-page-portal-enquiries cor-page">
    <div class="cor-toolbar">
        <div class="cor-toolbar-filters">
            <div class="cor-toolbar-field">
                <label class="cor-toolbar-label" for="filterDateFrom">Date range</label>
                <div class="cor-date-range">
                    <input id="filterDateFrom" type="date" wire:model.live="filterDateFrom" class="cor-toolbar-input" />
                    <span class="cor-date-sep">–</span>
                    <input id="filterDateTo" type="date" wire:model.live="filterDateTo" class="cor-toolbar-input" />
                </div>
            </div>
            <div class="cor-toolbar-field">
                <label class="cor-toolbar-label" for="filterStatus">Status</label>
                <select id="filterStatus" wire:model.live="filterStatus" class="cor-toolbar-input">
                    @foreach ($this->statusFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cor-toolbar-field cor-toolbar-search">
                <label class="cor-toolbar-label" for="filterSearch">Search</label>
                <input
                    id="filterSearch"
                    type="search"
                    wire:model.live.debounce.400ms="filterSearch"
                    class="cor-toolbar-input"
                    placeholder="Order ID, customer, destination..."
                />
            </div>
        </div>
        <div class="cor-toolbar-actions">
            <button type="button" wire:click="exportOrders" class="cor-btn cor-btn-outline">
                Export
            </button>
        </div>
    </div>

    <div class="cor-layout">
        <div class="cor-queue-panel">
            <div class="cor-queue-head">
                <div>
                    <h2 class="cor-panel-title">Order Queue</h2>
                    <p class="cor-panel-sub">{{ $pendingCount }} pending reviews</p>
                </div>
            </div>
            <div class="cor-table-wrap">
                <table class="cor-table">
                    <thead>
                        <tr>
                            <th>Portal Order ID</th>
                            <th>Customer</th>
                            <th>Destination</th>
                            <th>Submitted Date</th>
                            <th>Review Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr
                                wire:key="cor-row-{{ $row['id'] }}"
                                wire:click="openDetail({{ $row['id'] }})"
                                @class(['cor-row', 'cor-row-selected' => $selectedEnquiryId === $row['id']])
                            >
                                <td class="cor-mono">{{ $row['reference_no'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td class="cor-destination">{{ $row['destination'] }}</td>
                                <td>{{ $row['submitted_at'] }}</td>
                                <td>
                                    <span @class(['cor-status-pill', 'cor-status-'.$row['status_color']])>
                                        {{ $row['status_label'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="cor-empty">
                                    No portal orders found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cor-detail-panel">
            @if($detail)
                <div class="cor-order-head">
                    <div>
                        <div class="cor-order-head-top">
                            <h2 class="cor-order-id">{{ $detail['reference_no'] }}</h2>
                            <span @class(['cor-status-pill', 'cor-status-'.$detail['status_color']])>
                                {{ $detail['status_label'] }}
                            </span>
                        </div>
                        <p class="cor-order-meta">
                            <span class="cor-branch-tag">Branch: {{ $detail['branch_name'] }}</span>
                            · {{ $detail['customer'] }}
                            · Submitted {{ $detail['submitted_at'] }}
                        </p>
                    </div>
                    @if($detail['quotation_url'])
                        <a href="{{ $detail['quotation_url'] }}" wire:navigate class="cor-external-link" title="View quotation">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                    @endif
                </div>

                <div class="cor-pickup-delivery">
                    <section class="cor-info-card">
                        <h3 class="cor-section-title">Pickup Information</h3>
                        <div class="cor-info-block">
                            <div class="cor-info-label">Location</div>
                            <div class="cor-info-value">{!! nl2br(e($detail['pickup_address'])) !!}</div>
                        </div>
                        <div class="cor-info-block">
                            <div class="cor-info-label">Requested pickup</div>
                            <div class="cor-info-value">{{ $detail['pickup_datetime'] }}</div>
                        </div>
                        <div class="cor-info-block">
                            <div class="cor-info-label">PIC</div>
                            <div class="cor-info-value">{{ $detail['pickup_contact'] }}</div>
                        </div>
                        @if(filled($detail['pickup_maps_url']))
                            <a href="{{ $detail['pickup_maps_url'] }}" target="_blank" rel="noopener" class="cor-link">Open in Google Maps</a>
                        @endif
                    </section>

                    <section class="cor-info-card">
                        <h3 class="cor-section-title">Delivery Information</h3>
                        <div class="cor-info-block">
                            <div class="cor-info-label">Destination</div>
                            <div class="cor-info-value">{!! nl2br(e($detail['delivery_address'])) !!}</div>
                        </div>
                        <div class="cor-info-block">
                            <div class="cor-info-label">Requested delivery</div>
                            <div class="cor-info-value">{{ $detail['delivery_datetime'] }}</div>
                        </div>
                        <div class="cor-info-block">
                            <div class="cor-info-label">PIC</div>
                            <div class="cor-info-value">{{ $detail['delivery_contact'] }}</div>
                        </div>
                    </section>
                </div>

                <section class="cor-items-card">
                    <h3 class="cor-section-title">Item Details</h3>
                    <div class="cor-table-wrap">
                        <table class="cor-items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Weight</th>
                                    <th>Dimensions</th>
                                    <th>Special Request</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($detail['items'] as $item)
                                    <tr wire:key="cor-item-{{ $detail['id'] }}-{{ $item['index'] }}">
                                        <td>
                                            <div class="cor-item-name">{{ $item['item_name'] }}</div>
                                            <div class="cor-item-packaging">{{ $item['packaging'] }}</div>
                                        </td>
                                        <td>{{ $item['quantity'] }}</td>
                                        <td>{{ $item['weight'] }}</td>
                                        <td>{{ $item['dimensions'] }}</td>
                                        <td>
                                            @if(filled($item['special_request']))
                                                <span class="cor-special-request">{{ $item['special_request'] }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="cor-items-total">Total Est. Weight: {{ $detail['total_weight_kg'] }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <div class="cor-bottom-cards">
                    <section class="cor-mini-card">
                        <h3 class="cor-section-title">Applicable Pricing</h3>
                        <div class="cor-pricing-ref">{{ $detail['pricing']['contract_ref'] }}</div>
                        <div class="cor-pricing-amount">{{ $detail['pricing']['amount'] }}</div>
                        <span @class([
                            'cor-verify-badge',
                            'cor-verify-badge-success' => $detail['pricing']['verified'],
                            'cor-verify-badge-pending' => ! $detail['pricing']['verified'],
                        ])>
                            {{ $detail['pricing']['verified_label'] }}
                        </span>
                        <p class="cor-mini-note">{{ $detail['pricing']['note'] }}</p>
                        @if($detail['quotation_url'])
                            <a href="{{ $detail['quotation_url'] }}" wire:navigate class="cor-link">View Saved Pricing</a>
                        @endif
                    </section>

                    <section class="cor-mini-card">
                        <h3 class="cor-section-title">Payment Slip Uploaded</h3>
                        @if($detail['payment']['uploaded'])
                            <div class="cor-payment-thumb">Payment slip</div>
                        @else
                            <div class="cor-payment-empty">No payment slip uploaded</div>
                        @endif
                        <div class="cor-payment-meta">
                            <div><span>Amount</span> {{ $detail['payment']['amount'] }}</div>
                            @if(filled($detail['payment']['reference']))
                                <div><span>Reference</span> {{ $detail['payment']['reference'] }}</div>
                            @endif
                            @if(filled($detail['payment']['date']))
                                <div><span>Date</span> {{ $detail['payment']['date'] }}</div>
                            @endif
                        </div>
                    </section>

                    <section class="cor-mini-card cor-mini-card-wide">
                        <h3 class="cor-section-title">Traceability &amp; Notification Status</h3>
                        <div class="cor-trace-flow">
                            @foreach($detail['traceability'] as $step)
                                <div
                                    wire:key="cor-trace-{{ $detail['id'] }}-{{ $step['key'] }}"
                                    @class([
                                        'cor-trace-step',
                                        'cor-trace-step-active' => $step['active'],
                                        'cor-trace-step-completed' => $step['completed'],
                                    ])
                                >
                                    {{ $step['label'] }}
                                </div>
                            @endforeach
                        </div>
                        <ul class="cor-notification-list">
                            @foreach($detail['notifications'] as $notification)
                                <li wire:key="cor-notif-{{ $detail['id'] }}-{{ $notification['label'] }}">
                                    <span>{{ $notification['label'] }}</span>
                                    <strong>{{ $notification['status'] }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                </div>

                @if(filled($detail['rejection_reason']))
                    <div class="cor-rejection-banner">
                        Rejection reason: {{ $detail['rejection_reason'] }}
                    </div>
                @endif

                <div class="cor-footer-actions">
                    @if($detail['can_reject'])
                        @if($showRejectForm)
                            <div class="cor-reject-form">
                                <textarea
                                    wire:model="rejectReason"
                                    rows="2"
                                    class="cor-reject-input"
                                    placeholder="Enter rejection reason..."
                                ></textarea>
                                <div class="cor-reject-form-actions">
                                    <button type="button" wire:click="toggleRejectForm" class="cor-btn cor-btn-outline">Cancel</button>
                                    <button type="button" wire:click="rejectEnquiry({{ $detail['id'] }})" class="cor-btn cor-btn-danger-solid">Confirm Reject</button>
                                </div>
                            </div>
                        @else
                            <button type="button" wire:click="toggleRejectForm" class="cor-btn cor-btn-danger-outline">Reject Order</button>
                        @endif
                    @endif

                    @if($detail['can_create_quotation'])
                        <button type="button" wire:click="createQuotation({{ $detail['id'] }})" class="cor-btn cor-btn-secondary-solid">
                            Generate Quotation
                        </button>
                    @elseif($detail['quotation_url'])
                        <a href="{{ $detail['quotation_url'] }}" wire:navigate class="cor-btn cor-btn-secondary-solid">
                            View Quotation
                        </a>
                    @endif

                    @if($detail['can_approve'])
                        <button type="button" wire:click="approveOrder({{ $detail['id'] }})" class="cor-btn cor-btn-success">
                            Approve Order
                        </button>
                    @endif
                </div>
            @else
                <div class="cor-detail-empty">
                    <h2 class="cor-panel-title">Order Details</h2>
                    <p>Select an order from the queue to review pickup, delivery, and pricing details.</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
