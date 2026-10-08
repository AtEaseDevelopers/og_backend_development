@php
    $selectedCsns = $this->selectedCsns;
    $totalDue = $this->totalDue;
    $received = $this->receivedAmount;
    $outstanding = $this->outstandingAmount;
    $change = $this->changeAmount;
    $customerCsns = $this->customerCsns;
    $allTicked = $customerCsns->isNotEmpty() && $customerCsns->every(fn ($c) => in_array($c->id, $this->selectedCsnIds, true));
    $money = fn (float $amount): string => 'RM '.number_format($amount, 2);
    $lcd = fn (float $amount): string => number_format($amount, 2);
@endphp

<x-filament-panels::page class="fi-page-cash-bill cb-page">
    <div class="cb-intro">
        <div class="cb-badges">
            <span class="cb-badge cb-badge-muted">{{ $this->branchViewLabel }}</span>
            <span class="cb-badge cb-badge-date">Counter Date: {{ $this->counterDateLabel }}</span>
        </div>
    </div>

    {{-- Cash Bill Calculator: customer -> tick the unpaid Cash Bill CSNs (or scan their QR codes) --}}
    <section class="cb-card">
        <div class="cb-card-head">
            <h2 class="cb-card-title">Cash Bill Calculator</h2>
        </div>

        <div class="cb-pick-grid">
            <div>
                <label class="cb-section-label" for="cbCustomer">Customer</label>
                <select id="cbCustomer" wire:model.live="customerId" class="cb-select">
                    <option value="">Select a customer with unpaid Cash Bill CSNs…</option>
                    @foreach ($this->customerOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="cb-section-label" for="cbScan">Scan CSN QR code</label>
                <div class="cb-search-field">
                    <svg class="cb-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                    </svg>
                    <input
                        id="cbScan"
                        type="text"
                        wire:model="scan"
                        wire:keydown.enter.prevent="scanCsn"
                        placeholder="Scan or type the CSN number, then Enter"
                        autocomplete="off"
                        class="cb-search-input"
                    />
                </div>
            </div>
        </div>

        <div class="cb-table-wrap">
            <table class="cb-table">
                <thead>
                    <tr>
                        <th class="cb-check-col">
                            @if ($customerCsns->isNotEmpty())
                                <input type="checkbox" wire:click="toggleAll" @checked($allTicked) title="Tick all">
                            @endif
                        </th>
                        <th>CSN Number</th>
                        <th>CSN date</th>
                        <th>Source Branch</th>
                        <th class="cb-num">Amount</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customerCsns as $csn)
                        @php $ticked = in_array($csn->id, $this->selectedCsnIds, true); @endphp
                        <tr wire:key="cb-csn-{{ $csn->id }}" @class(['cb-row-ticked' => $ticked]) wire:click="toggleCsn({{ $csn->id }})" style="cursor:pointer">
                            <td class="cb-check-col"><input type="checkbox" @checked($ticked) wire:click.stop="toggleCsn({{ $csn->id }})"></td>
                            <td class="cb-mono">{{ $csn->number }}</td>
                            <td>{{ $csn->issued_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $csn->sourceBranch?->name ?: '—' }}</td>
                            <td class="cb-num">{{ $money((float) $csn->total_amount) }}</td>
                            <td><span class="cb-status-pill">{{ $this->paymentStatusLabel($csn) }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="cb-empty">
                                {{ $this->customerId ? 'This customer has no unpaid Cash Bill CSNs.' : 'Choose a customer, or scan a CSN QR code, to list the unpaid Cash Bill CSNs.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="cb-table-footer">
            <span>Selected CSNs: <strong>{{ $selectedCsns->count() }}</strong>@if ($customerCsns->isNotEmpty()) of {{ $customerCsns->count() }}@endif</span>
            <span class="cb-footer-total">
                <span class="cb-footer-total-label">Total Selected Amount</span>
                <span class="cb-lcd cb-lcd-dark">RM {{ $lcd($totalDue) }}</span>
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
                    <label class="cb-section-label" for="amountReceived">Amount Received (RM)</label>
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

        <div class="cb-slips">
            <div class="cb-section-label">Payment slips / receipts <span class="cb-optional">(optional · images or PDF, up to 8 MB each, max 10)</span></div>
            <label class="cb-slip-drop">
                <input type="file" wire:model="slips" multiple accept="image/*,application/pdf" class="cb-slip-input">
                <span wire:loading.remove wire:target="slips">Click to choose files, or take a photo</span>
                <span wire:loading wire:target="slips">Uploading…</span>
            </label>
            @error('slips') <p class="cb-error">{{ $message }}</p> @enderror
            @error('slips.*') <p class="cb-error">{{ $message }}</p> @enderror
            @if ($slips)
                <div class="cb-slip-list">
                    @foreach ($slips as $i => $slip)
                        <div class="cb-slip" wire:key="slip-{{ $i }}">
                            @if (str_starts_with((string) $slip->getMimeType(), 'image/'))
                                <img src="{{ $slip->temporaryUrl() }}" alt="{{ $slip->getClientOriginalName() }}">
                            @else
                                <span class="cb-slip-ext">PDF</span>
                            @endif
                            <span class="cb-slip-name" title="{{ $slip->getClientOriginalName() }}">{{ $slip->getClientOriginalName() }}</span>
                            <button type="button" class="cb-slip-remove" wire:click="removeSlip({{ $i }})" title="Remove">&times;</button>
                        </div>
                    @endforeach
                </div>
            @endif
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


    <style>
        .cb-pick-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: .9rem; padding: 18px 18px 14px; }
        @media (max-width: 768px) { .cb-pick-grid { grid-template-columns: minmax(0, 1fr); } }
        .cb-select { width: 100%; height: 2.6rem; padding: 0 2rem 0 .75rem; border: 1px solid rgb(209 213 219); border-radius: .5rem; background-color: #fff; font-size: .9rem; color: inherit; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cb-pick-grid .cb-section-label { display: block; margin-bottom: .35rem; }
        .cb-pick-grid .cb-search-field { width: 100%; }
        .dark .cb-select { background: rgb(17 24 39); border-color: rgb(55 65 81); }
        .cb-check-col { width: 2.5rem; text-align: center; }
        .cb-row-ticked td { background: rgb(240 253 244); }
        .dark .cb-row-ticked td { background: rgb(22 101 52 / .18); }
        .cb-slips { padding: 0 18px 18px; }
        .cb-optional { font-weight: 400; text-transform: none; letter-spacing: 0; color: rgb(100 116 139); }
        .cb-slip-drop { position: relative; display: flex; align-items: center; justify-content: center; padding: .9rem; border: 1px dashed rgb(203 213 225); border-radius: .6rem; font-size: .85rem; color: rgb(71 85 105); cursor: pointer; }
        .cb-slip-input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .cb-slip-list { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .6rem; }
        .cb-slip { position: relative; display: flex; flex-direction: column; align-items: center; width: 6.5rem; padding: .35rem; border: 1px solid rgb(226 232 240); border-radius: .5rem; }
        .cb-slip img, .cb-slip-ext { width: 100%; height: 4.5rem; object-fit: cover; border-radius: .35rem; display: grid; place-items: center; background: rgb(241 245 249); font-size: .75rem; font-weight: 700; color: rgb(100 116 139); }
        .cb-slip-name { width: 100%; margin-top: .25rem; font-size: .68rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: center; }
        .cb-slip-remove { position: absolute; top: .15rem; right: .25rem; width: 1.2rem; height: 1.2rem; border-radius: 999px; background: rgb(15 23 42 / .75); color: #fff; font-size: .85rem; line-height: 1; }
        .cb-error { margin-top: .35rem; font-size: .8rem; color: rgb(185 28 28); }
    </style>
    @script
    <script>
        $wire.on('print-cash-bill-receipt', () => {
            window.print();
        });
    </script>
    @endscript
</x-filament-panels::page>
