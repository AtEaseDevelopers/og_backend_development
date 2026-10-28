@php
    // several orders on one page, every one open (each in its colour); the page logic works on one order at a
    // time ($this->useOrder): the values of each order are read inside its own block below
    $pageOrders = $this->pageOrders();
    $multiOrder = count($pageOrders) > 1;
    $workingOrder = $this->activeOrder;
    $editing = $this->isEditing();
    // Create quotation reuses this page: customer or lead, no payment / photos
    $isQuote = $this->isQuote();
    $copy = $this->pageCopy();
    $headerEditable = $this->headerEditable();
    $pairs = $this->orders[$workingOrder]['pairs'] ?? [];
    $lockedCount = count(array_filter(array_keys($pairs), fn ($i) => $this->isPairLocked($i)));
    $locations = $this->locationOptions();
    $stores = $this->storeOptions();
    $serviceOptions = $this->serviceTypeOptions();
    $newAddress = \App\Support\OrderFormOptions::NEW_ADDRESS;
@endphp

<x-filament-panels::page class="ow-page">
    {{-- consignor block: Pickup / Store switch, quick links beside a label, PIC + contact pair, store address card --}}
    <style>
        .ow-page .ow-party-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem .75rem; flex-wrap: wrap; margin-bottom: .6rem; }
        .ow-page .ow-party-head .ow-party-title { margin: 0; }
        .ow-page .ow-seg { display: inline-flex; border: 1px solid var(--ow-line); border-radius: .5rem; overflow: hidden; background: var(--ow-bg); }
        .ow-page .ow-seg label { position: relative; display: inline-flex; align-items: center; margin: 0; padding: .3rem .85rem; font-size: .78rem; line-height: 1.2; color: var(--ow-muted); cursor: pointer; border-right: 1px solid var(--ow-line); user-select: none; }
        .ow-page .ow-seg label:last-child { border-right: 0; }
        .ow-page .ow-seg input { position: absolute; inset: 0; width: 100%; height: 100%; margin: 0; opacity: 0; cursor: inherit; }
        .ow-page .ow-seg label:has(input:checked) { background: var(--ow-primary); color: var(--ow-primary-text); font-weight: 600; }
        .ow-page .ow-seg label:has(input:focus-visible) { outline: 2px solid var(--ow-link); outline-offset: -2px; }
        .ow-page .ow-seg label:has(input:disabled) { cursor: not-allowed; opacity: .65; }
        .ow-page .ow-label-row { display: flex; align-items: baseline; justify-content: space-between; gap: .5rem; }
        .ow-page .ow-label-row label { margin-bottom: .25rem; }
        .ow-page .ow-quick { display: inline-flex; gap: .75rem; font-size: .72rem; white-space: nowrap; }
        .ow-page .ow-quick .ow-btn-link { font-size: inherit; padding: 0; }
        .ow-page .ow-party .ow-pic-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; margin-top: .6rem; }
        .ow-page .ow-party .ow-pic-grid .ow-field + .ow-field { margin-top: 0; }
        .ow-page .ow-party .ow-pic-grid + .ow-field { margin-top: .6rem; }
        .ow-page .ow-store-card { margin-top: .4rem; border: 1px dashed var(--ow-line); border-radius: .5rem; padding: .5rem .65rem; background: var(--ow-bg); font-size: .8rem; }
        .ow-page .ow-store-address { white-space: pre-line; overflow-wrap: anywhere; }
        .ow-page .ow-fgrid-do { grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: .85rem; }
        @media (max-width: 480px) {
            .ow-page .ow-party .ow-pic-grid, .ow-page .ow-fgrid-do { grid-template-columns: minmax(0, 1fr); }
        }
        /* photos of a consignor & consignee block: thumbnails (saved / picked) above the picker */
        .ow-page .ow-photo-grid { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .5rem; }
        .ow-page .ow-photo { position: relative; display: block; width: 6.5rem; border: 1px solid var(--ow-line); border-radius: .5rem; overflow: hidden; background: var(--ow-bg); color: var(--ow-text); text-decoration: none; }
        .ow-page a.ow-photo:hover { border-color: var(--ow-link); }
        .ow-page .ow-photo img, .ow-page .ow-photo .ow-photo-file { display: flex; align-items: center; justify-content: center; width: 100%; height: 4.5rem; object-fit: cover; background: var(--ow-soft-2); }
        .ow-page .ow-photo .ow-photo-file { font-size: .72rem; font-weight: 700; letter-spacing: .04em; color: var(--ow-muted); }
        .ow-page .ow-photo-name { display: block; padding: .2rem .35rem; font-size: .68rem; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ow-page .ow-photo-tag { position: absolute; top: .25rem; left: .25rem; padding: 0 .35rem; border-radius: 999px; font-size: .6rem; line-height: 1.4; font-weight: 600; background: var(--ow-progress-bg); color: var(--ow-progress-fg); border: 1px solid var(--ow-progress-bd); }
        .ow-page .ow-photo-remove { position: absolute; top: .25rem; right: .25rem; display: inline-flex; align-items: center; justify-content: center; width: 1.35rem; height: 1.35rem; border: 0; border-radius: 999px; background: rgb(15 23 42 / .72); color: #fff; font-size: .7rem; line-height: 1; cursor: pointer; }
        .ow-page .ow-photo-remove:hover, .ow-page .ow-photo-remove:focus-visible { background: #b91c1c; outline: none; }
        /* product row: previous records (history) icon next to the remove button */
        .ow-page .ow-row-actions { display: inline-flex; align-items: center; gap: .4rem; }
        .ow-page .ow-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 1.85rem; height: 1.85rem; padding: 0; border: 1px solid var(--ow-line); border-radius: .45rem; background: var(--ow-bg); color: var(--ow-muted); cursor: pointer; }
        .ow-page .ow-icon-btn:hover, .ow-page .ow-icon-btn:focus-visible { color: var(--ow-link); border-color: var(--ow-link); outline: none; }
        .ow-page .ow-icon-btn svg { width: 1rem; height: 1rem; }
        /* product row: photos (small tiles, click to view) and the price keyed in when there is no rate */
        .ow-page .ow-pay-split { margin-top: .75rem; padding: .75rem .9rem; border: 1px solid var(--ow-line); border-radius: .6rem; }
        .ow-page .ow-pay-split-sum { margin-top: .5rem; text-align: right; font-size: .85rem; font-weight: 600; }
        .ow-page .ow-pay-change { margin-top: .75rem; padding: .6rem .9rem; border-radius: .6rem; background: rgb(240 253 244); border: 1px solid rgb(187 247 208); color: rgb(22 101 52); }
        .dark .ow-page .ow-pay-change { background: rgb(34 197 94 / .12); border-color: rgb(34 197 94 / .35); color: rgb(134 239 172); }
        .ow-page .ow-product-pick { display: grid; grid-template-columns: minmax(7.5rem, 9rem) minmax(0, 1fr); gap: .4rem; }
        /* type, sub-category, product on one line: the product gets the room (user, 25 Oct 2026) */
        .ow-page .ow-product-pick:has(> :nth-child(3)) { grid-template-columns: minmax(6.5rem, 7.5rem) minmax(7.5rem, 9.5rem) minmax(12rem, 1fr); }
        .ow-page .ow-dims { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .4rem; margin-top: .45rem; }
        .ow-page .ow-dim { display: grid; gap: .15rem; font-size: .7rem; color: var(--ow-muted); }
        .ow-page .ow-dim-range { display: flex; align-items: center; gap: .25rem; }
        .ow-page .ow-dim-range .ow-input { min-width: 0; flex: 1 1 0; padding-left: .4rem; padding-right: .4rem; }
        /* Create quotation: the special price block under a product row (new product fields, conditions, ranges) */
        .ow-page .ow-special-row td { background: rgb(248 250 252); padding-top: .5rem; padding-bottom: .65rem; }
        .dark .ow-page .ow-special-row td { background: rgb(17 24 39 / .45); }
        .ow-page .ow-special { display: grid; gap: .5rem; }
        .ow-page .ow-special-title { font-size: .72rem; font-weight: 600; color: var(--ow-muted); }
        .ow-page .ow-special-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr)); gap: .4rem; }
        .ow-page .ow-ranges { width: auto; border-collapse: collapse; font-size: .7rem; color: var(--ow-muted); }
        .ow-page .ow-ranges th, .ow-page .ow-ranges td { padding: .15rem .35rem; text-align: left; font-weight: 500; }
        .ow-page .ow-ranges .ow-input { width: 7rem; }
        @media (max-width: 639px) {
            .ow-page .ow-product-pick, .ow-page .ow-product-pick:has(> :nth-child(3)) { grid-template-columns: minmax(0, 1fr); }
            .ow-page .ow-dims { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        .ow-page .ow-item-photos { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
        .ow-page .ow-item-photo { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 2.4rem; height: 2.4rem; border: 1px solid var(--ow-line); border-radius: .4rem; overflow: hidden; background: var(--ow-soft-2); color: var(--ow-muted); font-size: .58rem; font-weight: 700; text-decoration: none; cursor: zoom-in; }
        .ow-page .ow-item-photo:hover, .ow-page .ow-item-photo:focus-visible { border-color: var(--ow-link); outline: none; }
        .ow-page .ow-item-photo img { width: 100%; height: 100%; object-fit: cover; }
        .ow-page .ow-item-photo.ow-item-photo-new { border-color: var(--ow-progress-bd); }
        .ow-page .ow-item-photo .ow-item-photo-x { position: absolute; top: 1px; right: 1px; display: inline-flex; align-items: center; justify-content: center; width: .95rem; height: .95rem; padding: 0; border: 0; border-radius: 999px; background: rgb(15 23 42 / .75); color: #fff; font-size: .55rem; line-height: 1; cursor: pointer; }
        .ow-page .ow-item-photo .ow-item-photo-x:hover, .ow-page .ow-item-photo .ow-item-photo-x:focus-visible { background: #b91c1c; outline: none; }
        .ow-page .ow-item-photo-add { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 2.4rem; height: 2.4rem; border: 1px dashed var(--ow-line); border-radius: .4rem; color: var(--ow-muted); cursor: pointer; }
        .ow-page .ow-item-photo-add:hover, .ow-page .ow-item-photo-add:focus-within { color: var(--ow-link); border-color: var(--ow-link); }
        .ow-page .ow-item-photo-add input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .ow-page .ow-item-photo-add svg { width: 1.05rem; height: 1.05rem; pointer-events: none; }
        .ow-page .ow-manual-price { display: inline-flex; align-items: center; gap: .35rem; }
        .ow-page .ow-manual-price span { font-size: .78rem; color: var(--ow-muted); }
        .ow-page .ow-manual-price .ow-input { width: 7.5rem; text-align: right; }
    </style>
    @if ($editing)
        <style>
            .ow-page .ow-input:disabled, .ow-page .ow-select:disabled, .ow-page .ow-textarea:disabled { background: var(--ow-soft); color: var(--ow-muted); cursor: not-allowed; }
        </style>
        @if ($this->needsHeartbeat())
            <div wire:poll.2s="heartbeat" class="hidden" aria-hidden="true"></div>
        @endif
    @endif

    <a href="{{ $copy['back_url'] }}" class="ow-back">{{ $copy['back_label'] }}</a>
    <div class="ow-head">
        <div>
            {{-- the main label only (user, 28 Oct 2026): no crumb line or description --}}
            <h1 class="ow-title">{{ $copy['title'] }}</h1>
        </div>
        {{-- another customer's order on this page: each order keeps its own colour; one Save creates them all --}}
        @if ($this->canAddOrder())
            <div class="ow-actions">
                <button type="button" wire:click="addOrder" class="ow-btn" title="Another customer's order on this page; each order keeps its own colour and is created with the same Save">+ Add another order</button>
            </div>
        @endif
    </div>

    {{-- the consignor is picked from the customers, as the Customer field (searchable, user 26 Oct 2026) --}}
    @php $consignorNames = $this->consignorNames(); @endphp

    <div class="ow-stack">
        @if ($this->duplicatedFrom)
            <div class="ow-callout ow-callout-info">
                <strong>Prefilled from {{ $this->duplicatedFrom }}</strong> · the customer, consignors &amp; consignees and products are copied; the prices are today's. Check the details, then create the order.
            </div>
        @endif
        @if ($editing)
            <div class="ow-callout ow-callout-info">
                @if ($this->enquiryOnly)
                    <strong>Editing {{ $this->orderNumber }} · not priced yet</strong><br>
                    Changes update the submitted order form. The order records (one per consignor &amp; consignee) are created when pricing starts.
                @else
                    <strong>Editing {{ $this->orderNumber }} · {{ count($pairs) }} {{ \Illuminate\Support\Str::plural('record', count($pairs)) }}</strong><br>
                    Changes apply to records that are still a draft or in negotiation. Prices already on a record are kept; a product new to a record takes the UOM price-list rate once a salesperson owns the order.
                    @if ($lockedCount > 0)
                        {{ $lockedCount }} {{ \Illuminate\Support\Str::plural('record', $lockedCount) }} {{ $lockedCount === 1 ? 'is' : 'are' }} locked and shown read-only.
                    @endif
                @endif
            </div>
        @endif

        {{-- every order of the page, open and editable, in its colour: section 1 + its consignors & consignees --}}
        @foreach ($pageOrders as $pageOrder)
        @php
            $oi = $pageOrder['index'];
            $this->useOrder($oi);
            $form = $this->orders[$oi]['form'];
            $pairs = $this->orders[$oi]['pairs'];
            // the errors under the fields belong to the order that was checked
            $ep = $this->errorOrder === null || $this->errorOrder === $oi ? '' : 'other-order.';
            $showPrices = $this->showPrices();
            $totals = $this->pairTotals();
            $uomOptions = $this->catalogOptions('uom');
            // chartered lorries are picked from the same list (one trip each)
            $lorryOptions = $this->catalogOptions('lorry');
            $productOptions = $uomOptions + $lorryOptions;
            // the product row: type first, then that type's products (a Measurement product with its size and weight)
            $productKinds = $this->productKinds();
            $measurementOptions = $this->measurementOptions();
            $kindOptions = ['item' => $this->kindProductOptions('item'), 'lorry' => $this->kindProductOptions('lorry')];
            $subOptions = ['item' => $this->subCategoryOptions('item'), 'lorry' => $this->subCategoryOptions('lorry')];
            // saved consignors / consignees (Master Data → Consignor / Consignee): the customer's tagged ones first
            $savedConsignors = $isQuote ? ['tagged' => [], 'others' => []] : $this->savedParties('consignor');
            $savedConsignees = $isQuote ? ['tagged' => [], 'others' => []] : $this->savedParties('consignee');
            $addresses = $this->addressOptions();
            $customerName = $this->customerName();
        @endphp
        <div @class(['ow-order', 'ow-order-multi' => $multiOrder]) style="--ow-order-color: {{ $pageOrder['color'] }}; --ow-order-tint: {{ $pageOrder['tint'] }}" wire:key="ow-order-{{ $oi }}" id="ow-order-{{ $oi }}">
        @if ($multiOrder)
            <div class="ow-order-head">
                <span><span class="ow-order-dot" aria-hidden="true"></span> Order {{ $pageOrder['number'] }} of {{ count($pageOrders) }}@if ($customerName) <span class="ow-note">· {{ $customerName }}</span>@endif</span>
                <button type="button" wire:click="removeOrder({{ $oi }})" wire:confirm="Remove order {{ $pageOrder['number'] }} from this page?" class="ow-btn ow-btn-sm ow-btn-danger">Remove order</button>
            </div>
        @endif

        {{-- 1. Customer & ownership --}}
        <div class="ow-card ow-card-pad" id="ow-order-section1-{{ $oi }}">
            <div class="ow-card-title">1. Customer &amp; ownership</div>
            {{-- one grid, visual order = DOM (tab) order: Customer · Billing address (2 columns) · Received through, then PIC · Contact number and the rest; on 2-column widths Customer takes its own row so Billing address does not leave a gap beside it --}}
            @php $customerTags = $this->customerTypeTags(); @endphp
            <style>
                .ow-page .ow-fgrid-cust .ow-field-wide { grid-column: span 2 / span 2; min-width: 0; }
                .ow-page .ow-fgrid-cust .ow-field-wide .ow-textarea { resize: vertical; }
                @media (max-width: 900px) { .ow-page .ow-fgrid-cust .ow-field-lead { grid-column: 1 / -1; } }
            </style>
            <div class="ow-fgrid ow-fgrid-cust">
                @if ($isQuote)
                    {{-- a customer, or a lead (a new customer without a record: "+ New lead" adds one to Master Data → Leads) --}}
                    <div class="ow-field ow-field-lead">
                        <label class="ow-label-row">
                            <span>Customer / lead <span class="ow-req">*</span></span>
                            <button type="button" wire:click="mountAction('createLead')" class="ow-btn-link">+ New lead</button>
                        </label>
                        <select wire:model.live="orders.{{ $oi }}.form.party" class="ow-select">
                            <option value="">— Select customer or lead —</option>
                            @foreach ($this->partyOptions() as $key => $party)
                                @php $customerKey = str_starts_with($key, 'customer:') ? substr($key, 9) : null; @endphp
                                <option value="{{ $key }}" @if ($party['lead']) data-og-tag="Lead" data-og-tag-tone="lead" @elseif ($customerKey && isset($customerTags[$customerKey])) data-og-tag="{{ $customerTags[$customerKey]['label'] }}" data-og-tag-tone="{{ $customerTags[$customerKey]['tone'] }}" @endif>{{ $party['label'] }}</option>
                            @endforeach
                        </select>
                        @error($ep.'form.party')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                @else
                    <div class="ow-field ow-field-lead">
                        <label class="ow-label-row">
                            <span>Customer <span class="ow-req">*</span></span>
                            @if ($headerEditable)
                                {{-- a customer without a record yet: "+ New customer" adds one and picks it --}}
                                <button type="button" wire:click="mountAction('createCustomer', { order: {{ $oi }} })" class="ow-btn-link">+ New customer</button>
                            @endif
                        </label>
                        <select wire:model.live="orders.{{ $oi }}.form.customer_id" class="ow-select" @disabled(! $headerEditable)>
                            <option value="">— Select customer —</option>
                            @foreach ($this->customerOptions() as $id => $label)
                                <option value="{{ $id }}" @if (isset($customerTags[$id])) data-og-tag="{{ $customerTags[$id]['label'] }}" data-og-tag-tone="{{ $customerTags[$id]['tone'] }}" @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error($ep.'form.customer_id')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                @endif
                <div class="ow-field ow-field-wide" wire:key="ow-billing-address">
                    <label for="ow-billing-address-{{ $oi }}">Billing address</label>
                    <textarea id="ow-billing-address-{{ $oi }}" wire:model="orders.{{ $oi }}.form.customer_address" rows="2" class="ow-textarea" placeholder="{{ filled($form['customer_id'] ?? null) ? 'Customer\'s billing address' : 'Select a customer to fill in the billing address' }}" @disabled(! $headerEditable)></textarea>
                    @error($ep.'form.customer_address')<div class="ow-field-error">{{ $message }}</div>@enderror
                </div>
                @if ($this->showReceivedThrough())
                    <div class="ow-field">
                        <label>Received through (optional)</label>
                        <select wire:model="orders.{{ $oi }}.form.received_through" class="ow-select" @disabled(! $headerEditable)>
                            @if ($this->receivedThroughClearable())
                                <option value="">— Not specified —</option>
                            @endif
                            @foreach ($this->receivedThroughOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error($ep.'form.received_through')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                @endif
                <div class="ow-field">
                    <label for="ow-customer-pic-{{ $oi }}">PIC name</label>
                    <input id="ow-customer-pic-{{ $oi }}" type="text" wire:model.live.debounce.400ms="orders.{{ $oi }}.form.customer_pic_name" class="ow-input" autocomplete="off" placeholder="Customer's person in charge" @disabled(! $headerEditable)>
                    @error($ep.'form.customer_pic_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                </div>
                <div class="ow-field">
                    <label for="ow-customer-phone-{{ $oi }}">Contact number</label>
                    <input id="ow-customer-phone-{{ $oi }}" type="tel" inputmode="tel" wire:model.live.debounce.400ms="orders.{{ $oi }}.form.customer_pic_phone" class="ow-input" autocomplete="off" @disabled(! $headerEditable)>
                    @error($ep.'form.customer_pic_phone')<div class="ow-field-error">{{ $message }}</div>@enderror
                </div>
                @if ($isQuote)
                    <div class="ow-field">
                        <label for="ow-quote-date-{{ $oi }}">Quotation date <span class="ow-req">*</span></label>
                        <input id="ow-quote-date-{{ $oi }}" type="date" wire:model="orders.{{ $oi }}.form.quote_date" class="ow-input">
                        @error($ep.'form.quote_date')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                @endif
                @unless ($isQuote)
                <div class="ow-field">
                    <label>Salesperson</label>
                    <select wire:model.live="orders.{{ $oi }}.form.salesperson_id" class="ow-select" @disabled(! $this->salespersonEditable())>
                        @if ($this->allowNoSalesperson())
                            <option value="">— No salesperson yet —</option>
                        @endif
                        @foreach ($this->salespersonOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error($ep.'form.salesperson_id')<div class="ow-field-error">{{ $message }}</div>@enderror
                </div>
                @endunless
            </div>
            {{-- the long note under the salesperson was removed (user, 28 Oct 2026); only the locks are still said --}}
            @if ($editing && (! $headerEditable || ! $this->salespersonEditable()))
                <p class="ow-note" style="margin-top:.5rem">
                    @if (! $headerEditable)Customer, billing address, received through and payment term are locked because a record of this order is confirmed.@endif
                    @if (! $this->salespersonEditable()) The salesperson is fixed for this order.@endif
                </p>
            @endif
        </div>

        @if ($isQuote)
            @include('filament.pages.partials.quote-products')
        @else
        {{-- 2. Consignor & consignee blocks --}}
        @foreach ($pairs as $i => $pair)
            @php $locked = $this->isPairLocked($i); @endphp
            <div class="ow-card ow-card-pad ow-pair" wire:key="ow-o{{ $oi }}-pair-{{ $i }}">
                <div class="ow-pair-head">
                    <div class="ow-card-title" style="margin:0">{{ 2 + $i }}. Consignor &amp; consignee @if (count($pairs) > 1)<span class="ow-pill ow-pill-gray ow-pill-plain">Record {{ $i + 1 }} of {{ count($pairs) }}</span>@endif
                        @if ($editing)
                            @if (filled($pair['record_number'] ?? null))
                                <span class="ow-pill ow-pill-gray ow-pill-plain ow-mono">{{ $pair['record_number'] }}</span>
                            @elseif (! $this->enquiryOnly)
                                <span class="ow-pill ow-pill-progress">New record</span>
                            @endif
                            @if ($locked)
                                <span class="ow-pill ow-pill-action">{{ $pair['lock_label'] ?? 'Locked' }}</span>
                            @endif
                        @endif
                    </div>
                    <div class="ow-pair-actions">
                        {{-- this consignor & consignee's payment term (each record its own; starts as the customer's) --}}
                        @php
                            $termOptions = $this->pairOrderTypeOptions($i);
                            $term = (string) ($pair['order_type'] ?? '');
                            $termTone = match ($term) { 'cash' => 'cash', 'cod' => 'cod', 'term' => 'term', default => 'none' };
                        @endphp
                        @php $termFixed = count($termOptions) <= 1 && array_key_exists($term, $termOptions); @endphp
                        <select wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.order_type" class="ow-term-select ow-term-{{ $termTone }}" title="{{ $locked ? 'Payment term (locked record)' : ($termFixed ? 'This customer\'s type allows '.($termOptions[$term] ?? $term).' only' : 'Payment term of this consignor & consignee') }}" aria-label="Payment term of consignor &amp; consignee {{ $i + 1 }}" @disabled($locked || count($termOptions) <= 1 && array_key_exists($term, $termOptions))>
                            @foreach ($termOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @if ($this->canRemovePair($i))
                            <button type="button" wire:click="inOrder({{ $oi }}, 'removePair', {{ $i }})" class="ow-btn ow-btn-sm ow-btn-danger">Remove</button>
                        @endif
                    </div>
                </div>
                @error($ep.'pairs.'.$i.'.order_type')<div class="ow-field-error" style="margin:-.5rem 0 .75rem;text-align:right">{{ $message }}</div>@enderror

                @if ($locked)
                    <div class="ow-callout ow-callout-warning" style="margin-bottom:.85rem">{{ $pair['lock_note'] ?? 'This record is locked. Confirmed records are changed with the Revise action on the order page.' }}</div>
                @endif

                @if ($editing)<fieldset @disabled($locked) style="border:0;padding:0;margin:0;min-width:0">@endif
                @php
                    $isStore = ($pair['service_type'] ?? '') === \App\Enums\ServiceType::Store->value;
                    $fieldId = 'ow-o'.$oi.'-p'.$i;
                @endphp
                <div class="ow-grid-2">
                    <div class="ow-party">
                        <div class="ow-party-head">
                            <div class="ow-party-title" id="{{ $fieldId }}-consignor-title">Consignor</div>
                            {{-- Pickup (collected from the consignor) or Store (brought to an O&G branch): radios, so arrow keys switch --}}
                            <div class="ow-seg" role="radiogroup" aria-labelledby="{{ $fieldId }}-consignor-title">
                                @foreach ($serviceOptions as $value => $label)
                                    <label>
                                        <input type="radio" name="{{ $fieldId }}-mode" value="{{ $value }}" wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.service_type">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @include('filament.pages.partials.saved-party-select', ['role' => 'consignor', 'parties' => $savedConsignors, 'model' => "orders.{$oi}.pairs.{$i}.consignor_party_id", 'oi' => $oi, 'pi' => $i, 'current' => $pair['consignor_name'] ?? '', 'partyId' => $pair['consignor_party_id'] ?? ''])
                        @error($ep.'pairs.'.$i.'.consignor_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                        @if ($isStore)
                            @php $storeInfo = $this->storeInfo($pair['store_id'] ?? null); @endphp
                            <div class="ow-field" wire:key="ow-o{{ $oi }}-pair-{{ $i }}-store">
                                <div class="ow-label-row">
                                    <label for="{{ $fieldId }}-store">(Pick Up) Store <span class="ow-req">*</span></label>
                                </div>
                                <select id="{{ $fieldId }}-store" wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.store_id" class="ow-select" @disabled($locked)>
                                    <option value="">— Select store —</option>
                                    @foreach ($stores as $id => $store)
                                        <option value="{{ $id }}" @if ($store['sub'] !== '') data-og-sub="{{ $store['sub'] }}" @endif>{{ $store['label'] }}</option>
                                    @endforeach
                                </select>
                                @error($ep.'pairs.'.$i.'.store_id')<div class="ow-field-error">{{ $message }}</div>@enderror
                                @if ($storeInfo)
                                    <div class="ow-store-card">
                                        @if ($storeInfo['address'] !== '')
                                            <div class="ow-store-address">{{ $storeInfo['address'] }}</div>
                                            @if ($storeInfo['address_from_branch'] ?? false)
                                                <div class="ow-note">No address saved for {{ $storeInfo['name'] }} yet · its branch's address is used as the pickup location.</div>
                                            @endif
                                        @else
                                            <div class="ow-note">No address saved for {{ $storeInfo['name'] }} yet · the store name is used as the pickup location.</div>
                                        @endif
                                        @if ($storeInfo['branch'] !== '' || $storeInfo['pic'] !== '' || $storeInfo['phone'] !== '')
                                            <div class="ow-note">{{ collect([$storeInfo['branch'] !== '' ? 'Branch '.$storeInfo['branch'] : null, $storeInfo['pic'] !== '' ? 'PIC '.$storeInfo['pic'] : null, $storeInfo['phone'] !== '' ? 'Tel '.$storeInfo['phone'] : null])->filter()->implode(' · ') }}</div>
                                        @endif
                                    </div>
                                @elseif (filled($pair['legacy_store_branch_id'] ?? null))
                                    <div class="ow-note" style="margin-top:.25rem">This record was saved with the company {{ \App\Domains\MasterData\Models\Branch::query()->whereKey($pair['legacy_store_branch_id'])->value('name') ?? '' }} as its store. Pick a store to replace it.</div>
                                @elseif ($stores === [])
                                    <div class="ow-note" style="margin-top:.25rem">No store yet · add one in Master Data → Stores.</div>
                                @endif
                            </div>
                        @endif
                        <div class="ow-field">
                            <label for="{{ $fieldId }}-from">From (price list location)</label>
                            <select id="{{ $fieldId }}-from" wire:model="orders.{{ $oi }}.pairs.{{ $i }}.from_location_id" class="ow-select">
                                <option value="">— Select —</option>
                                @foreach ($locations as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-pic-grid">
                            <div class="ow-field">
                                <label for="{{ $fieldId }}-consignor-pic">PIC name</label>
                                <input id="{{ $fieldId }}-consignor-pic" type="text" wire:model="orders.{{ $oi }}.pairs.{{ $i }}.consignor_pic_name" class="ow-input" autocomplete="off">
                                @error($ep.'pairs.'.$i.'.consignor_pic_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="ow-field">
                                <label for="{{ $fieldId }}-consignor-phone">Contact number</label>
                                <input id="{{ $fieldId }}-consignor-phone" type="tel" inputmode="tel" wire:model="orders.{{ $oi }}.pairs.{{ $i }}.consignor_pic_phone" class="ow-input" autocomplete="off">
                                @error($ep.'pairs.'.$i.'.consignor_pic_phone')<div class="ow-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        @if ($isStore)
                            <div class="ow-field" wire:key="ow-o{{ $oi }}-pair-{{ $i }}-pickup-store">
                                <label for="{{ $fieldId }}-pickup-store">Consignor Address</label>
                                <textarea id="{{ $fieldId }}-pickup-store" wire:model="orders.{{ $oi }}.pairs.{{ $i }}.pickup_location" rows="2" class="ow-textarea" style="resize:vertical" placeholder="The store's address"></textarea>
                                <div class="ow-note" style="margin-top:.2rem">From the store · edit it for this record.</div>
                                @error($ep.'pairs.'.$i.'.pickup_location')<div class="ow-field-error">{{ $message }}</div>@enderror
                            </div>
                        @else
                            <div class="ow-field" wire:key="ow-o{{ $oi }}-pair-{{ $i }}-pickup">
                            <div class="ow-label-row">
                                <label for="{{ $fieldId }}-pickup">Consignor Address</label>
                                @unless ($isQuote || $locked)
                                    {{-- several addresses on this side, each with its own DO number (user, 28 Oct 2026) --}}
                                    <label class="ow-check" style="display:inline-flex;align-items:center;gap:.3rem;font-weight:400"><input type="checkbox" wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.multi_pickup"> <span>Multiple address</span></label>
                                @endunless
                            </div>
                                {{-- a text box (user, 26 Oct 2026): filled from the consignor's address, still editable --}}
                                <textarea id="{{ $fieldId }}-pickup" wire:model.blur="orders.{{ $oi }}.pairs.{{ $i }}.pickup_location" rows="2" class="ow-textarea" style="resize:vertical" placeholder="Filled from the consignor · or type the address" @disabled($locked)></textarea>
                                @error($ep.'pairs.'.$i.'.pickup_location')<div class="ow-field-error">{{ $message }}</div>@enderror
                            @if (! empty($pair['multi_pickup']))
                                {{-- the other addresses of this side, each with its own DO number (user, 28 Oct 2026) --}}
                                <div class="ow-table-scroll" style="margin-top:.4rem">
                                    <table class="ow-price-table ow-stops">
                                        <thead><tr><th>Address</th><th style="width:12rem">DO number <span class="ow-req">*</span></th><th style="width:2.5rem"></th></tr></thead>
                                        <tbody>
                                            @foreach ($pair['pickup_stops'] ?? [] as $si => $stop)
                                                <tr wire:key="ow-o{{ $oi }}-pair-{{ $i }}-pickup-stop-{{ $si }}">
                                                    <td><textarea wire:model="orders.{{ $oi }}.pairs.{{ $i }}.pickup_stops.{{ $si }}.location" rows="1" class="ow-textarea" style="resize:vertical" placeholder="Address" @disabled($locked)></textarea></td>
                                                    <td>
                                                        <input type="text" wire:model="orders.{{ $oi }}.pairs.{{ $i }}.pickup_stops.{{ $si }}.do_number" class="ow-input" placeholder="DO number" @disabled($locked)>
                                                        @error($ep.'pairs.'.$i.'.pickup_stops.'.$si.'.do_number')<div class="ow-field-error">{{ $message }}</div>@enderror
                                                    </td>
                                                    <td style="white-space:nowrap">@if (! $locked && count($pair['pickup_stops']) > 1)<button type="button" wire:click="inOrder({{ $oi }}, 'removeStop', {{ $i }}, 'pickup', {{ $si }})" class="ow-icon-btn" title="Remove address" aria-label="Remove address">✕</button>@endif</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @unless ($locked)<button type="button" wire:click="inOrder({{ $oi }}, 'addStop', {{ $i }}, 'pickup')" class="ow-btn-link" style="margin-top:.3rem">+ Add address</button>@endunless
                            @endif
                            </div>
                        @endif
                    </div>

                    <div class="ow-party">
                        @php $consigneeStore = ($pair['consignee_mode'] ?? '') === 'store'; @endphp
                        <div class="ow-party-head">
                            <div class="ow-party-title" id="{{ $fieldId }}-consignee-title">Consignee</div>
                            {{-- Deliver (to the consignee) or Store (to one of the company's stores, the customer collects there), user 27 Oct 2026 --}}
                            <div class="ow-seg" role="radiogroup" aria-labelledby="{{ $fieldId }}-consignee-title">
                                @foreach (['deliver' => 'Deliver', 'store' => 'Self Collection'] as $value => $label)
                                    <label>
                                        <input type="radio" name="{{ $fieldId }}-consignee-mode" value="{{ $value }}" wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.consignee_mode" @disabled($locked)>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @include('filament.pages.partials.saved-party-select', ['role' => 'consignee', 'parties' => $savedConsignees, 'model' => "orders.{$oi}.pairs.{$i}.consignee_party_id", 'oi' => $oi, 'pi' => $i, 'current' => $pair['consignee_name'] ?? '', 'partyId' => $pair['consignee_party_id'] ?? ''])
                        @error($ep.'pairs.'.$i.'.consignee_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                        @if ($consigneeStore)
                            @php $collectInfo = $this->storeInfo($pair['consignee_store_id'] ?? null); @endphp
                            <div class="ow-field" wire:key="ow-o{{ $oi }}-pair-{{ $i }}-consignee-store">
                                <label for="{{ $fieldId }}-consignee-store">(Drop Off) Store <span class="ow-req">*</span></label>
                                <select id="{{ $fieldId }}-consignee-store" wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.consignee_store_id" class="ow-select" @disabled($locked)>
                                    <option value="">— Select store —</option>
                                    @foreach ($stores as $id => $store)
                                        <option value="{{ $id }}" @if ($store['sub'] !== '') data-og-sub="{{ $store['sub'] }}" @endif>{{ $store['label'] }}</option>
                                    @endforeach
                                </select>
                                @error($ep.'pairs.'.$i.'.consignee_store_id')<div class="ow-field-error">{{ $message }}</div>@enderror
                                <div class="ow-note" style="margin-top:.3rem">The customer collects the goods at this store. A subsheet with transfer code <strong>O</strong> (own collection) is created with the CSN.</div>
                            </div>
                        @endif
                        <div class="ow-field">
                            <label for="{{ $fieldId }}-to">To (price list location)</label>
                            <select id="{{ $fieldId }}-to" wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.to_location_id" class="ow-select">
                                <option value="">— Select —</option>
                                @foreach ($locations as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-pic-grid">
                            <div class="ow-field">
                                <label for="{{ $fieldId }}-consignee-pic">PIC name</label>
                                <input id="{{ $fieldId }}-consignee-pic" type="text" wire:model="orders.{{ $oi }}.pairs.{{ $i }}.consignee_pic_name" class="ow-input" autocomplete="off">
                                @error($ep.'pairs.'.$i.'.consignee_pic_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="ow-field">
                                <label for="{{ $fieldId }}-consignee-phone">Contact number</label>
                                <input id="{{ $fieldId }}-consignee-phone" type="tel" inputmode="tel" wire:model="orders.{{ $oi }}.pairs.{{ $i }}.consignee_pic_phone" class="ow-input" autocomplete="off">
                                @error($ep.'pairs.'.$i.'.consignee_pic_phone')<div class="ow-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="ow-field" wire:key="ow-o{{ $oi }}-pair-{{ $i }}-dropoff">
                            <div class="ow-label-row">
                                <label for="{{ $fieldId }}-dropoff">Consignee Address</label>
                                @unless ($isQuote || $locked)
                                    {{-- several addresses on this side, each with its own DO number (user, 28 Oct 2026) --}}
                                    <label class="ow-check" style="display:inline-flex;align-items:center;gap:.3rem;font-weight:400"><input type="checkbox" wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.multi_dropoff"> <span>Multiple address</span></label>
                                @endunless
                            </div>
                            {{-- a text box (user, 26 Oct 2026): filled from the consignee's address, still editable --}}
                            <textarea id="{{ $fieldId }}-dropoff" wire:model.blur="orders.{{ $oi }}.pairs.{{ $i }}.drop_off_location" rows="2" class="ow-textarea" style="resize:vertical" placeholder="Filled from the consignee · or type the address" @disabled($locked)></textarea>
                            @error($ep.'pairs.'.$i.'.drop_off_location')<div class="ow-field-error">{{ $message }}</div>@enderror
                            @if (! empty($pair['multi_dropoff']))
                                {{-- the other addresses of this side, each with its own DO number (user, 28 Oct 2026) --}}
                                <div class="ow-table-scroll" style="margin-top:.4rem">
                                    <table class="ow-price-table ow-stops">
                                        <thead><tr><th>Address</th><th style="width:12rem">DO number <span class="ow-req">*</span></th><th style="width:2.5rem"></th></tr></thead>
                                        <tbody>
                                            @foreach ($pair['dropoff_stops'] ?? [] as $si => $stop)
                                                <tr wire:key="ow-o{{ $oi }}-pair-{{ $i }}-dropoff-stop-{{ $si }}">
                                                    <td><textarea wire:model="orders.{{ $oi }}.pairs.{{ $i }}.dropoff_stops.{{ $si }}.location" rows="1" class="ow-textarea" style="resize:vertical" placeholder="Address" @disabled($locked)></textarea></td>
                                                    <td>
                                                        <input type="text" wire:model="orders.{{ $oi }}.pairs.{{ $i }}.dropoff_stops.{{ $si }}.do_number" class="ow-input" placeholder="DO number" @disabled($locked)>
                                                        @error($ep.'pairs.'.$i.'.dropoff_stops.'.$si.'.do_number')<div class="ow-field-error">{{ $message }}</div>@enderror
                                                    </td>
                                                    <td style="white-space:nowrap">@if (! $locked && count($pair['dropoff_stops']) > 1)<button type="button" wire:click="inOrder({{ $oi }}, 'removeStop', {{ $i }}, 'dropoff', {{ $si }})" class="ow-icon-btn" title="Remove address" aria-label="Remove address">✕</button>@endif</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @unless ($locked)<button type="button" wire:click="inOrder({{ $oi }}, 'addStop', {{ $i }}, 'dropoff')" class="ow-btn-link" style="margin-top:.3rem">+ Add address</button>@endunless
                            @endif
                        </div>
                        <div class="ow-field">
                            <label for="{{ $fieldId }}-dropoff-type">Drop-off type</label>
                            <select id="{{ $fieldId }}-dropoff-type" wire:model="orders.{{ $oi }}.pairs.{{ $i }}.drop_off_type" class="ow-select">
                                @foreach ($this->dropOffTypeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                @php $multiStops = ! empty($pair['multi_pickup']) || ! empty($pair['multi_dropoff']); @endphp
                <div class="ow-fgrid ow-fgrid-do">
                    @unless ($multiStops)
                    <div class="ow-field">
                        <label for="{{ $fieldId }}-do">DO number @unless ($isQuote)<span class="ow-req">*</span>@endunless</label>
                        <input id="{{ $fieldId }}-do" type="text" wire:model="orders.{{ $oi }}.pairs.{{ $i }}.customer_do_number" class="ow-input" placeholder="Customer's DO number">
                        @error($ep.'pairs.'.$i.'.customer_do_number')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                    @endunless
                    <div class="ow-field">
                        <label for="{{ $fieldId }}-delivery">Expected delivery date</label>
                        <input id="{{ $fieldId }}-delivery" type="text" wire:model="orders.{{ $oi }}.pairs.{{ $i }}.expected_delivery_date" class="ow-input" maxlength="255" autocomplete="off" placeholder="Optional · e.g. 13/10 before noon">
                        @error($ep.'pairs.'.$i.'.expected_delivery_date')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="ow-table-wrap" style="margin-top:.9rem">
                    <table class="ow-price-table ow-products">
                        <thead>
                            <tr>
                                <th>Product <span class="ow-req">*</span></th>
                                <th>Quantity <span class="ow-req">*</span></th>
                                @unless ($isQuote)<th>Photos</th>@endunless
                                <th class="ow-num">Unit price</th>
                                <th class="ow-num">Line total</th>
                                <th style="width:4.5rem">
                                    @if (! $locked && filled($form['customer_id'] ?? null))
                                        {{-- every product's earlier orders of this customer; a row's own icon shows that product only --}}
                                        <button type="button" wire:click="mountAction('customerHistory', { order: {{ $oi }} })" class="ow-icon-btn" title="All previous records of this customer" aria-label="All previous records of this customer">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l3 2"/></svg>
                                        </button>
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pair['items'] as $j => $item)
                                @php $fixedItem = blank($item['kind'] ?? null) && (($item['line_type'] ?? 'uom') === 'item' || (filled($item['item_name'] ?? null) && ! array_key_exists((string) ($item['catalog_key'] ?? ''), $productOptions))); @endphp
                                <tr wire:key="ow-o{{ $oi }}-pair-{{ $i }}-item-{{ $j }}">
                                    <td style="min-width:30rem">
                                        @if ($fixedItem)
                                            {{-- product kept from the order record that is not in the UOM product list --}}
                                            <input type="text" class="ow-input" value="{{ $item['item_name'] }}" readonly>
                                            <div class="ow-price-hint">{{ ($item['line_type'] ?? '') === 'lorry' ? 'Lorry type' : (($item['line_type'] ?? '') === 'item' && filled($item['catalog_key'] ?? null) ? 'Transport item' : 'Not in the product list · remove it and pick a product to change it') }}</div>
                                        @else
                                            @php $kind = $this->itemKind($item); @endphp
                                            <div class="ow-product-pick">
                                                <select wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.kind" class="ow-select" aria-label="Product type">
                                                    <option value="">— Type —</option>
                                                    @foreach ($productKinds as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @if ($kind === 'measurement')
                                                    <select wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.sub_category" class="ow-select" aria-label="Sub-category">
                                                        <option value="">— Sub-category —</option>
                                                        @foreach ($measurementOptions as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                        <option value="{{ \App\Filament\Pages\CreateOrder::OTHER_PRODUCT }}">Others</option>
                                                    </select>
                                                    @if (($item['sub_category'] ?? '') === \App\Filament\Pages\CreateOrder::OTHER_PRODUCT)
                                                        <input type="text" wire:model.live.debounce.600ms="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.item_name" class="ow-input" placeholder="Product name" aria-label="Product name" autocomplete="off">
                                                    @endif
                                                @elseif ($kind !== '')
                                                    {{-- type, then sub-category, then the product of that sub-category (user, 25 Oct 2026) --}}
                                                    @php $sub = (string) ($item['sub_category'] ?? ''); @endphp
                                                    <select wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.sub_category" class="ow-select" aria-label="Sub-category">
                                                        <option value="">— Sub-category —</option>
                                                        @foreach ($subOptions[$kind] ?? [] as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                        <option value="{{ \App\Filament\Pages\CreateOrder::OTHER_PRODUCT }}">Others</option>
                                                    </select>
                                                    @if ($sub === \App\Filament\Pages\CreateOrder::OTHER_PRODUCT)
                                                        {{-- a product not in the system: named here, kept on this order only --}}
                                                        <input type="text" wire:model.live.debounce.600ms="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.item_name" class="ow-input" placeholder="Product name" aria-label="Product name" autocomplete="off">
                                                    @elseif ($sub !== '')
                                                        <select wire:model.live="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.catalog_key" class="ow-select" aria-label="Product">
                                                            <option value="">— Select {{ $kind === 'lorry' ? 'lorry' : 'item' }} —</option>
                                                            @foreach ($this->kindProductOptions($kind, $sub) as $key => $label)
                                                                <option value="{{ $key }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <select class="ow-select" disabled aria-label="Product"><option>Choose the sub-category first</option></select>
                                                    @endif
                                                @else
                                                    <select class="ow-select" disabled aria-label="Product"><option>Choose the type first</option></select>
                                                @endif
                                            </div>
                                            @if ($kind === 'measurement' && ($item['sub_category'] ?? '') !== \App\Filament\Pages\CreateOrder::OTHER_PRODUCT)
                                                <div class="ow-dims">
                                                    {{-- L, W, H as min – max ranges (user, 28 Oct 2026): the price list's size bands are ranges too --}}
                                                    @foreach (['length' => 'L (cm)', 'width' => 'W (cm)', 'height' => 'H (cm)'] as $dim => $dimLabel)
                                                        <div class="ow-dim">
                                                            <span>{{ $dimLabel }} <span class="ow-req">*</span></span>
                                                            <div class="ow-dim-range">
                                                                <input type="text" inputmode="decimal" autocomplete="off" placeholder="Min" aria-label="{{ $dimLabel }} min" wire:model.live.debounce.600ms="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.{{ $dim }}" class="ow-input" x-on:input="$el.value = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')">
                                                                <span aria-hidden="true">–</span>
                                                                <input type="text" inputmode="decimal" autocomplete="off" placeholder="Max" aria-label="{{ $dimLabel }} max" wire:model.live.debounce.600ms="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.{{ $dim }}_max" class="ow-input" x-on:input="$el.value = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')">
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                    <label class="ow-dim">
                                                        <span>Weight (kg) <span class="ow-req">*</span></span>
                                                        <input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.600ms="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.weight" class="ow-input" x-on:input="$el.value = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')">
                                                    </label>
                                                </div>
                                                <div class="ow-price-hint">{{ $this->measurementNote($item) }}</div>
                                            @endif
                                        @endif
                                        @error($ep.'pairs.'.$i.'.items.'.$j.'.item_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                                    </td>
                                    <td style="width:7rem">
                                        <input type="number" min="1" step="1" inputmode="numeric" x-on:keydown="if (['.', ',', 'e', 'E', '-', '+'].includes($event.key)) $event.preventDefault()" wire:model.live.debounce.500ms="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.quantity" class="ow-input" @disabled(($item['line_type'] ?? 'uom') !== 'uom')>
                                        @error($ep.'pairs.'.$i.'.items.'.$j.'.quantity')<div class="ow-field-error">{{ $message }}</div>@enderror
                                    </td>
                                    @unless ($isQuote)
                                    <td style="min-width:7.5rem">
                                        {{-- photos of this product: saved ones and the ones picked now (click a tile to view it) --}}
                                        @php $newItemPhotos = $this->newPhotos($i, $j); @endphp
                                        <div class="ow-item-photos">
                                            @foreach ($item['existing_photos'] ?? [] as $photo)
                                                <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="ow-item-photo" data-og-view data-url="{{ $photo['url'] }}" data-name="{{ $photo['name'] }}" data-image="{{ $photo['is_image'] ? '1' : '0' }}" title="{{ $photo['name'] }}">
                                                    @if ($photo['is_image'])<img src="{{ $photo['url'] }}" alt="{{ $photo['name'] }}" loading="lazy">@else{{ strtoupper(pathinfo($photo['name'], PATHINFO_EXTENSION) ?: 'FILE') }}@endif
                                                </a>
                                            @endforeach
                                            @foreach ($newItemPhotos as $k => $photo)
                                                <span class="ow-item-photo ow-item-photo-new" wire:key="ow-o{{ $oi }}-pair-{{ $i }}-item-{{ $j }}-photo-{{ $k }}" @if ($photo['url']) data-og-view data-url="{{ $photo['url'] }}" data-name="{{ $photo['name'] }}" data-image="1" tabindex="0" role="button" @endif title="{{ $photo['name'] }} · new">
                                                    @if ($photo['url'])<img src="{{ $photo['url'] }}" alt="{{ $photo['name'] }}">@else{{ strtoupper(pathinfo($photo['name'], PATHINFO_EXTENSION) ?: 'FILE') }}@endif
                                                    @unless ($locked)
                                                        <button type="button" wire:click="inOrder({{ $oi }}, 'removePhoto', {{ $i }}, {{ $j }}, {{ $k }})" class="ow-item-photo-x" title="Remove" aria-label="Remove {{ $photo['name'] }}">✕</button>
                                                    @endunless
                                                </span>
                                            @endforeach
                                            @unless ($locked)
                                                <label class="ow-item-photo-add" title="Add photos (JPG, PNG, WEBP or PDF · up to 8 MB each)">
                                                    <input type="file" wire:model="photoUploads.{{ $oi }}.{{ $i }}.{{ $j }}" multiple accept="image/*,application/pdf" aria-label="Add photos for {{ $item['item_name'] ?: 'this product' }}" x-on:livewire-upload-finish="$el.value = ''" x-on:livewire-upload-error="$el.value = ''">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                                </label>
                                            @endunless
                                        </div>
                                        <div wire:loading wire:target="photoUploads.{{ $oi }}.{{ $i }}.{{ $j }}" class="ow-note">Uploading…</div>
                                        @error($ep.'pairs.'.$i.'.items.'.$j.'.photos')<div class="ow-field-error">{{ $message }}</div>@enderror
                                        @error($ep.'pairs.'.$i.'.items.'.$j.'.photos.*')<div class="ow-field-error">{{ $message }}</div>@enderror
                                    </td>
                                    @endunless
                                    <td class="ow-num" style="width:15rem;white-space:normal">
                                        @if (! $showPrices)
                                            <span class="ow-note">Select a salesperson to see prices</span>
                                        @elseif ($item['unit_price'] !== null)
                                            {{-- the customer's special price, else the default price, filled in and editable (editing: the record's price) --}}
                                            <label class="ow-manual-price">
                                                <span>RM</span>
                                                <input type="text" inputmode="decimal" autocomplete="off" x-on:input="const v = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1'); if (v !== $el.value) $el.value = v" x-on:blur="const n = parseFloat($el.value); if ($el.value.trim() !== '' && ! isNaN(n) && $el.value !== n.toFixed(2)) { $el.value = n.toFixed(2); $el.dispatchEvent(new Event('input')) }" wire:model.live.debounce.500ms="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.manual_price" class="ow-input ow-price-input" placeholder="{{ number_format((float) $item['unit_price'], 2, '.', '') }}" aria-label="Unit price for {{ $item['item_name'] }}" @disabled($locked)>
                                            </label>
                                            @php $sourceLabel = match ($item['source'] ?? null) { 'special' => 'Special price', 'master' => 'Default price', default => null }; @endphp
                                            @if ($item['price_edited'] ?? false)
                                                <div class="ow-price-hint ow-price-diff">Changed · {{ $sourceLabel ?? 'Current price' }} RM {{ number_format((float) $item['unit_price'], 2) }}</div>
                                            @elseif ($sourceLabel || $item['tier'])
                                                <div class="ow-price-hint">
                                                    @if ($sourceLabel)<span @class(['ow-price-special' => ($item['source'] ?? null) === 'special'])>{{ $sourceLabel }}</span>@endif{{ $sourceLabel && $item['tier'] ? ' · ' : '' }}{{ $item['tier'] }}
                                                </div>
                                            @endif
                                            @error($ep.'pairs.'.$i.'.items.'.$j.'.manual_price')<div class="ow-field-error">{{ $message }}</div>@enderror
                                        @elseif (filled($item['item_name']) && blank($pair['to_location_id']))
                                            <span class="ow-note">Select the "To" location</span>
                                            @if ($item['available'] ?? null)<div class="ow-price-hint">Rated: {{ $item['available'] }}</div>@endif
                                        @elseif (filled($item['item_name']))
                                            {{-- no special / price-list rate: the price is keyed in here (blank = priced later under Items & pricing) --}}
                                            <label class="ow-manual-price">
                                                <span>RM</span>
                                                <input type="text" inputmode="decimal" autocomplete="off" x-on:input="const v = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1'); if (v !== $el.value) $el.value = v" x-on:blur="const n = parseFloat($el.value); if ($el.value.trim() !== '' && ! isNaN(n) && $el.value !== n.toFixed(2)) { $el.value = n.toFixed(2); $el.dispatchEvent(new Event('input')) }" wire:model.live.debounce.500ms="orders.{{ $oi }}.pairs.{{ $i }}.items.{{ $j }}.manual_price" class="ow-input ow-price-input" placeholder="0.00" aria-label="Unit price for {{ $item['item_name'] }}" @disabled($locked)>
                                            </label>
                                            <div class="ow-price-hint ow-price-diff">{{ match (true) {
                                                ($item['no_band'] ?? false) => 'No size band fits · key in the price',
                                                ($item['sub_category'] ?? '') === \App\Filament\Pages\CreateOrder::OTHER_PRODUCT => 'Not in the system · for this order only · key in the price',
                                                ($item['kind'] ?? '') === 'measurement' && blank($item['catalog_key'] ?? null) => 'Enter L, W, H and weight for the list price, or key in the price',
                                                default => 'No rate for this location · key in the price',
                                            } }}</div>
                                            @if ($item['available'] ?? null)<div class="ow-price-hint">Rated: {{ $item['available'] }}</div>@endif
                                            @error($ep.'pairs.'.$i.'.items.'.$j.'.manual_price')<div class="ow-field-error">{{ $message }}</div>@enderror
                                        @else
                                            <span class="ow-note">—</span>
                                        @endif
                                    </td>
                                    <td class="ow-num" style="width:8rem">
                                        {{ $showPrices && $this->effectivePrice($item) !== null ? 'RM '.number_format($totals['lines'][$i][$j] ?? 0, 2) : '—' }}
                                    </td>
                                    <td style="width:4.5rem;white-space:nowrap">
                                        <span class="ow-row-actions">
                                            @if (! $locked && filled($form['customer_id'] ?? null) && filled($item['item_name'] ?? null))
                                                {{-- previous records of this product for the customer (information only) --}}
                                                <button type="button" wire:click="mountAction('productHistory', { order: {{ $oi }}, pair: {{ $i }}, item: {{ $j }} })" class="ow-icon-btn" title="Prices &amp; previous records of this product" aria-label="Previous records of {{ $item['item_name'] }} for this customer">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l3 2"/></svg>
                                                </button>
                                            @endif
                                            @if (count($pair['items']) > 1 && ! $locked)
                                                <button type="button" wire:click="inOrder({{ $oi }}, 'removeItem', {{ $i }}, {{ $j }})" class="ow-btn-link" title="Remove" style="color:#b91c1c">✕</button>
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (! $locked)
                    <button type="button" wire:click="inOrder({{ $oi }}, 'addItem', {{ $i }})" class="ow-btn-link" style="margin-top:.5rem">+ Add product</button>
                @endif

                {{-- price list minimum (pick-up / construction site / drop-off): a lower total is topped up --}}
                @php $minimum = $totals['minimums'][$i] ?? null; @endphp
                @if ($minimum && $minimum['top_up'] > 0)
                    <div class="ow-minimum-charge">
                        <span><strong>Minimum charge</strong> · {{ $minimum['label'] }} RM {{ number_format($minimum['minimum'], 2) }} (products RM {{ number_format($minimum['subtotal'], 2) }})</span>
                        <strong>+ RM {{ number_format($minimum['top_up'], 2) }}</strong>
                    </div>
                @endif

                {{-- photos / DO attachments saved for this consignor & consignee before photos were kept per product (read-only) --}}
                @php $savedPhotos = $pair['existing_photos'] ?? []; @endphp
                @if ($savedPhotos !== [])
                <div class="ow-field" style="margin-top:.85rem" wire:key="ow-o{{ $oi }}-pair-{{ $i }}-photos">
                    <label>Photos / DO attachments saved with this record</label>
                        <div class="ow-photo-grid">
                            @foreach ($savedPhotos as $photo)
                                <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="ow-photo" data-og-view data-url="{{ $photo['url'] }}" data-name="{{ $photo['name'] }}" data-image="{{ $photo['is_image'] ? '1' : '0' }}" title="{{ $photo['name'] }}">
                                    @if ($photo['is_image'])
                                        <img src="{{ $photo['url'] }}" alt="{{ $photo['name'] }}" loading="lazy">
                                    @else
                                        <span class="ow-photo-file">{{ strtoupper(pathinfo($photo['name'], PATHINFO_EXTENSION) ?: 'FILE') }}</span>
                                    @endif
                                    <span class="ow-photo-name">{{ $photo['name'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    <div class="ow-note">New photos are added per product in the table above.</div>
                </div>
                @endif

                {{-- notes (user, 27 Oct 2026): the driver's go on the CSN, the admin's stay internal --}}
                <div class="ow-fgrid ow-fgrid-2" style="margin-top:.85rem">
                    <div class="ow-field">
                        <label>Driver notes</label>
                        <textarea wire:model="orders.{{ $oi }}.pairs.{{ $i }}.instructions" rows="2" class="ow-textarea" style="resize:vertical" placeholder="e.g. Call before delivery"></textarea>
                    </div>
                    <div class="ow-field">
                        <label>Admin notes</label>
                        <textarea wire:model="orders.{{ $oi }}.pairs.{{ $i }}.admin_notes" rows="2" class="ow-textarea" style="resize:vertical" placeholder="For the office only"></textarea>
                    </div>
                </div>
                @if ($editing)</fieldset>@endif
            </div>
        @endforeach

        @if ($this->canAddPair())
            <div>
                <button type="button" wire:click="inOrder({{ $oi }}, 'addPair')" class="ow-btn">+ Add another consignor &amp; consignee</button>
            </div>
        @endif
        @endif

        </div>
        @endforeach
        @php
            // the page logic is back on the order it was working on
            $this->useOrder($workingOrder);
            $pairs = $this->orders[$workingOrder]['pairs'] ?? [];
        @endphp

        {{-- files saved earlier for the whole order (customer portal upload / the order-level upload used before
             photos were kept per consignor & consignee): read-only, shown on every record of the order --}}
        @if ($editing && ($sharedFiles = $this->existingAttachments()) !== [])
            <div class="ow-card ow-card-pad">
                <div class="ow-card-title">Order photos / DO attachments (whole order)</div>
                <div class="ow-photo-grid">
                    @foreach ($sharedFiles as $photo)
                        <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="ow-photo" data-og-view data-url="{{ $photo['url'] }}" data-name="{{ $photo['name'] }}" data-image="{{ $photo['is_image'] ? '1' : '0' }}" title="{{ $photo['name'] }}">
                            @if ($photo['is_image'])
                                <img src="{{ $photo['url'] }}" alt="{{ $photo['name'] }}" loading="lazy">
                            @else
                                <span class="ow-photo-file">{{ strtoupper(pathinfo($photo['name'], PATHINFO_EXTENSION) ?: 'FILE') }}</span>
                            @endif
                            <span class="ow-photo-name">{{ $photo['name'] }}</span>
                        </a>
                    @endforeach
                </div>
                <p class="ow-note">Saved for the whole order. New photos are added per product in each consignor &amp; consignee section above.</p>
            </div>
        @endif

        {{-- a quotation has no total (user, 21 Oct 2026): its products' quantity ranges are the prices offered --}}

        {{-- amount summary: every order of the page and the total of all --}}
        @unless ($editing || $isQuote)
            @php $grand = collect($pageOrders)->filter(fn ($o) => ($o['summary']['priced'] ?? false))->sum(fn ($o) => (float) ($o['summary']['total'] ?? 0)); @endphp
            <div class="ow-card ow-card-pad">
                <div class="ow-card-title">{{ $isQuote ? 'Quotation amount' : 'Amount summary' }}</div>
                <div class="ow-table-wrap">
                    <table class="ow-price-table ow-order-summary">
                        <thead>
                            <tr>
                                @unless ($isQuote)<th class="ow-pay-col">Pay now</th>@endunless
                                <th>{{ $isQuote ? 'Quotation' : 'Order' }}</th>
                                <th>{{ $isQuote ? 'Customer / lead' : 'Customer' }}</th>
                                <th>Consignor &amp; consignee</th>
                                <th>Payment term</th>
                                <th class="ow-num">Products</th>
                                <th class="ow-num">Minimum charge</th>
                                <th class="ow-num">Total</th>
                                @unless ($isQuote)<th>Payment</th>@endunless
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pageOrders as $order)
                                @php $s = $order['summary']; @endphp
                                <tr style="--ow-order-color: {{ $order['color'] }}" x-on:click="document.getElementById('ow-order-section1-{{ $order['index'] }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })" title="Go to order {{ $order['number'] }}">
                                    @unless ($isQuote)
                                    <td class="ow-pay-col" x-on:click.stop>
                                        <input type="checkbox" @checked($s['pay_now'] ?? false) @disabled(($s['cash_records'] ?? 0) === 0 && ! ($s['pay_now'] ?? false)) wire:click.stop="togglePayOrder({{ $order['index'] }})" title="{{ ($s['cash_records'] ?? 0) === 0 ? 'Needs a consignor & consignee with the Cash payment term' : 'Record the payment of this order now (its Cash records)' }}" aria-label="Pay order {{ $order['number'] }} now">
                                    </td>
                                    @endunless
                                    <td><span class="ow-order-dot" aria-hidden="true"></span> {{ $isQuote ? ($this->quoteNumber() ?? 'New quotation') : 'Order '.$order['number'] }}</td>
                                    <td>{{ $s['customer'] ?? '—' }}</td>
                                    <td>{{ $s['records'] ?? 0 }}</td>
                                    <td>{{ implode(', ', $s['terms'] ?? []) ?: '—' }}</td>
                                    @if ($s['priced'] ?? false)
                                        <td class="ow-num">RM {{ number_format((float) $s['items'], 2) }}</td>
                                        <td class="ow-num">{{ (float) $s['minimum'] > 0 ? 'RM '.number_format((float) $s['minimum'], 2) : '—' }}</td>
                                        <td class="ow-num"><strong>RM {{ number_format((float) $s['total'], 2) }}</strong></td>
                                    @else
                                        <td class="ow-num" colspan="3"><span class="ow-note">Not priced · assign a salesperson</span></td>
                                    @endif
                                    @unless ($isQuote)<td>{{ ($s['pay_now'] ?? false) ? 'Pay now · '.(($s['priced'] ?? false) ? 'RM '.number_format((float) ($s['cash_total'] ?? 0), 2) : 'not priced') : '—' }}</td>@endunless
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($multiOrder)
                            <tfoot>
                                <tr>
                                    <td colspan="7"><strong>All {{ count($pageOrders) }} orders</strong></td>
                                    <td class="ow-num"><strong>RM {{ number_format($grand, 2) }}</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endunless

        {{-- one payment for every order ticked "Pay now" in the Amount summary: their Cash records are confirmed, paid in
             full and billed (Cash Bill + CSN) on save --}}
        @unless ($editing || $isQuote)
            @php $paying = collect($pageOrders)->filter(fn ($o) => $o['summary']['pay_now'] ?? false)->values(); @endphp
            <div class="ow-card ow-card-pad">
                <div class="ow-card-title">Payment</div>
                @if ($paying->isEmpty())
                    <p class="ow-note">Tick <strong>Pay now</strong> for each order paid now. Their Cash consignor &amp; consignee records are confirmed for the customer and paid; fully paid, their Cash Bill and CSN are created on save.</p>
                @else
                    <div class="ow-pay-list">
                        @foreach ($paying as $order)
                            <span class="ow-pay-chip" style="--ow-order-color: {{ $order['color'] }}"><span class="ow-order-dot" aria-hidden="true"></span> Order {{ $order['number'] }} · {{ $order['summary']['customer'] ?? 'no customer yet' }} · {{ ($order['summary']['priced'] ?? false) ? 'RM '.number_format((float) ($order['summary']['cash_total'] ?? 0), 2) : 'not priced' }}</span>
                        @endforeach
                        <strong class="ow-pay-total">Total RM {{ number_format((float) $paying->sum(fn ($o) => ($o['summary']['priced'] ?? false) ? (float) ($o['summary']['cash_total'] ?? 0) : 0), 2) }}</strong>
                    </div>
                    @error('payment.record')<div class="ow-field-error">{{ $message }}</div>@enderror
                    @php $payPlan = $this->paymentPlan(); @endphp
                    {{-- the amount received: blank = in full; less = split over the orders (partial); more = change --}}
                    <div class="ow-fgrid" style="margin-top:.85rem">
                        <div class="ow-field">
                            <label>Amount received (RM)</label>
                            <input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.500ms="payment.amount_received" x-on:input="const v = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1'); if (v !== $el.value) $el.value = v" class="ow-input" placeholder="{{ number_format($payPlan['total'], 2, '.', '') }}">
                            <div class="ow-note">Blank = the full amount (RM {{ number_format($payPlan['total'], 2) }}).</div>
                            @error('payment.amount_received')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    @if (! $payPlan['full'] && $payPlan['received'] > 0)
                        <div class="ow-pay-split">
                            <div class="ow-note">Received <strong>RM {{ number_format($payPlan['received'], 2) }}</strong> of RM {{ number_format($payPlan['total'], 2) }}: enter how much goes to each order. An order paid less than its total is a partial payment: it stays <strong>Pending payment</strong> (no CSN yet).</div>
                            <table class="ow-price-table" style="margin-top:.4rem">
                                <thead><tr><th>Order</th><th class="ow-num">Cash total</th><th class="ow-num">Paying (RM)</th></tr></thead>
                                <tbody>
                                    @foreach ($paying as $order)
                                        @php $payKey = 'o'.$order['index']; $payDue = (float) ($order['summary']['cash_total'] ?? 0); $payAmt = $payPlan['amounts'][$payKey] ?? 0; @endphp
                                        <tr wire:key="ow-pay-split-{{ $order['index'] }}">
                                            <td><span class="ow-order-dot" style="--ow-order-color: {{ $order['color'] }}" aria-hidden="true"></span> Order {{ $order['number'] }} · {{ $order['summary']['customer'] ?? '—' }}</td>
                                            <td class="ow-num">RM {{ number_format($payDue, 2) }}</td>
                                            <td class="ow-num" style="width:11rem">
                                                <input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.500ms="payment.allocations.{{ $payKey }}" x-on:input="const v = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1'); if (v !== $el.value) $el.value = v" class="ow-input ow-price-input" aria-label="Paying for order {{ $order['number'] }}">
                                                @if ($payPlan['errors'][$payKey] ?? null)
                                                    <div class="ow-field-error">{{ $payPlan['errors'][$payKey] }}</div>
                                                @elseif ($payAmt > 0 && $payAmt < $payDue - 0.004)
                                                    <div class="ow-price-hint">partial · RM {{ number_format($payDue - $payAmt, 2) }} left</div>
                                                @elseif ($payAmt <= 0)
                                                    <div class="ow-price-hint">not paid now</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div @class(['ow-pay-split-sum', 'ow-price-diff' => abs($payPlan['left']) >= 0.005])>
                                Given RM {{ number_format($payPlan['allocated'], 2) }}{{ abs($payPlan['left']) >= 0.005 ? ' · RM '.number_format(abs($payPlan['left']), 2).($payPlan['left'] > 0 ? ' still to give' : ' too much') : ' · all of it given' }}
                            </div>
                        </div>
                    @elseif ($payPlan['change'] > 0.004)
                        <div class="ow-pay-change">Change to give back to the customer: <strong>RM {{ number_format($payPlan['change'], 2) }}</strong></div>
                    @endif
                    <div class="ow-fgrid" style="margin-top:.85rem">
                        <div class="ow-field">
                            <label>Payment method <span class="ow-req">*</span></label>
                            <select wire:model.live="payment.method" class="ow-select">
                                @foreach ($this->paymentMethodOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('payment.method')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ow-field">
                            <label>Payment date <span class="ow-req">*</span></label>
                            <input type="date" wire:model="payment.payment_date" max="{{ now()->toDateString() }}" class="ow-input">
                            @error('payment.payment_date')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ow-field">
                            <label>Reference (optional)</label>
                            <input type="text" wire:model="payment.reference" class="ow-input" placeholder="e.g. bank reference / receipt no." autocomplete="off">
                            @error('payment.reference')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="ow-field" style="margin-top:.75rem">
                        <label>Payment proof (slip / receipt)</label>
                        <input type="file" wire:model="paymentProofs" multiple accept="image/*,application/pdf" class="ow-input">
                        <div wire:loading wire:target="paymentProofs" class="ow-note">Uploading…</div>
                        @if ($paymentProofs !== [])
                            <div class="ow-pay-proofs">
                                @foreach ($paymentProofs as $k => $proof)
                                    <span class="ow-pill ow-pill-gray" wire:key="ow-proof-{{ $k }}">{{ method_exists($proof, 'getClientOriginalName') ? $proof->getClientOriginalName() : 'file' }} <button type="button" wire:click="removePaymentProof({{ $k }})" title="Remove" aria-label="Remove this proof">✕</button></span>
                                @endforeach
                            </div>
                        @endif
                        @error('paymentProofs.*')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                    @if (in_array($payment['method'] ?? '', ['cash', 'counter'], true))
                        <p class="ow-note" style="margin-top:.6rem">Cash / Pay at Counter payments need a second user to approve them before the Cash Bill and CSN are created (two approval levels). Bank transfer, online, e-wallet and cheque go straight to CSN created.</p>
                    @endif
                @endif
            </div>
        @endunless

        <div class="ow-footer-bar">
            <div class="ow-footer-meta">
                @php $footerTotal = collect($pageOrders)->filter(fn ($o) => ($o['summary']['priced'] ?? false))->sum(fn ($o) => (float) ($o['summary']['total'] ?? 0)); @endphp
                @unless ($isQuote)
                    Price: {{ $footerTotal > 0 ? 'RM '.number_format($footerTotal, 2).($multiOrder ? ' ('.count($pageOrders).' orders)' : '') : 'Not priced' }}
                @endunless
                @if ($editing)
                    · {{ count($pairs) }} {{ $this->enquiryOnly ? 'consignor & consignee '.\Illuminate\Support\Str::plural('block', count($pairs)) : \Illuminate\Support\Str::plural('record', count($pairs)) }} under {{ $this->orderNumber }}
                    @if ($lockedCount > 0) · {{ $lockedCount }} locked @endif
                @else
                    · {{ $isQuote ? 'Pending confirmation until Create order' : 'Customer confirmation: Not requested' }}
                    · {{ $multiOrder ? count($pageOrders).' orders, '.collect($pageOrders)->sum(fn ($o) => $o['summary']['records'] ?? 0).' records' : count($pairs).' '.\Illuminate\Support\Str::plural('record', count($pairs)).' under one order number' }}
                @endif
            </div>
            <div class="ow-actions">
                <a href="{{ $copy['cancel_url'] }}" class="ow-btn">Cancel</a>
                @php
                    $payNow = $this->paysAny();
                    $submit = match (true) {
                        $editing => $copy['submit'],
                        $multiOrder => 'Save '.count($pageOrders).' orders'.($payNow ? ' & record payment' : '').' →',
                        $payNow => 'Save order & record payment →',
                        default => $copy['submit'],
                    };
                @endphp
                <button type="button" wire:click="save" wire:loading.attr="disabled" class="ow-btn ow-btn-primary">{{ $submit }}</button>
            </div>
        </div>
    </div>
    {{-- photo viewer: a product photo / saved file opens here (same viewer as the payment slips) --}}
    <div x-data="{ view: null }"
         x-on:og-file-view.window="view = $event.detail"
         x-on:keydown.escape.window="view = null">
        <template x-teleport="body">
            <div x-show="view" x-cloak x-transition.opacity class="ow-file-viewer" x-on:click.self="view = null" role="dialog" aria-modal="true" aria-label="Photo">
                <div class="ow-file-viewer-box" x-show="view">
                    <div class="ow-file-viewer-head">
                        <span class="ow-file-viewer-name" x-text="view?.name"></span>
                        <a x-bind:href="view?.url" target="_blank" rel="noopener" class="ow-file-viewer-link">Open in new tab</a>
                        <button type="button" class="ow-file-viewer-close" x-on:click="view = null" aria-label="Close">&times;</button>
                    </div>
                    <div class="ow-file-viewer-body">
                        <template x-if="view && view.image"><img x-bind:src="view.url" x-bind:alt="view.name"></template>
                        <template x-if="view && ! view.image"><iframe x-bind:src="view.url" x-bind:title="view.name"></iframe></template>
                    </div>
                </div>
            </div>
        </template>
    </div>
    <script>
        (() => {
            if (window.ogOrderPhotoViewerReady) return;
            window.ogOrderPhotoViewerReady = true;

            // a row of the Amount summary opens its order: scroll to its "1. Customer & ownership"
            window.addEventListener('og-order-opened', (event) => setTimeout(() => {
                document.getElementById('ow-order-section1-' + (event.detail?.order ?? 0))?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 120));

            const open = (tile) => window.dispatchEvent(new CustomEvent('og-file-view', { detail: {
                url: tile.dataset.url,
                name: tile.dataset.name || '',
                image: tile.dataset.image === '1',
            } }));

            // a photo tile (product row / saved files) opens the viewer instead of a new tab
            document.addEventListener('click', (event) => {
                const tile = event.target.closest('[data-og-view]');
                if (! tile || event.target.closest('button') || event.ctrlKey || event.metaKey || event.shiftKey) return;
                event.preventDefault();
                open(tile);
            });
            document.addEventListener('keydown', (event) => {
                const tile = event.target.closest?.('span[data-og-view]');
                if (tile && (event.key === 'Enter' || event.key === ' ')) {
                    event.preventDefault();
                    open(tile);
                }
            });
        })();
    </script>
</x-filament-panels::page>
