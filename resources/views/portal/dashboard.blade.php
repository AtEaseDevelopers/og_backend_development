@extends('layouts.portal')

@section('title', 'Dashboard')

@section('content')
<h1>Your quotations</h1>
<div class="card">
    <table>
        <thead>
        <tr>
            <th>Number</th>
            <th>Branch</th>
            <th>Status</th>
            <th>Total</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse($quotations as $quotation)
            <tr>
                <td>{{ $quotation->number }}</td>
                <td>{{ $quotation->branch?->name }}</td>
                <td>{{ $quotation->status->label() }}</td>
                <td>RM {{ number_format($quotation->total_amount, 2) }}</td>
                <td><a href="{{ route('portal.quotations.show', $quotation) }}">View</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No quotations yet. Submit an enquiry to get started.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
