@php
    $data = $this->getListingData();
    $rows = $data['rows'] ?? [];
    $count = $data['count'] ?? 0;
    $detail = $this->getSelectedDetail();
@endphp

<x-filament-panels::page class="fi-page-credit-customer-approval cca-page">
    <div class="cca-layout">
        <div class="cca-list-panel">
            <div class="cca-list-head">
                <h2 class="cca-panel-title">Application List</h2>
                <div class="cca-list-actions">
                    <button type="button" wire:click="toggleFilterPanel" class="cca-btn cca-btn-outline cca-btn-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                        </svg>
                        Filter
                    </button>
                    <button type="button" wire:click="exportApplications" class="cca-btn cca-btn-outline cca-btn-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Export
                    </button>
                </div>
            </div>

            @if($showFilterPanel)
                <div class="cca-filter-panel">
                    <div class="cca-filter-grid">
                        <div class="cca-filter-field">
                            <label class="cca-filter-label" for="filterSearch">Search</label>
                            <input
                                id="filterSearch"
                                type="search"
                                wire:model.live.debounce.400ms="filterSearch"
                                class="cca-filter-input"
                                placeholder="Customer name, SSM..."
                            />
                        </div>
                        <div class="cca-filter-field">
                            <label class="cca-filter-label" for="filterStatus">Status</label>
                            <select id="filterStatus" wire:model.live="filterStatus" class="cca-filter-input">
                                @foreach ($this->statusFilterOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="cca-filter-field">
                            <label class="cca-filter-label" for="filterDateFrom">From</label>
                            <input id="filterDateFrom" type="date" wire:model.live="filterDateFrom" class="cca-filter-input" />
                        </div>
                        <div class="cca-filter-field">
                            <label class="cca-filter-label" for="filterDateTo">To</label>
                            <input id="filterDateTo" type="date" wire:model.live="filterDateTo" class="cca-filter-input" />
                        </div>
                    </div>
                    <button type="button" wire:click="resetFilters" class="cca-btn cca-btn-outline cca-btn-sm">Reset filters</button>
                </div>
            @endif

            <div class="cca-table-wrap">
                <table class="cca-table">
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>Registration No</th>
                            <th class="cca-col-amount">Limit Requested (MYR)</th>
                            <th>Branch</th>
                            <th>App Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr
                                wire:key="cca-row-{{ $row['id'] }}"
                                wire:click="openDetail({{ $row['id'] }})"
                                @class(['cca-row', 'cca-row-selected' => $selectedRequestId === $row['id']])
                            >
                                <td class="cca-customer-name">{{ $row['customer_name'] }}</td>
                                <td>{{ $row['registration_no'] }}</td>
                                <td class="cca-col-amount">{{ $row['requested_limit'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['app_date'] }}</td>
                                <td>
                                    <span @class(['cca-status-pill', 'cca-status-'.$row['status_color']])>
                                        {{ $row['status_label'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="cca-empty">
                                    No credit approval requests found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($count > 0)
                <div class="cca-pagination">
                    <span>Showing 1 to {{ $count }} of {{ $count }} entries</span>
                    <div class="cca-pagination-nav">
                        <button type="button" class="cca-page-btn" disabled aria-label="Previous page">&lsaquo;</button>
                        <button type="button" class="cca-page-btn" disabled aria-label="Next page">&rsaquo;</button>
                    </div>
                </div>
            @endif
        </div>

        <div class="cca-detail-panel">
            @if($detail)
                <div class="cca-detail-scroll">
                    <div class="cca-detail-head">
                        <div>
                            <h2 class="cca-detail-name">{{ $detail['customer_name'] }}</h2>
                            <p class="cca-detail-reg">{{ $detail['registration_no'] }}</p>
                        </div>
                        <span @class(['cca-status-pill', 'cca-status-'.$detail['status_color'], 'cca-status-badge-lg'])>
                            {{ $detail['status_label'] }}
                        </span>
                    </div>

                    <div class="cca-summary-card">
                        <div class="cca-summary-grid">
                            <div>
                                <div class="cca-summary-label">Requested Limit</div>
                                <div class="cca-summary-value">{{ $detail['requested_limit'] }}</div>
                            </div>
                            <div>
                                <div class="cca-summary-label">Credit Score</div>
                                <div class="cca-summary-value cca-credit-score">
                                    {{ $detail['credit_score'] }}
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5 19.5 4.5m0 0H9.75m9.75 0v9.75" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <section class="cca-section">
                        <h3 class="cca-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44 2.12-2.12a1.5 1.5 0 0 1 2.12 0l2.12 2.12m-2.12 2.12-2.12 2.12m0 0-2.12 2.12a1.5 1.5 0 0 1-2.12 0l-2.12-2.12m2.12-2.12 2.12-2.12M3 15.75V18A2.25 2.25 0 0 0 5.25 20.25h13.5A2.25 2.25 0 0 0 21 18.75v-2.25" />
                            </svg>
                            Documents
                        </h3>
                        <div class="cca-doc-list">
                            @foreach($detail['documents'] as $document)
                                <div class="cca-doc-item" wire:key="cca-doc-{{ $detail['id'] }}-{{ $document['name'] }}">
                                    <div @class(['cca-doc-icon', 'cca-doc-icon-'.$document['type']])>
                                        {{ strtoupper($document['type']) }}
                                    </div>
                                    <div>
                                        <div class="cca-doc-name">{{ $document['name'] }}</div>
                                        <div class="cca-doc-size">{{ $document['size'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="cca-section">
                        <h3 class="cca-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                            </svg>
                            Assessment Notes
                        </h3>
                        <div class="cca-notes-box">{!! nl2br(e($detail['assessment_notes'])) !!}</div>
                    </section>

                    <section class="cca-section">
                        <h3 class="cca-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            Audit Trail
                        </h3>
                        <div class="cca-audit-trail">
                            @foreach($detail['audit_trail'] as $entry)
                                <div
                                    wire:key="cca-audit-{{ $detail['id'] }}-{{ $entry['timestamp'] }}-{{ $entry['title'] }}"
                                    @class(['cca-audit-item', 'cca-audit-item-active' => $entry['active']])
                                >
                                    <div class="cca-audit-dot"></div>
                                    <div>
                                        <div class="cca-audit-title">{{ $entry['title'] }}</div>
                                        <div class="cca-audit-meta">{{ $entry['timestamp'] }} · {{ $entry['actor'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </div>

                @if($detail['can_approve'] || $detail['can_reject'] || $detail['can_request_info'])
                    <div class="cca-actions">
                        @if($showInfoForm)
                            <div class="cca-inline-form">
                                <textarea
                                    wire:model="infoRequestNote"
                                    rows="3"
                                    class="cca-form-input"
                                    placeholder="What information do you need from the customer?"
                                ></textarea>
                                <div class="cca-inline-form-actions">
                                    <button type="button" wire:click="toggleInfoForm" class="cca-btn cca-btn-outline">Cancel</button>
                                    <button type="button" wire:click="requestInfo({{ $detail['id'] }})" class="cca-btn cca-btn-outline">Send Request</button>
                                </div>
                            </div>
                        @elseif($showRejectForm)
                            <div class="cca-inline-form">
                                <textarea
                                    wire:model="rejectReason"
                                    rows="3"
                                    class="cca-form-input"
                                    placeholder="Rejection reason..."
                                ></textarea>
                                <div class="cca-inline-form-actions">
                                    <button type="button" wire:click="toggleRejectForm" class="cca-btn cca-btn-outline">Cancel</button>
                                    <button type="button" wire:click="rejectApplication({{ $detail['id'] }})" class="cca-btn cca-btn-danger-outline">Confirm Reject</button>
                                </div>
                            </div>
                        @else
                            @if($detail['can_approve'])
                                <button type="button" wire:click="approveWithLimit({{ $detail['id'] }})" class="cca-btn cca-btn-primary">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    Approve with Limit
                                </button>
                            @endif

                            <div class="cca-secondary-actions">
                                @if($detail['can_request_info'])
                                    <button type="button" wire:click="toggleInfoForm" class="cca-btn cca-btn-outline cca-btn-half">Request Info</button>
                                @endif
                                @if($detail['can_reject'])
                                    <button type="button" wire:click="toggleRejectForm" class="cca-btn cca-btn-danger-outline cca-btn-half">Reject</button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            @else
                <div class="cca-detail-empty">
                    <h2 class="cca-panel-title">Application Details</h2>
                    <p>Select an application from the list to review documents, notes, and audit history.</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
