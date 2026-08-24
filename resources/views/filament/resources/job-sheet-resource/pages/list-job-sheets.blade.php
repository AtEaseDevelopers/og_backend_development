@php
    $selected = $this->getSelectedJobSheetPanel();
    $operatingDateLabel = filled($filterOperatingDate)
        ? \Illuminate\Support\Carbon::parse($filterOperatingDate)->format('d/m/Y')
        : now()->format('d/m/Y');
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
        <form wire:submit="applyFilters" class="js-filter-grid">
            <div class="js-filter-field">
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
                <label class="js-filter-label" for="filterOperatingDate">Operating Date</label>
                <input
                    id="filterOperatingDate"
                    type="date"
                    wire:model.defer="filterOperatingDate"
                    class="js-filter-input"
                />
            </div>
            <div class="js-filter-field">
                <label class="js-filter-label" for="filterBranchId">Branch</label>
                <select id="filterBranchId" wire:model.defer="filterBranchId" class="js-filter-input">
                    <option value="">All Branches</option>
                    @foreach ($this->branchFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="js-filter-field">
                <label class="js-filter-label" for="filterLorryId">Lorry</label>
                <select id="filterLorryId" wire:model.defer="filterLorryId" class="js-filter-input">
                    <option value="">All Lorries</option>
                    @foreach ($this->lorryFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="js-filter-field">
                <label class="js-filter-label" for="filterDriverId">Driver</label>
                <select id="filterDriverId" wire:model.defer="filterDriverId" class="js-filter-input">
                    <option value="">All Drivers</option>
                    @foreach ($this->driverFilterOptions() as $value => $label)
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
                <button type="submit" class="js-btn js-btn-primary">Search</button>
                <button type="button" wire:click="resetFilters" class="js-btn js-btn-secondary">Reset</button>
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

        @script
            <script>
                const highlightJobSheetRow = (selectedId) => {
                    document.querySelectorAll('.js-list-panel .fi-ta-record').forEach((row) => {
                        const clickTarget = row.querySelector('[wire\\:click*="selectJobSheetFromTable"]');
                        const click = clickTarget?.getAttribute('wire:click') ?? '';
                        const id = click.match(/selectJobSheetFromTable\('(\d+)'\)/)?.[1];

                        row.classList.toggle('js-row-selected', id && Number(id) === Number(selectedId));
                    });
                };

                highlightJobSheetRow($wire.selectedJobSheetId);

                $wire.$watch('selectedJobSheetId', (selectedId) => {
                    highlightJobSheetRow(selectedId);
                });

                Livewire.hook('morph.updated', () => {
                    highlightJobSheetRow($wire.selectedJobSheetId);
                });
            </script>
        @endscript

        <div class="js-detail-panel" wire:key="js-detail-{{ $selectedJobSheetId ?? 'none' }}">
            @if ($selected)
                <div class="js-detail-header">
                    <div class="js-detail-kicker">Selected Job Sheet</div>
                    <span @class([
                        'js-status-badge',
                        'js-status-badge-' . ($selected['status_color'] ?? 'gray'),
                    ])>{{ $selected['status_label'] }}</span>
                </div>
                <div class="js-detail-number">{{ $selected['number'] }}</div>
                <div class="js-detail-fields">
                    <div class="js-detail-row">
                        <span class="js-detail-label">Operating Date</span>
                        <span class="js-detail-value">{{ $selected['operating_date'] ?? '—' }}</span>
                    </div>
                    <div class="js-detail-row">
                        <span class="js-detail-label">Assigned Lorry</span>
                        <span class="js-detail-value">{{ $selected['lorry'] ?? '—' }}</span>
                    </div>
                    <div class="js-detail-row">
                        <span class="js-detail-label">Operating Branch</span>
                        <span class="js-detail-value">{{ $selected['operating_branch'] ?? '—' }}</span>
                    </div>
                    <div class="js-detail-row">
                        <span class="js-detail-label">Default Driver</span>
                        <span class="js-detail-value">{{ $selected['default_driver'] ?? '—' }}</span>
                    </div>
                    <div class="js-detail-row">
                        <span class="js-detail-label">Current Driver</span>
                        <span class="js-detail-value">{{ $selected['current_driver'] ?? '—' }}</span>
                    </div>
                    <div class="js-detail-row">
                        <span class="js-detail-label">Task Count</span>
                        <span class="js-detail-value">{{ $selected['task_count'] ?? 0 }}</span>
                    </div>
                </div>
                <div class="js-detail-tags">
                    <span class="js-detail-tag">Lorry-Based Job Sheet</span>
                    <span class="js-detail-tag">Operating Branch Follows Assigned Lorry</span>
                    <span class="js-detail-tag">Auto-Generated Running Number</span>
                </div>
                <a href="{{ $selected['view_url'] }}" wire:navigate class="js-btn js-btn-primary js-detail-action">
                    View Job Sheet
                </a>
            @else
                <div class="js-detail-empty">
                    <div class="js-detail-kicker">Selected Job Sheet</div>
                    <p>Select a job sheet from the listing to preview it here.</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
