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
                @include('filament.pages.partials.portal-enquiry-detail', ['detail' => $detail, 'showRejectForm' => $showRejectForm])
            @else
                <div class="cor-detail-empty">
                    <h2 class="cor-panel-title">Order Details</h2>
                    <p>Select an order from the queue to review pickup, delivery, and pricing details.</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
