@extends('layouts.portal')

@section('title', 'Deliveries')

@section('content')
<h1>Consignment notes &amp; deliveries</h1>

<form class="card filters" method="GET">
    <div><label>Search</label><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="CSN / DO / consignee"></div>
    <div><label>Status</label>
        <select name="status">
            <option value="">All</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div><label>From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div><label>To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div class="actions" style="margin-bottom:.9rem"><a href="{{ route('portal.csns.index') }}">Reset</a><button type="submit">Filter</button></div>
</form>

<div class="card">
    <div class="table-scroll">
    <table>
        <thead><tr><th>Consignment note</th><th>Date</th><th>Order</th><th>Consignee</th><th>CSN status</th><th>Delivery orders</th><th></th></tr></thead>
        <tbody>
        @forelse ($csns as $csn)
            <tr>
                <td><strong>{{ $csn->number }}</strong>@if ($csn->customer_do_number)<br><span class="muted">DO {{ $csn->customer_do_number }}</span>@endif</td>
                <td>{{ $csn->created_at->format('d/m/Y') }}</td>
                <td>@if ($csn->quotation)<a href="{{ route('portal.quotations.show', $csn->quotation_id) }}">{{ $csn->quotation->number }}</a>@else — @endif</td>
                <td>{{ $csn->consignee_name ?: '—' }}<br><span class="muted">{{ $csn->delivery_city }}</span></td>
                <td>{{ $csn->status?->getLabel() }}</td>
                <td>
                    @forelse ($csn->deliveryOrders as $do)
                        <div style="white-space:nowrap">{{ $do->number }} · <span class="muted">{{ $do->status?->getLabel() }}</span></div>
                    @empty
                        <span class="muted">Not assigned yet</span>
                    @endforelse
                </td>
                <td><a href="{{ route('portal.csns.show', $csn) }}">Track</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No consignment notes match.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    {{ $csns->links('pagination::simple-default') }}
</div>
@endsection
