@php
    $data = $this->getMonitoringData();
    $stats = $data['stats'] ?? [];
    $tasks = $data['tasks'] ?? [];
    $legend = $data['status_legend'] ?? [];
    $incomplete = $data['incomplete'] ?? [];
    $selectedDateLabel = $data['selected_date_label'] ?? now()->format('d/m/Y');
    $incompleteCount = $data['incomplete_count'] ?? 0;
    $checkTime = $data['check_time'] ?? '16:15';
    $showAlert = $data['show_alert'] ?? false;

    $display = fn ($value): string => filled($value) ? (string) $value : '—';

    $statCards = [
        ['key' => 'total', 'label' => 'Total Tasks', 'tone' => 'default'],
        ['key' => 'assigned', 'label' => 'Assigned', 'tone' => 'info'],
        ['key' => 'in_transit', 'label' => 'In Transit', 'tone' => 'warning'],
        ['key' => 'delivered', 'label' => 'Delivered', 'tone' => 'success'],
        ['key' => 'failed', 'label' => 'Failed', 'tone' => 'danger'],
        ['key' => 'transferred', 'label' => 'Transferred', 'tone' => 'info'],
        ['key' => 'reassigned', 'label' => 'Reassigned', 'tone' => 'purple'],
        ['key' => 'cancelled', 'label' => 'Cancelled', 'tone' => 'muted'],
    ];
@endphp

<x-filament-panels::page
    @class([
        'fi-page-delivery-monitoring',
        'dm-page',
    ])
>
    <div class="dm-page-intro">
        <div class="dm-page-badges">
            <span class="dm-badge dm-badge-muted">HQ VIEW</span>
            <span class="dm-badge dm-badge-date">Selected Date: {{ $selectedDateLabel }}</span>
        </div>
    </div>

    <div class="dm-stats-grid">
        @foreach ($statCards as $card)
            <div class="dm-stat-card">
                <span class="dm-stat-label">{{ $card['label'] }}</span>
                <span @class([
                    'dm-stat-value',
                    'dm-stat-value-' . $card['tone'],
                ])>{{ number_format($stats[$card['key']] ?? 0) }}</span>
            </div>
        @endforeach
    </div>

    <div class="dm-filter-card">
        <div class="dm-filter-title">Search & Filters</div>
        <form wire:submit="applyFilters" class="dm-filter-grid">
            <div class="dm-filter-field">
                <label class="dm-filter-label" for="filterDeliveryDate">Delivery Date</label>
                <input
                    id="filterDeliveryDate"
                    type="date"
                    wire:model.defer="filterDeliveryDate"
                    class="dm-filter-input"
                />
            </div>
            <div class="dm-filter-field">
                <label class="dm-filter-label" for="filterBranchId">Branch</label>
                <select id="filterBranchId" wire:model.defer="filterBranchId" class="dm-filter-input">
                    <option value="">All Branches</option>
                    @foreach ($this->branchFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dm-filter-field">
                <label class="dm-filter-label" for="filterStatus">Status</label>
                <select id="filterStatus" wire:model.defer="filterStatus" class="dm-filter-input">
                    <option value="">All Statuses</option>
                    @foreach ($this->statusFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dm-filter-field">
                <label class="dm-filter-label" for="filterSearch">Search</label>
                <input
                    id="filterSearch"
                    type="search"
                    wire:model.defer="filterSearch"
                    class="dm-filter-input"
                    placeholder="Task / DO Reference"
                />
            </div>
            <div class="dm-filter-field">
                <label class="dm-filter-label" for="filterJobSheetId">Job Sheet</label>
                <select id="filterJobSheetId" wire:model.defer="filterJobSheetId" class="dm-filter-input">
                    <option value="">All Job Sheets</option>
                    @foreach ($this->jobSheetFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dm-filter-field">
                <label class="dm-filter-label" for="filterDriverId">Driver</label>
                <select id="filterDriverId" wire:model.defer="filterDriverId" class="dm-filter-input">
                    <option value="">All Drivers</option>
                    @foreach ($this->driverFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dm-filter-field">
                <label class="dm-filter-label" for="filterLorryId">Lorry</label>
                <select id="filterLorryId" wire:model.defer="filterLorryId" class="dm-filter-input">
                    <option value="">All Lorries</option>
                    @foreach ($this->lorryFilterOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dm-filter-actions">
                <button type="button" wire:click="resetFilters" class="dm-btn dm-btn-secondary">Reset</button>
                <button type="submit" class="dm-btn dm-btn-primary">Search</button>
            </div>
        </form>
    </div>

    <div class="dm-legend">
        <span class="dm-legend-title">Task Status Overview</span>
        <div class="dm-legend-items">
            @foreach ($legend as $item)
                <span @class([
                    'dm-legend-badge',
                    'dm-legend-badge-' . ($item['color'] ?? 'gray'),
                ])>{{ $item['label'] ?? '—' }}</span>
            @endforeach
        </div>
    </div>

    <div class="dm-table-card">
        <div class="dm-table-wrap">
            <table class="dm-table">
                <thead>
                    <tr>
                        <th>DO / Task Ref</th>
                        <th>Job Sheet</th>
                        <th>Branch</th>
                        <th>Driver</th>
                        <th>Lorry</th>
                        <th>Destination</th>
                        <th>Delivery Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tasks as $row)
                        <tr>
                            <td class="dm-cell-strong">
                                @if ($row['view_url'] ?? null)
                                    <a href="{{ $row['view_url'] }}" wire:navigate class="dm-do-link">{{ $display($row['do_number'] ?? null) }}</a>
                                @else
                                    {{ $display($row['do_number'] ?? null) }}
                                @endif
                            </td>
                            <td>{{ $display($row['job_sheet_number'] ?? null) }}</td>
                            <td>{{ $display($row['branch'] ?? null) }}</td>
                            <td>{{ $display($row['driver'] ?? null) }}</td>
                            <td>{{ $display($row['lorry'] ?? null) }}</td>
                            <td>{{ $display($row['destination'] ?? null) }}</td>
                            <td>{{ $display($row['delivery_date'] ?? null) }}</td>
                            <td>
                                <span @class([
                                    'dm-status-badge',
                                    'dm-status-badge-' . ($row['status_color'] ?? 'gray'),
                                ])>{{ $display($row['status_label'] ?? null) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="dm-empty">No delivery tasks found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showAlert)
        <div class="dm-alert-card">
            <div class="dm-alert-header">
                <div class="dm-alert-title-wrap">
                    <svg class="dm-alert-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <div>
                        <div class="dm-alert-title">4PM Incomplete Task Alert</div>
                        <div class="dm-alert-meta">
                            Date: {{ $selectedDateLabel }}
                            · Check Time: {{ $checkTime }}
                            · Scheduled Tasks Not Completed: {{ number_format($incompleteCount) }}
                        </div>
                    </div>
                </div>
                <button type="button" wire:click="runFlag" class="dm-btn dm-btn-danger">Admin Action Required</button>
            </div>
            <p class="dm-alert-rule">
                Rule: For delivery tasks scheduled on the selected date, tasks that remain not completed after 4PM are listed for Admin follow-up.
            </p>
            <div class="dm-table-wrap">
                <table class="dm-table dm-alert-table">
                    <thead>
                        <tr>
                            <th>DO / Task Ref</th>
                            <th>Job Sheet</th>
                            <th>Branch</th>
                            <th>Driver</th>
                            <th>Lorry</th>
                            <th>Destination</th>
                            <th>Current Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($incomplete as $row)
                            <tr>
                                <td class="dm-cell-strong">
                                    @if ($row['view_url'] ?? null)
                                        <a href="{{ $row['view_url'] }}" wire:navigate class="dm-do-link">{{ $display($row['do_number'] ?? null) }}</a>
                                    @else
                                        {{ $display($row['do_number'] ?? null) }}
                                    @endif
                                </td>
                                <td>{{ $display($row['job_sheet_number'] ?? null) }}</td>
                                <td>{{ $display($row['branch'] ?? null) }}</td>
                                <td>{{ $display($row['driver'] ?? null) }}</td>
                                <td>{{ $display($row['lorry'] ?? null) }}</td>
                                <td>{{ $display($row['destination'] ?? null) }}</td>
                                <td>
                                    <span @class([
                                        'dm-status-badge',
                                        'dm-status-badge-' . ($row['status_color'] ?? 'gray'),
                                    ])>{{ $display($row['status_label'] ?? null) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
