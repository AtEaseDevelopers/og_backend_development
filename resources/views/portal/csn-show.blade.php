@extends('layouts.portal')

@section('title', $csn->number)

@section('content')
<div class="page-head">
    <div>
        <h1 style="margin-bottom:.25rem">{{ $csn->number }}</h1>
        <p class="muted" style="margin:0">{{ $csn->status?->getLabel() }}
            @if ($csn->quotation) · Order <a href="{{ route('portal.quotations.show', $csn->quotation_id) }}">{{ $csn->quotation->number }}</a>@endif
            @if ($csn->customer_do_number) · Your DO {{ $csn->customer_do_number }}@endif
        </p>
    </div>
    <a class="btn secondary" href="{{ route('portal.csns.index') }}">← Back</a>
</div>

@include('portal.partials.timeline', ['timeline' => $timeline])

<div class="card">
    <h3>Consignment</h3>
    <div class="grid-info">
        <div><span class="muted">From</span><br>{{ $csn->consignor_name ?: ($csn->sourceBranch?->name ?? '—') }}<br><span class="muted">{{ $csn->consignor_address }}</span></div>
        <div><span class="muted">Consignee</span><br>{{ $csn->consignee_name ?: '—' }}@if ($csn->consignee_phone)<br><span class="muted">{{ $csn->consignee_phone }}</span>@endif</div>
        <div><span class="muted">Deliver to</span><br>{{ collect([$csn->delivery_address, $csn->delivery_postcode, $csn->delivery_city, $csn->delivery_state])->filter()->implode(', ') ?: '—' }}</div>
        <div><span class="muted">Payment term</span><br>{{ $csn->order_type?->getLabel() ?? '—' }}</div>
        @if ($csn->invoice)
            <div><span class="muted">Invoice</span><br><a href="{{ route('portal.documents.invoice', $csn->invoice) }}">{{ $csn->invoice->number }}</a></div>
        @endif
    </div>
    @if ($csn->lines->isNotEmpty())
        <div class="table-scroll" style="margin-top:1rem">
        <table>
            <thead><tr><th>Item</th><th>Qty</th><th>UOM</th></tr></thead>
            <tbody>
            @foreach ($csn->lines as $line)
                <tr><td>{{ $line->item_name }}</td><td>{{ rtrim(rtrim(number_format((float) $line->quantity, 3, '.', ''), '0'), '.') }}</td><td>{{ $line->uom }}</td></tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

<h3 style="margin:1.5rem 0 .75rem">Delivery orders ({{ count($deliveryOrders) }})</h3>
@forelse ($deliveryOrders as $do)
    @include('portal.partials.delivery-order', ['do' => $do, 'showTracking' => true])
@empty
    <div class="card muted">This consignment has not been assigned to a lorry yet.</div>
@endforelse
@endsection
