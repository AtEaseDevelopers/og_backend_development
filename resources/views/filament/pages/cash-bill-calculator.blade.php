@php
    $selectedCsns = $this->selectedCsns;
    $totalDue = $this->totalDue;
    $received = $this->receivedAmount;
    $outstanding = $this->outstandingAmount;
    $change = $this->changeAmount;
    // received less than the total: the Paying column splits it over the list
    $plan = $this->allocationPlan;
    $splitting = $received > 0 && ! $plan['full'];
    $customerCsns = $this->customerCsns;
    $customerOrders = $this->customerOrders;
    $selectedOrders = $this->selectedOrders;
    $openCsns = $customerCsns->reject(fn ($c) => in_array($c->id, $this->selectedCsnIds, true));
    $openOrders = $customerOrders->reject(fn ($o) => in_array($o->id, $this->selectedOrderIds, true));
    $allPicked = ($openCsns->isNotEmpty() || $openOrders->isNotEmpty())
        && $openCsns->every(fn ($c) => in_array($c->id, $this->pickedCsnIds, true))
        && $openOrders->every(fn ($o) => in_array($o->id, $this->pickedOrderIds, true));
    $pickedCount = count($this->pickedCsnIds) + count($this->pickedOrderIds);
    $listCount = $selectedCsns->count() + $selectedOrders->count();
    $customerCount = $selectedCsns->map(fn ($c) => $c->customer_id ?: 'name:'.$c->customer_name)->concat($selectedOrders->pluck('customer_id'))->unique()->count();
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

    {{-- Cash Bill Calculator: pick a customer's unpaid Cash Bill CSNs (tick + Add) or scan them into one payment
         list, which may hold CSNs of different customers, paid together --}}
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
                        placeholder="Scan or type the CSN number, then Enter (adds it to the payment list)"
                        autocomplete="off"
                        class="cb-search-input"
                    />
                </div>
            </div>
        </div>

        {{-- the customer's unpaid Cash Bill CSNs: tick and Add them to the payment list --}}
        @if ($this->customerId)
            <div class="cb-pick-head">
                <span class="cb-section-label">Unpaid CSNs and orders of this customer</span>
                <button type="button" wire:click="addPicked" class="cb-add-btn" @disabled($pickedCount === 0)>
                    Add{{ $pickedCount > 0 ? ' '.$pickedCount : '' }} to the payment list
                </button>
            </div>
            <div class="cb-table-wrap">
                <table class="cb-table">
                    <thead>
                        <tr>
                            <th class="cb-check-col">
                                @if ($openCsns->isNotEmpty() || $openOrders->isNotEmpty())
                                    <input type="checkbox" wire:click="togglePickAll" @checked($allPicked) title="Tick all">
                                @endif
                            </th>
                            <th>CSN / Order</th>
                            <th>CSN date</th>
                            <th>Source company</th>
                            <th class="cb-num">Amount due</th>
                            <th>Payment Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customerCsns as $csn)
                            @php
                                $added = in_array($csn->id, $this->selectedCsnIds, true);
                                $picked = in_array($csn->id, $this->pickedCsnIds, true);
                            @endphp
                            <tr wire:key="cb-pick-{{ $csn->id }}" @class(['cb-row-picked' => $picked, 'cb-row-added' => $added]) @unless ($added) wire:click="togglePick({{ $csn->id }})" style="cursor:pointer" @endunless>
                                <td class="cb-check-col">
                                    @if ($added)
                                        <span class="cb-added-tag">Added</span>
                                    @else
                                        <input type="checkbox" @checked($picked) wire:click.stop="togglePick({{ $csn->id }})">
                                    @endif
                                </td>
                                <td class="cb-mono">{{ $csn->number }}</td>
                                <td>{{ $csn->issued_at?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $csn->sourceBranch?->name ?: '—' }}</td>
                                <td class="cb-num">
                                    {{ $money($this->dueFor($csn)) }}
                                    @if ((float) ($csn->paid_total ?? 0) > 0)<div class="cb-paid-note">of {{ $money((float) $csn->total_amount) }} · paid {{ $money((float) $csn->paid_total) }}</div>@endif
                                </td>
                                <td><span class="cb-status-pill">{{ $this->paymentStatusLabel($csn) }}</span></td>
                            </tr>
                        @empty
                            @if ($customerOrders->isEmpty())
                                <tr><td colspan="6" class="cb-empty">This customer has no unpaid Cash Bill CSNs or orders.</td></tr>
                            @endif
                        @endforelse
                        {{-- Cash orders in Pending payment (no CSN yet): their Cash Bill and CSN are created once fully paid --}}
                        @foreach ($customerOrders as $order)
                            @php
                                $added = in_array($order->id, $this->selectedOrderIds, true);
                                $picked = in_array($order->id, $this->pickedOrderIds, true);
                            @endphp
                            <tr wire:key="cb-pick-order-{{ $order->id }}" @class(['cb-row-picked' => $picked, 'cb-row-added' => $added]) @unless ($added) wire:click="togglePickOrder({{ $order->id }})" style="cursor:pointer" @endunless>
                                <td class="cb-check-col">
                                    @if ($added)
                                        <span class="cb-added-tag">Added</span>
                                    @else
                                        <input type="checkbox" @checked($picked) wire:click.stop="togglePickOrder({{ $order->id }})">
                                    @endif
                                </td>
                                <td class="cb-mono">{{ $order->number }} <span class="cb-order-tag">Order · no CSN yet</span></td>
                                <td>{{ $order->created_at?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $order->branch?->name ?: '—' }}</td>
                                <td class="cb-num">
                                    {{ $money($this->dueForOrder($order)) }}
                                    @if ($this->dueForOrder($order) + 0.005 < (float) $order->total_amount)<div class="cb-paid-note">of {{ $money((float) $order->total_amount) }}</div>@endif
                                </td>
                                <td><span class="cb-status-pill">PENDING PAYMENT</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </section>

    {{-- the payment list: CSNs added from any customers, paid together --}}
    <section class="cb-card">
        <div class="cb-card-head cb-card-head-split">
            <h2 class="cb-card-title">Payment list</h2>
            <p class="cb-card-note">{{ $customerCount > 1 ? 'CSNs of '.$customerCount.' customers, paid together' : 'The CSNs paid in this transaction' }}</p>
        </div>
        <div class="cb-table-wrap">
            <table class="cb-table">
                <thead>
                    <tr>
                        <th>CSN / Order</th>
                        <th>Customer</th>
                        <th>CSN date</th>
                        <th>Source company</th>
                        <th class="cb-num">Amount due</th>
                        @if ($splitting)<th class="cb-num">Paying (RM)</th>@endif
                        <th>Payment Status</th>
                        <th class="cb-check-col"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($selectedCsns as $csn)
                        <tr wire:key="cb-csn-{{ $csn->id }}" class="cb-row-ticked">
                            <td class="cb-mono">{{ $csn->number }}</td>
                            <td>{{ $csn->customer?->company_name ?: ($csn->customer_name ?: '—') }}</td>
                            <td>{{ $csn->issued_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $csn->sourceBranch?->name ?: '—' }}</td>
                            <td class="cb-num">
                                {{ $money($this->dueFor($csn)) }}
                                @if ((float) ($csn->paid_total ?? 0) > 0)<div class="cb-paid-note">of {{ $money((float) $csn->total_amount) }} · paid {{ $money((float) $csn->paid_total) }}</div>@endif
                            </td>
                            @if ($splitting)
                                @include('filament.pages.partials.cash-bill-paying', ['key' => 'csn_'.$csn->id, 'due' => $this->dueFor($csn)])
                            @endif
                            <td><span class="cb-status-pill">{{ $this->paymentStatusLabel($csn) }}</span></td>
                            <td class="cb-check-col"><button type="button" wire:click="removeCsn({{ $csn->id }})" class="cb-row-remove" title="Remove from the payment list" aria-label="Remove {{ $csn->number }}">✕</button></td>
                        </tr>
                    @empty
                        @if ($selectedOrders->isEmpty())
                            <tr>
                                <td colspan="8" class="cb-empty">
                                    Choose a customer, tick its CSNs or orders and click Add, or scan a CSN QR code. Different customers can be paid together.
                                </td>
                            </tr>
                        @endif
                    @endforelse
                    @foreach ($selectedOrders as $order)
                        <tr wire:key="cb-order-{{ $order->id }}" class="cb-row-ticked">
                            <td class="cb-mono">{{ $order->number }} <span class="cb-order-tag">Order · no CSN yet</span></td>
                            <td>{{ $order->customer?->company_name ?: '—' }}</td>
                            <td>{{ $order->created_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $order->branch?->name ?: '—' }}</td>
                            <td class="cb-num">{{ $money($this->dueForOrder($order)) }}</td>
                            @if ($splitting)
                                @include('filament.pages.partials.cash-bill-paying', ['key' => 'order_'.$order->id, 'due' => $this->dueForOrder($order)])
                            @endif
                            <td><span class="cb-status-pill">PENDING PAYMENT</span></td>
                            <td class="cb-check-col"><button type="button" wire:click="removeOrderItem({{ $order->id }})" class="cb-row-remove" title="Remove from the payment list" aria-label="Remove {{ $order->number }}">✕</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="cb-table-footer">
            <span>In the payment list: <strong>{{ $listCount }}</strong>@if ($customerCount > 1) · {{ $customerCount }} customers @endif</span>
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
                    {{-- digits and one dot only; always 2 decimals once you leave it (50 → 50.00) --}}
                    <input
                        id="amountReceived"
                        type="text"
                        inputmode="decimal"
                        autocomplete="off"
                        wire:model.live.debounce.400ms="amountReceived"
                        x-on:input="const v = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1'); if (v !== $el.value) $el.value = v"
                        x-on:blur="const n = parseFloat($el.value); const f = isNaN(n) ? '0.00' : n.toFixed(2); if ($el.value !== f) { $el.value = f; $el.dispatchEvent(new Event('input')) }"
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

        {{-- received less than due: the user splits it over the list (Paying column); less than an item's due = partial --}}
        @if ($listCount > 0 && $splitting)
            {{-- plain words (user, 27 Oct 2026): which items the Paying amounts leave not fully paid --}}
            @php
                $dues = $this->itemDues();
                $notFull = [];
                foreach ($this->selectedCsns as $csn) {
                    $paying = (float) ($plan['amounts']['csn_'.$csn->id] ?? 0);
                    if ($paying + 0.005 < (float) ($dues['csn_'.$csn->id] ?? 0)) {
                        $notFull[] = $csn->number.' · RM '.number_format($dues['csn_'.$csn->id] - $paying, 2).' left';
                    }
                }
                foreach ($this->selectedOrders as $order) {
                    $paying = (float) ($plan['amounts']['order_'.$order->id] ?? 0);
                    if ($paying + 0.005 < (float) ($dues['order_'.$order->id] ?? 0)) {
                        $notFull[] = $order->number.' · RM '.number_format($dues['order_'.$order->id] - $paying, 2).' left';
                    }
                }
            @endphp
            {{-- green only when every item is fully paid; yellow while something is still outstanding (user, 27 Oct 2026) --}}
            <div @class(['cb-balance', 'cb-balance-ok' => $plan['ok'] && $notFull === []])>
                <span>
                    @if ($notFull === [])
                        Every item in the list is fully paid.
                    @else
                        Not fully paid:
                        @foreach ($notFull as $line)
                            <br><strong>{{ $line }}</strong>
                        @endforeach
                    @endif
                </span>
                <span class="cb-balance-sum">
                    Given RM {{ $lcd($plan['allocated']) }}
                    @if (abs($plan['left']) >= 0.005)
                        · <strong>RM {{ $lcd(abs($plan['left'])) }} {{ $plan['left'] > 0 ? 'still to give' : 'too much' }}</strong>
                    @else
                        · all of it given
                    @endif
                </span>
            </div>
        @endif

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
                    @disabled($listCount === 0)
                >
                    Fill Amount Due
                </button>
                <button
                    type="button"
                    wire:click="process"
                    class="cb-btn cb-btn-primary"
                    @disabled($listCount === 0 || $received <= 0 || ! $plan['ok'])
                >
                    Paid
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
        .cb-pick-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 14px 18px 8px; }
        .cb-add-btn { padding: .45rem .9rem; border-radius: .5rem; font-size: .82rem; font-weight: 600; background: rgb(15 23 42); color: #fff; }
        .cb-add-btn:disabled { opacity: .45; cursor: not-allowed; }
        .dark .cb-add-btn { background: rgb(241 245 249); color: rgb(15 23 42); }
        .cb-row-picked td { background: rgb(239 246 255); }
        .dark .cb-row-picked td { background: rgb(30 64 175 / .2); }
        .cb-row-added td { color: rgb(148 163 184); }
        .cb-added-tag { font-size: .68rem; font-weight: 700; color: rgb(21 128 61); }
        .cb-paid-note { font-size: .7rem; color: rgb(100 116 139); }
        .cb-order-tag { margin-left: .35rem; padding: 0 .35rem; border-radius: .25rem; font-family: inherit; font-size: .62rem; font-weight: 700; background: rgb(237 233 254); color: rgb(91 33 182); }
        .dark .cb-order-tag { background: rgb(76 29 149 / .35); color: rgb(196 181 253); }
        .cb-balance { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; margin: 0 18px 14px; padding: .7rem .9rem; border-radius: .6rem; background: rgb(255 251 235); border: 1px solid rgb(253 230 138); color: rgb(146 64 14); font-size: .88rem; }
        .cb-balance-select { width: auto; min-width: 18rem; }
        .cb-balance-ok { background: rgb(240 253 244); border-color: rgb(187 247 208); color: rgb(22 101 52); }
        .dark .cb-balance-ok { background: rgb(34 197 94 / .12); border-color: rgb(34 197 94 / .35); color: rgb(134 239 172); }
        .cb-balance-sum { margin-left: auto; white-space: nowrap; }
        .cb-pay-input { width: 7.5rem; height: 2.3rem; padding: 0 .6rem; border: 1px solid rgb(209 213 219); border-radius: .5rem; background: #fff; font-size: .9rem; text-align: right; color: inherit; }
        .dark .cb-pay-input { background: rgb(17 24 39); border-color: rgb(55 65 81); }
        .cb-pay-error { font-size: .72rem; color: rgb(185 28 28); margin-top: .2rem; }
        .dark .cb-pay-error { color: rgb(252 165 165); }
        .dark .cb-balance { background: rgb(245 158 11 / .12); border-color: rgb(245 158 11 / .35); color: rgb(252 211 77); }
        .cb-row-remove { width: 1.6rem; height: 1.6rem; border-radius: 999px; color: rgb(185 28 28); font-weight: 700; }
        .cb-row-remove:hover { background: rgb(254 226 226); }
        .dark .cb-row-remove:hover { background: rgb(127 29 29 / .4); }
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
