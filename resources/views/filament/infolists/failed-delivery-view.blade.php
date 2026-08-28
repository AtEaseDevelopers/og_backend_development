@php
    $header = $header ?? [];
    $reference = $reference ?? [];
    $photos = $photos ?? [];
    $photosMeta = $photos_meta ?? [];
    $reassignmentTypes = $reassignment_types ?? [];
    $assignment = $assignment ?? [];
    $driverOptions = $driver_options ?? [];
    $lorryOptions = $lorry_options ?? [];
    $auditRows = $audit_rows ?? [];
    $listUrl = $list_url ?? '#';
    $auditSortColumn = $audit_sort_column ?? 'sort_at';
    $auditSortDirection = $audit_sort_direction ?? 'desc';
    $selectedReplacementDriverName = $selected_replacement_driver_name ?? null;

    $display = fn ($value): string => filled($value) ? (string) $value : '—';

    $sortIcon = function (string $column) use ($auditSortColumn, $auditSortDirection): string {
        if ($auditSortColumn !== $column) {
            return '↕';
        }

        return $auditSortDirection === 'asc' ? '↑' : '↓';
    };

    $sortColumns = [
        'date_time' => 'Date',
        'reassignment_type' => 'Reassignment Type',
        'original_driver' => 'Original Driver',
        'replacement_driver' => 'Replacement Driver',
        'reason' => 'Reason',
        'user' => 'User',
    ];

    $selectedOption = $assignment['reassignment_option'] ?? 'standard';
@endphp

<div class="fdv-view">
    <div class="fdv-header-badges">
        @if ($header['do_number'] ?? null)
            <span class="fdv-meta-badge">DO: {{ $header['do_number'] }}</span>
        @endif
        @if ($header['job_sheet_number'] ?? null)
            <span class="fdv-meta-badge">Job Sheet: {{ $header['job_sheet_number'] }}</span>
        @endif
        <span class="fdv-status-badge fdv-status-badge-danger">Status: {{ $display($header['status_label'] ?? null) }}</span>
    </div>

    <div class="fdv-card">
        <div class="fdv-card-title">Failed Delivery Reference</div>
        <div class="fdv-ref-grid fdv-ref-grid-top">
            <div class="fdv-ref-item">
                <span class="fdv-ref-label">Delivery Order</span>
                <span class="fdv-ref-value">{{ $display($reference['do_number'] ?? null) }}</span>
            </div>
            <div class="fdv-ref-item">
                <span class="fdv-ref-label">Job Sheet</span>
                <span class="fdv-ref-value">{{ $display($reference['job_sheet_number'] ?? null) }}</span>
            </div>
            <div class="fdv-ref-item">
                <span class="fdv-ref-label">Date</span>
                <span class="fdv-ref-value">{{ $display($reference['date'] ?? null) }}</span>
            </div>
            <div class="fdv-ref-item">
                <span class="fdv-ref-label">Status</span>
                <span class="fdv-ref-badge fdv-ref-badge-danger">{{ $display($reference['status_label'] ?? null) }}</span>
            </div>
        </div>
        <div class="fdv-ref-grid fdv-ref-grid-middle">
            <div class="fdv-ref-item">
                <span class="fdv-ref-label">Customer</span>
                <span class="fdv-ref-value">{{ $display($reference['customer_name'] ?? null) }}</span>
            </div>
            <div class="fdv-ref-item">
                <span class="fdv-ref-label">Destination</span>
                <span class="fdv-ref-value fdv-ref-value-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                    {{ $display($reference['destination'] ?? null) }}
                    @if ($reference['branch'] ?? null)
                        <span class="fdv-ref-sub">({{ $reference['branch'] }})</span>
                    @endif
                </span>
            </div>
        </div>
        <div class="fdv-ref-grid fdv-ref-grid-bottom">
            <div class="fdv-ref-item fdv-ref-reason">
                <span class="fdv-ref-label fdv-ref-label-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    Failure Reason
                </span>
                <span class="fdv-ref-value">{{ $display($reference['reason'] ?? null) }}</span>
            </div>
            <div class="fdv-ref-item">
                <span class="fdv-ref-label">Time Reported</span>
                <span class="fdv-ref-value">{{ $display($reference['time_reported'] ?? null) }}</span>
            </div>
            <div class="fdv-ref-item">
                <span class="fdv-ref-label">Remarks</span>
                <span class="fdv-ref-value">{{ $display($reference['remarks'] ?? null) }}</span>
            </div>
            <div class="fdv-ref-item">
                <span class="fdv-ref-label">Proof</span>
                <span class="fdv-ref-value fdv-ref-value-icon">
                    @if ($reference['proof_available'] ?? false)
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" /></svg>
                        Available
                    @else
                        —
                    @endif
                </span>
            </div>
        </div>
    </div>

    <div class="fdv-card">
        <div class="fdv-card-header">
            <div class="fdv-card-title fdv-card-title-inline">Failure Photos</div>
            <div class="fdv-card-meta">
                Uploaded By: {{ $display($photosMeta['uploaded_by'] ?? null) }}
                | Related DO: {{ $display($photosMeta['related_do'] ?? null) }}
            </div>
        </div>
        <div class="fdv-photos-grid">
            @foreach ($photos as $photo)
                <div class="fdv-photo-item">
                    @if ($photo['url'] ?? null)
                        <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] ?? 'Photo' }}" class="fdv-photo-img" />
                    @else
                        <div class="fdv-photo-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" /></svg>
                        </div>
                    @endif
                    <span class="fdv-photo-label">{{ $photo['label'] ?? 'Photo' }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @if ($assignment['editable'] ?? false)
        <div class="fdv-card">
            <div class="fdv-card-title">Select Reassignment Type</div>
            <div class="fdv-type-grid">
                @foreach ($reassignmentTypes as $type)
                    <label @class([
                        'fdv-type-card',
                        'fdv-type-card-selected' => $selectedOption === ($type['value'] ?? ''),
                    ])>
                        <input
                            type="radio"
                            wire:model.live="reassignmentOption"
                            value="{{ $type['value'] }}"
                            class="fdv-type-radio"
                        />
                        <div class="fdv-type-content">
                            <div class="fdv-type-title">{{ $type['label'] ?? '—' }}</div>
                            <div class="fdv-type-description">{{ $type['description'] ?? '' }}</div>
                            <div class="fdv-type-commission">
                                <span class="fdv-type-commission-label">{{ $type['commission_title'] ?? 'Commission' }}</span>
                                <strong>{{ $type['commission_value'] ?? '—' }}</strong>
                                <span class="fdv-type-commission-note">{{ $type['commission_note'] ?? '' }}</span>
                                @if (($type['value'] ?? '') === 'duplicate' && filled($selectedReplacementDriverName))
                                    <span class="fdv-type-driver-note">({{ strtoupper($selectedReplacementDriverName) }})</span>
                                @endif
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    <div class="fdv-reassignment">
        <div class="fdv-card fdv-reassignment-card">
            <div class="fdv-reassignment-tag fdv-reassignment-tag-muted">Original</div>
            <div class="fdv-card-title">Current Failed Assignment</div>
            <div class="fdv-assign-row">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                <span>{{ $display($assignment['original_driver'] ?? null) }}</span>
            </div>
            <div class="fdv-assign-row">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177A48.987 48.987 0 0 0 12 6.75a48.987 48.987 0 0 0-4.5 1.077V18.75" /></svg>
                <span>{{ $display($assignment['original_lorry'] ?? null) }}</span>
            </div>
            <div class="fdv-reassignment-footer">
                <span class="fdv-ref-label">Failed DO</span>
                <span class="fdv-ref-value">{{ $display($assignment['failed_do'] ?? null) }}</span>
            </div>
        </div>

        <div class="fdv-reassignment-arrow" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
        </div>

        <div class="fdv-card fdv-reassignment-card fdv-reassignment-card-form">
            <div class="fdv-reassignment-tag fdv-reassignment-tag-dark">
                {{ $selectedOption === 'duplicate' ? 'Duplicate Reassignment' : 'Standard Reassignment' }}
            </div>
            <div class="fdv-card-title">Replacement Assignment</div>
            @if ($assignment['editable'] ?? false)
                <div class="fdv-form-field">
                    <label class="fdv-form-label" for="replacementDriverId">Replacement Driver</label>
                    <select id="replacementDriverId" wire:model.live="replacementDriverId" class="fdv-form-input">
                        <option value="">Select driver...</option>
                        @foreach ($driverOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fdv-form-field">
                    <label class="fdv-form-label" for="replacementLorryId">Replacement Lorry</label>
                    <select id="replacementLorryId" wire:model.live="replacementLorryId" class="fdv-form-input">
                        <option value="">Select lorry...</option>
                        @foreach ($lorryOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="fdv-assign-row">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                    <span>{{ $display($assignment['replacement_driver'] ?? null) }}</span>
                </div>
                <div class="fdv-assign-row">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177A48.987 48.987 0 0 0 12 6.75a48.987 48.987 0 0 0-4.5 1.077V18.75" /></svg>
                    <span>{{ $display($assignment['replacement_lorry'] ?? null) }}</span>
                </div>
                @if ($assignment['replacement_do'] ?? null)
                    <div class="fdv-reassignment-footer">
                        <span class="fdv-ref-label">Replacement DO</span>
                        <span class="fdv-ref-value">{{ $display($assignment['replacement_do'] ?? null) }}</span>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <div class="fdv-card">
        <div class="fdv-audit-card-header">
            <div class="fdv-card-title fdv-audit-card-title">Reassignment Audit Trail</div>
            <div class="fdv-audit-search-wrap">
                <svg class="fdv-audit-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="auditSearch"
                    class="fdv-audit-search"
                    placeholder="Search audit trail..."
                />
            </div>
        </div>
        <div class="fdv-table-wrap">
            <table class="fdv-table">
                <thead>
                    <tr>
                        @foreach ($sortColumns as $columnKey => $columnLabel)
                            <th>
                                <button
                                    type="button"
                                    wire:click="sortAuditColumn('{{ $columnKey }}')"
                                    @class([
                                        'fdv-sort-btn',
                                        'fdv-sort-btn-active' => $auditSortColumn === $columnKey,
                                    ])
                                >
                                    <span>{{ $columnLabel }}</span>
                                    <span class="fdv-sort-icon">{{ $sortIcon($columnKey) }}</span>
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($auditRows as $row)
                        <tr>
                            <td>{{ $display($row['date_time'] ?? null) }}</td>
                            <td><span class="fdv-type-badge">{{ $display($row['reassignment_type'] ?? null) }}</span></td>
                            <td>{{ $display($row['original_driver'] ?? null) }}</td>
                            <td>{{ $display($row['replacement_driver'] ?? null) }}</td>
                            <td>{{ $display($row['reason'] ?? null) }}</td>
                            <td>{{ $display($row['user'] ?? null) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="fdv-empty">No audit entries yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="fdv-footer">
        <a href="{{ $listUrl }}" wire:navigate class="fdv-btn fdv-btn-secondary">Back to Failed Delivery Review</a>
        @if ($assignment['editable'] ?? false)
            <button type="button" wire:click="saveReassignment" class="fdv-btn fdv-btn-primary">Save</button>
        @endif
    </div>
</div>
