@php
    $operatingDateLabel = $this->getOperatingDateLabel();
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-job-sheets',
        'js-list-page',
    ])
>
    <div class="js-page-intro">
        <div class="js-page-badges">
            <span class="js-badge js-badge-muted">HQ VIEW</span>
            <span class="js-badge js-badge-date">{{ $operatingDateLabel }}</span>
        </div>
    </div>

    <div class="js-page-layout">
    <div class="js-filter-card">
        {{-- One filter per table column, in column order; desktop rows: number, trip, date (2 wide), branch, lorry / driver, task count, status, buttons --}}
        <form wire:submit="applyFilters" class="js-filter-grid">
            <div class="js-filter-field js-filter-field-start">
                <label class="js-filter-label" for="filterNumber">Job Sheet Number</label>
                <input
                    id="filterNumber"
                    type="text"
                    wire:model.defer="filterNumber"
                    class="js-filter-input"
                    placeholder="Search..."
                />
            </div>
            <div class="js-filter-field">
                <label class="js-filter-label" for="filterTripNo">Trip</label>
                <input
                    id="filterTripNo"
                    type="text"
                    inputmode="numeric"
                    wire:model.defer="filterTripNo"
                    class="js-filter-input"
                    placeholder="e.g. 2"
                />
            </div>
            <div class="js-filter-field js-filter-field-wide">
                <span class="js-filter-label">Operating Date</span>
                <x-og.date-range :live="false" from="filterOperatingFrom" to="filterOperatingTo" :from-value="$filterOperatingFrom" :to-value="$filterOperatingTo" label="Operating date" />
            </div>
            <div class="js-filter-field">
                <label class="js-filter-label" for="filterBranchId">Company</label>
                <select id="filterBranchId" wire:model.defer="filterBranchId" class="js-filter-input">
                    <option value="">All Companies</option>
                    @foreach ($this->branchFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="js-filter-field js-filter-field-end">
                <label class="js-filter-label" for="filterLorryId">Lorry</label>
                <select id="filterLorryId" wire:model.defer="filterLorryId" class="js-filter-input">
                    <option value="">All Lorries</option>
                    @foreach ($this->lorryFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="js-filter-field js-filter-field-start">
                <label class="js-filter-label" for="filterDriverId">Driver</label>
                <select id="filterDriverId" wire:model.defer="filterDriverId" class="js-filter-input">
                    <option value="">All Drivers</option>
                    @foreach ($this->driverFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="js-filter-field">
                <label class="js-filter-label" for="filterTaskCount">Task Count</label>
                <select id="filterTaskCount" wire:model.defer="filterTaskCount" class="js-filter-input">
                    <option value="">Any</option>
                    @foreach ($this->taskCountFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="js-filter-field">
                <label class="js-filter-label" for="filterStatus">Status</label>
                <select id="filterStatus" wire:model.defer="filterStatus" class="js-filter-input">
                    <option value="">All Statuses</option>
                    @foreach ($this->statusFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="js-filter-actions">
                <button type="button" wire:click="resetFilters" class="js-btn js-btn-secondary">Reset</button>
                <button type="submit" class="js-btn js-btn-primary">Search</button>
            </div>
        </form>
    </div>

    <div class="js-list-panel">
            <div class="js-list-header">
                <h2 class="js-list-title">Job Sheet Listing</h2>
                <span class="js-record-count">{{ number_format($this->getFilteredJobSheetCount()) }} Records</span>
            </div>
            {{ $this->table }}
        </div>

    </div>
</x-filament-panels::page>
