@extends('layouts.portal')

@section('title', 'New Order')

@section('content')
<h1>Order form</h1>
<p class="muted" style="margin-top:-.5rem">Submit your transport request. Our team will review it and issue a quotation for your confirmation.</p>

@if ($errors->any())
    <div class="card" style="border-color:#fca5a5;background:#fef2f2">
        <strong>Please check the form:</strong>
        <ul style="margin:.5rem 0 0 1rem">
            @foreach ($errors->all() as $error)
                <li class="error" style="margin:0">{{ $error }}</li>
            @endforeach
        </ul>
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
                    <option value="{{ $sp->id }}" @selected(old('salesperson_id') == $sp->id)>{{ $sp->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div id="orders"></div>

    <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;margin:0 0 1rem;flex-wrap:wrap">
        <p class="muted" style="margin:0">Each extra item is a new order (Order 1, Order 2, …), same as Admin.</p>
        <button type="button" class="btn secondary" onclick="addOrder()" title="Another order on this page; each keeps its own colour and is submitted together">+ Add another item</button>
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
            <button type="button" class="btn danger small og-remove-order" onclick="removeOrder(this)">Remove order</button>
        </div>

        <div class="card">
            <h3 style="margin-top:0">1. Customer &amp; ownership</h3>
            <div class="og-cust-grid">
                <div>
                    <label>Customer *</label>
                    <input data-order="customer_name" required value="{{ $customerDefaults['name'] }}" placeholder="Company name">
                </div>
                <div class="og-field-billing">
                    <label>Billing address</label>
                    <textarea data-order="customer_address" rows="2" placeholder="Your billing address">{{ $customerDefaults['address'] }}</textarea>
                </div>
                <div>
                    <label>PIC name</label>
                    <input data-order="customer_pic_name" value="{{ $customerDefaults['pic_name'] }}" placeholder="Person in charge" autocomplete="name">
                </div>
                <div>
                    <label>Contact number</label>
                    <input type="tel" inputmode="tel" data-order="customer_pic_phone" value="{{ $customerDefaults['pic_phone'] }}" placeholder="Contact number" autocomplete="tel">
                </div>
            </div>
            <input type="hidden" data-order="branch_id" value="{{ $selectedBranch?->id ?? $customer?->branch_id }}">
            <input type="hidden" data-order="order_type" value="{{ $defaultOrderType }}">
            <input type="hidden" data-order="service_type" value="pickup">
            <input type="hidden" data-order="payment_method" value="{{ array_key_first($paymentMethods) }}">
        </div>

        <div class="card">
            <h3 style="margin-top:0">2. Goods</h3>
            <p class="muted" style="margin-top:-.5rem">Choose the type first, the same as Admin: Measurement, Item, or Chartered Lorry. Item can have several lines on this order.</p>
            <div class="og-items"></div>
            <button type="button" class="btn secondary og-add-item" style="display:none" onclick="addItem(this.closest('.og-order'), { kind: 'item' })">+ Add item</button>
            <input type="hidden" data-order="route_mode" value="single">
        </div>

        <div class="card">
            <h3 style="margin-top:0">3. Pickup</h3>
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
            <textarea data-order="pickup_address" class="pickup-address" rows="2" required></textarea>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 1rem">
                <div>
                    <label>Pickup Google Maps URL</label>
                    <input type="url" data-order="pickup_maps_url" class="pickup-maps" placeholder="https://maps.app.goo.gl/…">
                </div>
                <div>
                    <label>Required delivery date</label>
                    <input type="date" data-order="preferred_delivery_date">
                </div>
            </div>
            <label>Special requirements / instructions</label>
            <textarea data-order="special_requirements" rows="2"></textarea>
            <div class="og-extra-pickups"></div>
            <button type="button" class="btn secondary og-add-pickup" style="display:none" onclick="addExtraPickup(this.closest('.og-order'))">Add New Destination</button>
        </div>

        <div class="card">
            <h3 style="margin-top:0">Contact persons for this order</h3>
            <p class="muted" style="margin-top:-.5rem">Who we can call about this shipment (e.g. at the warehouse and in the office).</p>
            <div class="og-contacts"></div>
            <button type="button" class="btn secondary" onclick="addContact(this.closest('.og-order'))">+ Add contact person</button>
        </div>

        <div class="card">
            <h3 style="margin-top:0" class="og-dropoff-heading">4. Drop-off destination</h3>
            <div class="og-destinations"></div>
            <button type="button" class="btn secondary og-add-dest" style="display:none" onclick="addDestination(this.closest('.og-order'))">Add New Destination</button>
        </div>

        <div class="card">
            <h3 style="margin-top:0">5. Photos &amp; supporting documents</h3>
            <label>Photos, DO, packing list or other documents (up to 10 files: JPG / PNG / PDF / Word / Excel, 10 MB each)</label>
            <input type="file" class="og-photos" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv">
        </div>
    </div>
</template>

<template id="destination-template">
    <div class="dest-row" style="border:1px dashed var(--line);border-radius:.5rem;padding:.75rem 1rem;margin-bottom:.75rem">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <strong>Destination <span class="dest-no"></span></strong>
            <button type="button" class="btn secondary remove-dest" style="padding:.25rem .6rem" onclick="const o=this.closest('.og-order'); this.closest('.dest-row').remove(); applyRouteMode(o)">Remove</button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 1rem;margin-top:.5rem">
            <div><label>Consignee *</label><input data-name="consignee_name" required></div>
            <div><label>Consignee phone</label><input data-name="consignee_phone"></div>
            <div><label>Drop-off type *</label>
                <select data-name="drop_off_type" required>
                    @foreach ($dropOffTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if ($deliveryAddresses->isNotEmpty())
            <label>Saved delivery address</label>
            <select class="dest-saved" onchange="pickSavedDestination(this)">
                <option value="">— Enter a new address —</option>
                @foreach ($deliveryAddresses as $a)
                    <option value="{{ $a->id }}" data-label="{{ $a->label }}" data-address="{{ $a->address }}" data-postcode="{{ $a->postcode }}" data-city="{{ $a->city }}" data-state="{{ $a->state }}" data-maps="{{ $a->google_maps_url }}">{{ $a->label }} — {{ \Illuminate\Support\Str::limit($a->address, 60) }}</option>
                @endforeach
            </select>
        @endif
        <label>Address *</label><textarea data-name="address" rows="2" required></textarea>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0 1rem">
            <div><label>City</label><input data-name="city"></div>
            <div><label>Postcode</label><input data-name="postcode"></div>
            <div><label>State</label><input data-name="state"></div>
            <div><label>Google Maps link</label><input type="url" data-name="maps_url" placeholder="https://maps.app.goo.gl/…"></div>
        </div>
        <label>Special requirements / instructions</label>
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
        <textarea data-name="address" rows="2" required></textarea>
        <label>Google Maps URL</label>
        <input type="url" data-name="maps_url" placeholder="https://maps.app.goo.gl/…">
        <label>Special requirements / instructions</label>
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
            <div class="item-dest-wrap" style="display:none">
                <label>Destination</label>
                <select data-name="destination_index" class="dest-select"></select>
            </div>
        </div>
        <div class="item-measure" style="display:none;margin-top:.35rem">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0 .75rem">
                <div><label>L (cm) min – max *</label><div style="display:flex;gap:.35rem;align-items:center"><input type="number" step="0.01" min="0" data-name="length" oninput="syncItemName(this.closest('.item-row'))"><span>–</span><input type="number" step="0.01" min="0" data-name="length_max" oninput="syncItemName(this.closest('.item-row'))"></div></div>
                <div><label>W (cm) min – max *</label><div style="display:flex;gap:.35rem;align-items:center"><input type="number" step="0.01" min="0" data-name="width" oninput="syncItemName(this.closest('.item-row'))"><span>–</span><input type="number" step="0.01" min="0" data-name="width_max" oninput="syncItemName(this.closest('.item-row'))"></div></div>
                <div><label>H (cm) min – max *</label><div style="display:flex;gap:.35rem;align-items:center"><input type="number" step="0.01" min="0" data-name="height" oninput="syncItemName(this.closest('.item-row'))"><span>–</span><input type="number" step="0.01" min="0" data-name="height_max" oninput="syncItemName(this.closest('.item-row'))"></div></div>
                <div><label>Weight (kg) *</label><input type="number" step="0.001" min="0" data-name="weight"></div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr auto;gap:0 .75rem;align-items:end">
            <div><label>Special handling</label><input data-name="handling" placeholder="e.g. Fragile, keep upright, forklift needed"></div>
            <div><button type="button" class="btn secondary remove-item" style="margin-bottom:.9rem;display:none" onclick="removeItem(this)">Remove item</button></div>
        </div>
    </div>
</template>

<script>
    const catalog = @json($productCatalog);
    const oldOrders = @json(array_values(old('orders', [])));
    const defaultPic = @json($customer?->pics->firstWhere('is_default', true) ?? $customer?->pics->first());
    const ORDER_COLORS = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2'];

    function field(row, name) { return row.querySelector(`[data-name="${name}"]`); }
    function orderList() { return [...document.querySelectorAll('#orders > .og-order')]; }

    function pickSavedPickup(sel) {
        const opt = sel.selectedOptions[0];
        const order = sel.closest('.og-order');
        if (!opt || !opt.value || !order) return;
        order.querySelector('.pickup-address').value = opt.dataset.address || '';
        order.querySelector('.pickup-maps').value = opt.dataset.maps || '';
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
            order.querySelectorAll('.og-items .item-row').forEach((row, j) => bindNames(row, `orders[${i}][items]`, j));
            order.querySelectorAll('.og-contacts .contact-row').forEach((row, j) => bindNames(row, `orders[${i}][contacts]`, j));
            refreshDestinationSelects(order);
        });
    }

    function hasLorry(order) {
        return [...order.querySelectorAll('.og-items [data-name="kind"]')].some(el => el.value === 'lorry');
    }

    function extraPickupCount(order) {
        return order.querySelectorAll('.og-extra-pickups .pickup-row').length;
    }

    function destCount(order) {
        return order.querySelectorAll('.og-destinations .dest-row').length;
    }

    function routeMode(order) {
        if (! hasLorry(order)) return 'single';
        if (extraPickupCount(order) > 0) return 'multi_pickup';
        if (destCount(order) > 1) return 'multi_dropoff';
        return 'single';
    }

    function applyRouteMode(order) {
        if (!order) return;
        const lorry = hasLorry(order);
        if (! lorry) {
            order.querySelectorAll('.og-extra-pickups .pickup-row').forEach(row => row.remove());
            [...order.querySelectorAll('.og-destinations .dest-row')].forEach((row, i) => { if (i > 0) row.remove(); });
        }
        const extraPickups = extraPickupCount(order);
        const dests = destCount(order);
        const mode = routeMode(order);
        const routeInput = order.querySelector('[data-order="route_mode"]');
        if (routeInput) routeInput.value = mode;
        const pickupAdd = order.querySelector('.og-add-pickup');
        const destAdd = order.querySelector('.og-add-dest');
        if (pickupAdd) pickupAdd.style.display = lorry && dests <= 1 ? '' : 'none';
        if (destAdd) destAdd.style.display = lorry && extraPickups === 0 ? '' : 'none';
        order.querySelector('.og-dropoff-heading').textContent = dests > 1 ? '4. Drop-off destinations' : '4. Drop-off destination';
        order.querySelectorAll('.remove-dest').forEach(btn => {
            btn.style.display = dests > 1 ? '' : 'none';
        });
        order.querySelectorAll('.item-dest-wrap').forEach(el => {
            el.style.display = dests > 1 ? '' : 'none';
        });
        syncGoodsButtons(order);
        bindAll();
    }

    function syncGoodsButtons(order) {
        const rows = [...order.querySelectorAll('.og-items .item-row')];
        const canAdd = rows.some(row => field(row, 'kind')?.value === 'item');
        const addBtn = order.querySelector('.og-add-item');
        if (addBtn) addBtn.style.display = canAdd ? '' : 'none';
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
            `<option value="${value}" ${String(value) === String(selected ?? '') ? 'selected' : ''}>${label}</option>`
        ).join('');
        sel.disabled = options.length === 0 && placeholder.includes('first');
    }

    function onItemKindChange(row, selectedSub, selectedProduct) {
        const kind = field(row, 'kind').value;
        const subs = Object.keys(catalog[kind] || {});
        fillSelect(field(row, 'sub_category'), subs.map(s => [s, s]), kind ? '— Sub-category —' : 'Choose the type first', selectedSub);
        row.querySelector('.item-measure').style.display = kind === 'measurement' ? '' : 'none';
        row.querySelector('.item-product').style.display = kind === 'measurement' || kind === '' ? 'none' : '';
        row.querySelectorAll('.item-measure input').forEach(el => { el.required = kind === 'measurement'; });
        field(row, 'sub_category').required = kind !== '';
        if (kind && subs.length === 1 && ! selectedSub) {
            field(row, 'sub_category').value = subs[0];
        }
        onItemSubChange(row, selectedProduct);
        applyRouteMode(row.closest('.og-order'));
    }

    function onItemSubChange(row, selectedProduct) {
        const kind = field(row, 'kind').value;
        const sub = field(row, 'sub_category').value;
        const productSel = row.querySelector('.product-select');
        if (kind === 'measurement' || ! kind) {
            fillSelect(productSel, [], kind === 'measurement' ? 'Size is entered below' : 'Choose the type first');
            productSel.disabled = true;
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
        if (kind === 'measurement') {
            field(row, 'item_name').value = sub || '';
        } else {
            field(row, 'item_name').value = productLabel || sub || '';
        }
    }

    function refreshDestinationSelects(order) {
        if (!order) return;
        const names = [...order.querySelectorAll('.og-destinations .dest-row')].map((row, i) =>
            field(row, 'consignee_name').value || `Destination ${i + 1}`);
        order.querySelectorAll('.dest-select').forEach(sel => {
            const current = sel.value;
            sel.innerHTML = names.map((n, i) => `<option value="${i}">${n}</option>`).join('');
            if (current !== '' && current < names.length) sel.value = current;
        });
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
        Object.entries(values).forEach(([k, v]) => { const el = field(row, k); if (el) el.value = v ?? ''; });
        field(row, 'consignee_name').addEventListener('input', () => refreshDestinationSelects(order));
        order.querySelector('.og-destinations').appendChild(row);
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
        if (values.kind) field(row, 'kind').value = values.kind;
        onItemKindChange(row, values.sub_category, values.uom_id);
        Object.entries(values).forEach(([k, v]) => {
            if (k === 'kind' || k === 'sub_category' || k === 'uom_id') return;
            const el = field(row, k); if (el) el.value = v ?? '';
        });
        if (values.item_name) field(row, 'item_name').value = values.item_name;
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
        applyRouteMode(order);
    }

    function addOrder(data = null) {
        const tpl = document.getElementById('order-template').content.cloneNode(true);
        const order = tpl.querySelector('.og-order');
        document.getElementById('orders').appendChild(order);
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

    function removeOrder(btn) {
        if (orderList().length <= 1) return;
        if (!confirm('Remove this order from the form?')) return;
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
