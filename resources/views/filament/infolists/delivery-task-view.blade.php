@php
    $header = $header ?? [];
    $overview = $overview ?? [];
    $photos = $photos ?? [];
    $photosMeta = $photos_meta ?? [];
    $documents = $documents ?? [];
    $signature = $signature ?? [];
    $gps = $gps ?? [];
    $offlineSync = $offline_sync ?? [];
    $timestamps = $timestamps ?? [];
    $monitoringUrl = $monitoring_url ?? '#';

    $display = fn ($value): string => filled($value) ? (string) $value : '—';
@endphp

<div class="dtv-view">
    <div class="dtv-header-badges">
        @if ($header['do_number'] ?? null)
            <span class="dtv-meta-badge">DO: {{ $header['do_number'] }}</span>
        @endif
        @if ($header['job_sheet_number'] ?? null)
            <span class="dtv-meta-badge">Job Sheet: {{ $header['job_sheet_number'] }}</span>
        @endif
        <span @class([
            'dtv-status-badge',
            'dtv-status-badge-' . ($header['status_color'] ?? 'gray'),
        ])>
            @if (($header['status_color'] ?? null) === 'success')
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            @endif
            {{ strtoupper($display($header['status_label'] ?? null)) }}
        </span>
    </div>

    {{-- Task Overview --}}
    <div class="dtv-card">
        <div class="dtv-card-header">
            <div class="dtv-card-title">Task Overview</div>
        </div>
        <div class="dtv-overview-grid">
            <div class="dtv-overview-item">
                <span class="dtv-label">Branch</span>
                <span class="dtv-value">{{ $display($overview['branch'] ?? null) }}</span>
            </div>
            <div class="dtv-overview-item">
                <span class="dtv-label">Driver</span>
                <span class="dtv-value">{{ $display($overview['driver'] ?? null) }}</span>
            </div>
            <div class="dtv-overview-item">
                <span class="dtv-label">Lorry</span>
                <span class="dtv-value dtv-value-box">{{ $display($overview['lorry'] ?? null) }}</span>
            </div>
            <div class="dtv-overview-item">
                <span class="dtv-label">Date</span>
                <span class="dtv-value">{{ $display($overview['date'] ?? null) }}</span>
            </div>
            <div class="dtv-overview-item dtv-overview-destination">
                <span class="dtv-label">Destination</span>
                <span class="dtv-value dtv-value-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                    {{ $display($overview['destination'] ?? null) }}
                </span>
            </div>
        </div>
    </div>

    {{-- Proof of Delivery Photos --}}
    <div class="dtv-card">
        <div class="dtv-card-header">
            <div class="dtv-card-title">Proof of Delivery</div>
            <div class="dtv-card-meta">
                Uploaded By: {{ $display($photosMeta['uploaded_by'] ?? null) }}
                · Related DO: {{ $display($photosMeta['related_do'] ?? null) }}
            </div>
        </div>
        <div class="dtv-photos-grid">
            @foreach ($photos as $photo)
                <div class="dtv-photo-item">
                    @if ($photo['url'] ?? null)
                        <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] ?? 'Photo' }}" class="dtv-photo-img" />
                    @else
                        <div class="dtv-photo-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" /></svg>
                        </div>
                    @endif
                    <span class="dtv-photo-label">{{ $photo['label'] ?? 'Photo' }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 2x2 detail grid --}}
    <div class="dtv-detail-grid">
        <div class="dtv-card">
            <div class="dtv-card-title">Signed Delivery Documents</div>
            @forelse ($documents as $document)
                <div class="dtv-document-row">
                    <div class="dtv-document-info">
                        <span class="dtv-document-name">{{ $display($document['name'] ?? null) }}</span>
                        <span class="dtv-document-meta">{{ $display($document['size'] ?? null) }}</span>
                    </div>
                    <div class="dtv-document-actions">
                        <span class="dtv-doc-badge">{{ $display($document['status'] ?? null) }}</span>
                        @if ($document['url'] ?? null)
                            <a href="{{ $document['url'] }}" target="_blank" rel="noopener" class="dtv-download-btn" title="Download">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="dtv-empty">No signed documents uploaded yet.</div>
            @endforelse
        </div>

        <div class="dtv-card">
            <div class="dtv-card-title">Recipient Signature</div>
            <div class="dtv-signature-block">
                <span class="dtv-label">Signee Name</span>
                <span class="dtv-value">{{ $display($signature['signee_name'] ?? null) }}</span>
                <div class="dtv-signature-box">
                    @if ($signature['signature_url'] ?? null)
                        <img src="{{ $signature['signature_url'] }}" alt="Recipient signature" class="dtv-signature-img" />
                    @else
                        <span class="dtv-signature-placeholder">No signature recorded</span>
                    @endif
                </div>
                <span class="dtv-signature-time">{{ $display($signature['signed_at'] ?? null) }}</span>
            </div>
            <p class="dtv-footnote">Recipient signature is recorded as delivery proof.</p>
        </div>

        <div class="dtv-card">
            <div class="dtv-card-title">Recorded GPS</div>
            <div class="dtv-gps-card">
                <div class="dtv-gps-icon-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                </div>
                <div>
                    <div class="dtv-gps-location">{{ $display($gps['location'] ?? null) }}</div>
                    <div class="dtv-gps-coords">
                        Latitude: {{ $display($gps['latitude'] ?? null) }}
                        · Longitude: {{ $display($gps['longitude'] ?? null) }}
                    </div>
                </div>
            </div>
            <div class="dtv-meta-grid">
                <div><span class="dtv-label">GPS Record Type</span><span class="dtv-value">{{ $display($gps['record_type'] ?? null) }}</span></div>
                <div><span class="dtv-label">Recorded By</span><span class="dtv-value">{{ $display($gps['recorded_by'] ?? null) }}</span></div>
                <div><span class="dtv-label">Related DO</span><span class="dtv-value">{{ $display($gps['related_do'] ?? null) }}</span></div>
            </div>
            <p class="dtv-footnote">GPS information reflects the location recorded through the Driver App.</p>
        </div>

        <div class="dtv-card">
            <div class="dtv-card-title">Offline Synchronization</div>
            <div class="dtv-sync-table">
                <div class="dtv-sync-row">
                    <span>Offline Record Supported</span>
                    <strong>{{ $display($offlineSync['offline_supported'] ?? null) }}</strong>
                </div>
                <div class="dtv-sync-row">
                    <span>Local Driver App Record</span>
                    <strong>{{ $display($offlineSync['local_record'] ?? null) }}</strong>
                </div>
                <div class="dtv-sync-row">
                    <span>Latest Synchronization</span>
                    <strong>{{ $display($offlineSync['latest_sync'] ?? null) }}</strong>
                </div>
                <div class="dtv-sync-row">
                    <span>Synchronization Result</span>
                    @if ($offlineSync['sync_success'] ?? false)
                        <span class="dtv-sync-badge">Synchronized</span>
                    @else
                        <strong>{{ $display($offlineSync['sync_result'] ?? null) }}</strong>
                    @endif
                </div>
            </div>
            <p class="dtv-footnote">Offline records remain available locally until synchronized with HQ.</p>
        </div>
    </div>

    {{-- Operational Timestamps --}}
    <div class="dtv-card">
        <div class="dtv-card-title">Operational Timestamps</div>
        <div class="dtv-table-wrap">
            <table class="dtv-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Recorded Source</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($timestamps as $row)
                        <tr>
                            <td @class(['dtv-event-highlight' => $row['highlight'] ?? false])>{{ $display($row['event'] ?? null) }}</td>
                            <td @class(['dtv-event-highlight' => $row['highlight'] ?? false])>{{ $display($row['date'] ?? null) }}</td>
                            <td @class(['dtv-event-highlight' => $row['highlight'] ?? false])>{{ $display($row['time'] ?? null) }}</td>
                            <td>{{ $display($row['source'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="dtv-footer">
        <a href="{{ $monitoringUrl }}" wire:navigate class="dtv-btn dtv-btn-secondary">Back to Delivery Monitoring</a>
    </div>
</div>
