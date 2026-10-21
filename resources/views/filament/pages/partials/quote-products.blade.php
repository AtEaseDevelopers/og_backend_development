{{-- Create quotation (user, 21 Oct 2026): products only, no consignor & consignee. Each row is one product with its
     pickup and drop-off location, kept as a record of one product (pricing, Accept by customer and the PDF read it).
     Under each row its special price block: the price list template's columns for the product's category (a new
     product's sub-category, item and limits; pickup location, minimum charges, dates; quantity ranges A–E), filled
     from the default price row. Accept by customer saves the block as the customer's special price. --}}
@php
    $limitLabels = \App\Filament\Resources\UomResource::LIMITS;
    $minimumLabels = ['pickup_min_charge' => 'Pick-up minimum (RM)', 'construction_min_charge' => 'Construction site minimum (RM)', 'drop_off_min_charge' => 'Drop-off minimum (RM)'];
    $decimalOnly = "\$el.value = \$el.value.replace(/[^0-9.]/g, '').replace(/(\\..*)\\./g, '\$1')";
@endphp
<div class="ow-card ow-card-pad">
    <div class="ow-card-title">2. Products</div>
    <div class="ow-table-wrap">
        <table class="ow-price-table ow-products">
            <thead>
                <tr>
                    <th>Product <span class="ow-req">*</span></th>
                    <th>Pickup location</th>
                    <th>Drop-off location <span class="ow-req">*</span></th>
                    <th>Drop-off type</th>
                    <th>Quantity <span class="ow-req">*</span></th>
                    <th class="ow-num">Unit price</th>
                    <th class="ow-num">Line total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pairs as $i => $pair)
                    @php
                        $item = $pair['items'][0] ?? [];
                        $kind = $this->itemKind($item);
                        $new = $this->isNewProduct($item);
                        $row = "orders.{$oi}.pairs.{$i}";
                        $field = "{$row}.items.0";
                    @endphp
                    <tr wire:key="ow-q{{ $oi }}-row-{{ $i }}">
                        <td style="min-width:20rem">
                            <div class="ow-product-pick">
                                <select wire:model.live="{{ $field }}.kind" class="ow-select" aria-label="Product type">
                                    <option value="">— Type —</option>
                                    @foreach ($productKinds as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if ($kind === 'measurement')
                                    <select wire:model.live="{{ $field }}.sub_category" class="ow-select" aria-label="Product">
                                        <option value="">— Select product —</option>
                                        @foreach ($measurementOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                        <option value="__new__">+ New product / size band…</option>
                                    </select>
                                @elseif ($kind !== '')
                                    <select wire:model.live="{{ $field }}.catalog_key" class="ow-select" aria-label="Product">
                                        <option value="">— Select {{ $kind === 'lorry' ? 'lorry' : 'item' }} —</option>
                                        @foreach ($kindOptions[$kind] ?? [] as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                        <option value="new">+ New {{ $kind === 'lorry' ? 'lorry' : 'item' }}…</option>
                                    </select>
                                @else
                                    <select class="ow-select" disabled aria-label="Product"><option>Choose the type first</option></select>
                                @endif
                            </div>
                            @if ($kind !== '' && ! $new)
                                {{-- a Measurement product's size picks its band; the weight of an item or lorry load is noted --}}
                                <div class="ow-dims" @if ($kind !== 'measurement') style="grid-template-columns: minmax(0, 8rem)" @endif>
                                    @foreach ($kind === 'measurement' ? ['length' => 'L (cm)', 'width' => 'W (cm)', 'height' => 'H (cm)', 'weight' => 'Weight (kg)'] : ['weight' => 'Weight (kg)'] as $dim => $dimLabel)
                                        <label class="ow-dim">
                                            <span>{{ $dimLabel }} @if ($kind === 'measurement')<span class="ow-req">*</span>@endif</span>
                                            <input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.600ms="{{ $field }}.{{ $dim }}" class="ow-input" x-on:input="{{ $decimalOnly }}">
                                        </label>
                                    @endforeach
                                </div>
                                @if ($kind === 'measurement')
                                    <div class="ow-price-hint">{{ $this->measurementNote($item) }}</div>
                                @endif
                            @elseif ($new && filled($item['item_name'] ?? null))
                                <div class="ow-price-hint">New product: {{ $item['item_name'] }}</div>
                            @endif
                            @error($ep.'pairs.'.$i.'.items.0.item_name')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </td>
                        <td style="min-width:10rem">
                            <select wire:model.live="{{ $row }}.from_location_id" class="ow-select" aria-label="Pickup location">
                                <option value="">— Select —</option>
                                @foreach ($locations as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td style="min-width:10rem">
                            <select wire:model.live="{{ $row }}.to_location_id" class="ow-select" aria-label="Drop-off location">
                                <option value="">— Select —</option>
                                @foreach ($locations as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error($ep.'pairs.'.$i.'.to_location_id')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </td>
                        <td style="min-width:9rem">
                            <select wire:model.live="{{ $row }}.drop_off_type" class="ow-select" aria-label="Drop-off type">
                                @foreach ($this->dropOffTypeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td style="width:6.5rem">
                            <input type="number" min="1" step="1" inputmode="numeric" x-on:keydown="if (['.', ',', 'e', 'E', '-', '+'].includes($event.key)) $event.preventDefault()" wire:model.live.debounce.500ms="{{ $field }}.quantity" class="ow-input" aria-label="Quantity">
                            @error($ep.'pairs.'.$i.'.items.0.quantity')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </td>
                        <td class="ow-num" style="width:12rem;white-space:normal">
                            @if (filled($item['item_name'] ?? null))
                                {{-- the price of the quantity's range filled in (editable: it goes back into that range) --}}
                                <label class="ow-manual-price">
                                    <span>RM</span>
                                    <input type="text" inputmode="decimal" autocomplete="off" x-on:input="const v = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1'); if (v !== $el.value) $el.value = v" x-on:blur="const n = parseFloat($el.value); if ($el.value.trim() !== '' && ! isNaN(n) && $el.value !== n.toFixed(2)) { $el.value = n.toFixed(2); $el.dispatchEvent(new Event('input')) }" wire:model.live.debounce.500ms="{{ $field }}.manual_price" class="ow-input ow-price-input" placeholder="{{ ($item['unit_price'] ?? null) !== null ? number_format((float) $item['unit_price'], 2, '.', '') : '0.00' }}" aria-label="Unit price for {{ $item['item_name'] }}">
                                </label>
                                @if (($item['unit_price'] ?? null) !== null)
                                    @php $priceLabel = ($item['source'] ?? null) === 'quote_range' ? 'Special price' : 'Default price'; @endphp
                                    @if ($item['price_edited'] ?? false)
                                        <div class="ow-price-hint ow-price-diff">Changed · {{ $priceLabel }} RM {{ number_format((float) $item['unit_price'], 2) }}</div>
                                    @else
                                        <div class="ow-price-hint">{{ $priceLabel }}{{ ($item['tier'] ?? null) ? ' · '.$item['tier'] : '' }}</div>
                                    @endif
                                @elseif (blank($pair['to_location_id'] ?? null))
                                    <div class="ow-price-hint">Select the drop-off location for the list price</div>
                                @elseif ($new)
                                    <div class="ow-price-hint ow-price-diff">Key in the price, or fill the quantity ranges below</div>
                                @else
                                    <div class="ow-price-hint ow-price-diff">{{ ($item['no_band'] ?? false) ? 'No size band fits · key in the price' : (($item['kind'] ?? '') === 'measurement' && blank($item['catalog_key'] ?? null) ? 'Enter L, W, H and weight for the list price, or key in the price' : 'No rate for this location · key in the price') }}</div>
                                @endif
                                @error($ep.'pairs.'.$i.'.items.0.manual_price')<div class="ow-field-error">{{ $message }}</div>@enderror
                            @else
                                <span class="ow-note">—</span>
                            @endif
                        </td>
                        <td class="ow-num" style="width:9rem">
                            @php $minimum = $totals['minimums'][$i] ?? null; $topUp = (float) ($minimum['top_up'] ?? 0); @endphp
                            {{ $this->effectivePrice($item) !== null ? 'RM '.number_format(($totals['lines'][$i][0] ?? 0) + $topUp, 2) : '—' }}
                            @if ($topUp > 0)
                                {{-- below the price list's minimum charge: topped up to it --}}
                                <div class="ow-price-hint ow-price-diff">{{ $minimum['label'] }} RM {{ number_format((float) $minimum['minimum'], 2) }} · +RM {{ number_format($topUp, 2) }}</div>
                            @endif
                        </td>
                        <td style="width:3rem">
                            @if (count($pairs) > 1)
                                <button type="button" wire:click="inOrder({{ $oi }}, 'removePair', {{ $i }})" class="ow-icon-btn" title="Remove this product" aria-label="Remove this product">✕</button>
                            @endif
                        </td>
                    </tr>
                    @if ($kind !== '')
                        @php $columns = $this->specialColumns($item); @endphp
                        <tr wire:key="ow-q{{ $oi }}-special-{{ $i }}" class="ow-special-row">
                            <td colspan="8">
                                <div class="ow-special">
                                    @if ($new)
                                        <div class="ow-special-title">New {{ $kind === 'lorry' ? 'lorry' : 'product' }} · added to the {{ $productKinds[$kind] ?? '' }} products when the customer accepts</div>
                                        <div class="ow-special-grid">
                                            @if ($columns['sub_category'])
                                                <label class="ow-dim">
                                                    <span>Sub-category</span>
                                                    <input type="text" list="ow-sub-{{ $kind }}" autocomplete="off" wire:model.live.debounce.600ms="{{ $field }}.product.sub_category" class="ow-input" placeholder="e.g. Carton">
                                                </label>
                                            @endif
                                            <label class="ow-dim">
                                                <span>{{ $kind === 'lorry' ? 'Lorry size' : 'Item' }} <span class="ow-req">*</span></span>
                                                <input type="text" autocomplete="off" wire:model.live.debounce.600ms="{{ $field }}.product.base_item" class="ow-input" placeholder="{{ $kind === 'lorry' ? 'e.g. 10 FT Lorry' : 'Leave blank to use the sub-category' }}">
                                            </label>
                                            @foreach ($columns['limits'] as $limit)
                                                <label class="ow-dim">
                                                    <span>{{ $limitLabels[$limit] }}</span>
                                                    <input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.600ms="{{ $field }}.product.{{ $limit }}" class="ow-input" x-on:input="{{ $decimalOnly }}">
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="ow-special-title">Special price for this customer{{ $new ? '' : ' · starts as the default price row; change what differs' }}</div>
                                    <div class="ow-special-grid">
                                        <label class="ow-dim">
                                            <span>Pickup location</span>
                                            <input type="text" list="ow-pickup-locations" autocomplete="off" wire:model.live.debounce.600ms="{{ $field }}.special.pickup_location" class="ow-input">
                                        </label>
                                        @foreach ($columns['minimums'] as $minimum)
                                            <label class="ow-dim">
                                                <span>{{ $minimumLabels[$minimum] }}</span>
                                                <input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.600ms="{{ $field }}.special.{{ $minimum }}" class="ow-input" x-on:input="{{ $decimalOnly }}">
                                            </label>
                                        @endforeach
                                        <label class="ow-dim">
                                            <span>Effective date</span>
                                            <input type="date" wire:model.live="{{ $field }}.special.effective_from" class="ow-input">
                                        </label>
                                        @if ($columns['expiry'])
                                            <label class="ow-dim">
                                                <span>Expiry date</span>
                                                <input type="date" wire:model.live="{{ $field }}.special.expires_on" class="ow-input">
                                            </label>
                                        @endif
                                    </div>
                                    <table class="ow-ranges">
                                        <thead>
                                            <tr>
                                                <th>{{ $columns['ranges'] > 1 ? 'Range' : '' }}</th>
                                                <th>Quantity from</th>
                                                @if ($columns['quantity_to'])<th>Quantity to</th>@endif
                                                <th>Price for this range (RM)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach (array_slice(\App\Support\UomPriceRows::RANGES, 0, $columns['ranges']) as $letter)
                                                <tr>
                                                    <td>{{ $columns['ranges'] > 1 ? strtoupper($letter) : '' }}</td>
                                                    <td><input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.600ms="{{ $field }}.special.range_{{ $letter }}_from" class="ow-input" x-on:input="{{ $decimalOnly }}"></td>
                                                    @if ($columns['quantity_to'])
                                                        <td><input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.600ms="{{ $field }}.special.range_{{ $letter }}_to" class="ow-input" placeholder="and above" x-on:input="{{ $decimalOnly }}"></td>
                                                    @endif
                                                    <td><input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.600ms="{{ $field }}.special.range_{{ $letter }}_price" class="ow-input" x-on:input="{{ $decimalOnly }}"></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @foreach (collect($errors->keys())->filter(fn ($k) => str_starts_with($k, $ep.'pairs.'.$i.'.items.0.special.') || str_starts_with($k, $ep.'pairs.'.$i.'.items.0.product.')) as $errorKey)
                                        <div class="ow-field-error">{{ $errors->first($errorKey) }}</div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top:.6rem">
        <button type="button" wire:click="inOrder({{ $oi }}, 'addPair')" class="ow-btn-link">+ Add product</button>
    </div>
    @foreach ($productKinds as $kindValue => $kindLabel)
        <datalist id="ow-sub-{{ $kindValue }}">
            @foreach ($this->subCategoryOptions($kindValue) as $sub)
                <option value="{{ $sub }}"></option>
            @endforeach
        </datalist>
    @endforeach
    <datalist id="ow-pickup-locations">
        @foreach ($this->pickupLocationOptions() as $pickup)
            <option value="{{ $pickup }}"></option>
        @endforeach
    </datalist>
</div>
