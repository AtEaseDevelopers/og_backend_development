@php
    $header = $header ?? [];
    $stepper = $stepper ?? [];
    $tracking = $tracking ?? [];
    $information = $information ?? [];
    $assignment = $assignment ?? [];
    $summary = $summary ?? [];
    $auditRows = $audit_rows ?? [];
    $viewUrl = $view_url ?? '#';
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
        'date' => 'Date',
        'time' => 'Time',
        'user' => 'User',
        'change_type' => 'Change Type',
        'original_value' => 'Original Value',
        'new_value' => 'New Value',
    ];
@endphp

<div class="jsv-view jsv-audit-view">
    @include('filament.infolists.partials.job-sheet-context', [
        'stepper' => $stepper,
        'tracking' => $tracking,
        'information' => $information,
        'assignment' => $assignment,
        'summary' => $summary,
        'read_only' => true,
    ])

    <div class="jsv-card">
        <div class="jsv-audit-card-header">
            <div class="jsv-card-title jsv-audit-card-title">Job Sheet Change Audit</div>
            <div class="jsv-audit-search-wrap">
                <svg class="jsv-audit-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="auditSearch"
                    class="jsv-audit-search"
                    placeholder="Search audit log..."
                />
            </div>
        </div>
        <div class="jsv-table-wrap">
            <table class="jsv-table jsv-audit-table">
                <thead>
                    <tr>
                        @foreach ($sortColumns as $columnKey => $columnLabel)
                            <th>
                                <button
                                    type="button"
                                    wire:click="sortAuditColumn('{{ $columnKey }}')"
                                    @class([
                                        'jsv-sort-btn',
                                        'jsv-sort-btn-active' => $auditSortColumn === $columnKey,
                                    ])
                                >
                                    <span>{{ $columnLabel }}</span>
                                    <span class="jsv-sort-icon">{{ $sortIcon($columnKey) }}</span>
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($auditRows as $row)
                        <tr wire:key="audit-row-{{ $row['sort_at'] ?? 0 }}-{{ md5(json_encode($row)) }}">
                            <td>{{ $display($row['date'] ?? null) }}</td>
                            <td>{{ $display($row['time'] ?? null) }}</td>
                            <td>{{ $display($row['user'] ?? null) }}</td>
                            <td>{{ $display($row['change_type'] ?? null) }}</td>
                            <td>{{ $display($row['original_value'] ?? null) }}</td>
                            <td><strong>{{ $display($row['new_value'] ?? null) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="jsv-empty">
                                @if (filled($audit_search ?? null))
                                    No audit entries match your search.
                                @else
                                    No audit entries recorded for this job sheet yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="jsv-footer jsv-audit-footer">
        <div class="jsv-footer-left jsv-audit-footer-meta">
            <span><strong>Job Sheet:</strong> {{ $display($header['number'] ?? null) }}</span>
            <span><strong>Status:</strong> {{ $display($header['status_label'] ?? null) }}</span>
            <span><strong>Lorry/Driver:</strong> {{ $display($assignment['lorry_number'] ?? null) }} / {{ $display($assignment['current_driver'] ?? null) }}</span>
        </div>
        <div class="jsv-footer-actions">
            <a href="{{ $viewUrl }}" wire:navigate class="jsv-btn jsv-btn-secondary">Back to Jobsheet</a>
        </div>
    </div>
</div>
