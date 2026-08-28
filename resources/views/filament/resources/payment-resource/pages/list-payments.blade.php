<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-payments',
        'pl-page',
    ])
>
    <div class="pl-filter-card">
        <form wire:submit="applyFilters" class="pl-filter-grid">
            <div class="pl-filter-field pl-filter-search">
                <label class="pl-filter-label" for="filterSearch">Search</label>
                <div class="pl-search-wrap">
                    <svg class="pl-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        id="filterSearch"
                        type="search"
                        wire:model.defer="filterSearch"
                        class="pl-filter-input pl-filter-input-search"
                        placeholder="Search Payment No., CSN or Customer..."
                    />
                </div>
            </div>
            <div class="pl-filter-field">
                <label class="pl-filter-label" for="filterType">Payment Type</label>
                <select id="filterType" wire:model.defer="filterType" class="pl-filter-input">
                    <option value="">All Types</option>
                    @foreach ($this->typeFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pl-filter-field">
                <label class="pl-filter-label" for="filterStatus">Status</label>
                <select id="filterStatus" wire:model.defer="filterStatus" class="pl-filter-input">
                    <option value="">All Statuses</option>
                    @foreach ($this->statusFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pl-filter-actions">
                <button type="submit" class="pl-btn pl-btn-primary">Search</button>
                <button type="button" wire:click="resetFilters" class="pl-btn pl-btn-secondary">Reset</button>
            </div>
        </form>
    </div>

    <div class="pl-list-panel">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
