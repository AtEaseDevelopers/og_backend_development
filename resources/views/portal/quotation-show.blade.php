@extends('layouts.portal')

@section('title', $quotation->number)

@section('content')
<h1>{{ $quotation->number }}</h1>
<p class="muted">{{ $quotation->status->label() }} · RM {{ number_format($quotation->total_amount, 2) }}</p>

<div class="card">
    <h3>Destinations</h3>
    @foreach($quotation->destinations as $destination)
        <p><strong>{{ $destination->consignee_name }}</strong><br>{{ $destination->address }}</p>
    @endforeach
</div>

<div class="card">
    <h3>Items</h3>
    <table>
        <thead><tr><th>Item</th><th>Qty</th><th>UOM</th><th>Total</th></tr></thead>
        <tbody>
        @foreach($quotation->lines as $line)
            <tr>
                <td>{{ $line->item_name }}</td>
                <td>{{ $line->quantity }}</td>
                <td>{{ $line->uom }}</td>
                <td>RM {{ number_format($line->line_total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@if(in_array($quotation->status, [\App\Enums\QuotationStatus::Sent, \App\Enums\QuotationStatus::Draft, \App\Enums\QuotationStatus::Accepted], true))
<div class="card" style="display:flex; gap:1rem; flex-wrap:wrap">
    <form method="POST" action="{{ route('portal.quotations.confirm', $quotation) }}">
        @csrf
        <button class="btn" type="submit">Confirm quotation</button>
    </form>
    <form method="POST" action="{{ route('portal.quotations.reject', $quotation) }}">
        @csrf
        <input name="rejection_reason" placeholder="Rejection reason" style="width:220px;display:inline-block;margin:0">
        <button class="btn secondary" type="submit">Reject</button>
    </form>
</div>
@endif

<div class="card">
    <h3>Request amendment</h3>
    <form method="POST" action="{{ route('portal.quotations.amend', $quotation) }}">
        @csrf
        <textarea name="remarks" rows="3" required placeholder="Describe the changes you need"></textarea>
        <button class="btn secondary" type="submit">Submit amendment request</button>
    </form>
</div>
@endsection
