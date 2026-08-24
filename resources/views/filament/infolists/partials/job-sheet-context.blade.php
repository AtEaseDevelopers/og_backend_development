@php
    $stepper = $stepper ?? [];
    $tracking = $tracking ?? [];
    $information = $information ?? [];
    $assignment = $assignment ?? [];
    $summary = $summary ?? [];
    $driverOptions = $driver_options ?? [];
    $lorryOptions = $lorry_options ?? [];
    $readOnly = $read_only ?? ! ($assignment['editable'] ?? false);

    $display = fn ($value): string => filled($value) ? (string) $value : '—';
@endphp

<div class="jsv-card jsv-stepper-card">
    <div class="jsv-stepper">
        @foreach ($stepper as $step)
            <div @class(['jsv-step', 'jsv-step-' . ($step['state'] ?? 'upcoming')])>
                <div class="jsv-step-icon">
                    @if (($step['state'] ?? '') === 'done')
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    @elseif ($step['key'] === 'in_transit')
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
                    @elseif ($step['key'] === 'completed')
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.25-.75M3 15l2.25.75M21 3v1.5M21 21v-6m0 0-2.25-.75M21 15l-2.25.75M9 3h6m-6 18h6M9 8.25h6M9 12h6M9 15.75h6" /></svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    @endif
                </div>
                <div class="jsv-step-label">{{ $step['label'] ?? '' }}</div>
            </div>
        @endforeach
    </div>
    <div class="jsv-stepper-notes">
        <p>Job Sheet changes to <strong>In Transit</strong> after driver check-in and journey commencement.</p>
        <p>Job Sheet changes to <strong>Completed</strong> when all delivery orders are delivered or closed.</p>
    </div>
</div>

<div class="jsv-tracking-bar">
    <div class="jsv-tracking-item">
        <div class="jsv-tracking-label">Driver Check-in</div>
        <div @class(['jsv-tracking-value', 'jsv-tracking-success' => $tracking['driver_check_in_done'] ?? false])>{{ $display($tracking['driver_check_in'] ?? null) }}</div>
    </div>
    <div class="jsv-tracking-item">
        <div class="jsv-tracking-label">Journey Commencement</div>
        <div @class(['jsv-tracking-value', 'jsv-tracking-success' => $tracking['journey_commencement_done'] ?? false])>{{ $display($tracking['journey_commencement'] ?? null) }}</div>
    </div>
    <div class="jsv-tracking-item">
        <div class="jsv-tracking-label">In-Transit Start Date</div>
        <div class="jsv-tracking-value">{{ $display($tracking['in_transit_start_date'] ?? null) }}</div>
    </div>
    <div class="jsv-tracking-item">
        <div class="jsv-tracking-label">In-Transit Start Time</div>
        <div class="jsv-tracking-value">{{ $display($tracking['in_transit_start_time'] ?? null) }}</div>
    </div>
</div>

<div class="jsv-grid-2">
    <div class="jsv-card">
        <div class="jsv-card-title">Job Sheet Information</div>
        <div class="jsv-grid-2 jsv-info-grid">
            <div class="jsv-field">
                <div class="jsv-label">Job Sheet No</div>
                <div class="jsv-value jsv-value-strong">{{ $display($information['number'] ?? null) }}</div>
            </div>
            <div class="jsv-field">
                <div class="jsv-label">Operating Date</div>
                <div class="jsv-value">{{ $display($information['operating_date'] ?? null) }}</div>
            </div>
            <div class="jsv-field">
                <div class="jsv-label">Operating Branch</div>
                <div class="jsv-value">{{ $display($information['operating_branch'] ?? null) }}</div>
            </div>
            <div class="jsv-field">
                <div class="jsv-label">Status</div>
                <div class="jsv-value">
                    <span @class(['jsv-status-badge', 'jsv-status-badge-' . ($information['status_color'] ?? 'gray')])>{{ $display($information['status_label'] ?? null) }}</span>
                </div>
            </div>
        </div>
        <div class="jsv-summary-row">
            <div class="jsv-summary-stat"><strong>{{ $summary['route_groups'] ?? 0 }}</strong><span>Route Groups</span></div>
            <div class="jsv-summary-stat"><strong>{{ $summary['subsheets'] ?? 0 }}</strong><span>Subsheets</span></div>
            <div class="jsv-summary-stat"><strong>{{ $summary['csns'] ?? 0 }}</strong><span>CSNs</span></div>
            <div class="jsv-summary-stat"><strong>{{ $summary['dos'] ?? 0 }}</strong><span>DOs</span></div>
        </div>
    </div>

    <div class="jsv-card">
        <div class="jsv-card-title">Lorry &amp; Driver Assignment</div>
        <div class="jsv-grid-2 jsv-info-grid">
            <div class="jsv-field">
                <div class="jsv-label">Lorry Number</div>
                <div class="jsv-value jsv-value-box">{{ $display($assignment['lorry_number'] ?? null) }}</div>
            </div>
            <div class="jsv-field">
                <div class="jsv-label">Default Driver</div>
                <div class="jsv-value">{{ $display($assignment['default_driver'] ?? null) }}</div>
            </div>
        </div>

        <div class="jsv-assignment-field">
            <div class="jsv-assignment-label-row">
                <div class="jsv-label">Current Driver</div>
                @if (! $readOnly && ($assignment['editable'] ?? false))
                    <span class="jsv-editable-badge">Editable in Draft</span>
                @endif
            </div>
            @if (! $readOnly && ($assignment['editable'] ?? false))
                <select wire:model="driverId" class="jsv-select">
                    <option value="">Select driver</option>
                    @foreach ($driverOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            @else
                <div class="jsv-value jsv-value-box">{{ $display($assignment['current_driver'] ?? null) }}</div>
            @endif
        </div>

        <div class="jsv-assignment-field">
            <div class="jsv-assignment-label-row">
                <div class="jsv-label">Assigned Lorry</div>
                @if (! $readOnly && ($assignment['editable'] ?? false))
                    <span class="jsv-editable-badge">Editable in Draft</span>
                @endif
            </div>
            @if (! $readOnly && ($assignment['editable'] ?? false))
                <select wire:model="lorryId" class="jsv-select">
                    <option value="">Select lorry</option>
                    @foreach ($lorryOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            @else
                <div class="jsv-value jsv-value-box">{{ $display($assignment['lorry_number'] ?? null) }}</div>
            @endif
        </div>
    </div>
</div>
