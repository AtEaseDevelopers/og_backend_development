@extends('layouts.portal')

@section('title', 'Orders')

@section('content')
<div class="page-head">
    <h1>Orders &amp; quotations</h1>
    <a class="btn" href="{{ route('portal.enquiry.create') }}">+ New order</a>
</div>

<form class="card filters" method="GET">
    <div><label>Search</label><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Quotation / DO number"></div>
    <div><label>Stage</label>
        <select name="status">
            <option value="">All</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div><label>From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div><label>To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div class="actions" style="margin-bottom:.9rem"><a href="{{ route('portal.orders') }}">Reset</a><button type="submit">Filter</button></div>
</form>

<div class="card">
    <div class="table-scroll">
    <table>
        <thead><tr><th>Number</th><th>Date</th><th>Your DO</th><th>Type</th><th>Stage</th><th>Payment</th><th>CSN</th><th style="text-align:right">Total</th><th></th></tr></thead>
        <tbody>
        @forelse ($quotations as $quotation)
            <tr>
                <td>{{ $quotation->number }} <span class="muted">v{{ $quotation->version }}</span></td>
                <td>{{ $quotation->created_at->format('d/m/Y') }}</td>
                <td>{{ $quotation->customer_do_number ?: '—' }}</td>
                <td>{{ $quotation->orderType()?->getLabel() ?? '—' }}</td>
                <td>{{ $quotation->status->label() }}</td>
                <td>{!! \App\Support\PortalBadge::html(\App\Support\PortalPaymentStatus::for($quotation)) !!}</td>
                <td>
                    @foreach ($quotation->consignmentNotes as $csn)
                        <a href="{{ route('portal.csns.show', $csn) }}">{{ $csn->number }}</a><br>
                    @endforeach
                </td>
                <td style="text-align:right">RM {{ number_format($quotation->total_amount, 2) }}</td>
                <td><a href="{{ route('portal.quotations.show', $quotation) }}">View</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">No orders match.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    {{ $quotations->links('pagination::simple-default') }}
</div>
@endsection
