@php
    $selectedCsns = $this->selectedCsns;
    $totalDue = $this->totalDue;
    $received = $this->receivedAmount;
    $outstanding = $this->outstandingAmount;
    $change = $this->changeAmount;
    $searchMatches = filled($this->search) ? $this->csnSearchResults : collect();
    $money = fn (float $amount): string => 'MYR '.number_format($amount, 2);
    $lcd = fn (float $amount): string => number_format($amount, 2);
@endphp

<x-filament-panels::page class="fi-page-cash-bill cb-page">
    <div class="cb-intro">
        <div class="cb-badges">
            <span class="cb-badge cb-badge-muted">{{ $this->branchViewLabel }}</span>
            <span class="cb-badge cb-badge-date">Counter Date: {{ $this->counterDateLabel }}</span>
        </div>
    </div>

    {{-- Cash Bill Calculator --}}
    <section class="cb-card">
        <div class="cb-card-head">
            <h2 class="cb-card-title">Cash Bill Calculator</h2>
            <div class="cb-search-wrap">
                <div class="cb-search-field">
                    <svg class="cb-search-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                    </svg>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        wire:keydown.enter.prevent="addFromSearch"
                        placeholder="Search CSN or customer…"
                        class="cb-search-input"
                    />
                </div>
                <button type="button" class="cb-icon-btn" wire:click="addFromSearch" title="Add CSN">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 15.75a3 3 0 013 3V21M6 18h12" />
                    </svg>
                </button>
            </div>
        </div>

        @if($searchMatches->isNotEmpty())
            <div class="cb-search-results">
                @foreach($searchMatches as $csn)
                    <button type="button" wire:click="addCsn({{ $csn->id }})" class="cb-search-result">
                        <span class="cb-search-result-csn">{{ $csn->number }}</span>
                        <span class="cb-search-result-meta">{{ $csn->customer_name }} · {{ $money((float) $csn->total_amount) }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        <div class="cb-table-wrap">
            <table class="cb-table">
                <thead>
                    <tr>
                        <th>CSN Number</th>
                        <th>Customer</th>
                        <th>Source Branch</th>
                        <th class="cb-num">Amount</th>
                        <th>Payment Status</th>
                        <th class="cb-action-col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($selectedCsns as $csn)
                        <tr wire:key="cb-csn-{{ $csn->id }}">
                            <td class="cb-mono">{{ $csn->number }}</td>
                            <td>{{ $csn->customer_name ?: $csn->customer?->company_name ?: '—' }}</td>
                            <td>{{ $csn->sourceBranch?->name ?: '—' }}</td>
                            <td class="cb-num">{{ $money((float) $csn->total_amount) }}</td>
                            <td><span class="cb-status-pill">{{ $this->paymentStatusLabel($csn) }}</span></td>
                            <td class="cb-action-col">
                                <button type="button" wire:click="removeCsn({{ $csn->id }})" class="cb-remove-btn" title="Remove">
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 012 0v4a1 1 0 11-2 0V9zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V9a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="cb-empty">Search and add unpaid Cash Bill CSNs to begin.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="cb-table-footer">
            <span>Selected CSNs: <strong>{{ $selectedCsns->count() }}</strong></span>
            <span class="cb-footer-total">
                <span class="cb-footer-total-label">Total Selected Amount</span>
                <span class="cb-lcd cb-lcd-dark">MYR {{ $lcd($totalDue) }}</span>
            </span>
        </div>
    </section>

    {{-- Payment Collection --}}
    <section class="cb-card">
        <div class="cb-card-head cb-card-head-split">
            <h2 class="cb-card-title">Payment Collection</h2>
            <p class="cb-card-note">The system automatically calculates outstanding balance and change amount.</p>
        </div>

        <div class="cb-payment-grid">
            <div class="cb-payment-methods">
                <div class="cb-section-label">Payment Method</div>
                <div class="cb-method-grid">
                    @foreach($this->paymentMethods() as $paymentMethod)
                        @if($paymentMethod['key'] === 'counter')
                            <button
                                type="button"
                                wire:click="selectMethod('{{ $paymentMethod['key'] }}')"
                                @class([
                                    'cb-method-btn cb-method-btn-wide',
                                    'cb-method-btn-active' => $method === $paymentMethod['key'],
                                ])
                            >
                                {{ $paymentMethod['label'] }}
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="selectMethod('{{ $paymentMethod['key'] }}')"
                                @class([
                                    'cb-method-btn',
                                    'cb-method-btn-active' => $method === $paymentMethod['key'],
                                ])
                            >
                                {{ $paymentMethod['label'] }}
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="cb-payment-amounts">
                <div class="cb-amount-block">
                    <div class="cb-section-label">Total Due</div>
                    <div class="cb-lcd cb-lcd-dark cb-lcd-lg">{{ $lcd($totalDue) }}</div>
                </div>
                <div class="cb-amount-block">
                    <label class="cb-section-label" for="amountReceived">Amount Received (MYR)</label>
                    <input
                        id="amountReceived"
                        type="text"
                        inputmode="decimal"
                        wire:model.live.debounce.400ms="amountReceived"
                        class="cb-lcd-input"
                    />
                </div>
            </div>
        </div>

        <div class="cb-settlement-bar">
            <div class="cb-settlement-math">
                <div class="cb-math-item">
                    <span class="cb-math-label">Total Due</span>
                    <span class="cb-lcd cb-lcd-sm">{{ $lcd($totalDue) }}</span>
                </div>
                <span class="cb-math-symbol">−</span>
                <div class="cb-math-item">
                    <span class="cb-math-label">Received</span>
                    <span class="cb-lcd cb-lcd-sm">{{ $lcd($received) }}</span>
                </div>
                <span class="cb-math-symbol">=</span>
                <div class="cb-math-item">
                    <span class="cb-math-label">Outstanding</span>
                    <span class="cb-lcd cb-lcd-sm">{{ $lcd($outstanding) }}</span>
                </div>
            </div>

            <div class="cb-settlement-actions">
                <div class="cb-change-block">
                    <span class="cb-change-label">Change Amount</span>
                    <span class="cb-lcd cb-lcd-green cb-lcd-lg">{{ $lcd($change) }}</span>
                </div>
                <button
                    type="button"
                    wire:click="applyFullPayment"
                    class="cb-btn cb-btn-outline"
                    @disabled($selectedCsns->isEmpty())
                >
                    Fill Amount Due
                </button>
                <button
                    type="button"
                    wire:click="process"
                    class="cb-btn cb-btn-primary"
                    @disabled($selectedCsns->isEmpty() || $outstanding > 0.009)
                >
                    Full Payment
                </button>
            </div>
        </div>
    </section>

    @if(filled($lastReceiptNumber))
        <div id="cb-receipt-print" class="cb-print-only">
            <h1>Official Receipt</h1>
            <p>Receipt No: {{ $lastReceiptNumber }}</p>
            <p>Date: {{ $this->counterDateLabel }}</p>
        </div>
    @endif

    @script
    <script>
        $wire.on('print-cash-bill-receipt', () => {
            window.print();
        });
    </script>
    @endscript
</x-filament-panels::page>
