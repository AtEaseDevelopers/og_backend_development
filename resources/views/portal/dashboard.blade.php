@extends('layouts.portal')

@section('title', 'Dashboard')

@section('content')
<div class="page-head">
    <h1>Welcome, {{ auth()->user()->name }}</h1>
    <a class="btn" href="{{ route('portal.enquiry.create') }}">+ New order</a>
</div>

@if (! config('og.portal_full_menu'))
<div class="card">
    <h3 style="margin-top:0">Your submitted orders</h3>
    <p class="muted">O&amp;G reviews each order and contacts you with the quotation.</p>
    <div class="table-scroll">
    <table>
        <thead><tr><th>Reference</th><th>Submitted</th><th>Pickup date</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($enquiries as $enquiry)
            <tr>
                <td>{{ $enquiry->order_number ?: $enquiry->reference_no }}</td>
                <td>{{ $enquiry->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ ($enquiry->pickupDate() ?? $enquiry->preferred_delivery_date)?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $enquiry->status instanceof \App\Enums\PortalEnquiryStatus ? $enquiry->status->label() : ucfirst((string) $enquiry->status) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No orders yet. Click "+ New order" to send your first one.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@else

<div class="stats">
    <a class="stat" href="{{ route('portal.orders', ['status' => 'sent']) }}" style="text-decoration:none;color:inherit"><span class="muted">Quotations awaiting you</span><strong>{{ $counts['awaiting_you'] }}</strong></a>
    <a class="stat" href="{{ route('portal.csns.index', ['status' => 'in_transit']) }}" style="text-decoration:none;color:inherit"><span class="muted">Deliveries on the way</span><strong>{{ $counts['in_transit'] }}</strong></a>
    <div class="stat"><span class="muted">Delivered (last 30 days)</span><strong>{{ $counts['delivered_30d'] }}</strong></div>
    <a class="stat" href="{{ route('portal.statement') }}" style="text-decoration:none;color:inherit"><span class="muted">Outstanding balance</span><strong>RM {{ number_format($counts['outstanding'], 2) }}</strong></a>
</div>

<div class="card">
    <div class="page-head"><h3 style="margin:0">Recent orders</h3><a href="{{ route('portal.orders') }}">All orders →</a></div>
    <div class="table-scroll">
    <table>
        <thead><tr><th>Number</th><th>Date</th><th>Type</th><th>Stage</th><th>Payment</th><th style="text-align:right">Total</th><th></th></tr></thead>
        <tbody>
        @forelse ($quotations as $quotation)
            <tr>
                <td>{{ $quotation->number }} <span class="muted">v{{ $quotation->version }}</span></td>
                <td>{{ $quotation->created_at->format('d/m/Y') }}</td>
                <td>{{ $quotation->orderType()?->getLabel() ?? '—' }}</td>
                <td>{{ $quotation->status->label() }}</td>
                <td>{!! \App\Support\PortalBadge::html(\App\Support\PortalPaymentStatus::for($quotation)) !!}</td>
                <td style="text-align:right">RM {{ number_format($quotation->total_amount, 2) }}</td>
                <td><a href="{{ route('portal.quotations.show', $quotation) }}">View</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No orders yet. Submit an order form to get started.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="card">
    <div class="page-head"><h3 style="margin:0">Recent deliveries</h3><a href="{{ route('portal.csns.index') }}">All deliveries →</a></div>
    <div class="table-scroll">
    <table>
        <thead><tr><th>Consignment note</th><th>Consignee</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($csns as $csn)
            <tr>
                <td>{{ $csn->number }}</td>
                <td>{{ $csn->consignee_name ?: '—' }}</td>
                <td>{{ $csn->status?->getLabel() }}</td>
                <td><a href="{{ route('portal.csns.show', $csn) }}">Track</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No deliveries yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

@if (($enquiries ?? collect())->isNotEmpty())
<div class="card">
    <div class="page-head"><h3 style="margin:0">Submitted orders awaiting quotation</h3></div>
    <div class="table-scroll">
    <table>
        <thead><tr><th>Reference</th><th>Submitted</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($enquiries as $enquiry)
            <tr>
                <td><a href="{{ route('portal.enquiry.pdf', $enquiry) }}" target="_blank" rel="noopener">{{ $enquiry->order_number ?: $enquiry->reference_no }}</a></td>
                <td>{{ $enquiry->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $enquiry->status instanceof \App\Enums\PortalEnquiryStatus ? $enquiry->status->label() : ucfirst((string) $enquiry->status) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif
@endif
@endsection
