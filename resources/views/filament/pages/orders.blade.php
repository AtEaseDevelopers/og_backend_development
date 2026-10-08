@php
    $data = $this->getOrders();
    $rows = $data['rows'];
    $cards = $this->cards($data['summary']);
    $columns = $this->columns();
@endphp

<x-filament-panels::page class="ow-page">
    {{-- Header --}}
    <div class="ow-head">
        <div>
            <div class="ow-crumb">Operations / Orders</div>
            <h1 class="ow-title">Order management</h1>
        </div>
        <a href="{{ $this->createUrl() }}" class="ow-btn ow-btn-primary">+ Create order for customer</a>
    </div>

    <div class="ow-stack">
        {{-- Summary cards --}}
        <div class="ow-cards">
            @foreach ($cards as $card)
                <button type="button"
                        wire:click="selectCard('{{ $card['key'] }}')"
                        @class([
                            'ow-stat',
                            'ow-stat-'.$card['key'] => $card['key'] !== '',
                            'ow-stat-active' => ($this->card ?? '') === $card['key'],
                        ])>
                    <div class="ow-stat-label">{{ $card['label'] }}</div>
                    <div class="ow-stat-value">{{ $card['count'] }}</div>
                    <div class="ow-stat-hint">{{ $card['hint'] }}</div>
                </button>
            @endforeach
        </div>

        {{-- Order stage tags: click one to filter the table --}}
        <div class="ow-tags" role="group" aria-label="Filter by order stage">
            @foreach ($this->stageTags($data['stage_counts']) as $tag)
                <button type="button"
                        wire:key="ow-stage-{{ $tag['key'] ?: 'all' }}"
                        wire:click="selectStage('{{ $tag['key'] }}')"
                        aria-pressed="{{ $this->stage === $tag['key'] ? 'true' : 'false' }}"
                        @class([
                            'ow-tag',
                            'ow-tag-'.$tag['color'],
                            'ow-tag-active' => $this->stage === $tag['key'],
                            'ow-tag-empty' => $tag['count'] === 0,
                        ])>
                    <span>{{ $tag['label'] }}</span>
                    <span class="ow-tag-count">{{ $tag['count'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Filters + table --}}
        <div class="ow-card">
            <div class="ow-filters">
                <div class="ow-filter-top">
                    <div class="ow-field ow-search">
                        <label for="ow-search">Search</label>
                        <input type="search"
                               id="ow-search"
                               wire:model.live.debounce.400ms="search"
                               class="ow-input"
                               placeholder="Search order, enquiry, customer or DO…">
                    </div>
                    {{-- The two date ranges are used most, so they are always shown (not behind "Filters +") --}}
                    <div class="ow-field">
                        <label>Order created date</label>
                        <x-og.date-range from="createdFrom" to="createdTo" :from-value="$createdFrom" :to-value="$createdTo" label="Order created date" />
                    </div>
                    <div class="ow-field">
                        <label>Quotation valid until</label>
                        <x-og.date-range from="validFrom" to="validTo" :from-value="$validFrom" :to-value="$validTo" label="Quotation valid until" />
                    </div>
                    <div class="ow-actions">
                        <button type="button" wire:click="toggleFilters" @class(['ow-btn', 'ow-pill-dark' => $filtersOpen])>
                            Filters {{ $filtersOpen ? '−' : '+' }}
                        </button>
                        <button type="button" wire:click="resetFilters" class="ow-btn-link">Reset</button>
                    </div>
                </div>

                <div class="ow-fgrid ow-fgrid-5">
                    <div class="ow-field">
                        <label>Customer</label>
                        <select wire:model.live="customer" class="ow-select">
                            @foreach ($this->customerOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ow-field">
                        <label>Customer type</label>
                        <select wire:model.live="customerType" class="ow-select">
                            @foreach ($this->customerTypeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ow-field">
                        <label>Order type</label>
                        <select wire:model.live="orderType" class="ow-select">
                            @foreach ($this->orderTypeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ow-field">
                        <label>Service</label>
                        <select wire:model.live="serviceType" class="ow-select">
                            @foreach ($this->serviceTypeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ow-field">
                        <label>Salesperson</label>
                        <select wire:model.live="salesperson" class="ow-select">
                            @foreach ($this->salespersonOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($filtersOpen)
                    <div class="ow-fgrid">
                        <div class="ow-field">
                            <label>Payment status</label>
                            <select wire:model.live="paymentStatus" class="ow-select">
                                @foreach ($this->paymentStatusOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Payment method</label>
                            <select wire:model.live="paymentMethod" class="ow-select">
                                @foreach ($this->paymentMethodOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>SA location prefix</label>
                            <select wire:model.live="saLocation" class="ow-select">
                                @foreach ($this->saLocationOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Pricing source</label>
                            <select wire:model.live="pricingSource" class="ow-select">
                                @foreach ($this->pricingSourceOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="ow-fgrid">
                        <div class="ow-field">
                            <label>Quotation status</label>
                            <select wire:model.live="quotationStatus" class="ow-select">
                                @foreach ($this->quotationStatusOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Drop-off type</label>
                            <select wire:model.live="dropOffType" class="ow-select">
                                @foreach ($this->dropOffTypeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Amount · Min (MYR)</label>
                            <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="amountMin" class="ow-input">
                        </div>
                        <div class="ow-field">
                            <label>Amount · Max (MYR)</label>
                            <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="amountMax" class="ow-input">
                        </div>
                    </div>
                @endif
            </div>

            <div class="ow-table-wrap">
                <table class="ow-table ow-orders-table">
                    {{-- Order / Customer carries the most information, so it gets the widest column --}}
                    <colgroup>
                        <col class="ow-col-order">
                        <col class="ow-col-route">
                        <col class="ow-col-stage">
                        <col class="ow-col-payment">
                        <col class="ow-col-amount">
                        <col class="ow-col-next">
                    </colgroup>
                    <thead>
                        {{-- Click a header to sort: ascending, descending, then back to newest first --}}
                        <tr>
                            @foreach ($columns as $column)
                                @php $sorted = $sort === $column['key']; @endphp
                                <th @class(['ow-num' => $column['num'], 'ow-th-sorted' => $sorted])
                                    @if ($sorted) aria-sort="{{ $dir === 'desc' ? 'descending' : 'ascending' }}" @endif>
                                    <button type="button" wire:click="sortBy('{{ $column['key'] }}')" class="ow-th-sort">
                                        {{ $column['label'] }}
                                        <span class="ow-sort-ind" aria-hidden="true">{{ $sorted ? ($dir === 'desc' ? '▼' : '▲') : '↕' }}</span>
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr wire:key="ow-row-{{ $row['kind'] }}-{{ $row['id'] }}"
                                class="ow-row ow-row-{{ $row['stage']['color'] }}"
                                x-on:click="if (! $event.target.closest('a, button')) window.location.href = @js($row['url'])">
                                <td>
                                    <a href="{{ $row['url'] }}" class="ow-order-no">{{ $row['order_number'] }}</a>
                                    <div class="ow-l2">
                                        {{ $row['customer'] }}
                                        @if ($row['customer_type'])
                                            <span class="ow-ctype ow-ctype-{{ $row['customer_type']['key'] }}" title="Customer type">{{ $row['customer_type']['label'] }}</span>
                                        @endif
                                    </div>
                                    <div class="ow-l3">
                                        {{ $row['document_number'] ?? $row['enquiry_ref'] }}@if ($row['order_type']) · {{ $row['order_type'] }}@endif
                                        @if ($row['version'] && $row['version'] > 1) · v{{ $row['version'] }}@endif
                                    </div>
                                    @if ($row['kind'] === 'enquiry')
                                        <div class="ow-l3">{{ $row['source_line'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $row['route_from'] }}</div>
                                    <div>→ {{ $row['route_to'] }}</div>
                                    @if ($row['consignee'] && $row['consignee'] !== $row['route_to'])
                                        <div class="ow-l3">{{ $row['consignee'] }}</div>
                                    @endif
                                    <div class="ow-l3">
                                        @if ($row['salesperson'])
                                            {{ $row['salesperson'] }}
                                        @else
                                            <span class="ow-pill ow-pill-action ow-pill-plain">No salesperson</span>
                                        @endif
                                        @if ($row['service_type']) · {{ $row['service_type'] }}@endif
                                    </div>
                                </td>
                                <td>
                                    <span class="ow-pill ow-pill-{{ $row['stage']['color'] }}">{{ $row['stage']['label'] }}</span>
                                    <div class="ow-l2">{{ $row['stage']['hint'] }}</div>
                                </td>
                                <td>
                                    <span class="ow-pill ow-pill-{{ $row['payment']['color'] }}">{{ $row['payment']['label'] }}</span>
                                    @if ($row['payment']['method'])
                                        <div class="ow-l2">{{ $row['payment']['method'] }}</div>
                                    @endif
                                    @if ($row['payment']['hint'] && $row['payment']['hint'] !== $row['payment']['method'])
                                        <div class="ow-l3">{{ $row['payment']['hint'] }}</div>
                                    @endif
                                </td>
                                <td class="ow-num">
                                    <div @class(['ow-l1', 'ow-l2' => $row['amount_muted']])>{{ $row['amount'] }}</div>
                                    @if ($row['order_type'])
                                        <div class="ow-l3">{{ $row['order_type'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ $row['next_url'] }}" class="ow-next">{{ $row['next_step'] }} →</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="ow-empty">No orders match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="ow-foot">
                <span>{{ $data['count'] }} of {{ $data['total'] }} {{ \Illuminate\Support\Str::plural('record', $data['total']) }}</span>
                <span>All results on one page</span>
            </div>
        </div>

        <div class="ow-flow">{{ implode(' → ', $this->steps()) }}</div>
    </div>
</x-filament-panels::page>
