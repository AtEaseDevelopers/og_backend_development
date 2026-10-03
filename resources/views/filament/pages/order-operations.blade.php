@php
    $isOrders = $this->isOrdersTab();

    if ($isOrders) {
        $data = $this->getListingData();
        $rows = $data['rows'] ?? [];
        $detail = $this->getSelectedDetail();
        $cards = $this->getOrderCards();
        $ordersCount = $data['count'] ?? 0;
        $csnCount = $this->getCsnTotal();
    } else {
        $scopes = $this->getCsnScopes();
        $scopeMap = collect($scopes)->keyBy('key');
        $csnCount = $scopeMap['all']['count'] ?? 0;
        $ordersCount = $this->getOrderSummary()['needs_attention'] ?? 0;
    }
@endphp

<x-filament-panels::page class="fi-page-portal-enquiries fi-page-order-operations cor-page ops-page">
    {{-- Tab switcher ------------------------------------------------------ --}}
    <div class="ops-tabs" role="tablist" aria-label="Order management sections">
        <a
            href="{{ $this->getTabUrl('orders') }}"
            role="tab"
            aria-selected="{{ $isOrders ? 'true' : 'false' }}"
            @class(['ops-tab', 'ops-tab-active' => $isOrders])
        >
            <span class="ops-tab-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7.5 3.75h9A2.25 2.25 0 0 1 18.75 6v12a2.25 2.25 0 0 1-2.25 2.25h-9A2.25 2.25 0 0 1 5.25 18V6A2.25 2.25 0 0 1 7.5 3.75Z"/></svg>
            </span>
            <span class="ops-tab-text">
                <span class="ops-tab-label">Customer Order Review</span>
                <span class="ops-tab-sub">Enquiry → Quotation → Confirmation</span>
            </span>
            <span class="ops-tab-count">{{ $ordersCount }}</span>
        </a>

        <a
            href="{{ $this->getTabUrl('csn') }}"
            role="tab"
            aria-selected="{{ $isOrders ? 'false' : 'true' }}"
            @class(['ops-tab', 'ops-tab-active' => ! $isOrders])
        >
            <span class="ops-tab-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375A1.125 1.125 0 0 1 2.25 17.625V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
            </span>
            <span class="ops-tab-text">
                <span class="ops-tab-label">Consignment Notes</span>
                <span class="ops-tab-sub">CSN → Lorry → Delivery</span>
            </span>
            <span class="ops-tab-count">{{ $csnCount }}</span>
        </a>
    </div>

    @if ($isOrders)
        {{-- ============================ ORDERS TAB ============================ --}}
        <div
            class="ops-section"
            role="tabpanel"
            x-data
            x-on:ops-scroll-to-detail.window="$nextTick(() => document.getElementById('ops-detail')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
        >
            <div class="ops-section-head">
                <div>
                    <h2 class="ops-section-title">Customer Order Review</h2>
                    <p class="ops-section-sub">Review and verify transportation requests submitted via the Customer Portal, then price them.</p>
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
                        wire:click="selectOrderCard('{{ $card['status'] }}')"
                        @class(['ops-card', 'ops-card-active' => $this->isOrderCardActive($card['status'])])
                    >
                        <span class="ops-card-label">{{ $card['label'] }}</span>
                        <span class="ops-card-value">{{ $card['count'] }}</span>
                        <span class="ops-card-hint">{{ $card['hint'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="ops-legend">
                @foreach ($this->getOrderLegend() as $legend)
                    <span class="ops-legend-item ops-legend-{{ $legend['color'] }}">{{ $legend['label'] }}</span>
                @endforeach
            </div>

            <div class="cor-toolbar ops-toolbar">
                <div class="cor-toolbar-filters">
                    <div class="cor-toolbar-field cor-toolbar-search">
                        <label class="cor-toolbar-label" for="opsFilterSearch">Search</label>
                        <input
                            id="opsFilterSearch"
                            type="search"
                            wire:model.live.debounce.400ms="filterSearch"
                            class="cor-toolbar-input"
                            placeholder="Search order, enquiry, customer or address…"
                        />
                    </div>
                    <div class="cor-toolbar-field">
                        <label class="cor-toolbar-label" for="opsFilterStatus">Order stage</label>
                        <select id="opsFilterStatus" wire:model.live="filterStatus" class="cor-toolbar-input">
                            @foreach ($this->statusFilterOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cor-toolbar-field">
                        <label class="cor-toolbar-label" for="opsFilterDateFrom">Submitted</label>
                        <div class="cor-date-range">
                            <input id="opsFilterDateFrom" type="date" wire:model.live="filterDateFrom" class="cor-toolbar-input" />
                            <span class="cor-date-sep">–</span>
                            <input id="opsFilterDateTo" type="date" wire:model.live="filterDateTo" class="cor-toolbar-input" />
                        </div>
                    </div>
                </div>
                <div class="cor-toolbar-actions">
                    <button type="button" wire:click="resetFilters" class="cor-btn cor-btn-outline">Reset</button>
                    <button type="button" wire:click="exportOrders" class="cor-btn cor-btn-outline">Export</button>
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
                                    wire:key="ops-row-{{ $row['id'] }}"
                                    wire:click="openDetail({{ $row['id'] }})"
                                    @class([
                                        'cor-row',
                                        'ops-row',
                                        'ops-row-'.$row['status_color'],
                                        'cor-row-selected' => $selectedEnquiryId === $row['id'],
                                    ])
                                >
                                    <td>
                                        <div class="ops-primary cor-mono">{{ $row['reference_no'] }}</div>
                                        <div class="ops-secondary">{{ $row['customer'] }}</div>
                                        <div class="ops-tertiary">{{ $row['branch'] }} · {{ $row['submitted_by'] }} · {{ $row['submitted_at'] }}</div>
                                    </td>
                                    <td>
                                        <div class="ops-secondary ops-route">{{ $row['route'] }}</div>
                                        <div class="ops-tertiary">{{ $row['items_summary'] }} · Delivery {{ $row['preferred_delivery_date'] }}</div>
                                    </td>
                                    <td>
                                        <span @class(['cor-status-pill', 'cor-status-'.$row['status_color']])>{{ $row['status_label'] }}</span>
                                        <div class="ops-tertiary">{{ $row['stage_hint'] }}</div>
                                    </td>
                                    <td>
                                        <div class="ops-secondary">{{ $row['payment_label'] }}</div>
                                        <div class="ops-tertiary">{{ $row['payment_hint'] }}</div>
                                    </td>
                                    <td class="ops-td-right">
                                        <div @class(['ops-amount', 'ops-amount-muted' => $row['quotation_number'] === null])>{{ $row['amount'] }}</div>
                                    </td>
                                    <td>
                                        @if ($row['quotation_url'] && in_array($row['status'], ['quoted', 'in_review'], true))
                                            <a href="{{ $row['quotation_url'] }}" wire:navigate class="ops-next" @click.stop>{{ $row['next_step'] }} →</a>
                                        @else
                                            <span class="ops-next">{{ $row['next_step'] }} →</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="cor-empty">No portal orders found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="ops-panel-foot">
                    <span>{{ $ordersCount }} {{ \Illuminate\Support\Str::plural('record', $ordersCount) }}</span>
                    <span>{{ $this->getDateRangeLabel() }}</span>
                </div>
            </div>

            @if ($detail)
                <div class="cor-detail-panel ops-detail" id="ops-detail" wire:key="ops-detail-{{ $detail['id'] }}" wire:poll.2s="heartbeat">
                    <div class="ops-detail-bar">
                        <span class="ops-detail-crumb">Order details · {{ $detail['customer'] }}</span>
                        <button type="button" wire:click="closeDetail" class="cor-btn cor-btn-outline ops-btn-sm">Close</button>
                    </div>
                    @include('filament.pages.partials.portal-enquiry-detail', ['detail' => $detail, 'showRejectForm' => $showRejectForm])
                </div>
            @endif

            <p class="ops-flow">Enquiry → Quotation → Confirmation → Proforma → Payment / Admin release → Invoice / Cash Bill → CSN</p>
        </div>
    @else
        {{-- ============================= CSN TAB ============================== --}}
        <div class="ops-section" role="tabpanel">
            <div class="ops-section-head">
                <div>
                    <h2 class="ops-section-title">CSN management</h2>
                    <p class="ops-section-sub">See every consignment note, its lorry assignment and its next step.</p>
                </div>
                <a href="{{ $this->getCreateCsnUrl() }}" class="ops-btn ops-btn-primary">
                    <span aria-hidden="true">+</span> New consignment note
                </a>
            </div>

            <div class="ops-cards">
                @foreach ($scopes as $scope)
                    @continue(! $scope['card'])
                    <button
                        type="button"
                        wire:key="ops-csn-card-{{ $scope['key'] }}"
                        wire:click="setCsnScope('{{ $scope['key'] }}')"
                        @class(['ops-card', 'ops-card-active' => $csnScope === $scope['key']])
                    >
                        <span class="ops-card-label">{{ $scope['label'] }}</span>
                        <span class="ops-card-value">{{ $scope['count'] }}</span>
                        <span class="ops-card-hint">{{ $scope['hint'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="ops-subtabs" role="tablist" aria-label="Consignment note scope">
                @foreach ($scopes as $scope)
                    <button
                        type="button"
                        role="tab"
                        wire:key="ops-csn-scope-{{ $scope['key'] }}"
                        wire:click="setCsnScope('{{ $scope['key'] }}')"
                        aria-selected="{{ $csnScope === $scope['key'] ? 'true' : 'false' }}"
                        @class(['ops-subtab', 'ops-subtab-active' => $csnScope === $scope['key']])
                    >
                        {{ $scope['label'] }}
                        <span class="ops-subtab-count">{{ $scope['count'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="ops-panel ops-table-panel">
                {{ $this->table }}
            </div>

            <p class="ops-flow">CSN → Lorry assignment → Driver check-in → Job Sheet / Trip → Delivery → EOD → AutoCount</p>
        </div>
    @endif
</x-filament-panels::page>
