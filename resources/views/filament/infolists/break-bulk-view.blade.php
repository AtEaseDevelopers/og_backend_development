@php
    $header = $header ?? [];
    $reference = $reference ?? [];
    $photos = $photos ?? [];
    $photosMeta = $photos_meta ?? [];
    $reassignment = $reassignment ?? [];
    $driverOptions = $driver_options ?? [];
    $lorryOptions = $lorry_options ?? [];
    $auditRows = $audit_rows ?? [];
    $listUrl = $list_url ?? '#';
    $auditSortColumn = $audit_sort_column ?? 'sort_at';
    $auditSortDirection = $audit_sort_direction ?? 'desc';

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
@endphp

<div class="bbv-view">
    {{-- Header badges --}}
    <div class="bbv-header-badges">
        @if ($header['csn_number'] ?? null)
            <span class="bbv-meta-badge">CSN: {{ $header['csn_number'] }}</span>
        @endif
        @if ($header['do_number'] ?? null)
            <span class="bbv-meta-badge">DO: {{ $header['do_number'] }}</span>
        @endif
        @if ($header['job_sheet_number'] ?? null)
            <span class="bbv-meta-badge">Job Sheet: {{ $header['job_sheet_number'] }}</span>
        @endif
        <span @class([
            'bbv-status-badge',
            'bbv-status-badge-' . ($header['status_color'] ?? 'info'),
        ])>Status: {{ $display($header['status_label'] ?? null) }}</span>
    </div>

    {{-- Break-Bulk Reference --}}
    <div class="bbv-card">
        <div class="bbv-card-title">Break-Bulk Reference</div>
        <div class="bbv-ref-grid bbv-ref-grid-top">
            <div class="bbv-ref-item">
                <span class="bbv-ref-label">Delivery Order</span>
                <span class="bbv-ref-value">{{ $display($reference['do_number'] ?? null) }}</span>
            </div>
            <div class="bbv-ref-item">
                <span class="bbv-ref-label">Job Sheet</span>
                <span class="bbv-ref-value">{{ $display($reference['job_sheet_number'] ?? null) }}</span>
            </div>
            <div class="bbv-ref-item">
                <span class="bbv-ref-label">Date</span>
                <span class="bbv-ref-value">{{ $display($reference['date'] ?? null) }}</span>
            </div>
            <div class="bbv-ref-item">
                <span class="bbv-ref-label">Status</span>
                <span class="bbv-ref-badge bbv-ref-badge-danger">{{ $display($reference['status_label'] ?? null) }}</span>
            </div>
        </div>
        <div class="bbv-ref-grid bbv-ref-grid-middle">
            <div class="bbv-ref-item">
                <span class="bbv-ref-label">Customer Name</span>
                <span class="bbv-ref-value">{{ $display($reference['customer_name'] ?? null) }}</span>
            </div>
            <div class="bbv-ref-item">
                <span class="bbv-ref-label">Break-bulk Location</span>
                <span class="bbv-ref-value bbv-ref-value-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                    {{ $display($reference['location'] ?? null) }}
                </span>
            </div>
            <div class="bbv-ref-item">
                <span class="bbv-ref-label">Destination</span>
                <span class="bbv-ref-value bbv-ref-value-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                    {{ $display($reference['destination'] ?? null) }}
                </span>
            </div>
        </div>
        <div class="bbv-ref-grid bbv-ref-grid-bottom">
            <div class="bbv-ref-item bbv-ref-reason">
                <span class="bbv-ref-label bbv-ref-label-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    Failure Reason
                </span>
                <span class="bbv-ref-value">{{ $display($reference['reason'] ?? null) }}</span>
            </div>
            <div class="bbv-ref-item">
                <span class="bbv-ref-label">Time Reported</span>
                <span class="bbv-ref-value">{{ $display($reference['time_reported'] ?? null) }}</span>
            </div>
            <div class="bbv-ref-item">
                <span class="bbv-ref-label">Remarks</span>
                <span class="bbv-ref-value">{{ $display($reference['remarks'] ?? null) }}</span>
            </div>
            <div class="bbv-ref-item">
                <span class="bbv-ref-label">Proof</span>
                <span class="bbv-ref-value bbv-ref-value-icon">
                    @if ($reference['proof_available'] ?? false)
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                        Available
                    @else
                        —
                    @endif
                </span>
            </div>
        </div>
    </div>

    {{-- Photos --}}
    <div class="bbv-card">
        <div class="bbv-card-title">Breakbulk Photos</div>
        <div class="bbv-photos-grid">
            @foreach ($photos as $photo)
                <div class="bbv-photo-item">
                    @if ($photo['url'] ?? null)
                        <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] ?? 'Photo' }}" class="bbv-photo-img" />
                    @else
                        <div class="bbv-photo-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" /></svg>
                        </div>
                    @endif
                    <span class="bbv-photo-label">{{ $photo['label'] ?? 'Photo' }}</span>
                </div>
            @endforeach
        </div>
        <div class="bbv-photos-meta">
            Uploaded By: {{ $display($photosMeta['uploaded_by'] ?? null) }}
            | Related DO: {{ $display($photosMeta['related_do'] ?? null) }}
        </div>
    </div>

    {{-- Reassignment --}}
    <div class="bbv-reassignment">
        <div class="bbv-card bbv-reassignment-card">
            <div class="bbv-reassignment-tag bbv-reassignment-tag-muted">Original</div>
            <div class="bbv-card-title">Current Breakbulk Driver</div>
            <div class="bbv-assign-row">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                <span>{{ $display($reassignment['original_driver'] ?? null) }}</span>
            </div>
            <div class="bbv-assign-row">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177A48.987 48.987 0 0 0 12 6.75a48.987 48.987 0 0 0-4.5 1.077V18.75" /></svg>
                <span>{{ $display($reassignment['original_lorry'] ?? null) }}</span>
            </div>
            <div class="bbv-reassignment-footer">
                <span class="bbv-ref-label">Break-Bulk DO</span>
                <span class="bbv-ref-value">{{ $display($reassignment['break_bulk_do'] ?? null) }}</span>
            </div>
        </div>

        <div class="bbv-reassignment-arrow" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
        </div>

        <div class="bbv-card bbv-reassignment-card bbv-reassignment-card-form">
            <div class="bbv-reassignment-tag bbv-reassignment-tag-dark">Standard Reassignment</div>
            <div class="bbv-card-title">Replacement Assignment</div>
            @if ($reassignment['editable'] ?? false)
                <div class="bbv-form-field">
                    <label class="bbv-form-label" for="replacementDriverId">Replacement Driver</label>
                    <select id="replacementDriverId" wire:model.live="replacementDriverId" class="bbv-form-input">
                        <option value="">Select driver...</option>
                        @foreach ($driverOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="bbv-form-field">
                    <label class="bbv-form-label" for="replacementLorryId">Replacement Lorry</label>
                    <select id="replacementLorryId" wire:model.live="replacementLorryId" class="bbv-form-input">
                        <option value="">Select lorry...</option>
                        @foreach ($lorryOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="bbv-info-box">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                    A new sub-sheet will be created
                </div>
            @else
                <div class="bbv-assign-row">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                    <span>{{ $display($reassignment['replacement_driver'] ?? null) }}</span>
                </div>
                <div class="bbv-assign-row">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177A48.987 48.987 0 0 0 12 6.75a48.987 48.987 0 0 0-4.5 1.077V18.75" /></svg>
                    <span>{{ $display($reassignment['replacement_lorry'] ?? null) }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Audit trail --}}
    <div class="bbv-card">
        <div class="bbv-audit-card-header">
            <div class="bbv-card-title bbv-audit-card-title">Reassignment Audit Trail</div>
            <div class="bbv-audit-search-wrap">
                <svg class="bbv-audit-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="auditSearch"
                    class="bbv-audit-search"
                    placeholder="Search audit trail..."
                />
            </div>
        </div>
        <div class="bbv-table-wrap">
            <table class="bbv-table">
                <thead>
                    <tr>
                        @foreach ($sortColumns as $columnKey => $columnLabel)
                            <th>
                                <button
                                    type="button"
                                    wire:click="sortAuditColumn('{{ $columnKey }}')"
                                    @class([
                                        'bbv-sort-btn',
                                        'bbv-sort-btn-active' => $auditSortColumn === $columnKey,
                                    ])
                                >
                                    <span>{{ $columnLabel }}</span>
                                    <span class="bbv-sort-icon">{{ $sortIcon($columnKey) }}</span>
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($auditRows as $row)
                        <tr>
                            <td>{{ $display($row['date_time'] ?? null) }}</td>
                            <td><span class="bbv-type-badge">{{ $display($row['reassignment_type'] ?? null) }}</span></td>
                            <td>{{ $display($row['original_driver'] ?? null) }}</td>
                            <td>{{ $display($row['replacement_driver'] ?? null) }}</td>
                            <td>{{ $display($row['reason'] ?? null) }}</td>
                            <td>{{ $display($row['user'] ?? null) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="bbv-empty">No audit entries yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Footer --}}
    <div class="bbv-footer">
        <div class="bbv-footer-left">
            <strong>{{ $display($header['do_number'] ?? null) }}</strong>
            <span>{{ strtoupper($display($header['status_label'] ?? null)) }}</span>
        </div>
        <div class="bbv-footer-actions">
            <a href="{{ $listUrl }}" wire:navigate class="bbv-btn bbv-btn-secondary">Back to Break Bulk Listing</a>
            @if ($reassignment['editable'] ?? false)
                <button type="button" wire:click="saveReassignment" class="bbv-btn bbv-btn-primary">Save</button>
            @endif
        </div>
    </div>
</div>
