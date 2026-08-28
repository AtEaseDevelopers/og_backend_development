@php
    $queueRows = $this->getQueueRows();
    $verification = $this->getVerificationData();
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-ocr-uploads',
        'ocr-page',
    ])
>
    <div
        @if($this->hasExtractingUploads() || $this->awaitingExtractionId)
            wire:poll.2s="pollExtractionQueue"
        @endif
    >
    <div class="ocr-page-intro">
        <span class="ocr-badge ocr-badge-muted">HQ VIEW</span>
    </div>

    <div class="ocr-dashboard">
        <section class="ocr-upload-card">
            <h2 class="ocr-card-title">Upload Quotation</h2>
            <p class="ocr-card-note">Drag and drop a hardcopy quotation here, or click to browse. Supports PDF, JPG, PNG.</p>

            <label class="ocr-dropzone">
                <input type="file" wire:model="uploadFile" accept=".pdf,.jpg,.jpeg,.png" class="ocr-dropzone-input" />
                <div class="ocr-dropzone-inner">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    <span class="ocr-dropzone-title">Drop quotation file here</span>
                    <span class="ocr-dropzone-sub">PDF, JPG or PNG up to 10 MB</span>
                </div>
            </label>

            <div class="ocr-upload-actions">
                <button type="button" wire:click="processUpload" wire:loading.attr="disabled" class="ocr-btn ocr-btn-primary">
                    Upload &amp; Extract
                </button>
            </div>
        </section>

        <section class="ocr-queue-card">
            <h2 class="ocr-card-title">Active Queue</h2>
            <div class="ocr-table-wrap">
                <table class="ocr-table">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Uploaded By</th>
                            <th>Timestamp</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($queueRows as $row)
                            <tr wire:key="ocr-queue-{{ $row['id'] }}">
                                <td>
                                    @if($row['can_review'])
                                        <button type="button" wire:click="openVerification({{ $row['id'] }})" class="ocr-file-link">
                                            {{ $row['filename'] }}
                                        </button>
                                    @else
                                        <span class="ocr-file-name">{{ $row['filename'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $row['uploaded_by'] }}</td>
                                <td>{{ $row['timestamp'] }}</td>
                                <td>
                                    @if($row['status'] === 'extracting')
                                        <div class="ocr-status-extracting">
                                            <span>{{ $row['status_label'] }}</span>
                                            @if(filled($row['progress_message']))
                                                <small>{{ $row['progress_message'] }}</small>
                                            @endif
                                            <div class="ocr-progress-track">
                                                <div class="ocr-progress-bar" style="width: {{ max($row['progress'], 5) }}%"></div>
                                            </div>
                                        </div>
                                    @elseif($row['is_failed'] ?? false)
                                        <span class="ocr-status-pill ocr-status-failed">{{ $row['status_label'] }}</span>
                                    @else
                                        <span @class([
                                            'ocr-status-pill',
                                            'ocr-status-pending' => $row['status'] === 'pending_review',
                                            'ocr-status-completed' => $row['status'] === 'completed',
                                            'ocr-status-draft' => $row['status'] === 'draft',
                                        ])>
                                            {{ $row['status_label'] }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="ocr-empty">No OCR uploads yet. Upload a quotation to begin.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @if($verification)
        <div class="ocr-verify-overlay">
            <section class="ocr-verify-panel">
                <div class="ocr-verify-head">
                    <div>
                        <h2 class="ocr-verify-title">Data Verification: {{ $verification['filename'] }}</h2>
                    </div>
                    <div class="ocr-verify-head-actions">
                        <span class="ocr-confidence-badge">{{ $verification['confidence_label'] }}</span>
                        <button type="button" wire:click="closeVerification" class="ocr-close-btn" aria-label="Close verification panel">&times;</button>
                    </div>
                </div>

                <div class="ocr-verify-grid">
                    <div class="ocr-preview-card">
                        @if(str_starts_with($verification['mime_type'], 'image/'))
                            <img src="{{ $verification['document_url'] }}" alt="Uploaded quotation" class="ocr-preview-image" />
                        @elseif($verification['mime_type'] === 'application/pdf')
                            <iframe src="{{ $verification['document_url'] }}" title="Uploaded quotation" class="ocr-preview-frame"></iframe>
                        @else
                            <div class="ocr-preview-paper">
                                <div class="ocr-preview-header">QUOTATION</div>
                                @foreach($verification['preview'] as $block)
                                    <div @class(['ocr-preview-block', 'ocr-preview-'.$block['tone']])>
                                        <span class="ocr-preview-label">{{ $block['label'] }}</span>
                                        <span class="ocr-preview-text">{{ $block['text'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="ocr-fields-card">
                        <div class="ocr-field-section">
                            <h3 class="ocr-section-title">Customer Information</h3>
                            <div class="ocr-field">
                                <label class="ocr-field-label" for="verifyCustomerName">Customer / Business Name</label>
                                <div class="ocr-field-input-wrap ocr-field-verified">
                                    <input id="verifyCustomerName" type="text" wire:model="verifyCustomerName" class="ocr-field-input" />
                                    <span class="ocr-verified-icon" title="Verified field">✓</span>
                                </div>
                            </div>
                            <div class="ocr-field">
                                <label class="ocr-field-label" for="verifyDeliveryAddress">Delivery Address (Malaysian Context)</label>
                                <textarea id="verifyDeliveryAddress" wire:model="verifyDeliveryAddress" rows="4" class="ocr-field-textarea"></textarea>
                            </div>
                        </div>

                        <div class="ocr-field-section">
                            <div class="ocr-lines-head">
                                <h3 class="ocr-section-title">Line Items</h3>
                                <button type="button" wire:click="addVerifyLine" class="ocr-btn ocr-btn-outline ocr-btn-sm">+ Add Row</button>
                            </div>
                            <div class="ocr-lines-table-wrap">
                                <table class="ocr-lines-table">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Qty</th>
                                            <th>Rate (MYR)</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($verifyLines as $index => $line)
                                            <tr wire:key="ocr-line-{{ $index }}">
                                                <td>
                                                    <input type="text" wire:model="verifyLines.{{ $index }}.description" class="ocr-line-input" />
                                                </td>
                                                <td>
                                                    <input type="text" inputmode="decimal" wire:model="verifyLines.{{ $index }}.quantity" class="ocr-line-input ocr-line-input-sm" />
                                                </td>
                                                <td>
                                                    <input type="text" inputmode="decimal" wire:model="verifyLines.{{ $index }}.rate" class="ocr-line-input ocr-line-input-sm" />
                                                </td>
                                                <td class="ocr-line-action">
                                                    <button type="button" wire:click="removeVerifyLine({{ $index }})" class="ocr-line-remove" @disabled(count($verifyLines) <= 1)>&times;</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ocr-verify-footer">
                    <button type="button" wire:click="rescanDocument" class="ocr-btn ocr-btn-danger-text">Re-scan Document</button>
                    <div class="ocr-verify-footer-actions">
                        <button type="button" wire:click="saveDraft" class="ocr-btn ocr-btn-outline">Save Draft</button>
                        <button type="button" wire:click="verifyAndCreateQuote" class="ocr-btn ocr-btn-primary">Verify &amp; Create Quote</button>
                    </div>
                </div>
            </section>
        </div>
    @endif
    </div>
</x-filament-panels::page>
