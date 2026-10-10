{{-- Items & pricing section of the order page. --}}
@if ($pr['mode'] === 'start')
    <div class="ow-card ow-card-pad">
        <div class="ow-card-title">Admin pricing</div>
        @if ($pr['can_start'])
            <p>
                Pricing creates {{ $pr['destinations_count'] }} order {{ \Illuminate\Support\Str::plural('record', $pr['destinations_count']) }}
                (one per consignor &amp; consignee) under <strong class="ow-mono">{{ $d['number'] }}</strong>.
                Each record gets its own quotation number; product rates are taken from the UOM price list.
            </p>
            <div class="ow-actions" style="margin-top:.75rem">
                <button type="button" wire:click="startPricing" wire:loading.attr="disabled" class="ow-btn ow-btn-primary">Provide pricing →</button>
            </div>
        @else
            <p class="ow-note">{{ $pr['why_not'] ?? 'Pricing is not available for this enquiry.' }}</p>
            @if (! empty($pr['records_url']))
                <div class="ow-actions" style="margin-top:.75rem">
                    <a href="{{ $pr['records_url'] }}" class="ow-btn ow-btn-primary">Open order →</a>
                </div>
            @endif
        @endif
    </div>
@elseif (! $hasOwner)
    <div class="ow-card ow-card-pad">
        <div class="ow-card-title">Admin pricing <span class="ow-pill ow-pill-action">Pending salesperson</span></div>
        <p>Product prices are hidden until a salesperson owns this order. Assign one under <strong>Record ownership</strong>; the rates from the UOM price list are then filled in automatically.</p>
        <div class="ow-actions" style="margin-top:.75rem">
            <button type="button" wire:click="focusAssign" class="ow-btn ow-btn-primary">Assign salesperson →</button>
        </div>
    </div>
@elseif ($showPreview && $order)
    @php $pv = $pr['preview']; @endphp
    <div class="ow-card ow-card-pad">
        <div class="ow-card-title">
            <span class="ow-note" style="font-weight:500">Quotation preview · Version {{ $pv['version'] }}</span>
            <span class="ow-pill ow-pill-gray">{{ $pv['status'] }}</span>
        </div>
        <div class="ow-title-mono" style="font-size:1.15rem;margin-bottom:.75rem">{{ $pv['number'] }}</div>
        <div class="ow-dl">
            <div><div class="ow-dt">Customer</div><div class="ow-dd">{{ $pv['customer'] }}</div></div>
            <div><div class="ow-dt">Route</div><div class="ow-dd">{{ $pv['route'] }}</div></div>
            <div><div class="ow-dt">Item / Quantity</div><div class="ow-dd">{{ $pv['items'] }}</div></div>
            <div><div class="ow-dt">Order type</div><div class="ow-dd">{{ $pv['order_type'] }}</div></div>
        </div>
        <div class="ow-total-row"><span>Proposed total</span><span>{{ $pv['total'] }}</span></div>
        @if ($pv['remarks'])
            <p class="ow-note" style="margin-top:.5rem;white-space:pre-line">{{ $pv['remarks'] }}</p>
        @endif
        <div class="ow-callout ow-callout-info" style="margin-top:.85rem">
            <strong>Customer will receive a quotation to accept or reject</strong><br>
            Sending emails the portal link and prepares the WhatsApp message. The price offered is recorded in the order activity.
        </div>
        <div class="ow-actions-split" style="margin-top:.85rem">
            <button type="button" wire:click="backToPricing" class="ow-btn">← Edit pricing</button>
            <div class="ow-actions">
                @if ($can['accept'])
                    <button type="button" wire:click="mountAction('accept')" class="ow-btn">Customer already confirmed</button>
                @endif
                @if ($can['send'])
                    <button type="button" wire:click="mountAction('send')" class="ow-btn ow-btn-primary">Send for confirmation →</button>
                @endif
            </div>
        </div>
    </div>
@elseif ($pr['editable'] ?? false)
    @php
        $columns = $this->pricing['columns'] ?? [];
        $totals = $this->pricingTotals();
        $lookupOptions = $this->catalogOptions('uom');
        $hasOverride = false;
        foreach ($this->pricing['rows'] ?? [] as $row) {
            foreach ($row['prices'] ?? [] as $c => $price) {
                $list = $row['list'][$c]['price'] ?? null;
                if ($list !== null && filled($price) && abs((float) $price - (float) $list) > 0.004) {
                    $hasOverride = true;
                }
            }
        }
        // products kept on the order without a price yet (saved as lines without a unit price, left out of the total)
        $unpricedRows = collect($this->pricing['rows'] ?? [])
            ->filter(fn ($row) => filled($row['item_name'] ?? null) && collect($row['prices'] ?? [])->filter(fn ($p) => filled($p))->isEmpty())
            ->pluck('item_name')->unique()->values();
    @endphp
    <div class="ow-stack">
        <div class="ow-card ow-card-pad">
            <div class="ow-card-title">Admin pricing</div>
            <div>{{ $d['customer'] }} · {{ $pr['preview']['route'] }}</div>
            <div class="ow-note" style="margin-bottom:.85rem">{{ $pr['preview']['items'] }}</div>

            <div class="ow-field" style="max-width:18rem;margin-bottom:.75rem">
                <label>Pricing reference</label>
                <select wire:model.live="pricing.reference" class="ow-select">
                    <option value="price_list">UOM price list</option>
                    <option value="customer_special">Customer special pricing</option>
                    <option value="manual">Manual pricing</option>
                </select>
            </div>

            <div class="ow-table-wrap">
                <table class="ow-price-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            @foreach ($columns as $column)
                                <th>{{ $column }} · unit</th>
                            @endforeach
                            <th class="ow-num">Line total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->pricing['rows'] ?? [] as $i => $row)
                            <tr wire:key="ow-price-row-{{ $i }}">
                                <td style="min-width:18rem">
                                    @if (($row['line_type'] ?? 'uom') === 'uom')
                                        <select wire:model.live="pricing.rows.{{ $i }}.catalog_key" class="ow-select">
                                            <option value="">— Select product —</option>
                                            @foreach ($lookupOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" class="ow-input" value="{{ $row['item_name'] }}" readonly>
                                        <div class="ow-price-hint">{{ $row['line_type'] === 'lorry' ? 'Lorry type' : 'Transport item' }}</div>
                                    @endif
                                </td>
                                <td style="min-width:5.5rem;width:6rem">
                                    <input type="number" min="1" step="1" inputmode="numeric" x-on:keydown="if (['.', ',', 'e', 'E', '-', '+'].includes($event.key)) $event.preventDefault()" wire:model.live.debounce.500ms="pricing.rows.{{ $i }}.quantity" class="ow-input" @disabled(($row['line_type'] ?? 'uom') !== 'uom')>
                                </td>
                                @foreach ($columns as $c => $column)
                                    @php
                                        $list = $row['list'][$c] ?? ['price' => null, 'tier' => null];
                                        $price = $row['prices'][$c] ?? null;
                                        $differs = $list['price'] !== null && filled($price) && abs((float) $price - (float) $list['price']) > 0.004;
                                    @endphp
                                    <td style="min-width:9rem">
                                        <div class="ow-money"><span>RM</span><input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="pricing.rows.{{ $i }}.prices.{{ $c }}" class="ow-input"></div>
                                        @if ($list['price'] !== null)
                                            <div @class(['ow-price-hint', 'ow-price-diff' => $differs])>
                                                List RM {{ number_format((float) $list['price'], 2) }}@if ($list['tier']) · {{ $list['tier'] }}@endif
                                            </div>
                                        @elseif (filled($row['item_name']))
                                            <div class="ow-price-hint">No list rate for {{ $column }}</div>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="ow-num">
                                    @if (filled($row['item_name'] ?? null) && collect($row['prices'] ?? [])->filter(fn ($p) => filled($p))->isEmpty())
                                        <span class="ow-note">No price yet</span>
                                    @else
                                        RM {{ number_format($totals['rows'][$i] ?? 0, 2) }}
                                    @endif
                                </td>
                                <td style="width:2rem">
                                    @if (count($this->pricing['rows']) > 1)
                                        <button type="button" wire:click="removePricingRow({{ $i }})" class="ow-btn-link" title="Remove" style="color:#b91c1c">✕</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" wire:click="addPricingRow" class="ow-btn-link" style="margin-top:.5rem">+ Add product</button>

            <div class="ow-fgrid" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-top:.85rem">
                <div class="ow-field"><label>Pickup charge (RM)</label><div class="ow-money"><span>RM</span><input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="pricing.pickup_charge" class="ow-input"></div></div>
                <div class="ow-field"><label>Drop-off charge (RM)</label><div class="ow-money"><span>RM</span><input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="pricing.drop_off_charge" class="ow-input"></div></div>
                <div class="ow-field"><label>Other charges (RM)</label><div class="ow-money"><span>RM</span><input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="pricing.other_charges" class="ow-input"></div></div>
            </div>
            <div class="ow-field" style="margin-top:.75rem">
                <label>Pricing remarks</label>
                <input type="text" wire:model="pricing.remarks" class="ow-input" placeholder="Charges based on stated quantity and route">
            </div>
            @if ($hasOverride)
                <div class="ow-field" style="margin-top:.75rem">
                    <label>Price override reason <span class="ow-req">*</span></label>
                    <input type="text" wire:model="pricing.override_reason" class="ow-input" placeholder="Why does this rate differ from the price list?">
                    <div class="ow-note" style="margin-top:.25rem">Rates in amber differ from the UOM price list. HQ Admin or Branch Manager permission and a reason are required; both are kept in the order activity.</div>
                </div>
            @endif

            @if ($unpricedRows->isNotEmpty())
                <div class="ow-callout ow-callout-warning" style="margin-top:.75rem">
                    <strong>No price yet:</strong> {{ $unpricedRows->implode(', ') }}<br>
                    Kept on the order and left out of the total. Enter a price before sending the quotation.
                </div>
            @endif

            <div class="ow-total-row"><span>Quotation total</span><span>RM {{ number_format($totals['total'], 2) }}</span></div>
            <div class="ow-note" style="margin-top:.35rem">Quantity × unit rate + pickup + drop-off + other charges.</div>

            <div class="ow-actions-split" style="margin-top:.9rem">
                <button type="button" wire:click="savePricing(false)" wire:loading.attr="disabled" class="ow-btn">Save pricing draft</button>
                <button type="button" wire:click="savePricing(true)" wire:loading.attr="disabled" class="ow-btn ow-btn-primary">Preview quotation →</button>
            </div>
        </div>
    </div>
@else
    @php $t = $pr['table']; @endphp
    <div class="ow-card ow-card-pad">
        <div class="ow-card-title">
            Transport charges
            <span class="ow-pill ow-pill-{{ $t['badge_color'] }}">{{ $t['badge'] }}</span>
        </div>
        <div class="ow-table-wrap">
            <table class="ow-price-table">
                <thead>
                    <tr><th>Item / service</th><th>Quantity range</th><th>Route</th><th class="ow-num">Unit</th><th class="ow-num">Charge</th></tr>
                </thead>
                <tbody>
                    @forelse ($t['rows'] as $row)
                        <tr>
                            <td><div>{{ $row['item'] }}</div>@if ($row['sub'])<div class="ow-note">{{ $row['sub'] }}</div>@endif</td>
                            <td><div>{{ $row['qty_range'] }}</div>@if ($row['range'])<div class="ow-note">{{ $row['range'] }}</div>@endif</td>
                            <td>{{ $row['route'] }}</td>
                            <td class="ow-num">{{ $row['unit'] }}</td>
                            <td class="ow-num">{{ $row['charge'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ow-note">No charges yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ow-total-row"><span>Total</span><span>{{ $t['total'] }}</span></div>
        <details style="margin-top:.75rem">
            <summary class="ow-link" style="cursor:pointer">Pricing basis &amp; reference</summary>
            <div class="ow-dl" style="margin-top:.6rem">
                @foreach ($t['basis'] as $basis)
                    <div><div class="ow-dt">{{ $basis['label'] }}</div><div class="ow-dd">{{ $basis['value'] }}</div></div>
                @endforeach
            </div>
        </details>
        @if ($can['revise'] && ! $d['show_decision'])
            <div class="ow-actions" style="margin-top:.75rem">
                <button type="button" wire:click="mountAction('revise')" class="ow-btn ow-btn-sm">Revise (new version)</button>
            </div>
        @endif
    </div>
@endif
