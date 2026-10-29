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
        <h3 style="margin-top:0">1. Order &amp; ownership</h3>

        @if ($linkedSalesperson)
            <div class="flash" style="background:#eff6ff;border-color:#bfdbfe">
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

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 1rem">
            <div>
                <label>Branch *</label>
                <select name="branch_id" required>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id', $selectedBranch?->id) == $branch->id)>{{ $branch->code }} — {{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Order type *</label>
                <select name="order_type" required>
                    @foreach ($orderTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('order_type', $defaultOrderType) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Service *</label>
                <select name="service_type" required>
                    @foreach ($serviceTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('service_type', 'pickup') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Payment method *</label>
                <select name="payment_method" required>
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Your DO number *</label>
                <input name="customer_do_number" value="{{ old('customer_do_number') }}" required placeholder="e.g. DO-2026-0123">
            </div>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0">2. Pickup</h3>
        @if ($pickupAddresses->isNotEmpty())
            <label>Saved pickup location</label>
            <select id="pickup-saved" onchange="pickSavedPickup(this)">
                <option value="">— Enter a new pickup address —</option>
                @foreach ($pickupAddresses as $a)
                    <option value="{{ $a->id }}" data-address="{{ collect([$a->address, $a->postcode, $a->city, $a->state])->filter()->implode(', ') }}" data-maps="{{ $a->google_maps_url }}" @selected(! old('pickup_address') && $a->is_default)>{{ $a->label }} — {{ \Illuminate\Support\Str::limit($a->address, 60) }}</option>
                @endforeach
            </select>
        @endif
        <label>Pickup address *</label>
        <textarea name="pickup_address" id="pickup-address" rows="2" required>{{ old('pickup_address') }}</textarea>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 1rem">
            <div>
                <label>Pickup Google Maps URL</label>
                <input type="url" name="pickup_maps_url" id="pickup-maps" value="{{ old('pickup_maps_url') }}" placeholder="https://maps.app.goo.gl/…">
            </div>
            <div>
                <label>Required delivery date</label>
                <input type="date" name="preferred_delivery_date" value="{{ old('preferred_delivery_date') }}">
            </div>
        </div>

        <label>Special requirements / instructions</label>
        <textarea name="special_requirements" rows="2">{{ old('special_requirements') }}</textarea>
    </div>

    <div class="card">
        <h3 style="margin-top:0">Contact persons for this order</h3>
        <p class="muted" style="margin-top:-.5rem">Who we can call about this shipment (e.g. at the warehouse and in the office).</p>
        <div id="contacts"></div>
        <button type="button" class="btn secondary" onclick="addContact()">+ Add contact person</button>
    </div>

    <div class="card">
        <h3 style="margin-top:0">3. Drop-off destination(s)</h3>
        <div id="destinations"></div>
        <button type="button" class="btn secondary" onclick="addDestination()">+ Add another destination</button>
    </div>

    <div class="card">
        <h3 style="margin-top:0">4. Goods</h3>
        <div id="items"></div>
        <button type="button" class="btn secondary" onclick="addItem()">+ Add another item</button>
    </div>

    <div class="card">
        <h3 style="margin-top:0">5. Photos &amp; supporting documents</h3>
        <label>Photos, DO, packing list or other documents (up to 10 files: JPG / PNG / PDF / Word / Excel, 10 MB each)</label>
        <input type="file" name="photos[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv">
    </div>

    <div style="display:flex;justify-content:flex-end;gap:.75rem;margin-bottom:2rem">
        <a href="{{ route('portal.dashboard') }}" class="btn secondary">Cancel</a>
        <button class="btn" type="submit">Submit order</button>
    </div>
</form>

<template id="destination-template">
    <div class="dest-row" style="border:1px dashed var(--line);border-radius:.5rem;padding:.75rem 1rem;margin-bottom:.75rem">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <strong>Destination <span class="dest-no"></span></strong>
            <button type="button" class="btn secondary" style="padding:.25rem .6rem" onclick="this.closest('.dest-row').remove(); renumber()">Remove</button>
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
    </div>
</template>

<template id="contact-template">
    <div class="contact-row" style="display:grid;grid-template-columns:2fr 1.5fr 1.5fr auto;gap:0 .75rem;align-items:end">
        <div><label>Name</label><input data-name="name"></div>
        <div><label>Contact number</label><input data-name="phone"></div>
        <div><label>Role</label><input data-name="role" placeholder="e.g. Warehouse"></div>
        <div><button type="button" class="btn secondary" style="margin-bottom:.9rem" onclick="this.closest('.contact-row').remove(); renumber()">✕</button></div>
    </div>
</template>

<datalist id="item-categories">
    @foreach ($categories as $category)<option value="{{ $category }}">@endforeach
</datalist>

<template id="item-template">
    <div class="item-row" style="border:1px dashed var(--line);border-radius:.5rem;padding:.75rem 1rem .1rem;margin-bottom:.75rem">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0 .75rem;align-items:end">
            <div style="grid-column:span 2"><label>Item description *</label><input data-name="item_name" required></div>
            <div><label>Category</label><input data-name="category" list="item-categories"></div>
            <div><label>Qty *</label><input type="number" step="0.001" min="0.001" data-name="quantity" value="1" required></div>
            <div><label>UOM</label><input data-name="uom" value="CTN"></div>
            <div><label>Weight (kg)</label><input type="number" step="0.001" min="0" data-name="weight"></div>
            <div><label>Dimensions (L×W×H cm)</label><input data-name="dimensions" placeholder="120×100×80"></div>
            <div><label>Destination</label><select data-name="destination_index" class="dest-select"></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr auto;gap:0 .75rem;align-items:end">
            <div><label>Special handling</label><input data-name="handling" placeholder="e.g. Fragile, keep upright, forklift needed"></div>
            <div><button type="button" class="btn secondary" style="margin-bottom:.9rem" onclick="this.closest('.item-row').remove()">Remove item</button></div>
        </div>
    </div>
</template>

<script>
    const oldDestinations = @json(old('destinations', []));
    const oldItems = @json(old('items', []));
    const oldContacts = @json(old('contacts', []));
    const defaultPic = @json($customer?->pics->firstWhere('is_default', true) ?? $customer?->pics->first());

    function pickSavedPickup(sel) {
        const opt = sel.selectedOptions[0];
        if (!opt || !opt.value) return;
        document.getElementById('pickup-address').value = opt.dataset.address || '';
        document.getElementById('pickup-maps').value = opt.dataset.maps || '';
    }

    function pickSavedDestination(sel) {
        const opt = sel.selectedOptions[0];
        if (!opt || !opt.value) return;
        const row = sel.closest('.dest-row');
        const set = (name, value) => { const el = row.querySelector(`[data-name="${name}"]`); if (el && value !== undefined) el.value = value || ''; };
        set('address', opt.dataset.address); set('postcode', opt.dataset.postcode); set('city', opt.dataset.city);
        set('state', opt.dataset.state); set('maps_url', opt.dataset.maps);
        const consignee = row.querySelector('[data-name="consignee_name"]');
        if (!consignee.value) consignee.value = opt.dataset.label || '';
        refreshDestinationSelects();
    }

    function addContact(values = {}) {
        const tpl = document.getElementById('contact-template').content.cloneNode(true);
        const row = tpl.querySelector('.contact-row');
        Object.entries(values).forEach(([k, v]) => { const el = row.querySelector(`[data-name="${k}"]`); if (el) el.value = v ?? ''; });
        document.getElementById('contacts').appendChild(row);
        renumber();
    }

    function bindNames(row, group, index) {
        row.querySelectorAll('[data-name]').forEach(el => {
            el.name = `${group}[${index}][${el.dataset.name}]`;
        });
    }

    function renumber() {
        document.querySelectorAll('#destinations .dest-row').forEach((row, i) => {
            row.querySelector('.dest-no').textContent = i + 1;
            bindNames(row, 'destinations', i);
        });
        document.querySelectorAll('#items .item-row').forEach((row, i) => bindNames(row, 'items', i));
        document.querySelectorAll('#contacts .contact-row').forEach((row, i) => bindNames(row, 'contacts', i));
        refreshDestinationSelects();
    }

    function refreshDestinationSelects() {
        const names = [...document.querySelectorAll('#destinations .dest-row')].map((row, i) =>
            row.querySelector('[data-name="consignee_name"]').value || `Destination ${i + 1}`);
        document.querySelectorAll('.dest-select').forEach(sel => {
            const current = sel.value;
            sel.innerHTML = names.map((n, i) => `<option value="${i}">${n}</option>`).join('');
            if (current !== '' && current < names.length) sel.value = current;
        });
    }

    function addDestination(values = {}) {
        const tpl = document.getElementById('destination-template').content.cloneNode(true);
        const row = tpl.querySelector('.dest-row');
        Object.entries(values).forEach(([k, v]) => { const el = row.querySelector(`[data-name="${k}"]`); if (el) el.value = v ?? ''; });
        row.querySelector('[data-name="consignee_name"]').addEventListener('input', refreshDestinationSelects);
        document.getElementById('destinations').appendChild(row);
        renumber();
    }

    function addItem(values = {}) {
        const tpl = document.getElementById('item-template').content.cloneNode(true);
        const row = tpl.querySelector('.item-row');
        document.getElementById('items').appendChild(row);
        renumber();
        Object.entries(values).forEach(([k, v]) => { const el = row.querySelector(`[data-name="${k}"]`); if (el) el.value = v ?? ''; });
    }

    (oldDestinations.length ? oldDestinations : [{}]).forEach(d => addDestination(d));
    (oldItems.length ? oldItems : [{}]).forEach(i => addItem(i));
    (oldContacts.length ? oldContacts : [defaultPic ? { name: defaultPic.name, phone: defaultPic.phone } : {}]).forEach(c => addContact(c));
    const savedPickup = document.getElementById('pickup-saved');
    if (savedPickup && !document.getElementById('pickup-address').value) pickSavedPickup(savedPickup);
</script>
@endsection
