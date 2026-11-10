@extends('layouts.portal')

@section('title', 'New Order')

@section('content')
<h1>Order form</h1>
<p class="muted" style="margin-top:-.5rem">Submit your transport request. Our team will review it and issue a quotation for your confirmation.</p>

<style>
    /* the order form's sections (client, 11 Nov 2026): 1 billing customer, 2 pickup / store, 3 delivery + items, 4 remark */
    .og-sub { margin: 1rem 0 .5rem; font-size: .95rem; }
    .og-help { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: .5rem; padding: .6rem .8rem; margin: 0 0 .9rem; font-size: .88rem; line-height: 1.45; }
    .og-help.warn { background: #fef3c7; border-color: #fde68a; }
    .og-bill { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: .6rem 1.25rem; margin-bottom: .5rem; }
    .og-bill span { display: block; font-size: .8rem; color: var(--muted); }
    .og-bill strong { font-weight: 600; white-space: pre-line; }
    .item-price { font-size: .88rem; margin: -.3rem 0 .8rem; min-height: 1.2rem; }
    .item-price b { font-weight: 700; }
    .og-declare { display: flex; gap: .6rem; align-items: flex-start; font-size: .95rem; color: inherit; }
    .og-declare input { width: auto; margin: .2rem 0 0; }
</style>

@if ($errors->any())
    <div class="card" style="border-color:#fca5a5;background:#fef2f2">
        <strong>Please check the form:</strong>
        <ul style="margin:.5rem 0 0 1rem">
            @foreach ($errors->all() as $error)
                <li class="error" style="margin:0">{{ $error }}</li>
            @endforeach
        </ul>
        <p class="muted" style="margin:.5rem 0 0">Photos and documents have to be chosen again.</p>
    </div>
@endif

<form method="POST" action="{{ route('portal.enquiry.store') }}" enctype="multipart/form-data" id="order-form">
    @csrf

    <div class="card">
        @if ($linkedSalesperson)
            <div class="flash" style="background:#eff6ff;border-color:#bfdbfe;margin-bottom:0">
                Your salesperson: <strong>{{ $linkedSalesperson->name }}</strong> (from your ordering link). This cannot be changed.
            </div>
        @else
            <label>Salesperson (optional)</label>
            <select name="salesperson_id">
                <option value="">— Let the branch assign —</option>
                @foreach ($salespersons as $sp)
                    <option value="{{ $sp->id }}" @selected((string) old('salesperson_id', $customer?->salesperson_id) === (string) $sp->id)>{{ $sp->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div id="orders"></div>

    <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;margin:0 0 1rem;flex-wrap:wrap">
        <p class="muted" style="margin:0">Each order keeps its own colour and all are submitted together.</p>
        <button type="button" class="btn secondary" onclick="addOrder()" title="Another order on this page; each keeps its own colour and is submitted together">+ Add another order</button>
    </div>

    {{-- the declaration (client, 11 Nov 2026): ticked once for everything submitted on this page --}}
    <div class="card">
        <h3 style="margin-top:0">Declaration</h3>
        <label class="og-declare">
            <input type="checkbox" name="declaration" value="1" required @checked(old('declaration'))>
            <span>{{ $declaration }}</span>
        </label>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:.75rem;margin-bottom:2rem">
        <a href="{{ route('portal.dashboard') }}" class="btn secondary">Cancel</a>
        <button class="btn" type="submit">Submit order</button>
    </div>
</form>

<template id="order-template">
    <div class="og-order">
        <div class="og-order-head">
            <strong><span class="og-order-dot"></span> Order <span class="og-order-no">1</span> of <span class="og-order-count">1</span></strong>
            {{-- no browser popup (user, 4 Nov 2026: it is swallowed in some browsers): the button asks inline --}}
            <span class="og-remove-wrap">
                <button type="button" class="btn danger small og-remove-order" onclick="askRemoveOrder(this)">Remove order</button>
                <span class="og-remove-ask" style="display:none;align-items:center;gap:.4rem">
                    <span>Remove this order?</span>
                    <button type="button" class="btn danger small" onclick="removeOrder(this)">Yes</button>
                    <button type="button" class="btn secondary small" onclick="cancelRemoveOrder(this)">No</button>
                </span>
            </span>
        </div>

        <div style="display:none">
            <input type="hidden" data-order="branch_id" value="{{ $selectedBranch?->id ?? $customer?->branch_id }}">
            <input type="hidden" data-order="order_type" value="{{ $defaultOrderType }}">
            <input type="hidden" data-order="payment_method" value="{{ array_key_first($paymentMethods) }}">
            <input type="hidden" data-order="route_mode" value="single">
        </div>

        {{-- 1. the billing customer = the consignor of the order; the server fills it from the logged-in account --}}
        <div class="card">
            <h3 style="margin-top:0">1. Billing customer</h3>
            <p class="muted" style="margin-top:-.5rem">This order is billed to your company, which is also the consignor (sender). To change these details, go to <a href="{{ route('portal.profile') }}">Profile</a>.</p>
            <div class="og-bill">
                <div><span>Company</span><strong>{{ $customerDefaults['name'] ?: '—' }}</strong></div>
                <div><span>Billing address</span><strong>{{ $customerDefaults['address'] ?: '—' }}</strong></div>
                <div><span>Person in charge</span><strong>{{ $customerDefaults['pic_name'] ?: '—' }}</strong></div>
                <div><span>Contact number</span><strong>{{ $customerDefaults['pic_phone'] ?: '—' }}</strong></div>
            </div>
            <h4 class="og-sub">Contact persons for this order</h4>
            <p class="muted" style="margin-top:-.3rem">Who we can call about this shipment (e.g. at the warehouse and in the office).</p>
            <div class="og-contacts"></div>
            <button type="button" class="btn secondary" onclick="addContact(this.closest('.og-order'))">+ Add contact person</button>
        </div>

        {{-- 2. how the goods reach O&G: we collect them (Pickup), or the customer sends them to an O&G store --}}
        <div class="card">
            <h3 style="margin-top:0">2. Pickup / Drop-off at store</h3>
            <label>How will the goods reach O&amp;G? *</label>
            <select data-order="service_type" onchange="applyRouteMode(this.closest('.og-order'))">
                <option value="pickup">Pickup — O&amp;G collects the goods from my address</option>
                @if ($stores !== [])
                    <option value="store">Drop-off at store — I will send the goods to an O&amp;G store</option>
                @endif
            </select>

            <div class="og-pickup-panel">
                @if ($pickupAddresses->isNotEmpty())
                    <label>Saved pickup location</label>
                    <select class="pickup-saved" onchange="pickSavedPickup(this)">
                        <option value="">— Enter a new pickup address —</option>
                        @foreach ($pickupAddresses as $a)
                            <option value="{{ $a->id }}" data-address="{{ collect([$a->address, $a->postcode, $a->city, $a->state])->filter()->implode(', ') }}" data-maps="{{ $a->google_maps_url }}" @selected($a->is_default)>{{ $a->label }} — {{ \Illuminate\Support\Str::limit($a->address, 60) }}</option>
                        @endforeach
                    </select>
                @endif
                <label>Pickup address *</label>
                <textarea data-order="pickup_address" class="pickup-address" rows="2" required oninput="refreshPickupSelects(this.closest('.og-order'))"></textarea>
                <label>Pickup Google Maps URL</label>
                <input type="url" data-order="pickup_maps_url" class="pickup-maps" placeholder="https://maps.app.goo.gl/…">
                <div class="og-extra-pickups"></div>
                <div class="og-help og-pickup-help">
                    <strong>Goods at more than one address?</strong> Use “Add another pickup address”, then choose the pickup address on each item in section 3.
                    An order can have several pickup addresses <em>or</em> several drop-off addresses, not both — for the rest, use “+ Add another order”.
                </div>
                <div class="og-help warn og-pickup-blocked" style="display:none">This order has several drop-off addresses, so it has one pickup address. For another pickup address, use “+ Add another order”.</div>
                <button type="button" class="btn secondary og-add-pickup" onclick="addExtraPickup(this.closest('.og-order'))">+ Add another pickup address</button>
            </div>

            <div class="og-store-panel" style="display:none">
                <label>O&amp;G store you will send the goods to *</label>
                <select data-order="store_id" class="og-store-select" onchange="showStoreAddress(this)">
                    <option value="">— Select a store —</option>
                    @foreach ($stores as $store)
                        <option value="{{ $store['id'] }}" data-address="{{ $store['address'] }}">{{ $store['label'] }}</option>
                    @endforeach
                </select>
                <p class="muted og-store-address" style="margin-top:-.5rem"></p>
            </div>

            <div style="max-width:16rem;margin-top:.9rem">
                <label class="og-date-label">Pickup date</label>
                <input type="date" data-order="pickup_date">
            </div>
        </div>

        {{-- 3. where the goods go (delivery or own collection) and the items, each on its drop-off address --}}
        <div class="card">
            <h3 style="margin-top:0">3. Delivery / Own collection and items</h3>
            <h4 class="og-sub" style="margin-top:0">Drop-off</h4>
            <div class="og-help og-dest-help">
                <strong>Sending to more than one address?</strong> Add each drop-off address here, then choose the drop-off address on each item below.
                Each drop-off address gets its own consignment note (CSN) under this order.
            </div>
            <div class="og-help warn og-dest-blocked" style="display:none">This order has several pickup addresses, so it has one drop-off address. For another drop-off address, use “+ Add another order”.</div>
            <div class="og-destinations"></div>
            <button type="button" class="btn secondary og-add-dest" onclick="addDestination(this.closest('.og-order'))">+ Add another drop-off address</button>

            <h4 class="og-sub">Items</h4>
            <p class="muted" style="margin-top:-.3rem">Choose the type: Measurement, Item or Chartered Lorry — or “Other” and describe the goods. Every item except a chartered lorry needs its size (L × W × H) and weight.</p>
            <div class="og-items"></div>
            <button type="button" class="btn secondary og-add-item" onclick="addItem(this.closest('.og-order'))">+ Add item</button>
        </div>

        {{-- 4. remark (not mandatory) and the documents of the whole order --}}
        <div class="card">
            <h3 style="margin-top:0">4. Remark</h3>
            <label>Remark (optional)</label>
            <textarea data-order="special_requirements" rows="2" placeholder="Anything we should know about this order"></textarea>
            <label>Supporting documents — DO, packing list or others (up to 10 files: JPG / PNG / PDF / Word / Excel, 10 MB each). Photos of an item go on the item in section 3.</label>
            <input type="file" class="og-photos" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv">
        </div>
    </div>
</template>

<template id="destination-template">
    <div class="dest-row" style="border:1px dashed var(--line);border-radius:.5rem;padding:.75rem 1rem;margin-bottom:.75rem">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <strong>Drop-off <span class="dest-no"></span></strong>
            <button type="button" class="btn secondary remove-dest" style="padding:.25rem .6rem" onclick="const o=this.closest('.og-order'); this.closest('.dest-row').remove(); applyRouteMode(o)">Remove</button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 1rem;margin-top:.5rem">
            <div><label>Delivery or own collection *</label>
                <select data-name="mode" onchange="onDestModeChange(this.closest('.dest-row'))">
                    <option value="delivery">Delivery — O&amp;G delivers to the address</option>
                    @if ($stores !== [])
                        <option value="collect">Own collection — consignee collects at an O&amp;G store</option>
                    @endif
                </select>
            </div>
            <div><label>Consignee (receiver) *</label><input data-name="consignee_name" required></div>
            <div><label>Consignee phone</label><input data-name="consignee_phone"></div>
            <div><label>Drop-off type *</label>
                <select data-name="drop_off_type" required>
                    @foreach ($dropOffTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="dest-collect" style="display:none">
            <label>O&amp;G store the goods are collected from *</label>
            <select data-name="collect_store_id" onchange="showStoreAddress(this)">
                <option value="">— Select a store —</option>
                @foreach ($stores as $store)
                    <option value="{{ $store['id'] }}" data-address="{{ $store['address'] }}">{{ $store['label'] }}</option>
                @endforeach
            </select>
            <p class="muted og-store-address" style="margin-top:-.5rem"></p>
        </div>
        <div class="dest-delivery">
            @if ($deliveryAddresses->isNotEmpty())
                <label>Saved delivery address</label>
                <select class="dest-saved" onchange="pickSavedDestination(this)">
                    <option value="">— Enter a new address —</option>
                    @foreach ($deliveryAddresses as $a)
                        <option value="{{ $a->id }}" data-label="{{ $a->label }}" data-address="{{ $a->address }}" data-postcode="{{ $a->postcode }}" data-city="{{ $a->city }}" data-state="{{ $a->state }}" data-maps="{{ $a->google_maps_url }}">{{ $a->label }} — {{ \Illuminate\Support\Str::limit($a->address, 60) }}</option>
                    @endforeach
                </select>
            @endif
            <label>Delivery address *</label><textarea data-name="address" rows="2" required></textarea>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0 1rem">
                <div><label>City</label><input data-name="city"></div>
                <div><label>Postcode</label><input data-name="postcode"></div>
                <div><label>State</label><input data-name="state"></div>
                <div><label>Google Maps link</label><input type="url" data-name="maps_url" placeholder="https://maps.app.goo.gl/…"></div>
            </div>
        </div>
        <label>Remark (optional)</label>
        <textarea data-name="special_requirements" rows="2"></textarea>
    </div>
</template>

<template id="pickup-template">
    <div class="pickup-row" style="border:1px dashed var(--line);border-radius:.5rem;padding:.75rem 1rem;margin:.75rem 0">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <strong>Pickup <span class="pickup-no"></span></strong>
            <button type="button" class="btn secondary" style="padding:.25rem .6rem" onclick="const o=this.closest('.og-order'); this.closest('.pickup-row').remove(); applyRouteMode(o)">Remove</button>
        </div>
        <label>Address *</label>
        <textarea data-name="address" rows="2" required oninput="refreshPickupSelects(this.closest('.og-order'))"></textarea>
        <label>Google Maps URL</label>
        <input type="url" data-name="maps_url" placeholder="https://maps.app.goo.gl/…">
        <label>Remark (optional)</label>
        <textarea data-name="special_requirements" rows="2"></textarea>
    </div>
</template>

<template id="contact-template">
    <div class="contact-row" style="display:grid;grid-template-columns:2fr 1.5fr 1.5fr auto;gap:0 .75rem;align-items:end">
        <div><label>Name</label><input data-name="name"></div>
        <div><label>Contact number</label><input data-name="phone"></div>
        <div><label>Role</label><input data-name="role" placeholder="e.g. Warehouse"></div>
        <div><button type="button" class="btn secondary" style="margin-bottom:.9rem" onclick="this.closest('.contact-row').remove(); bindAll()">✕</button></div>
    </div>
</template>

<template id="item-template">
    <div class="item-row" style="border:1px dashed var(--line);border-radius:.5rem;padding:.75rem 1rem .1rem;margin-bottom:.75rem">
        <input type="hidden" data-name="item_name">
        <input type="hidden" data-name="uom_id">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0 .75rem;align-items:end">
            <div>
                <label>Type *</label>
                <select data-name="kind" required onchange="onItemKindChange(this.closest('.item-row'))">
                    <option value="">— Type —</option>
                    @foreach ($productKinds as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                    <option value="other">Other — describe the goods</option>
                </select>
            </div>
            <div class="item-sub">
                <label>Sub-category *</label>
                <select data-name="sub_category" onchange="onItemSubChange(this.closest('.item-row'))">
                    <option value="">Choose the type first</option>
                </select>
            </div>
            <div class="item-product">
                <label>Product *</label>
                <select class="product-select" onchange="onItemProductChange(this.closest('.item-row'))" disabled>
                    <option value="">Choose the type first</option>
                </select>
            </div>
            <div>
                <label>Qty *</label>
                <input type="number" step="0.001" min="0.001" data-name="quantity" value="1" required>
            </div>
            {{-- which address this item is for (shown when the order has more than one) --}}
            <div class="item-pickup-wrap" style="display:none">
                <label>Pickup address *</label>
                <select data-name="pickup_index" class="pickup-select" onchange="delete this.dataset.want"></select>
            </div>
            <div class="item-dest-wrap" style="display:none">
                <label>Drop-off address *</label>
                <select data-name="destination_index" class="dest-select" onchange="delete this.dataset.want"></select>
            </div>
        </div>
        {{-- size and weight: every item except a chartered lorry; a Measurement takes a min – max range --}}
        <div class="item-measure" style="display:none;margin-top:.35rem">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0 .75rem">
                <div><label>L (cm)<span class="range-max"> min – max</span> *</label><div style="display:flex;gap:.35rem;align-items:center"><input type="number" step="0.01" min="0" data-name="length" oninput="syncItemName(this.closest('.item-row'))"><span class="range-max">–</span><input class="range-max" type="number" step="0.01" min="0" data-name="length_max" oninput="syncItemName(this.closest('.item-row'))"></div></div>
                <div><label>W (cm)<span class="range-max"> min – max</span> *</label><div style="display:flex;gap:.35rem;align-items:center"><input type="number" step="0.01" min="0" data-name="width" oninput="syncItemName(this.closest('.item-row'))"><span class="range-max">–</span><input class="range-max" type="number" step="0.01" min="0" data-name="width_max" oninput="syncItemName(this.closest('.item-row'))"></div></div>
                <div><label>H (cm)<span class="range-max"> min – max</span> *</label><div style="display:flex;gap:.35rem;align-items:center"><input type="number" step="0.01" min="0" data-name="height" oninput="syncItemName(this.closest('.item-row'))"><span class="range-max">–</span><input class="range-max" type="number" step="0.01" min="0" data-name="height_max" oninput="syncItemName(this.closest('.item-row'))"></div></div>
                <div><label>Weight (kg) *</label><input type="number" step="0.001" min="0" data-name="weight"></div>
            </div>
        </div>
        <div class="item-price"></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0 .75rem;align-items:end">
            <div><label class="item-desc-label">Description</label><input data-name="description" maxlength="500" placeholder="e.g. 2 pallets of floor tiles, fragile, keep upright"></div>
            <div><label>Photos of this item (up to 5: JPG / PNG / PDF)</label><input type="file" class="item-photos" multiple accept="image/*,.pdf"></div>
        </div>
        <div style="display:flex;justify-content:flex-end">
            <button type="button" class="btn secondary remove-item" style="margin-bottom:.9rem;display:none" onclick="removeItem(this)">Remove item</button>
        </div>
    </div>
</template>

<script>
    const catalog = @json($productCatalog);
    const oldOrders = @json(array_values(old('orders', [])));
    const defaultPic = @json($customer?->pics->firstWhere('is_default', true) ?? $customer?->pics->first());
    const PRICE_URL = @json(route('portal.enquiry.price'));
    const ORDER_COLORS = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2'];
    function field(row, name) { return row.querySelector(`[data-name="${name}"]`); }
    function orderList() { return [...document.querySelectorAll('#orders > .og-order')]; }
    function esc(text) { const d = document.createElement('div'); d.textContent = text ?? ''; return d.innerHTML; }
    function money(n) { return 'RM ' + Number(n).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function pickSavedPickup(sel) {
        const opt = sel.selectedOptions[0];
        const order = sel.closest('.og-order');
        if (!opt || !opt.value || !order) return;
        order.querySelector('.pickup-address').value = opt.dataset.address || '';
        order.querySelector('.pickup-maps').value = opt.dataset.maps || '';
        refreshPickupSelects(order);
    }

    function pickSavedDestination(sel) {
        const opt = sel.selectedOptions[0];
        if (!opt || !opt.value) return;
        const row = sel.closest('.dest-row');
        const set = (name, value) => { const el = field(row, name); if (el && value !== undefined) el.value = value || ''; };
        set('address', opt.dataset.address); set('postcode', opt.dataset.postcode); set('city', opt.dataset.city);
        set('state', opt.dataset.state); set('maps_url', opt.dataset.maps);
        const consignee = field(row, 'consignee_name');
        if (!consignee.value) consignee.value = opt.dataset.label || '';
        refreshDestinationSelects(sel.closest('.og-order'));
        schedulePrices(sel.closest('.og-order'));
    }

    // the address of the chosen O&G store under its dropdown
    function showStoreAddress(sel) {
        const out = sel.parentElement.querySelector('.og-store-address');
        const opt = sel.selectedOptions[0];
        if (out) out.textContent = opt && opt.value ? (opt.dataset.address || '') : '';
    }

    function bindNames(row, group, index) {
        row.querySelectorAll('[data-name]').forEach(el => {
            el.name = `${group}[${index}][${el.dataset.name}]`;
        });
    }

    function bindAll() {
        const list = orderList();
        const n = list.length;
        list.forEach((order, i) => {
            order.style.setProperty('--og-order-color', ORDER_COLORS[i % ORDER_COLORS.length]);
            order.classList.toggle('og-order-multi', n > 1);
            order.querySelector('.og-order-no').textContent = i + 1;
            order.querySelector('.og-order-count').textContent = n;
            order.querySelectorAll('[data-order]').forEach(el => {
                el.name = `orders[${i}][${el.dataset.order}]`;
            });
            const photos = order.querySelector('.og-photos');
            if (photos) photos.name = `orders[${i}][photos][]`;
            order.querySelectorAll('.og-destinations .dest-row').forEach((row, j) => {
                row.querySelector('.dest-no').textContent = j + 1;
                bindNames(row, `orders[${i}][destinations]`, j);
            });
            order.querySelectorAll('.og-extra-pickups .pickup-row').forEach((row, j) => {
                row.querySelector('.pickup-no').textContent = j + 2;
                bindNames(row, `orders[${i}][extra_pickups]`, j);
            });
            order.querySelectorAll('.og-items .item-row').forEach((row, j) => {
                bindNames(row, `orders[${i}][items]`, j);
                // the photos of an item are sent with the item
                row.querySelector('.item-photos').name = `orders[${i}][items][${j}][photos][]`;
            });
            order.querySelectorAll('.og-contacts .contact-row').forEach((row, j) => bindNames(row, `orders[${i}][contacts]`, j));
            refreshDestinationSelects(order);
            refreshPickupSelects(order);
        });
    }

    function serviceType(order) {
        return order.querySelector('[data-order="service_type"]').value || 'pickup';
    }

    function extraPickupCount(order) {
        return order.querySelectorAll('.og-extra-pickups .pickup-row').length;
    }

    function destCount(order) {
        return order.querySelectorAll('.og-destinations .dest-row').length;
    }

    // several pickup addresses or several drop-off addresses, for any goods, not both (the admin order's rule)
    function applyRouteMode(order) {
        if (!order) return;
        const store = serviceType(order) === 'store';
        if (store) {
            order.querySelectorAll('.og-extra-pickups .pickup-row').forEach(row => row.remove());
        }
        const extraPickups = extraPickupCount(order);
        const dests = destCount(order);
        order.querySelector('[data-order="route_mode"]').value = extraPickups > 0 ? 'multi_pickup' : (dests > 1 ? 'multi_dropoff' : 'single');

        order.querySelector('.og-pickup-panel').style.display = store ? 'none' : '';
        order.querySelector('.og-store-panel').style.display = store ? '' : 'none';
        order.querySelector('.pickup-address').required = ! store;
        order.querySelector('.og-store-select').required = store;
        order.querySelector('.og-date-label').textContent = store ? 'Date you will send the goods to the store' : 'Pickup date';

        order.querySelector('.og-add-pickup').style.display = dests <= 1 ? '' : 'none';
        order.querySelector('.og-pickup-help').style.display = dests <= 1 ? '' : 'none';
        order.querySelector('.og-pickup-blocked').style.display = dests > 1 ? '' : 'none';
        order.querySelector('.og-add-dest').style.display = extraPickups === 0 ? '' : 'none';
        order.querySelector('.og-dest-help').style.display = extraPickups === 0 ? '' : 'none';
        order.querySelector('.og-dest-blocked').style.display = extraPickups > 0 ? '' : 'none';

        order.querySelectorAll('.remove-dest').forEach(btn => { btn.style.display = dests > 1 ? '' : 'none'; });
        order.querySelectorAll('.item-dest-wrap').forEach(el => { el.style.display = dests > 1 ? '' : 'none'; });
        order.querySelectorAll('.item-pickup-wrap').forEach(el => { el.style.display = extraPickups > 0 ? '' : 'none'; });
        syncGoodsButtons(order);
        bindAll();
        schedulePrices(order);
    }

    function onDestModeChange(row) {
        const collect = field(row, 'mode').value === 'collect';
        row.querySelector('.dest-collect').style.display = collect ? '' : 'none';
        row.querySelector('.dest-delivery').style.display = collect ? 'none' : '';
        field(row, 'address').required = ! collect;
        field(row, 'collect_store_id').required = collect;
        schedulePrices(row.closest('.og-order'));
    }

    function syncGoodsButtons(order) {
        const rows = [...order.querySelectorAll('.og-items .item-row')];
        rows.forEach(row => {
            const rm = row.querySelector('.remove-item');
            if (rm) rm.style.display = rows.length > 1 ? '' : 'none';
        });
    }

    function removeItem(btn) {
        const order = btn.closest('.og-order');
        if (!order || order.querySelectorAll('.og-items .item-row').length <= 1) return;
        btn.closest('.item-row').remove();
        applyRouteMode(order);
    }

    function fillSelect(sel, options, placeholder, selected) {
        sel.innerHTML = `<option value="">${placeholder}</option>` + options.map(([value, label]) =>
            `<option value="${esc(value)}" ${String(value) === String(selected ?? '') ? 'selected' : ''}>${esc(label)}</option>`
        ).join('');
        sel.disabled = options.length === 0 && placeholder.includes('first');
    }

    function onItemKindChange(row, selectedSub, selectedProduct) {
        const kind = field(row, 'kind').value;
        const listed = kind !== '' && kind !== 'other';      // a product chosen from the price list
        const sized = kind !== '' && kind !== 'lorry';       // size and weight: everything except a chartered lorry
        const subs = Object.keys(catalog[kind] || {});
        fillSelect(field(row, 'sub_category'), subs.map(s => [s, s]), kind ? '— Sub-category —' : 'Choose the type first', selectedSub);
        row.querySelector('.item-sub').style.display = kind === 'other' ? 'none' : '';
        row.querySelector('.item-product').style.display = kind === 'measurement' || ! listed ? 'none' : '';
        row.querySelector('.item-measure').style.display = sized ? '' : 'none';
        row.querySelectorAll('.item-measure .range-max').forEach(el => { el.style.display = kind === 'measurement' ? '' : 'none'; });
        if (kind !== 'measurement') {
            row.querySelectorAll('.item-measure input.range-max').forEach(el => { el.value = ''; });
        }
        ['length', 'width', 'height', 'weight'].forEach(name => { field(row, name).required = sized; });
        field(row, 'sub_category').required = listed;
        // described goods: the description is the item
        field(row, 'description').required = kind === 'other';
        row.querySelector('.item-desc-label').textContent = kind === 'other' ? 'Description *' : 'Description';
        if (listed && subs.length === 1 && ! selectedSub) {
            field(row, 'sub_category').value = subs[0];
        }
        onItemSubChange(row, selectedProduct);
        applyRouteMode(row.closest('.og-order'));
    }

    function onItemSubChange(row, selectedProduct) {
        const kind = field(row, 'kind').value;
        const sub = field(row, 'sub_category').value;
        const productSel = row.querySelector('.product-select');
        if (kind === 'measurement' || kind === 'other' || ! kind) {
            fillSelect(productSel, [], kind === 'measurement' ? 'Size is entered below' : 'Choose the type first');
            productSel.disabled = true;
            field(row, 'uom_id').value = '';
            syncItemName(row);
            return;
        }
        const products = (catalog[kind] && catalog[kind][sub]) ? catalog[kind][sub] : [];
        fillSelect(productSel, products.map(p => [p.id, p.name]), sub ? '— Select ' + (kind === 'lorry' ? 'lorry' : 'item') + ' —' : 'Choose the sub-category first', selectedProduct);
        productSel.disabled = ! sub;
        if (products.length === 1 && ! selectedProduct) {
            productSel.value = products[0].id;
        }
        onItemProductChange(row);
    }

    function onItemProductChange(row) {
        const productSel = row.querySelector('.product-select');
        field(row, 'uom_id').value = productSel.value || '';
        syncItemName(row);
    }

    function syncItemName(row) {
        const kind = field(row, 'kind').value;
        const sub = field(row, 'sub_category').value;
        const productSel = row.querySelector('.product-select');
        const productLabel = productSel.selectedOptions[0] && productSel.value ? productSel.selectedOptions[0].textContent : '';
        if (kind === 'other') {
            field(row, 'item_name').value = '';        // the server takes the description
        } else if (kind === 'measurement') {
            field(row, 'item_name').value = sub || '';
        } else {
            field(row, 'item_name').value = productLabel || sub || '';
        }
    }

    // an item's address dropdown keeps its choice while the list of addresses is rebuilt
    function fillAddressSelect(sel, names) {
        const current = sel.dataset.want ?? sel.value;
        sel.innerHTML = names.map((n, i) => `<option value="${i}">${esc(n)}</option>`).join('');
        // a choice restored from a refused form waits until its address exists (addresses are added one by one)
        if (current !== '' && current !== undefined && Number(current) < names.length) {
            sel.value = current;
            delete sel.dataset.want;
        }
    }

    function refreshDestinationSelects(order) {
        if (!order) return;
        const names = [...order.querySelectorAll('.og-destinations .dest-row')].map((row, i) =>
            `Drop-off ${i + 1}` + (field(row, 'consignee_name').value ? ' — ' + field(row, 'consignee_name').value : ''));
        order.querySelectorAll('.dest-select').forEach(sel => fillAddressSelect(sel, names));
    }

    function refreshPickupSelects(order) {
        if (!order) return;
        const short = text => { const t = (text || '').replace(/\s+/g, ' ').trim(); return t.length > 40 ? t.slice(0, 40) + '…' : t; };
        const names = [order.querySelector('.pickup-address').value, ...[...order.querySelectorAll('.og-extra-pickups .pickup-row')].map(row => field(row, 'address').value)]
            .map((address, i) => `Pickup ${i + 1}` + (short(address) ? ' — ' + short(address) : ''));
        order.querySelectorAll('.pickup-select').forEach(sel => fillAddressSelect(sel, names));
    }

    // the price of each item (general price and the customer's own), asked from the server a moment after a change
    function schedulePrices(order) {
        if (!order) return;
        clearTimeout(order._priceTimer);
        order._priceTimer = setTimeout(() => order.querySelectorAll('.og-items .item-row').forEach(refreshPrice), 450);
    }

    async function refreshPrice(row) {
        const out = row.querySelector('.item-price');
        const order = row.closest('.og-order');
        const kind = field(row, 'kind').value;
        const later = '<span class="muted">The price will be given in the quotation.</span>';
        if (! kind) { out.innerHTML = ''; row._priceKey = ''; return; }
        if (kind === 'other') { out.innerHTML = later; row._priceKey = ''; return; }
        const dests = [...order.querySelectorAll('.og-destinations .dest-row')];
        const dest = dests[Number(field(row, 'destination_index').value || 0)] || dests[0];
        const params = new URLSearchParams({ kind, sub_category: field(row, 'sub_category').value, uom_id: field(row, 'uom_id').value, quantity: field(row, 'quantity').value });
        ['length', 'width', 'height', 'weight'].forEach(name => params.set(name, field(row, name).value));
        if (dest) {
            params.set('drop_off_type', field(dest, 'drop_off_type').value);
            if (field(dest, 'mode').value === 'collect') {
                params.set('collect_store_id', field(dest, 'collect_store_id').value);
            } else {
                ['city', 'state', 'address', 'consignee_name'].forEach(name => params.set(name, field(dest, name).value));
            }
        }
        const key = params.toString();
        if (row._priceKey === key) return;
        row._priceKey = key;
        try {
            const response = await fetch(PRICE_URL + '?' + key, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (! response.ok) throw new Error('price ' + response.status);
            const data = await response.json();
            if (row._priceKey !== key) return;
            if (data.status !== 'ok' || (data.general === null && data.yours === null)) {
                out.innerHTML = data.status === 'no_product' ? '' : later;
                return;
            }
            const qty = Number(data.quantity) || 1;
            const each = data.yours ?? data.general;
            const parts = [];
            if (data.general !== null) parts.push('General price: <b>' + money(data.general) + '</b> each');
            if (data.yours !== null) parts.push(esc(data.yours_label) + ': <b>' + money(data.yours) + '</b> each');
            out.innerHTML = parts.join(' &nbsp;·&nbsp; ')
                + ' <span class="muted">· ' + esc(data.product) + ' to ' + esc(data.location) + (qty !== 1 ? ' · about ' + money(each * qty) + ' for ' + qty : '') + ' · estimate, the quotation confirms the price</span>';
        } catch (e) {
            out.innerHTML = '';
            row._priceKey = '';
        }
    }

    function addContact(order, values = {}) {
        const tpl = document.getElementById('contact-template').content.cloneNode(true);
        const row = tpl.querySelector('.contact-row');
        Object.entries(values).forEach(([k, v]) => { const el = field(row, k); if (el) el.value = v ?? ''; });
        order.querySelector('.og-contacts').appendChild(row);
        bindAll();
    }

    function addDestination(order, values = {}) {
        const tpl = document.getElementById('destination-template').content.cloneNode(true);
        const row = tpl.querySelector('.dest-row');
        Object.entries(values).forEach(([k, v]) => { const el = field(row, k); if (el && v != null) el.value = v; });
        field(row, 'consignee_name').addEventListener('input', () => refreshDestinationSelects(order));
        order.querySelector('.og-destinations').appendChild(row);
        onDestModeChange(row);
        const store = field(row, 'collect_store_id');
        if (store) showStoreAddress(store);
        applyRouteMode(order);
    }

    function addExtraPickup(order, values = {}) {
        const tpl = document.getElementById('pickup-template').content.cloneNode(true);
        const row = tpl.querySelector('.pickup-row');
        Object.entries(values).forEach(([k, v]) => { const el = field(row, k); if (el) el.value = v ?? ''; });
        order.querySelector('.og-extra-pickups').appendChild(row);
        applyRouteMode(order);
    }

    function addItem(order, values = {}) {
        const tpl = document.getElementById('item-template').content.cloneNode(true);
        const row = tpl.querySelector('.item-row');
        order.querySelector('.og-items').appendChild(row);
        // goods described only come back without a type: "Other"
        const kind = values.kind || (values.description && ! values.uom_id && ! values.sub_category ? 'other' : '');
        if (kind) field(row, 'kind').value = kind;
        onItemKindChange(row, values.sub_category, values.uom_id);
        Object.entries(values).forEach(([k, v]) => {
            if (['kind', 'sub_category', 'uom_id', 'photos', 'attachments'].includes(k)) return;
            const el = field(row, k);
            if (! el || v == null) return;
            if (k === 'destination_index' || k === 'pickup_index') { el.dataset.want = v; return; }
            el.value = v;
        });
        if (values.item_name && kind !== 'other') field(row, 'item_name').value = values.item_name;
        syncItemName(row);
        applyRouteMode(order);
    }

    function fillOrder(order, data) {
        Object.entries(data || {}).forEach(([k, v]) => {
            if (['items', 'destinations', 'contacts', 'extra_pickups', 'photos', 'route_mode'].includes(k)) return;
            const el = order.querySelector(`[data-order="${k}"]`);
            if (el && v != null) el.value = v;
        });
        (data.items?.length ? data.items : [{}]).forEach(item => addItem(order, item));
        (data.destinations?.length ? data.destinations : [{}]).forEach(d => addDestination(order, d));
        (data.contacts?.length ? data.contacts : [defaultPic ? { name: defaultPic.name, phone: defaultPic.phone } : {}]).forEach(c => addContact(order, c));
        (data.extra_pickups || []).forEach(p => addExtraPickup(order, p));
        showStoreAddress(order.querySelector('.og-store-select'));
        applyRouteMode(order);
    }

    function addOrder(data = null) {
        const tpl = document.getElementById('order-template').content.cloneNode(true);
        const order = tpl.querySelector('.og-order');
        document.getElementById('orders').appendChild(order);
        // any change on the order may change an item's price (its drop-off address, type, size, quantity …)
        order.addEventListener('input', () => schedulePrices(order));
        order.addEventListener('change', () => schedulePrices(order));
        if (data) {
            fillOrder(order, data);
        } else {
            addItem(order);
            addDestination(order);
            addContact(order, defaultPic ? { name: defaultPic.name, phone: defaultPic.phone } : {});
            const saved = order.querySelector('.pickup-saved');
            if (saved && saved.value) pickSavedPickup(saved);
            applyRouteMode(order);
        }
        bindAll();
        if (orderList().length > 1) {
            order.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function askRemoveOrder(btn) {
        if (orderList().length <= 1) return;
        const wrap = btn.closest('.og-remove-wrap');
        btn.style.display = 'none';
        wrap.querySelector('.og-remove-ask').style.display = 'inline-flex';
    }

    function cancelRemoveOrder(btn) {
        const wrap = btn.closest('.og-remove-wrap');
        wrap.querySelector('.og-remove-ask').style.display = 'none';
        wrap.querySelector('.og-remove-order').style.display = '';
    }

    function removeOrder(btn) {
        if (orderList().length <= 1) return;
        btn.closest('.og-order').remove();
        bindAll();
    }

    if (oldOrders.length) {
        oldOrders.forEach(data => addOrder(data));
    } else {
        addOrder();
    }
</script>
@endsection
