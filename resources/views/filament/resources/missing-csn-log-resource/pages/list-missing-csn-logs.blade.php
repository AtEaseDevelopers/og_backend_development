@php
    $data = $this->getMonitoringData();
    $stats = $data['stats'] ?? [];
    $rows = $data['rows'] ?? [];
    $monitoringDateLabel = $data['monitoring_date_label'] ?? now()->format('d/m/Y');
    $graceDays = $data['grace_days'] ?? config('og.missing_csn_days', 7);
    $total = $data['total'] ?? 0;
    $page = $data['page'] ?? 1;
    $lastPage = $data['last_page'] ?? 1;
    $from = $data['from'] ?? 0;
    $to = $data['to'] ?? 0;

    $display = fn ($value): string => filled($value) ? (string) $value : '—';

    $statCards = [
        ['key' => 'unreturned', 'label' => 'Unreturned CSNs', 'tone' => 'default', 'icon' => 'box'],
        ['key' => 'pending_return', 'label' => 'Pending Return', 'tone' => 'warning', 'icon' => 'clock'],
        ['key' => 'missing', 'label' => 'Missing', 'tone' => 'danger', 'icon' => 'alert'],
    ];
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-missing-csn-logs',
        'mc-page',
    ])
>
    <div class="mc-page-intro">
        <div class="mc-page-badges">
            <span class="mc-badge mc-badge-muted">HQ VIEW</span>
            <span class="mc-badge mc-badge-date">Monitoring Date: {{ $monitoringDateLabel }}</span>
        </div>
    </div>

    <div class="mc-stats-grid">
        @foreach ($statCards as $card)
            <div @class([
                'mc-stat-card',
                'mc-stat-card-' . $card['tone'],
            ])>
                <div class="mc-stat-icon-wrap mc-stat-icon-{{ $card['tone'] }}">
                    @if ($card['icon'] === 'box')
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mc-stat-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                    @elseif ($card['icon'] === 'clock')
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mc-stat-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mc-stat-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    @endif
                </div>
                <div class="mc-stat-content">
                    <span class="mc-stat-label">{{ $card['label'] }}</span>
                    <span @class([
                        'mc-stat-value',
                        'mc-stat-value-' . $card['tone'],
                    ])>{{ number_format($stats[$card['key']] ?? 0) }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mc-rule-card">
        <div class="mc-rule-head">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mc-rule-icon">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <h2 class="mc-rule-title">CSN Return Rule</h2>
            <span class="mc-rule-active">ACTIVE</span>
        </div>
        <div class="mc-rule-body">
            <div class="mc-rule-param">
                <span class="mc-rule-param-label">Missing Period</span>
                <span class="mc-rule-param-value">{{ $graceDays }} Days</span>
            </div>
            <div class="mc-rule-flow">
                <span class="mc-rule-step">Within {{ $graceDays }} Days</span>
                <span class="mc-rule-arrow">→</span>
                <span class="mc-status-tag mc-status-tag-warning">PENDING RETURN</span>
                <span class="mc-rule-step">Period Exceeded</span>
                <span class="mc-rule-arrow">→</span>
                <span class="mc-status-tag mc-status-tag-danger">MISSING</span>
            </div>
            <p class="mc-rule-note">
                CSNs remain Pending Return within the configured period and become Missing once the return period expires without the original signed CSN being received.
            </p>
        </div>
    </div>

    <div class="mc-filter-card">
        <div class="mc-filter-head">
            <div class="mc-filter-title-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mc-filter-icon">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                </svg>
                <h2 class="mc-filter-title">Search & Filters</h2>
            </div>
        </div>
        <form wire:submit="applyFilters" class="mc-filter-form">
            <div class="mc-filter-grid mc-filter-grid-top">
                <div class="mc-filter-field">
                    <label class="mc-filter-label" for="filterBranchId">Branch</label>
                    <select id="filterBranchId" wire:model.defer="filterBranchId" class="mc-filter-input">
                        <option value="">All Branches</option>
                        @foreach ($this->branchFilterOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mc-filter-field">
                    <label class="mc-filter-label" for="filterDriverId">Driver</label>
                    <select id="filterDriverId" wire:model.defer="filterDriverId" class="mc-filter-input">
                        <option value="">All Drivers</option>
                        @foreach ($this->driverFilterOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mc-filter-field">
                    <label class="mc-filter-label" for="filterLorryId">Lorry</label>
                    <select id="filterLorryId" wire:model.defer="filterLorryId" class="mc-filter-input">
                        <option value="">All Lorries</option>
                        @foreach ($this->lorryFilterOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mc-filter-field">
                    <label class="mc-filter-label" for="filterCustomerId">Customer</label>
                    <select id="filterCustomerId" wire:model.defer="filterCustomerId" class="mc-filter-input">
                        <option value="">All Customers</option>
                        @foreach ($this->customerFilterOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mc-filter-grid mc-filter-grid-bottom">
                <div class="mc-filter-field">
                    <label class="mc-filter-label" for="filterCsnSearch">CSN Number</label>
                    <input
                        id="filterCsnSearch"
                        type="search"
                        wire:model.defer="filterCsnSearch"
                        class="mc-filter-input"
                        placeholder="Search CSN number"
                    />
                </div>
                <div class="mc-filter-field">
                    <label class="mc-filter-label" for="filterJobSheetId">Job Sheet</label>
                    <select id="filterJobSheetId" wire:model.defer="filterJobSheetId" class="mc-filter-input">
                        <option value="">All Job Sheets</option>
                        @foreach ($this->jobSheetFilterOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mc-filter-field">
                    <label class="mc-filter-label" for="filterMonitoringDate">Monitoring Date</label>
                    <input
                        id="filterMonitoringDate"
                        type="date"
                        wire:model.defer="filterMonitoringDate"
                        class="mc-filter-input"
                    />
                </div>
                <div class="mc-filter-actions">
                    <button type="button" wire:click="resetFilters" class="mc-btn mc-btn-secondary">Reset</button>
                    <button type="submit" class="mc-btn mc-btn-primary">Search</button>
                </div>
            </div>
        </form>
    </div>

    <div class="mc-list-panel">
        <div class="mc-list-header">
            <h2 class="mc-list-title">Centralized Missing CSN Listing</h2>
            <span class="mc-record-count">{{ number_format($total) }} Records</span>
        </div>
        <div class="mc-table-wrap">
            <table class="mc-table">
                <thead>
                    <tr>
                        <th>CSN Number</th>
                        <th>Job Sheet</th>
                        <th>Branch</th>
                        <th>Driver</th>
                        <th>Lorry</th>
                        <th>Customer</th>
                        <th>Return Due Date</th>
                        <th>Days Since Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td @class(['mc-cell-strong', 'mc-cell-danger' => $row['is_missing'] ?? false])>
                                @if ($row['is_missing'] ?? false)
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mc-row-alert-icon">
                                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5.125a.75.75 0 0 1 .75.75v3.25a.75.75 0 0 1-1.5 0V5.875A.75.75 0 0 1 10 5.125Zm0 8.25a.875.875 0 1 0 0-1.75.875.875 0 0 0 0 1.75Z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                                @if ($row['view_url'] ?? null)
                                    <a href="{{ $row['view_url'] }}" wire:navigate class="mc-csn-link">{{ $display($row['csn_number'] ?? null) }}</a>
                                @else
                                    {{ $display($row['csn_number'] ?? null) }}
                                @endif
                            </td>
                            <td>{{ $display($row['job_sheet_number'] ?? null) }}</td>
                            <td>{{ $display($row['branch_name'] ?? null) }}</td>
                            <td>{{ $display($row['driver_name'] ?? null) }}</td>
                            <td>{{ $display($row['lorry_registration'] ?? null) }}</td>
                            <td>{{ $display($row['customer_name'] ?? null) }}</td>
                            <td>{{ $display($row['return_due_date'] ?? null) }}</td>
                            <td @class(['mc-days-overdue' => filled($row['days_since_due'] ?? null)])>
                                {{ $display($row['days_since_due_label'] ?? null) }}
                            </td>
                            <td>
                                <span @class([
                                    'mc-status-tag',
                                    'mc-status-tag-warning' => ($row['status'] ?? null) === 'pending_return',
                                    'mc-status-tag-danger' => ($row['status'] ?? null) === 'missing',
                                ])>{{ $display($row['status_label'] ?? null) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="mc-empty">No unreturned CSN records found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($total > 0)
            <div class="mc-pagination">
                <span class="mc-pagination-meta">Showing {{ $from }}–{{ $to }} of {{ number_format($total) }} records</span>
                <div class="mc-pagination-controls">
                    <button
                        type="button"
                        wire:click="setMonitoringPage({{ max(1, $page - 1) }})"
                        @disabled($page <= 1)
                        class="mc-page-btn"
                    >Prev</button>
                    @for ($p = 1; $p <= min($lastPage, 5); $p++)
                        <button
                            type="button"
                            wire:click="setMonitoringPage({{ $p }})"
                            @class(['mc-page-btn', 'mc-page-btn-active' => $p === $page])
                        >{{ $p }}</button>
                    @endfor
                    <button
                        type="button"
                        wire:click="setMonitoringPage({{ min($lastPage, $page + 1) }})"
                        @disabled($page >= $lastPage)
                        class="mc-page-btn"
                    >Next</button>
                </div>
            </div>
        @endif
    </div>

    <div class="mc-footer-link">
        @php
            $returnedCsnUrl = \Filament\Facades\Filament::getTenant()
                ? \App\Filament\Pages\ReturnedCsnDesk::getUrl([], true, null, \Filament\Facades\Filament::getTenant())
                : \App\Filament\Pages\ReturnedCsnDesk::getUrl();
        @endphp
        <a href="{{ $returnedCsnUrl }}" wire:navigate class="mc-back-link">
            ← Back to Returned CSN Management
        </a>
    </div>
</x-filament-panels::page>
