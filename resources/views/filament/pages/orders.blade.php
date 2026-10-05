@php
    $data = $this->getOrders();
    $rows = $data['rows'];
    $cards = $this->getCards($data['summary']);
    $detail = $this->getSelectedDetail();
    $steps = $this->steps();
@endphp

<x-filament-panels::page class="fi-page-portal-enquiries fi-page-order-operations cor-page ops-page">
    <div
        class="ops-section"
        x-data
        x-on:ops-scroll-to-detail.window="$nextTick(() => document.getElementById('ops-detail')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
    >
        <div class="ops-section-head">
            <div>
                <h2 class="ops-section-title">Order management</h2>
                <p class="ops-section-sub">Every customer order from first enquiry to consignment note. Click an enquiry to review it; open an order to continue its next step.</p>
            </div>
            <a href="{{ $this->getCreateOrderUrl() }}" class="ops-btn ops-btn-primary">
                <span aria-hidden="true">+</span> Create order for customer
            </a>
        </div>

        <div class="ops-cards">
            @foreach ($cards as $card)
                <button
                    type="button"
                    wire:key="ops-card-{{ $card['key'] }}"
                    wire:click="selectCard('{{ $card['stage'] }}')"
                    @class(['ops-card', 'ops-card-active' => $this->isCardActive($card['stage'])])
                >
                    <span class="ops-card-label">{{ $card['label'] }}</span>
                    <span class="ops-card-value">{{ $card['count'] }}</span>
                    <span class="ops-card-hint">{{ $card['hint'] }}</span>
                </button>
            @endforeach
        </div>

        <div class="ops-legend">
            @foreach ($steps as $i => $step)
                <span class="ops-legend-item ops-legend-{{ $i < 2 ? 'gray' : ($i < 5 ? 'blue' : 'approved') }}">{{ $i + 1 }} · {{ $step }}</span>
            @endforeach
        </div>

        <div class="cor-toolbar ops-toolbar">
            <div class="cor-toolbar-filters">
                <div class="cor-toolbar-field cor-toolbar-search">
                    <label class="cor-toolbar-label" for="ordFilterSearch">Search</label>
                    <input id="ordFilterSearch" type="search" wire:model.live.debounce.400ms="filterSearch" class="cor-toolbar-input" placeholder="Order, enquiry, customer or DO number…" />
                </div>
                <div class="cor-toolbar-field">
                    <label class="cor-toolbar-label" for="ordFilterStage">Stage</label>
                    <select id="ordFilterStage" wire:model.live="filterStatus" class="cor-toolbar-input">
                        @foreach ($this->stageOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cor-toolbar-field">
                    <label class="cor-toolbar-label" for="ordFilterType">Order type</label>
                    <select id="ordFilterType" wire:model.live="filterOrderType" class="cor-toolbar-input">
                        @foreach ($this->orderTypeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cor-toolbar-field">
                    <label class="cor-toolbar-label" for="ordFilterBilling">Payment</label>
                    <select id="ordFilterBilling" wire:model.live="filterBilling" class="cor-toolbar-input">
                        @foreach ($this->billingOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cor-toolbar-field">
                    <label class="cor-toolbar-label" for="ordFilterSales">Salesperson</label>
                    <select id="ordFilterSales" wire:model.live="filterSalesperson" class="cor-toolbar-input">
                        @foreach ($this->salespersonFilterOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cor-toolbar-field">
                    <label class="cor-toolbar-label" for="ordFilterSa">SA location</label>
                    <select id="ordFilterSa" wire:model.live="filterSaLocation" class="cor-toolbar-input">
                        @foreach ($this->saLocationOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cor-toolbar-field">
                    <label class="cor-toolbar-label" for="ordFilterDateFrom">Created</label>
                    <div class="cor-date-range">
                        <input id="ordFilterDateFrom" type="date" wire:model.live="filterDateFrom" class="cor-toolbar-input" />
                        <span class="cor-date-sep">–</span>
                        <input id="ordFilterDateTo" type="date" wire:model.live="filterDateTo" class="cor-toolbar-input" />
                    </div>
                </div>
            </div>
            <div class="cor-toolbar-actions">
                <button type="button" wire:click="resetFilters" class="cor-btn cor-btn-outline">Reset</button>
            </div>
        </div>

        <div class="ops-panel">
            <div class="cor-table-wrap">
                <table class="cor-table ops-table">
                    <thead>
                        <tr>
                            <th>Order / Customer</th>
                            <th>Route / Service</th>
                            <th>Order stage</th>
                            <th>Payment</th>
                            <th class="ops-th-right">Amount</th>
                            <th>Next step</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr
                                wire:key="ord-row-{{ $row['kind'] }}-{{ $row['id'] }}"
                                @if ($row['enquiry_id'])
                                    wire:click="openDetail({{ $row['enquiry_id'] }})"
                                @elseif (! empty($row['view_url']))
                                    x-on:click="if (! $event.target.closest('a')) window.location.href = '{{ $row['view_url'] }}'"
                                @endif
                                @class([
                                    'cor-row',
                                    'ops-row',
                                    'ops-row-'.$row['stage_color'],
                                    'cor-row-selected' => $row['enquiry_id'] && $selectedEnquiryId === $row['enquiry_id'],
                                ])
                            >
                                <td>
                                    <div class="ops-primary cor-mono">{{ $row['reference'] }}</div>
                                    <div class="ops-secondary">{{ $row['customer'] }}</div>
                                    <div class="ops-tertiary">{{ $row['meta'] }}</div>
                                </td>
                                <td>
                                    <div class="ops-secondary ops-route">{{ $row['route'] }}</div>
                                    <div class="ops-tertiary">
                                        {{ $row['items_summary'] }}
                                        @if ($row['order_type']) · {{ $row['order_type'] }} @endif
                                        @if ($row['service_type']) · {{ $row['service_type'] }} @endif
                                    </div>
                                </td>
                                <td>
                                    <span @class(['cor-status-pill', 'cor-status-'.$row['stage_color']])>{{ $row['step'] }} · {{ $row['stage_label'] }}</span>
                                    <div class="ops-tertiary">{{ $row['stage_hint'] }}</div>
                                </td>
                                <td>
                                    <div class="ops-secondary">{{ $row['payment_label'] }}</div>
                                    <div class="ops-tertiary">{{ $row['payment_hint'] }}</div>
                                </td>
                                <td class="ops-td-right">
                                    <div @class(['ops-amount', 'ops-amount-muted' => $row['amount_muted']])>{{ $row['amount'] }}</div>
                                </td>
                                <td>
                                    @if ($row['next_url'])
                                        <a href="{{ $row['next_url'] }}" class="ops-next">{{ $row['next_step'] }} →</a>
                                    @else
                                        <span class="ops-next">{{ $row['next_step'] }} →</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="cor-empty">No orders match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ops-panel-foot">
                <span>{{ $data['count'] }} {{ \Illuminate\Support\Str::plural('record', $data['count']) }}</span>
                <span>{{ $this->getDateRangeLabel() }}</span>
            </div>
        </div>

        @if ($detail)
            <div class="cor-detail-panel ops-detail" id="ops-detail" wire:key="ops-detail-{{ $detail['id'] }}" wire:poll.2s="heartbeat">
                <div class="ops-detail-bar">
                    <span class="ops-detail-crumb">{{ $detail['quotation_number'] ? 'Order '.$detail['quotation_number'] : 'Enquiry review' }} · {{ $detail['customer'] }}</span>
                    <button type="button" wire:click="closeDetail" class="cor-btn cor-btn-outline ops-btn-sm">Close</button>
                </div>
                @include('filament.pages.partials.portal-enquiry-detail', ['detail' => $detail, 'showRejectForm' => $showRejectForm])
            </div>
        @endif
    </div>
</x-filament-panels::page>
