@extends('layouts.portal')

@section('title', 'Company profile')

@php
    use App\Domains\MasterData\Models\CustomerProfileChange;
    $pickup = $customer->addresses->where('type', 'pickup');
    $delivery = $customer->addresses->where('type', '!=', 'pickup');
@endphp

@section('content')
<h1>Company profile</h1>

@if ($errors->any())
    <div class="card errors">@foreach ($errors->all() as $error)<div class="error" style="margin:0">{{ $error }}</div>@endforeach</div>
@endif

@if ($pending->isNotEmpty())
    <div class="flash warn">
        <strong>Waiting for O&amp;G verification:</strong>
        @foreach ($pending as $change)<div>{{ $change->summary() }} <span class="muted">({{ $change->created_at->format('d/m/Y H:i') }})</span></div>@endforeach
    </div>
@endif

<div class="card">
    <h3>Company details</h3>
    <p class="muted" style="margin-top:-.5rem">Changes to the company name, BRN, TIN or SST number apply after O&amp;G verifies them. Address, phone and email change at once.</p>
    <form method="POST" action="{{ route('portal.profile.company') }}">
        @csrf
        <div class="grid-form">
            <div><label>Company name *</label><input name="company_name" value="{{ old('company_name', $customer->company_name) }}" required></div>
            <div><label>BRN *</label><input name="brn" value="{{ old('brn', $customer->brn) }}" required></div>
            <div><label>TIN</label><input name="tin" value="{{ old('tin', $customer->tin) }}"></div>
            <div><label>SST registration no.</label><input name="sst_registration_no" value="{{ old('sst_registration_no', $customer->sst_registration_no) }}"></div>
            <div><label>Contact number</label><input name="phone" value="{{ old('phone', $customer->phone) }}"></div>
            <div><label>Company email</label><input type="email" name="email" value="{{ old('email', $customer->email) }}"></div>
        </div>
        <label>Billing address</label>
        <textarea name="address" rows="2">{{ old('address', $customer->address) }}</textarea>
        <div class="actions"><button type="submit">Save company details</button></div>
    </form>
</div>

<div class="card">
    <h3>E-invoice details (LHDN buyer)</h3>
    <p class="muted" style="margin-top:-.5rem">Used on your e-invoices. Changes apply after O&amp;G verifies them.</p>
    <form method="POST" action="{{ route('portal.profile.einvoice') }}">
        @csrf
        <div class="grid-form">
            <div><label>Buyer name *</label><input name="einvoice_buyer_name" value="{{ old('einvoice_buyer_name', $customer->einvoice_buyer_name ?: $customer->company_name) }}" required></div>
            <div><label>Buyer TIN *</label><input name="einvoice_tin" value="{{ old('einvoice_tin', $customer->einvoice_tin ?: $customer->tin) }}" required></div>
            <div><label>ID type *</label>
                <select name="einvoice_id_type" required>
                    @foreach ($idTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('einvoice_id_type', $customer->einvoice_id_type ?: 'BRN') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div><label>ID number *</label><input name="einvoice_id_value" value="{{ old('einvoice_id_value', $customer->einvoice_id_value ?: $customer->brn) }}" required></div>
        </div>
        <label>Buyer address</label>
        <textarea name="einvoice_address" rows="2">{{ old('einvoice_address', $customer->einvoice_address ?: $customer->address) }}</textarea>
        <div class="actions"><button type="submit">Save e-invoice details</button></div>
    </form>
</div>

<div class="card">
    <h3>Persons in charge</h3>
    <div class="table-scroll">
    <table>
        <thead><tr><th>Name</th><th>Contact number</th><th>Email</th><th>Default</th><th></th></tr></thead>
        <tbody>
        @foreach ($customer->pics as $pic)
            @php $f = 'pic-'.$pic->id; @endphp
            <tr>
                <td><input form="{{ $f }}" name="name" value="{{ $pic->name }}" required style="margin:0"></td>
                <td><input form="{{ $f }}" name="phone" value="{{ $pic->phone }}" style="margin:0"></td>
                <td><input form="{{ $f }}" type="email" name="email" value="{{ $pic->email }}" style="margin:0"></td>
                <td><input form="{{ $f }}" type="checkbox" name="is_default" value="1" @checked($pic->is_default)></td>
                <td style="white-space:nowrap;text-align:right">
                    <button type="submit" class="small danger" form="del-{{ $f }}" onclick="return confirm('Remove this person in charge?')">Remove</button>
                    <button type="submit" class="small" form="{{ $f }}">Save</button>
                </td>
            </tr>
        @endforeach
        <tr>
            <td><input form="pic-new" name="name" placeholder="New PIC name" required style="margin:0"></td>
            <td><input form="pic-new" name="phone" placeholder="Contact number" style="margin:0"></td>
            <td><input form="pic-new" type="email" name="email" placeholder="Email" style="margin:0"></td>
            <td><input form="pic-new" type="checkbox" name="is_default" value="1"></td>
            <td style="text-align:right"><button type="submit" class="small" form="pic-new">Add</button></td>
        </tr>
        </tbody>
    </table>
    </div>
    <form id="pic-new" method="POST" action="{{ route('portal.profile.pics.store') }}">@csrf</form>
    @foreach ($customer->pics as $pic)
        <form id="pic-{{ $pic->id }}" method="POST" action="{{ route('portal.profile.pics.update', $pic) }}">@csrf @method('PUT')</form>
        <form id="del-pic-{{ $pic->id }}" method="POST" action="{{ route('portal.profile.pics.destroy', $pic) }}">@csrf @method('DELETE')</form>
    @endforeach
</div>

@foreach (['pickup' => ['Pickup addresses', $pickup], 'delivery' => ['Delivery addresses', $delivery]] as $type => [$title, $addresses])
    <div class="card">
        <h3>{{ $title }}</h3>
        @foreach ($addresses as $address)
            <details style="border:1px solid var(--line);border-radius:.5rem;padding:.6rem .8rem;margin-bottom:.5rem">
                <summary style="cursor:pointer"><strong>{{ $address->label ?: 'Address' }}</strong>@if ($address->is_default) <span class="muted">(default)</span>@endif — <span class="muted">{{ \Illuminate\Support\Str::limit($address->address, 80) }}</span></summary>
                <form method="POST" action="{{ route('portal.profile.addresses.update', $address) }}" style="margin-top:.75rem">
                    @csrf @method('PUT')
                    @include('portal.partials.address-fields', ['address' => $address, 'type' => $type])
                    <div class="actions">
                        <button type="submit" class="danger small" form="del-address-{{ $address->id }}" onclick="return confirm('Remove this address?')">Remove</button>
                        <button type="submit" class="small">Save</button>
                    </div>
                </form>
                <form id="del-address-{{ $address->id }}" method="POST" action="{{ route('portal.profile.addresses.destroy', $address) }}" style="display:none">@csrf @method('DELETE')</form>
            </details>
        @endforeach
        <details @if ($addresses->isEmpty()) open @endif style="border:1px dashed var(--line);border-radius:.5rem;padding:.6rem .8rem">
            <summary style="cursor:pointer;color:var(--accent);font-weight:600">+ Add {{ $type }} address</summary>
            <form method="POST" action="{{ route('portal.profile.addresses.store') }}" style="margin-top:.75rem">
                @csrf
                @include('portal.partials.address-fields', ['address' => null, 'type' => $type])
                <div class="actions"><button type="submit" class="small">Add address</button></div>
            </form>
        </details>
    </div>
@endforeach

<div class="card">
    <h3>Email notifications</h3>
    <form method="POST" action="{{ route('portal.profile.notifications') }}" class="actions" style="justify-content:space-between">
        @csrf
        <label style="display:flex;align-items:center;margin:0;color:var(--ink);font-size:.95rem">
            <input type="checkbox" name="email_notifications" value="1" @checked($customer->email_notifications)>
            Email me about quotations, payments, invoices and deliveries
        </label>
        <button type="submit" class="small">Save</button>
    </form>
    <p class="muted" style="margin:.5rem 0 0;font-size:.85rem">Account emails (email verification, password reset) are always sent.</p>
</div>

<div class="card">
    <h3>Change history</h3>
    <div class="table-scroll">
    <table>
        <thead><tr><th>When</th><th>By</th><th>Section</th><th>Change</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($history as $change)
            <tr>
                <td style="white-space:nowrap">{{ $change->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $change->user?->name ?? '—' }}</td>
                <td>{{ $change->sectionLabel() }} <span class="muted">· {{ $change->action }}</span></td>
                <td style="font-size:.85rem">{{ $change->summary() }}</td>
                <td>{!! \App\Support\PortalBadge::html(['label' => $change->statusLabel(), 'tone' => match ($change->status) {
                    CustomerProfileChange::STATUS_PENDING => 'amber',
                    CustomerProfileChange::STATUS_REJECTED => 'red',
                    CustomerProfileChange::STATUS_APPROVED => 'green',
                    default => 'stone',
                }]) !!}
                    @if ($change->review_remarks)<div class="muted" style="font-size:.8rem">{{ $change->review_remarks }}</div>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No changes yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
