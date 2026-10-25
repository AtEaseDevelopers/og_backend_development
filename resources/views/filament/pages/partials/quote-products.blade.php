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
                                    <select wire:model.live="{{ $field }}.sub_category" class="ow-select" aria-label="Sub-category">
                                        <option value="">— Sub-category —</option>
                                        @foreach ($measurementOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                        <option value="__new__">+ New product / size band…</option>
                                    </select>
                                @elseif ($kind !== '')
                                    {{-- type, then sub-category, then the product of that sub-category (user, 25 Oct 2026) --}}
                                    @php $sub = (string) ($item['sub_category'] ?? ''); @endphp
                                    <select wire:model.live="{{ $field }}.sub_category" class="ow-select" aria-label="Sub-category">
                                        <option value="">— Sub-category —</option>
                                        @foreach ($this->subCategoryOptions($kind) as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <select wire:model.live="{{ $field }}.catalog_key" class="ow-select" aria-label="Product">
                                        <option value="">{{ $sub === '' ? 'Choose the sub-category first' : '— Select '.($kind === 'lorry' ? 'lorry' : 'item').' —' }}</option>
                                        @if ($sub !== '')
                                            @foreach ($this->kindProductOptions($kind, $sub) as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        @endif
                                        <option value="new">+ New {{ $kind === 'lorry' ? 'lorry' : 'item' }}…</option>
                                    </select>
                                @else
                                    <select class="ow-select" disabled aria-label="Product"><option>Choose the type first</option></select>
                                @endif
                            </div>
                            @if ($kind === 'measurement' && ! $new)
                                <div class="ow-price-hint">{{ $this->measurementNote($item) }}</div>
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
                        {{-- no quantity on a quotation row (user, 21 Oct 2026): the quantity ranges below price it; the unit
                             price is range A's (the one for quantity 1) --}}
                        {{-- no unit price, line total or quotation total (user, 21 Oct 2026): the quantity ranges below are the
                             price the customer is asked to agree to --}}
                        <td style="width:3rem">
                            @if (count($pairs) > 1)
                                <button type="button" wire:click="inOrder({{ $oi }}, 'removePair', {{ $i }})" class="ow-icon-btn" title="Remove this product" aria-label="Remove this product">✕</button>
                            @endif
                        </td>
                    </tr>
                    @if ($kind !== '')
                        @php $columns = $this->specialColumns($item); @endphp
                        <tr wire:key="ow-q{{ $oi }}-special-{{ $i }}" class="ow-special-row">
                            <td colspan="5">
                                <div class="ow-special">
                                    @if ($new)
                                        <div class="ow-special-title">New {{ $kind === 'lorry' ? 'chartered lorry' : 'product' }} · added to the {{ $productKinds[$kind] ?? '' }} products when the customer accepts</div>
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
                                    <div class="ow-special-grid">
                                        {{-- the goods' size and weight (a Measurement product's size picks its band), then the price list's
                                             minimum charges; the pickup location is the row's (user, 21 Oct 2026) --}}
                                        @if (! $new)
                                            @foreach ($kind === 'measurement' ? ['length' => 'L (cm)', 'width' => 'W (cm)', 'height' => 'H (cm)', 'weight' => 'Weight (kg)'] : ['weight' => 'Weight (kg)'] as $dim => $dimLabel)
                                                <label class="ow-dim">
                                                    <span>{{ $dimLabel }} @if ($kind === 'measurement')<span class="ow-req">*</span>@endif</span>
                                                    <input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.600ms="{{ $field }}.{{ $dim }}" class="ow-input" x-on:input="{{ $decimalOnly }}">
                                                </label>
                                            @endforeach
                                        @endif
                                        @foreach ($columns['minimums'] as $minimum)
                                            <label class="ow-dim">
                                                <span>{{ $minimumLabels[$minimum] }}</span>
                                                <input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.600ms="{{ $field }}.special.{{ $minimum }}" class="ow-input" x-on:input="{{ $decimalOnly }}">
                                            </label>
                                        @endforeach
                                    </div>
                                    @php
                                        // range A always; the others once filled (e.g. the default price's) or added with "+ Add range"
                                        $special = $item['special'] ?? [];
                                        $lastFilled = 0;
                                        foreach (\App\Support\UomPriceRows::RANGES as $n => $letter) {
                                            if (filled($special["range_{$letter}_from"] ?? null) || filled($special["range_{$letter}_to"] ?? null) || filled($special["range_{$letter}_price"] ?? null)) {
                                                $lastFilled = $n + 1;
                                            }
                                        }
                                        $shown = min($columns['ranges'], max(1, $lastFilled, (int) ($item['ranges_shown'] ?? 1)));
                                    @endphp
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
                                            @foreach (array_slice(\App\Support\UomPriceRows::RANGES, 0, $shown) as $letter)
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
                                    @if ($shown < $columns['ranges'])
                                        <div><button type="button" wire:click="inOrder({{ $oi }}, 'showRange', {{ $i }})" class="ow-btn-link">+ Add range {{ strtoupper(\App\Support\UomPriceRows::RANGES[$shown]) }}</button></div>
                                    @endif
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
</div>
