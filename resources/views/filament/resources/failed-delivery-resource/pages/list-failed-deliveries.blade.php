@php
    $data = $this->getReviewData();
    $rows = $data['rows'] ?? [];
    $selectedDateLabel = $data['selected_date_label'] ?? now()->format('d/m/Y');
    $count = $data['count'] ?? 0;

    $display = fn ($value): string => filled($value) ? (string) $value : '—';
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-failed-deliveries',
        'fd-page',
    ])
>
    <div class="fd-page-intro">
        <div class="fd-page-badges">
            <span class="fd-badge fd-badge-muted">HQ VIEW</span>
            <span class="fd-badge fd-badge-date">Selected Date: {{ $selectedDateLabel }}</span>
        </div>
    </div>

    <div class="fd-filter-card">
        <div class="fd-filter-title">Search & Filters</div>
        <form wire:submit="applyFilters" class="fd-filter-form">
            <div class="fd-filter-grid fd-filter-grid-top">
                <div class="fd-filter-field">
                    <label class="fd-filter-label" for="filterDeliveryDate">Delivery Date</label>
                    <input
                        id="filterDeliveryDate"
                        type="date"
                        wire:model.defer="filterDeliveryDate"
                        class="fd-filter-input"
                    />
                </div>
                <div class="fd-filter-field">
                    <label class="fd-filter-label" for="filterBranchId">Branch</label>
                    <select id="filterBranchId" wire:model.defer="filterBranchId" class="fd-filter-input">
                        <option value="">All Branches</option>
                        @foreach ($this->branchFilterOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fd-filter-field">
                    <label class="fd-filter-label" for="filterDriverId">Driver</label>
                    <select id="filterDriverId" wire:model.defer="filterDriverId" class="fd-filter-input">
                        <option value="">All Drivers</option>
                        @foreach ($this->driverFilterOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fd-filter-field">
                    <label class="fd-filter-label" for="filterLorryId">Lorry</label>
                    <select id="filterLorryId" wire:model.defer="filterLorryId" class="fd-filter-input">
                        <option value="">All Lorries</option>
                        @foreach ($this->lorryFilterOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fd-filter-field">
                    <label class="fd-filter-label" for="filterJobSheetId">Job Sheet</label>
                    <select id="filterJobSheetId" wire:model.defer="filterJobSheetId" class="fd-filter-input">
                        <option value="">All Job Sheets</option>
                        @foreach ($this->jobSheetFilterOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fd-filter-field">
                    <label class="fd-filter-label" for="filterStatus">Status</label>
                    <div id="filterStatus" class="fd-filter-status">Failed</div>
                </div>
            </div>
            <div class="fd-filter-grid fd-filter-grid-bottom">
                <div class="fd-filter-field fd-filter-search">
                    <label class="fd-filter-label" for="filterSearch">Search</label>
                    <div class="fd-search-wrap">
                        <svg class="fd-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <input
                            id="filterSearch"
                            type="search"
                            wire:model.defer="filterSearch"
                            class="fd-filter-input fd-filter-input-search"
                            placeholder="Search DO / Task Reference"
                        />
                    </div>
                </div>
                <div class="fd-filter-actions">
                    <button type="button" wire:click="resetFilters" class="fd-btn fd-btn-secondary">Reset</button>
                    <button type="submit" class="fd-btn fd-btn-primary">Search</button>
                </div>
            </div>
        </form>
    </div>

    <div class="fd-list-panel">
        <div class="fd-list-header">
            <h2 class="fd-list-title">Failed Delivery Task Listing</h2>
            <span class="fd-record-count">{{ number_format($count) }} Records</span>
        </div>
        <div class="fd-table-wrap">
            <table class="fd-table">
                <thead>
                    <tr>
                        <th>DO / Task Ref</th>
                        <th>Job Sheet</th>
                        <th>Branch</th>
                        <th>Driver</th>
                        <th>Lorry</th>
                        <th>Destination</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="fd-cell-strong">
                                @if ($row['view_url'] ?? null)
                                    <a href="{{ $row['view_url'] }}" wire:navigate class="fd-do-link">{{ $display($row['do_number'] ?? null) }}</a>
                                @else
                                    {{ $display($row['do_number'] ?? null) }}
                                @endif
                            </td>
                            <td>{{ $display($row['job_sheet_number'] ?? null) }}</td>
                            <td>{{ $display($row['branch'] ?? null) }}</td>
                            <td>{{ $display($row['driver'] ?? null) }}</td>
                            <td>{{ $display($row['lorry'] ?? null) }}</td>
                            <td>{{ $display($row['destination'] ?? null) }}</td>
                            <td>{{ $display($row['date'] ?? null) }}</td>
                            <td>
                                <span class="fd-status-badge">{{ strtoupper($display($row['status_label'] ?? null)) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="fd-empty">No failed delivery tasks found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
