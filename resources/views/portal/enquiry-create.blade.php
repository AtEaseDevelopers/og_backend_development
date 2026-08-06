@extends('layouts.portal')

@section('title', 'New Enquiry')

@section('content')
<h1>Quotation enquiry</h1>
<form method="POST" action="{{ route('portal.enquiry.store') }}" class="card">
    @csrf
    <label>Branch</label>
    <select name="branch_id" required>
        @foreach($branches as $branch)
            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
        @endforeach
    </select>

    <label>Pickup address</label>
    <textarea name="pickup_address" rows="2" required>{{ old('pickup_address') }}</textarea>

    <label>Pickup Google Maps URL</label>
    <input name="pickup_maps_url" value="{{ old('pickup_maps_url') }}">

    <label>Preferred delivery date</label>
    <input type="date" name="preferred_delivery_date" value="{{ old('preferred_delivery_date') }}">

    <label>Special requirements</label>
    <textarea name="special_requirements" rows="2">{{ old('special_requirements') }}</textarea>

    <h3>Delivery destination</h3>
    <label>Consignee</label>
    <input name="destinations[0][consignee_name]" required>
    <label>Address</label>
    <textarea name="destinations[0][address]" rows="2" required></textarea>
    <label>Postcode</label>
    <input name="destinations[0][postcode]">
    <label>State</label>
    <input name="destinations[0][state]">

    <h3>Item</h3>
    <label>Item name</label>
    <input name="items[0][item_name]" required>
    <label>Quantity</label>
    <input type="number" step="0.001" name="items[0][quantity]" value="1" required>
    <label>UOM</label>
    <input name="items[0][uom]" value="CTN">
    <label>Weight</label>
    <input type="number" step="0.001" name="items[0][weight]">

    <button class="btn" type="submit">Submit enquiry</button>
</form>
@endsection
