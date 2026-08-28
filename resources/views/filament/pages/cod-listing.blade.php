@php
    $data = $this->getListingData();
    $rows = $data['rows'] ?? [];
    $count = $data['count'] ?? 0;
    $dateLabel = $data['selected_date_label'] ?? now()->format('d/m/Y');
@endphp

<x-filament-panels::page class="fi-page-cod-listing cl-page">
    <div class="cl-page-toolbar">
        <div class="cl-date-field">
            <label class="cl-date-label" for="filterDate">Date:</label>
            <input
                id="filterDate"
                type="date"
                wire:model.live="filterDate"
                class="cl-date-input"
            />
            <span class="cl-date-display">{{ $dateLabel }}</span>
        </div>
    </div>

    <div class="cl-filter-card">
        <form wire:submit="applyFilters" class="cl-filter-grid">
            <div class="cl-filter-field cl-filter-search">
                <label class="cl-filter-label" for="filterSearch">Search</label>
                <div class="cl-search-wrap">
                    <svg class="cl-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        id="filterSearch"
                        type="search"
                        wire:model.defer="filterSearch"
                        class="cl-filter-input cl-filter-input-search"
                        placeholder="Search Driver, Lorry or Job Sheet..."
                    />
                </div>
            </div>
            <div class="cl-filter-field">
                <label class="cl-filter-label" for="filterStatus">Status</label>
                <select id="filterStatus" wire:model.defer="filterStatus" class="cl-filter-input">
                    <option value="">All Statuses</option>
                    @foreach ($this->statusFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cl-filter-actions">
                <button type="submit" class="cl-btn cl-btn-primary">Search</button>
                <button type="button" wire:click="resetFilters" class="cl-btn cl-btn-secondary">Reset</button>
            </div>
        </form>
    </div>

    <div class="cl-list-panel">
        <div class="cl-table-wrap">
            <table class="cl-table">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Lorry</th>
                        <th>Job Sheet</th>
                        <th class="cl-num">COD Deliveries</th>
                        <th class="cl-num">Total COD to Collect</th>
                        <th>Status</th>
                        <th class="cl-action-col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr wire:key="cod-row-{{ $row['id'] }}">
                            <td>{{ $row['driver'] }}</td>
                            <td>{{ $row['lorry'] }}</td>
                            <td class="cl-mono">{{ $row['job_sheet'] }}</td>
                            <td class="cl-num">{{ $row['cod_deliveries'] }}</td>
                            <td class="cl-num cl-amount">{{ $row['total_cod_label'] }}</td>
                            <td>
                                <span @class([
                                    'cl-status-pill',
                                    'cl-status-in-progress' => $row['status'] === 'in_progress',
                                    'cl-status-completed' => $row['status'] === 'completed',
                                ])>
                                    {{ $row['status_label'] }}
                                </span>
                            </td>
                            <td class="cl-action-col">
                                <a href="{{ $row['view_url'] }}" wire:navigate class="cl-view-btn" title="View job sheet">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="cl-empty">No COD job sheets found for this date.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="cl-table-footer">
            <span>Showing {{ $count > 0 ? '1' : '0' }} to {{ number_format($count) }} of {{ number_format($count) }} entries</span>
        </div>
    </div>
</x-filament-panels::page>
