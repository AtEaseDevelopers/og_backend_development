@php
    $showPrices = $this->showPrices();
    $totals = $this->pairTotals();
    $uomOptions = $this->catalogOptions('uom');
    $locations = $this->locationOptions();
    $addresses = $this->addressOptions();
    $customerName = $this->customerName();
    $editing = $this->isEditing();
    $copy = $this->pageCopy();
    $headerEditable = $this->headerEditable();
    $lockedCount = count(array_filter(array_keys($pairs), fn ($i) => $this->isPairLocked($i)));
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
            <div class="ow-crumb">{{ $copy['crumb'] }}</div>
            <h1 class="ow-title">{{ $copy['title'] }}</h1>
            <p class="ow-sub">{{ $copy['sub'] }}</p>
        </div>
    </div>

    <datalist id="ow-consignor-list">
        @foreach ($this->consignorSuggestions() as $suggestion)
            <option value="{{ $suggestion }}"></option>
        @endforeach
    </datalist>

    <div class="ow-stack">
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
        @else
            <div class="ow-callout ow-callout-info">
                <strong>Entered by {{ auth()->user()?->name }} · On behalf of customer</strong><br>
                Submitting this form does not mean the customer has accepted a price. Every consignor &amp; consignee below becomes its own record (own quotation, invoice and CSN numbers) under one order number.
            </div>
        @endif

        {{-- 1. Customer & ownership --}}
        <div class="ow-card ow-card-pad">
            <div class="ow-card-title">1. Customer &amp; ownership</div>
            {{-- one grid, visual order = DOM (tab) order: Customer · Billing address (2 columns, once a customer is picked) · Received through, then the rest; on 2-column widths Customer takes its own row so Billing address does not leave a gap beside it --}}
            @php $billingShown = filled($form['customer_id'] ?? null); @endphp
            <style>
                .ow-page .ow-fgrid-cust .ow-field-wide { grid-column: span 2 / span 2; min-width: 0; }
                .ow-page .ow-fgrid-cust .ow-field-wide .ow-textarea { resize: vertical; }
                @media (max-width: 900px) { .ow-page .ow-fgrid-cust .ow-field-lead { grid-column: 1 / -1; } }
            </style>
            <div class="ow-fgrid ow-fgrid-cust">
                <div @class(['ow-field', 'ow-field-lead' => $billingShown])>
                    <label>Customer <span class="ow-req">*</span></label>
                    <select wire:model.live="form.customer_id" class="ow-select" @disabled(! $headerEditable)>
                        <option value="">— Select customer —</option>
                        @foreach ($this->customerOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.customer_id')<div class="ow-field-error">{{ $message }}</div>@enderror
                </div>
                @if ($billingShown)
                    <div class="ow-field ow-field-wide" wire:key="ow-billing-address">
                        <label for="ow-billing-address">Billing address</label>
                        <textarea id="ow-billing-address" wire:model="form.customer_address" rows="2" class="ow-textarea" placeholder="Customer's billing address" @disabled(! $headerEditable)></textarea>
                        <div class="ow-note" style="margin-top:.2rem">From the customer's saved address · edit it for this order. Used on every record of the order.</div>
                        @error('form.customer_address')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                @endif
                @if ($this->showReceivedThrough())
                    <div class="ow-field">
                        <label>Received through (optional)</label>
                        <select wire:model="form.received_through" class="ow-select" @disabled(! $headerEditable)>
                            @if ($this->receivedThroughClearable())
                                <option value="">— Not specified —</option>
                            @endif
                            @foreach ($this->receivedThroughOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('form.received_through')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                @endif
                <div class="ow-field">
                    <label>Salesperson</label>
                    <select wire:model.live="form.salesperson_id" class="ow-select" @disabled(! $this->salespersonEditable())>
                        @if ($this->allowNoSalesperson())
                            <option value="">— No salesperson yet —</option>
                        @endif
                        @foreach ($this->salespersonOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ow-field">
                    <label>Payment term <span class="ow-req">*</span></label>
                    <select wire:model.live="form.order_type" class="ow-select" @disabled(! $headerEditable)>
                        @foreach ($this->orderTypeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.order_type')<div class="ow-field-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <p class="ow-note" style="margin-top:.75rem">
                @if ($editing)
                    @if (! $headerEditable)
                        Customer, billing address, received through and payment term are locked because a record of this order is already confirmed. Use <strong>Change payment term</strong> on the order page for the payment term.
                    @endif
                    @if (! $this->salespersonEditable())
                        The salesperson is fixed for this order.
                    @elseif (! $this->allowNoSalesperson())
                        Changing the salesperson moves every record of this order to the new owner.
                    @else
                        Salesperson is optional. Without one product prices stay hidden.
                    @endif
                    Payment terms follow the customer's type: Credit customers Credit / Term, Cash or COD; COD customers COD; Cash customers Cash.
                @else
                    Salesperson is optional. Without one the order is saved as <strong>Pending salesperson</strong> and product prices stay hidden.
                    Payment terms follow the customer's type: Credit customers Credit / Term, Cash or COD; COD customers COD; Cash customers Cash.
                    Cash orders go through the payment summary (the payment method is recorded with the payment); Credit term and COD orders go straight to invoice and CSN after the customer confirms.
                @endif
            </p>
        </div>

        {{-- 2. Consignor & consignee blocks --}}
        @foreach ($pairs as $i => $pair)
            @php $locked = $this->isPairLocked($i); @endphp
            <div class="ow-card ow-card-pad ow-pair" wire:key="ow-pair-{{ $i }}">
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
                    @if ($this->canRemovePair($i))
                        <button type="button" wire:click="removePair({{ $i }})" class="ow-btn ow-btn-sm ow-btn-danger">Remove</button>
                    @endif
                </div>

                @if ($locked)
                    <div class="ow-callout ow-callout-warning" style="margin-bottom:.85rem">{{ $pair['lock_note'] ?? 'This record is locked. Confirmed records are changed with the Revise action on the order page.' }}</div>
                @endif

                @if ($editing)<fieldset @disabled($locked) style="border:0;padding:0;margin:0;min-width:0">@endif
                @php
                    $isStore = ($pair['service_type'] ?? '') === \App\Enums\ServiceType::Store->value;
                    $fieldId = 'ow-p'.$i;
                @endphp
                <div class="ow-grid-2">
                    <div class="ow-party">
                        <div class="ow-party-head">
                            <div class="ow-party-title" id="{{ $fieldId }}-consignor-title">Consignor</div>
                            {{-- Pickup (collected from the consignor) or Store (brought to an O&G branch): radios, so arrow keys switch --}}
                            <div class="ow-seg" role="radiogroup" aria-labelledby="{{ $fieldId }}-consignor-title">
                                @foreach ($serviceOptions as $value => $label)
                                    <label>
                                        <input type="radio" name="{{ $fieldId }}-mode" value="{{ $value }}" wire:model.live="pairs.{{ $i }}.service_type">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="ow-field">
                            <div class="ow-label-row">
                                <label for="{{ $fieldId }}-consignor">Consignor (optional)</label>
                                @if (! $locked)
                                    <span class="ow-quick">
                                        @if (filled($pair['consignor_name'] ?? null))
                                            <button type="button" wire:click="clearConsignor({{ $i }})" class="ow-btn-link">Clear</button>
                                        @endif
                                        @if ($customerName && trim((string) ($pair['consignor_name'] ?? '')) !== $customerName)
                                            <button type="button" wire:click="useCustomerAsConsignor({{ $i }})" class="ow-btn-link">Use customer</button>
                                        @endif
                                    </span>
                                @endif
                            </div>
                            <input id="{{ $fieldId }}-consignor" type="text" wire:model.blur="pairs.{{ $i }}.consignor_name" list="ow-consignor-list" class="ow-input" autocomplete="off">
                            @error('pairs.'.$i.'.consignor_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </div>
                        @if ($isStore)
                            @php $storeInfo = $this->storeInfo($pair['store_branch_id'] ?? null); @endphp
                            <div class="ow-field" wire:key="ow-pair-{{ $i }}-store">
                                <label for="{{ $fieldId }}-store">Store <span class="ow-req">*</span></label>
                                <select id="{{ $fieldId }}-store" wire:model.live="pairs.{{ $i }}.store_branch_id" class="ow-select">
                                    <option value="">— Select store —</option>
                                    @foreach ($stores as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('pairs.'.$i.'.store_branch_id')<div class="ow-field-error">{{ $message }}</div>@enderror
                                @if ($storeInfo)
                                    <div class="ow-store-card">
                                        @if ($storeInfo['address'] !== '')
                                            <div class="ow-store-address">{{ $storeInfo['address'] }}</div>
                                        @else
                                            <div class="ow-note">
                                                No address saved for {{ $storeInfo['name'] }} yet ·
                                                @if ($storeInfo['edit_url'])<a href="{{ $storeInfo['edit_url'] }}" target="_blank" rel="noopener" class="ow-link">add it in Branches</a>@else add it in Master Data → Branches @endif.
                                                Until then the store name is used as the pickup address.
                                            </div>
                                        @endif
                                        @if ($storeInfo['phone'] !== '')
                                            <div class="ow-note">Tel {{ $storeInfo['phone'] }}</div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                        <div class="ow-field">
                            <label for="{{ $fieldId }}-from">From (price list location)</label>
                            <select id="{{ $fieldId }}-from" wire:model="pairs.{{ $i }}.from_location_id" class="ow-select">
                                <option value="">— Select —</option>
                                @foreach ($locations as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-pic-grid">
                            <div class="ow-field">
                                <label for="{{ $fieldId }}-consignor-pic">PIC name</label>
                                <input id="{{ $fieldId }}-consignor-pic" type="text" wire:model="pairs.{{ $i }}.consignor_pic_name" class="ow-input" autocomplete="off">
                                @error('pairs.'.$i.'.consignor_pic_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="ow-field">
                                <label for="{{ $fieldId }}-consignor-phone">Contact number</label>
                                <input id="{{ $fieldId }}-consignor-phone" type="tel" inputmode="tel" wire:model="pairs.{{ $i }}.consignor_pic_phone" class="ow-input" autocomplete="off">
                                @error('pairs.'.$i.'.consignor_pic_phone')<div class="ow-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        @unless ($isStore)
                            <div class="ow-field" wire:key="ow-pair-{{ $i }}-pickup">
                                <label for="{{ $fieldId }}-pickup">Pickup location</label>
                                <select id="{{ $fieldId }}-pickup" wire:model.live="pairs.{{ $i }}.pickup_preset" class="ow-select">
                                    <option value="">— Select —</option>
                                    @foreach ($addresses as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                    <option value="{{ $newAddress }}">+ New address…</option>
                                </select>
                                @if (($pair['pickup_preset'] ?? '') === $newAddress)
                                    <textarea wire:model="pairs.{{ $i }}.pickup_location" rows="2" class="ow-textarea" style="margin-top:.4rem;resize:vertical" placeholder="Type the pickup address" aria-label="New pickup address"></textarea>
                                @elseif (filled($pair['pickup_location'] ?? null))
                                    <div class="ow-note" style="margin-top:.25rem;white-space:pre-line">{{ $pair['pickup_location'] }}</div>
                                @endif
                                @error('pairs.'.$i.'.pickup_location')<div class="ow-field-error">{{ $message }}</div>@enderror
                            </div>
                        @endunless
                    </div>

                    <div class="ow-party">
                        <div class="ow-party-head">
                            <div class="ow-party-title">Consignee (drop-off)</div>
                        </div>
                        <div class="ow-field">
                            <label for="{{ $fieldId }}-consignee">Consignee (optional)</label>
                            <input id="{{ $fieldId }}-consignee" type="text" wire:model="pairs.{{ $i }}.consignee_name" class="ow-input" autocomplete="off">
                            @error('pairs.'.$i.'.consignee_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ow-field">
                            <label for="{{ $fieldId }}-to">To (price list location)</label>
                            <select id="{{ $fieldId }}-to" wire:model.live="pairs.{{ $i }}.to_location_id" class="ow-select">
                                <option value="">— Select —</option>
                                @foreach ($locations as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-pic-grid">
                            <div class="ow-field">
                                <label for="{{ $fieldId }}-consignee-pic">PIC name</label>
                                <input id="{{ $fieldId }}-consignee-pic" type="text" wire:model="pairs.{{ $i }}.consignee_pic_name" class="ow-input" autocomplete="off">
                                @error('pairs.'.$i.'.consignee_pic_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="ow-field">
                                <label for="{{ $fieldId }}-consignee-phone">Contact number</label>
                                <input id="{{ $fieldId }}-consignee-phone" type="tel" inputmode="tel" wire:model="pairs.{{ $i }}.consignee_pic_phone" class="ow-input" autocomplete="off">
                                @error('pairs.'.$i.'.consignee_pic_phone')<div class="ow-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        {{-- same picker as the pickup location: a saved address, or "New address…" typed right below --}}
                        <div class="ow-field" wire:key="ow-pair-{{ $i }}-dropoff">
                            <label for="{{ $fieldId }}-dropoff">Drop-off location</label>
                            <select id="{{ $fieldId }}-dropoff" wire:model.live="pairs.{{ $i }}.drop_off_preset" class="ow-select">
                                <option value="">— Select —</option>
                                @foreach ($addresses as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                                <option value="{{ $newAddress }}">+ New address…</option>
                            </select>
                            @if (($pair['drop_off_preset'] ?? '') === $newAddress)
                                <textarea wire:model="pairs.{{ $i }}.drop_off_location" rows="2" class="ow-textarea" style="margin-top:.4rem;resize:vertical" placeholder="Type the drop-off address" aria-label="New drop-off address"></textarea>
                            @elseif (filled($pair['drop_off_location'] ?? null))
                                <div class="ow-note" style="margin-top:.25rem;white-space:pre-line">{{ $pair['drop_off_location'] }}</div>
                            @endif
                            @error('pairs.'.$i.'.drop_off_location')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ow-field">
                            <label for="{{ $fieldId }}-dropoff-type">Drop-off type</label>
                            <select id="{{ $fieldId }}-dropoff-type" wire:model="pairs.{{ $i }}.drop_off_type" class="ow-select">
                                @foreach ($this->dropOffTypeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="ow-fgrid ow-fgrid-do">
                    <div class="ow-field">
                        <label for="{{ $fieldId }}-do">DO number <span class="ow-req">*</span></label>
                        <input id="{{ $fieldId }}-do" type="text" wire:model="pairs.{{ $i }}.customer_do_number" class="ow-input" placeholder="Customer's DO number">
                        @error('pairs.'.$i.'.customer_do_number')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="ow-field">
                        <label for="{{ $fieldId }}-delivery">Expected delivery date <span class="ow-req">*</span></label>
                        <input id="{{ $fieldId }}-delivery" type="date" wire:model="pairs.{{ $i }}.expected_delivery_date" class="ow-input">
                        <div class="ow-note" style="margin-top:.2rem">Becomes the CSN date when the CSN is created.</div>
                        @error('pairs.'.$i.'.expected_delivery_date')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="ow-table-wrap" style="margin-top:.9rem">
                    <table class="ow-price-table ow-products">
                        <thead>
                            <tr>
                                <th>Product <span class="ow-req">*</span></th>
                                <th>Quantity <span class="ow-req">*</span></th>
                                <th class="ow-num">Unit price</th>
                                <th class="ow-num">Line total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pair['items'] as $j => $item)
                                @php $fixedItem = ($item['line_type'] ?? 'uom') !== 'uom' || (filled($item['item_name'] ?? null) && ! array_key_exists((string) ($item['catalog_key'] ?? ''), $uomOptions)); @endphp
                                <tr wire:key="ow-pair-{{ $i }}-item-{{ $j }}">
                                    <td style="min-width:22rem">
                                        @if ($fixedItem)
                                            {{-- product kept from the order record that is not in the UOM product list --}}
                                            <input type="text" class="ow-input" value="{{ $item['item_name'] }}" readonly>
                                            <div class="ow-price-hint">{{ ($item['line_type'] ?? '') === 'lorry' ? 'Lorry type' : (($item['line_type'] ?? '') === 'item' && filled($item['catalog_key'] ?? null) ? 'Transport item' : 'Not in the product list · remove it and pick a product to change it') }}</div>
                                        @else
                                            <select wire:model.live="pairs.{{ $i }}.items.{{ $j }}.catalog_key" class="ow-select">
                                                <option value="">— Select product —</option>
                                                @foreach ($uomOptions as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        @error('pairs.'.$i.'.items.'.$j.'.item_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                                    </td>
                                    <td style="width:7rem">
                                        <input type="number" min="1" step="1" inputmode="numeric" x-on:keydown="if (['.', ',', 'e', 'E', '-', '+'].includes($event.key)) $event.preventDefault()" wire:model.live.debounce.500ms="pairs.{{ $i }}.items.{{ $j }}.quantity" class="ow-input" @disabled(($item['line_type'] ?? 'uom') !== 'uom')>
                                        @error('pairs.'.$i.'.items.'.$j.'.quantity')<div class="ow-field-error">{{ $message }}</div>@enderror
                                    </td>
                                    <td class="ow-num" style="width:15rem;white-space:normal">
                                        @if (! $showPrices)
                                            <span class="ow-note">Select a salesperson to see prices</span>
                                        @elseif ($item['unit_price'] !== null)
                                            <div class="ow-l1">RM {{ number_format((float) $item['unit_price'], 2) }}</div>
                                            @if ($item['tier'])<div class="ow-price-hint">{{ $item['tier'] }}</div>@endif
                                        @elseif (filled($item['item_name']) && blank($pair['to_location_id']))
                                            <span class="ow-note">Select the "To" location</span>
                                            @if ($item['available'] ?? null)<div class="ow-price-hint">Rated: {{ $item['available'] }}</div>@endif
                                        @elseif (filled($item['item_name']))
                                            <span class="ow-note ow-price-diff">No rate for this location</span>
                                            <div class="ow-price-hint">{{ ($item['available'] ?? null) ? 'Rated: '.$item['available'] : 'No price list rate for this product' }}</div>
                                            <div class="ow-price-hint">Saved without a price · price it under Items &amp; pricing</div>
                                        @else
                                            <span class="ow-note">—</span>
                                        @endif
                                    </td>
                                    <td class="ow-num" style="width:8rem">
                                        {{ $showPrices && $item['unit_price'] !== null ? 'RM '.number_format($totals['lines'][$i][$j] ?? 0, 2) : '—' }}
                                    </td>
                                    <td style="width:4.5rem;white-space:nowrap">
                                        <span class="ow-row-actions">
                                            @if (! $locked && filled($form['customer_id'] ?? null) && filled($item['item_name'] ?? null))
                                                {{-- previous records of this product for the customer (information only) --}}
                                                <button type="button" wire:click="mountAction('productHistory', { pair: {{ $i }}, item: {{ $j }} })" class="ow-icon-btn" title="Previous records of this product for the customer" aria-label="Previous records of {{ $item['item_name'] }} for this customer">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l3 2"/></svg>
                                                </button>
                                            @endif
                                            @if (count($pair['items']) > 1 && ! $locked)
                                                <button type="button" wire:click="removeItem({{ $i }}, {{ $j }})" class="ow-btn-link" title="Remove" style="color:#b91c1c">✕</button>
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (! $locked)
                    <button type="button" wire:click="addItem({{ $i }})" class="ow-btn-link" style="margin-top:.5rem">+ Add product</button>
                @endif

                {{-- photos / DO attachments of this consignor & consignee (saved with its record) --}}
                @php
                    $savedPhotos = $pair['existing_photos'] ?? [];
                    $pickedPhotos = $this->newPhotos($i);
                @endphp
                <div class="ow-field" style="margin-top:.85rem" wire:key="ow-pair-{{ $i }}-photos">
                    <label for="{{ $fieldId }}-photos">Photos / DO attachments</label>
                    @if ($savedPhotos !== [] || $pickedPhotos !== [])
                        <div class="ow-photo-grid">
                            @foreach ($savedPhotos as $photo)
                                <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="ow-photo" title="{{ $photo['name'] }}">
                                    @if ($photo['is_image'])
                                        <img src="{{ $photo['url'] }}" alt="{{ $photo['name'] }}" loading="lazy">
                                    @else
                                        <span class="ow-photo-file">{{ strtoupper(pathinfo($photo['name'], PATHINFO_EXTENSION) ?: 'FILE') }}</span>
                                    @endif
                                    <span class="ow-photo-name">{{ $photo['name'] }}</span>
                                </a>
                            @endforeach
                            @foreach ($pickedPhotos as $k => $photo)
                                <div class="ow-photo" title="{{ $photo['name'] }}" wire:key="ow-pair-{{ $i }}-photo-{{ $k }}">
                                    @if ($photo['url'])
                                        <img src="{{ $photo['url'] }}" alt="{{ $photo['name'] }}">
                                    @else
                                        <span class="ow-photo-file">{{ strtoupper(pathinfo($photo['name'], PATHINFO_EXTENSION) ?: 'FILE') }}</span>
                                    @endif
                                    <span class="ow-photo-tag">New</span>
                                    @unless ($locked)
                                        <button type="button" wire:click="removePhoto({{ $i }}, {{ $k }})" class="ow-photo-remove" title="Remove" aria-label="Remove {{ $photo['name'] }}">✕</button>
                                    @endunless
                                    <span class="ow-photo-name">{{ $photo['name'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @unless ($locked)
                        <input id="{{ $fieldId }}-photos" type="file" wire:model="photoUploads.{{ $i }}" multiple accept="image/*,application/pdf" class="ow-input" x-on:livewire-upload-finish="$el.value = ''" x-on:livewire-upload-error="$el.value = ''">
                        <div wire:loading wire:target="photoUploads.{{ $i }}" class="ow-note">Uploading…</div>
                        <div class="ow-note" style="margin-top:.2rem">JPG, PNG, WEBP or PDF · up to 8 MB each · saved with this record when the order is saved.</div>
                    @endunless
                    @error('pairs.'.$i.'.photos')<div class="ow-field-error">{{ $message }}</div>@enderror
                    @error('pairs.'.$i.'.photos.*')<div class="ow-field-error">{{ $message }}</div>@enderror
                    @error('photoUploads.'.$i)<div class="ow-field-error">{{ $message }}</div>@enderror
                    @error('photoUploads.'.$i.'.*')<div class="ow-field-error">{{ $message }}</div>@enderror
                </div>

                <div class="ow-field" style="margin-top:.85rem">
                    <label>Instructions</label>
                    <input type="text" wire:model="pairs.{{ $i }}.instructions" class="ow-input" placeholder="e.g. Call before delivery">
                </div>
                @if ($editing)</fieldset>@endif
            </div>
        @endforeach

        @if ($this->canAddPair())
            <div>
                <button type="button" wire:click="addPair" class="ow-btn">+ Add another consignor &amp; consignee</button>
            </div>
        @endif

        {{-- files saved earlier for the whole order (customer portal upload / the order-level upload used before
             photos were kept per consignor & consignee): read-only, shown on every record of the order --}}
        @if ($editing && ($sharedFiles = $this->existingAttachments()) !== [])
            <div class="ow-card ow-card-pad">
                <div class="ow-card-title">Order photos / DO attachments (whole order)</div>
                <div class="ow-photo-grid">
                    @foreach ($sharedFiles as $photo)
                        <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="ow-photo" title="{{ $photo['name'] }}">
                            @if ($photo['is_image'])
                                <img src="{{ $photo['url'] }}" alt="{{ $photo['name'] }}" loading="lazy">
                            @else
                                <span class="ow-photo-file">{{ strtoupper(pathinfo($photo['name'], PATHINFO_EXTENSION) ?: 'FILE') }}</span>
                            @endif
                            <span class="ow-photo-name">{{ $photo['name'] }}</span>
                        </a>
                    @endforeach
                </div>
                <p class="ow-note">Saved for the whole order. New photos are added in each consignor &amp; consignee section above.</p>
            </div>
        @endif

        <div class="ow-footer-bar">
            <div class="ow-footer-meta">
                Price: {{ $showPrices && $totals['items'] > 0 ? 'RM '.number_format($totals['items'], 2).' (products)' : 'Not priced' }}
                @if ($editing)
                    · {{ count($pairs) }} {{ $this->enquiryOnly ? 'consignor & consignee '.\Illuminate\Support\Str::plural('block', count($pairs)) : \Illuminate\Support\Str::plural('record', count($pairs)) }} under {{ $this->orderNumber }}
                    @if ($lockedCount > 0) · {{ $lockedCount }} locked @endif
                @else
                    · Customer confirmation: Not requested
                    · {{ count($pairs) }} {{ \Illuminate\Support\Str::plural('record', count($pairs)) }} under one order number
                @endif
            </div>
            <div class="ow-actions">
                <a href="{{ $copy['cancel_url'] }}" class="ow-btn">Cancel</a>
                <button type="button" wire:click="save" wire:loading.attr="disabled" class="ow-btn ow-btn-primary">{{ $copy['submit'] }}</button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
