@php
    $header = $header ?? [];
    $stepper = $stepper ?? [];
    $tracking = $tracking ?? [];
    $information = $information ?? [];
    $assignment = $assignment ?? [];
    $summary = $summary ?? [];
    $groupedTasks = $grouped_tasks ?? [];
    $driverOptions = $driver_options ?? [];
    $lorryOptions = $lorry_options ?? [];
    $listUrl = $list_url ?? '#';

    $display = fn ($value): string => filled($value) ? (string) $value : '—';
@endphp

<div class="jsv-view">
    @include('filament.infolists.partials.job-sheet-context', [
        'stepper' => $stepper,
        'tracking' => $tracking,
        'information' => $information,
        'assignment' => $assignment,
        'summary' => $summary,
        'driver_options' => $driverOptions,
        'lorry_options' => $lorryOptions,
        'read_only' => false,
    ])

    {{-- Grouped tasks --}}
    <div class="jsv-card">
        <div class="jsv-card-title">Grouped Tasks</div>

        @forelse ($groupedTasks as $group)
            <div class="jsv-task-group">
                <div class="jsv-task-group-header">
                    <span class="jsv-task-group-code">{{ $group['code'] ?? '—' }}</span>
                    <span class="jsv-task-group-type">{{ $group['type'] ?? 'DELIVERY' }}</span>
                    <span class="jsv-task-group-meta">STATE: {{ $group['state'] ?? '—' }}</span>
                    <span class="jsv-task-group-meta">POSTCODE: {{ $group['postcode'] ?? '—' }}</span>
                    <span class="jsv-task-group-count">{{ ($group['csn_count'] ?? 0) }} CSNs • {{ ($group['do_count'] ?? 0) }} DOs</span>
                </div>
                <div class="jsv-table-wrap">
                    <table class="jsv-table">
                        <thead>
                            <tr>
                                <th>CSN / DO Ref</th>
                                <th>Customer</th>
                                <th>Destination</th>
                                <th>Transfer Allocation</th>
                                <th>Driver / Lorry</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['rows'] ?? [] as $row)
                                <tr>
                                    <td>
                                        <div class="jsv-table-primary">{{ $display($row['csn_number'] ?? null) }}</div>
                                        <div class="jsv-table-secondary">{{ $display($row['do_number'] ?? null) }}</div>
                                    </td>
                                    <td>{{ $display($row['customer'] ?? null) }}</td>
                                    <td>{{ $display($row['destination'] ?? null) }}</td>
                                    <td>{{ $display($row['transfer_allocation'] ?? null) }}</td>
                                    <td>{{ $display($row['driver_lorry'] ?? null) }}</td>
                                    <td>
                                        @if ($row['do_url'] ?? null)
                                            <a href="{{ $row['do_url'] }}" wire:navigate class="jsv-action-link" title="View delivery order">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="jsv-empty">No tasks assigned to this job sheet yet.</div>
        @endforelse
    </div>

    {{-- Sticky footer --}}
    <div class="jsv-footer">
        <div class="jsv-footer-left">
            <strong>{{ $display($header['number'] ?? null) }}</strong>
            <span>{{ strtoupper($display($header['status_label'] ?? null)) }} STATUS</span>
        </div>
        <div class="jsv-footer-center">
            <span>{{ $summary['route_groups'] ?? 0 }} Route Groups</span>
            <span>{{ $summary['subsheets'] ?? 0 }} Subsheets</span>
            <span>{{ $summary['csns'] ?? 0 }} CSNs</span>
            <span>{{ $summary['dos'] ?? 0 }} DOs</span>
        </div>
        <div class="jsv-footer-actions">
            <a href="{{ $listUrl }}" wire:navigate class="jsv-btn jsv-btn-secondary">Back to Listing</a>
            @if ($assignment['editable'] ?? false)
                <button type="button" wire:click="saveDraft" class="jsv-btn jsv-btn-primary">Save Draft</button>
            @endif
        </div>
    </div>
</div>
