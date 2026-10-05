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
@endphp

<x-filament-panels::page class="ow-page">
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
            <div class="ow-fgrid">
                <div class="ow-field">
                    <label>Customer <span class="ow-req">*</span></label>
                    <select wire:model.live="form.customer_id" class="ow-select" @disabled(! $headerEditable)>
                        <option value="">— Select customer —</option>
                        @foreach ($this->customerOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.customer_id')<div class="ow-field-error">{{ $message }}</div>@enderror
                </div>
                @if ($this->showReceivedThrough())
                    <div class="ow-field">
                        <label>Received through</label>
                        <select wire:model="form.received_through" class="ow-select" @disabled(! $headerEditable)>
                            @foreach ($this->receivedThroughOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
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
            <div class="ow-fgrid">
                <div class="ow-field">
                    <label>Service</label>
                    <select wire:model="form.service_type" class="ow-select">
                        @foreach ($this->serviceTypeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ow-field">
                    <label>Payment method</label>
                    <select wire:model="form.payment_method" class="ow-select">
                        @foreach ($this->paymentMethodOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="ow-note" style="margin-top:.75rem">
                @if ($editing)
                    @if (! $headerEditable)
                        Customer, received through and payment term are locked because a record of this order is already confirmed. Use <strong>Change payment term</strong> on the order page for the payment term.
                    @endif
                    @if (! $this->salespersonEditable())
                        The salesperson is fixed for this order.
                    @elseif (! $this->allowNoSalesperson())
                        Changing the salesperson moves every record of this order to the new owner.
                    @else
                        Salesperson is optional. Without one product prices stay hidden.
                    @endif
                    Service and payment method apply to the records that can still be edited.
                @else
                    Salesperson is optional. Without one the order is saved as <strong>Pending salesperson</strong> and product prices stay hidden.
                    Cash orders go through the payment summary; Credit term and COD orders go straight to invoice and CSN after the customer confirms.
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
                <div class="ow-grid-2">
                    <div class="ow-party">
                        <div class="ow-party-title">Consignor (pickup)</div>
                        <div class="ow-field">
                            <label>Consignor <span class="ow-req">*</span></label>
                            <input type="text" wire:model="pairs.{{ $i }}.consignor_name" list="ow-consignor-list" class="ow-input" placeholder="Company name (defaults to the customer)">
                            @error('pairs.'.$i.'.consignor_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ow-field">
                            <label>From</label>
                            <select wire:model="pairs.{{ $i }}.from_location_id" class="ow-select">
                                <option value="">— Select —</option>
                                @foreach ($locations as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Company number</label>
                            <input type="text" wire:model="pairs.{{ $i }}.consignor_brn" class="ow-input">
                        </div>
                        <div class="ow-field">
                            <label>Billing address</label>
                            <textarea wire:model="pairs.{{ $i }}.customer_address" rows="2" class="ow-textarea"></textarea>
                        </div>
                        <div class="ow-field">
                            <label>Pickup location</label>
                            <select wire:model.live="pairs.{{ $i }}.pickup_preset" class="ow-select">
                                <option value="">Select a saved address or type below</option>
                                @foreach ($addresses as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Pickup location detail</label>
                            <textarea wire:model="pairs.{{ $i }}.pickup_location" rows="2" class="ow-textarea"></textarea>
                        </div>
                    </div>

                    <div class="ow-party">
                        <div class="ow-party-title">Consignee (drop-off)</div>
                        <div class="ow-field">
                            <label>Consignee <span class="ow-req">*</span></label>
                            <input type="text" wire:model="pairs.{{ $i }}.consignee_name" class="ow-input" placeholder="Company name">
                            @error('pairs.'.$i.'.consignee_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ow-field">
                            <label>To (price list location)</label>
                            <select wire:model.live="pairs.{{ $i }}.to_location_id" class="ow-select">
                                <option value="">— Select —</option>
                                @foreach ($locations as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Company number</label>
                            <input type="text" wire:model="pairs.{{ $i }}.consignee_brn" class="ow-input">
                        </div>
                        <div class="ow-field">
                            <label>Billing address</label>
                            <textarea wire:model="pairs.{{ $i }}.consignee_address" rows="2" class="ow-textarea"></textarea>
                        </div>
                        <div class="ow-field">
                            <label>Drop-off location</label>
                            <select wire:model.live="pairs.{{ $i }}.drop_off_preset" class="ow-select">
                                <option value="">Select a saved address or type below</option>
                                @foreach ($addresses as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Drop-off location detail</label>
                            <textarea wire:model="pairs.{{ $i }}.drop_off_location" rows="2" class="ow-textarea"></textarea>
                        </div>
                    </div>
                </div>

                <div class="ow-fgrid" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-top:.85rem">
                    <div class="ow-field">
                        <label>DO number</label>
                        <input type="text" wire:model="pairs.{{ $i }}.customer_do_number" class="ow-input" placeholder="Optional · can be added later">
                        @error('pairs.'.$i.'.customer_do_number')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="ow-field">
                        <label>Expected delivery date <span class="ow-req">*</span></label>
                        <input type="date" wire:model="pairs.{{ $i }}.expected_delivery_date" class="ow-input">
                        @error('pairs.'.$i.'.expected_delivery_date')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="ow-field">
                        <label>Drop-off type</label>
                        <select wire:model="pairs.{{ $i }}.drop_off_type" class="ow-select">
                            @foreach ($this->dropOffTypeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
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
                                        @else
                                            <span class="ow-note">—</span>
                                        @endif
                                    </td>
                                    <td class="ow-num" style="width:8rem">
                                        {{ $showPrices && $item['unit_price'] !== null ? 'RM '.number_format($totals['lines'][$i][$j] ?? 0, 2) : '—' }}
                                    </td>
                                    <td style="width:2rem">
                                        @if (count($pair['items']) > 1 && ! $locked)
                                            <button type="button" wire:click="removeItem({{ $i }}, {{ $j }})" class="ow-btn-link" title="Remove" style="color:#b91c1c">✕</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (! $locked)
                    <button type="button" wire:click="addItem({{ $i }})" class="ow-btn-link" style="margin-top:.5rem">+ Add product</button>
                @endif

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

        {{-- Attachments --}}
        <div class="ow-card ow-card-pad">
            <div class="ow-card-title">Order photos / DO attachments</div>
            @if ($editing && ($existingFiles = $this->existingAttachments()) !== [])
                <div class="ow-note" style="margin-bottom:.5rem">
                    On file:
                    @foreach ($existingFiles as $file)
                        @if ($file['url'])<a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="ow-link">{{ $file['name'] }}</a>@else{{ $file['name'] }}@endif{{ $loop->last ? '' : ',' }}
                    @endforeach
                    · new files below are added to these.
                </div>
            @endif
            <input type="file" wire:model="attachments" multiple accept="image/*,application/pdf" class="ow-input">
            <div wire:loading wire:target="attachments" class="ow-note">Uploading…</div>
            @error('attachments.*')<div class="ow-field-error">{{ $message }}</div>@enderror
            @if ($attachments)
                <div class="ow-note" style="margin-top:.4rem">{{ collect($attachments)->map(fn ($f) => $f->getClientOriginalName())->implode(', ') }}</div>
            @endif
        </div>

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
