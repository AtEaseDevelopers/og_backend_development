@php
    $listingDetails = $this->getListingDetails();
    $currentScan = $this->getCurrentScanDetail();
    $reconciliationDate = app(\App\Support\ReturnedCsnReconciliationData::class)->reconciliationDateLabel();
    $display = fn ($value): string => filled($value) ? (string) $value : '—';
    $scanReady = ! filled($this->scanInput);
    $csnLocated = filled($currentScan);
    $scanNotFound = filled($this->scanInput) && ! $csnLocated;
    $driverOptions = $this->driverOptions();
    $commissionBanner = $this->commissionBanner;
@endphp

<x-filament-panels::page
    @class([
        'fi-page-returned-csn-desk',
        'rcsn-page',
    ])
>
    <div class="rcsn-page-intro">
        <div class="rcsn-page-badges">
            <span class="rcsn-badge rcsn-badge-muted">HQ VIEW</span>
            <span class="rcsn-badge rcsn-badge-date">Reconciliation Date: {{ $reconciliationDate }}</span>
        </div>
    </div>

    <div class="rcsn-scan-card">
        <div class="rcsn-scan-card-head">
            <h2 class="rcsn-card-title">CSN Return Scanning</h2>
            @if ($csnLocated)
                <span class="rcsn-located-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="rcsn-located-icon">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                    CSN Record Located
                </span>
            @elseif ($scanNotFound)
                <span class="rcsn-located-badge rcsn-located-badge-danger">CSN Not Found</span>
            @endif
        </div>

        <div class="rcsn-scan-body">
            <div class="rcsn-qr-placeholder">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="rcsn-qr-icon">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />
                </svg>
                <span class="rcsn-qr-label">{{ $scanReady ? 'SCAN READY' : 'SCANNED' }}</span>
            </div>

            <div class="rcsn-scan-input-wrap">
                <label class="rcsn-field-label" for="scanInput">Scan Returned CSN QR Code or Enter Manually</label>
                <input
                    id="scanInput"
                    type="text"
                    wire:model.live.debounce.400ms="scanInput"
                    placeholder="# CSN-2608-1208"
                    class="rcsn-scan-input"
                    autocomplete="off"
                />
            </div>
        </div>

        <div class="rcsn-scan-info">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="rcsn-info-icon">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
            </svg>
            Scanning automatically fetches associated Delivery Order and Job Sheet records for verification.
        </div>
    </div>

    @if ($listingDetails !== [])
        <div class="rcsn-listing-section">
            @foreach ($listingDetails as $detail)
                @php($itemIndex = $detail['list_index'] ?? 0)
                <div class="rcsn-detail-card" wire:key="rcsn-item-{{ $detail['consignment_note_id'] ?? $itemIndex }}">
                    <h2 class="rcsn-card-title">Returned CSN Detail</h2>

                    <div class="rcsn-detail-grid">
                        <div class="rcsn-detail-cell">
                            <span class="rcsn-detail-label">CSN</span>
                            <span class="rcsn-detail-value">{{ $display($detail['csn_number'] ?? null) }}</span>
                        </div>
                        <div class="rcsn-detail-cell">
                            <span class="rcsn-detail-label">Customer</span>
                            <span class="rcsn-detail-value">{{ $display($detail['customer_name'] ?? null) }}</span>
                        </div>
                        <div class="rcsn-detail-cell">
                            <span class="rcsn-detail-label">Delivery Order</span>
                            <span class="rcsn-detail-value">{{ $display($detail['do_number'] ?? null) }}</span>
                        </div>
                        <div class="rcsn-detail-cell">
                            <span class="rcsn-detail-label">Job Sheet</span>
                            <span class="rcsn-detail-value">{{ $display($detail['job_sheet_number'] ?? null) }}</span>
                        </div>
                        <div class="rcsn-detail-cell">
                            <span class="rcsn-detail-label">Returned By</span>
                            @if ($detail['eligible_for_return'] ?? false)
                                <select wire:model.live="reconciliationItems.{{ $itemIndex }}.returned_by_driver_id" class="rcsn-detail-select">
                                    <option value="">Select driver</option>
                                    @foreach ($driverOptions as $id => $name)
                                        <option value="{{ $id }}">{{ $name }} (Driver)</option>
                                    @endforeach
                                </select>
                            @else
                                <span class="rcsn-detail-value">
                                    {{ $display($detail['returned_by'] ?? null) }}
                                    @if (filled($detail['returned_by'] ?? null))
                                        <span class="rcsn-detail-muted">(Driver)</span>
                                    @endif
                                </span>
                            @endif
                        </div>
                        <div class="rcsn-detail-cell">
                            <span class="rcsn-detail-label">Received By</span>
                            <span class="rcsn-detail-value">
                                {{ $display($detail['received_by'] ?? null) }}
                                @if (filled($detail['received_by'] ?? null))
                                    <span class="rcsn-detail-muted">(Office User)</span>
                                @endif
                            </span>
                        </div>
                        <div class="rcsn-detail-cell">
                            <span class="rcsn-detail-label">Return Date / Time</span>
                            <span class="rcsn-detail-value">{{ $display($detail['returned_at'] ?? now()->format('d/m/Y H:i')) }}</span>
                        </div>
                    </div>

                    @if ($detail['eligible_for_return'] ?? false)
                        <div class="rcsn-edit-row">
                            <label class="rcsn-toggle-field">
                                <input type="checkbox" wire:model.live="reconciliationItems.{{ $itemIndex }}.is_signed" class="rcsn-toggle-input" />
                                <span class="rcsn-toggle-label">Recipient signed</span>
                            </label>
                            <label class="rcsn-toggle-field">
                                <input type="checkbox" wire:model.live="reconciliationItems.{{ $itemIndex }}.is_stamped" class="rcsn-toggle-input" />
                                <span class="rcsn-toggle-label">Customer stamped</span>
                            </label>
                        </div>

                        <div class="rcsn-remarks-wrap">
                            <label class="rcsn-field-label" for="remarks-{{ $itemIndex }}">Remarks</label>
                            <textarea
                                id="remarks-{{ $itemIndex }}"
                                wire:model.defer="reconciliationItems.{{ $itemIndex }}.remarks"
                                rows="3"
                                class="rcsn-remarks-input"
                                placeholder="Optional notes about the returned CSN"
                            ></textarea>
                        </div>
                    @endif

                    <div class="rcsn-status-tags">
                        @foreach ($detail['status_tags'] ?? [] as $tag)
                            <span @class([
                                'rcsn-status-tag',
                                'rcsn-status-tag-' . ($tag['tone'] ?? 'muted'),
                            ])>
                                <span class="rcsn-status-tag-label">{{ $tag['label'] }}:</span>
                                {{ $tag['value'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($commissionBanner)
        <div class="rcsn-commission-banner">
            <div class="rcsn-commission-left">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="rcsn-commission-icon">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
                {{ $commissionBanner['message'] ?? 'Commission eligibility updated for the assigned driver.' }}
            </div>
            @if (filled($commissionBanner['related_do'] ?? null))
                <span class="rcsn-commission-related">Related DO: {{ $commissionBanner['related_do'] }}</span>
            @endif
        </div>
    @endif

    <div class="rcsn-secondary-actions">
        <button type="button" wire:click="flagMissing" class="rcsn-flag-missing-btn">
            Flag overdue as missing
        </button>
        <span class="rcsn-grace-note">
            Grace period: {{ config('og.missing_csn_days') }} days after delivery.
        </span>
    </div>
</x-filament-panels::page>
