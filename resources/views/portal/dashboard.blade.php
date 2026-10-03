@extends('layouts.portal')

@section('title', 'Dashboard')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
    <h1>Your orders</h1>
    <a class="btn" href="{{ route('portal.enquiry.create') }}" style="color:white">+ New order</a>
</div>
<div class="card">
    <table>
        <thead>
        <tr>
            <th>Number</th>
            <th>Branch</th>
            <th>Type</th>
            <th>Stage</th>
            <th>Billing</th>
            <th style="text-align:right">Total</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse($quotations as $quotation)
            <tr>
                <td>{{ $quotation->number }} <span class="muted">v{{ $quotation->version }}</span></td>
                <td>{{ $quotation->branch?->name }}</td>
                <td>{{ $quotation->orderType()?->getLabel() ?? '—' }}</td>
                <td>{{ $quotation->status->label() }}</td>
                <td class="muted">{{ $quotation->billingStatus()->getLabel() }}</td>
                <td style="text-align:right">RM {{ number_format($quotation->total_amount, 2) }}</td>
                <td><a href="{{ route('portal.quotations.show', $quotation) }}">View</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No orders yet. Submit an order form to get started.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
