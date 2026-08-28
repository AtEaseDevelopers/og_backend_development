<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-break-bulks',
        'bb-list-page',
    ])
>
    <div class="bb-page-intro">
        <div class="bb-page-badges">
            <span class="bb-badge bb-badge-muted">HQ VIEW</span>
        </div>
    </div>

    <div class="bb-filter-card">
        <form wire:submit="applyFilters" class="bb-filter-grid">
            <div class="bb-filter-field bb-filter-search">
                <label class="bb-filter-label" for="filterSearch">Search</label>
                <div class="bb-search-wrap">
                    <svg class="bb-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        id="filterSearch"
                        type="search"
                        wire:model.defer="filterSearch"
                        class="bb-filter-input bb-filter-input-search"
                        placeholder="Search Break-Bulk No., DO, CSN or Driver..."
                    />
                </div>
            </div>
            <div class="bb-filter-field">
                <label class="bb-filter-label" for="filterStatus">Status</label>
                <select id="filterStatus" wire:model.defer="filterStatus" class="bb-filter-input">
                    <option value="">All Statuses</option>
                    @foreach ($this->statusFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="bb-filter-field">
                <label class="bb-filter-label" for="filterSource">Source</label>
                <select id="filterSource" wire:model.defer="filterSource" class="bb-filter-input">
                    <option value="">All Sources</option>
                    @foreach ($this->sourceFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="bb-filter-actions">
                <button type="submit" class="bb-btn bb-btn-primary">Search</button>
                <button type="button" wire:click="resetFilters" class="bb-btn bb-btn-secondary">Reset</button>
            </div>
        </form>
    </div>

    <div class="bb-list-panel">
        <div class="bb-list-header">
            <h2 class="bb-list-title">Break-Bulk Listing</h2>
            <div class="bb-list-header-right">
                <span class="bb-record-count">{{ number_format($this->getFilteredBreakBulkCount()) }} Records</span>
                <a href="{{ \App\Filament\Resources\BreakBulkResource::getUrl('create') }}" wire:navigate class="bb-btn bb-btn-primary bb-create-btn">
                    Create Break-Bulk
                </a>
            </div>
        </div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
