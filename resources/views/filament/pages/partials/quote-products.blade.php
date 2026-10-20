{{-- Create quotation (user, 21 Oct 2026): products only, no consignor & consignee. Each row is one product with its
     pickup and drop-off location, kept as a record of one product (pricing, Accept by customer and the PDF read it). --}}
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
                        $row = "orders.{$oi}.pairs.{$i}";
                    @endphp
                    <tr wire:key="ow-q{{ $oi }}-row-{{ $i }}">
                        <td style="min-width:20rem">
                            <div class="ow-product-pick">
                                <select wire:model.live="{{ $row }}.items.0.kind" class="ow-select" aria-label="Product type">
                                    <option value="">— Type —</option>
                                    @foreach ($productKinds as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if ($kind === 'measurement')
                                    <select wire:model.live="{{ $row }}.items.0.sub_category" class="ow-select" aria-label="Product">
                                        <option value="">— Select product —</option>
                                        @foreach ($measurementOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($kind !== '')
                                    <select wire:model.live="{{ $row }}.items.0.catalog_key" class="ow-select" aria-label="Product">
                                        <option value="">— Select {{ $kind === 'lorry' ? 'lorry' : 'item' }} —</option>
                                        @foreach ($kindOptions[$kind] ?? [] as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <select class="ow-select" disabled aria-label="Product"><option>Choose the type first</option></select>
                                @endif
                            </div>
                            @if ($kind !== '')
                                {{-- a Measurement product's size picks its band; the weight of an item or lorry load is noted --}}
                                <div class="ow-dims" @if ($kind !== 'measurement') style="grid-template-columns: minmax(0, 8rem)" @endif>
                                    @foreach ($kind === 'measurement' ? ['length' => 'L (cm)', 'width' => 'W (cm)', 'height' => 'H (cm)', 'weight' => 'Weight (kg)'] : ['weight' => 'Weight (kg)'] as $dim => $dimLabel)
                                        <label class="ow-dim">
                                            <span>{{ $dimLabel }} @if ($kind === 'measurement')<span class="ow-req">*</span>@endif</span>
                                            <input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.600ms="{{ $row }}.items.0.{{ $dim }}" class="ow-input" x-on:input="$el.value = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')">
                                        </label>
                                    @endforeach
                                </div>
                                @if ($kind === 'measurement')
                                    <div class="ow-price-hint">{{ $this->measurementNote($item) }}</div>
                                @endif
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
                            <input type="number" min="1" step="1" inputmode="numeric" x-on:keydown="if (['.', ',', 'e', 'E', '-', '+'].includes($event.key)) $event.preventDefault()" wire:model.live.debounce.500ms="{{ $row }}.items.0.quantity" class="ow-input" aria-label="Quantity">
                            @error($ep.'pairs.'.$i.'.items.0.quantity')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </td>
                        <td class="ow-num" style="width:12rem;white-space:normal">
                            @if (filled($item['item_name'] ?? null))
                                {{-- the default price filled in (editable: the price quoted); none = keyed in --}}
                                <label class="ow-manual-price">
                                    <span>RM</span>
                                    <input type="text" inputmode="decimal" autocomplete="off" x-on:input="const v = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1'); if (v !== $el.value) $el.value = v" x-on:blur="const n = parseFloat($el.value); if ($el.value.trim() !== '' && ! isNaN(n) && $el.value !== n.toFixed(2)) { $el.value = n.toFixed(2); $el.dispatchEvent(new Event('input')) }" wire:model.live.debounce.500ms="{{ $row }}.items.0.manual_price" class="ow-input ow-price-input" placeholder="{{ ($item['unit_price'] ?? null) !== null ? number_format((float) $item['unit_price'], 2, '.', '') : '0.00' }}" aria-label="Unit price for {{ $item['item_name'] }}">
                                </label>
                                @if (($item['unit_price'] ?? null) !== null)
                                    @if ($item['price_edited'] ?? false)
                                        <div class="ow-price-hint ow-price-diff">Changed · Default price RM {{ number_format((float) $item['unit_price'], 2) }}</div>
                                    @else
                                        <div class="ow-price-hint">Default price{{ ($item['tier'] ?? null) ? ' · '.$item['tier'] : '' }}</div>
                                    @endif
                                @elseif (blank($pair['to_location_id'] ?? null))
                                    <div class="ow-price-hint">Select the drop-off location for the list price</div>
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
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top:.6rem">
        <button type="button" wire:click="inOrder({{ $oi }}, 'addPair')" class="ow-btn-link">+ Add product</button>
    </div>
</div>
