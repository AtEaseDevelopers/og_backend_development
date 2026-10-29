@extends('layouts.portal')

@section('title', 'Dashboard')

@section('content')
<h1>Welcome, {{ auth()->user()->name }}</h1>

<div class="card">
    <h3 style="margin-top:0">Your submitted orders</h3>
    <p class="muted">O&amp;G reviews each order and contacts you with the quotation.</p>
    <div class="table-scroll">
    <table>
        <thead><tr><th>Reference</th><th>Submitted</th><th>Preferred delivery</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($enquiries as $enquiry)
            <tr>
                <td>{{ $enquiry->order_number ?: $enquiry->reference_no }}</td>
                <td>{{ $enquiry->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $enquiry->preferred_delivery_date?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $enquiry->status instanceof \App\Enums\PortalEnquiryStatus ? $enquiry->status->label() : ucfirst((string) $enquiry->status) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No orders yet. Click "+ New order" to send your first one.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
