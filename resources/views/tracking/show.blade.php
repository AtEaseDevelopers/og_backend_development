@extends('layouts.portal')

@section('title', 'Track '.$deliveryOrder->number)

@section('content')
<h1>Shipment tracking</h1>
<div class="card">
    <p><strong>DO:</strong> {{ $deliveryOrder->number }}</p>
    <p><strong>CSN:</strong> {{ $deliveryOrder->consignmentNote?->number }}</p>
    <p><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $deliveryOrder->status->value)) }}</p>
    <p><strong>Branch:</strong> {{ $deliveryOrder->sourceBranch?->name }}</p>
    <p><strong>Driver:</strong> {{ $deliveryOrder->driver?->name ?? '—' }}</p>
    <p><strong>Address:</strong> {{ $deliveryOrder->consignmentNote?->delivery_address }}</p>
    @if($deliveryOrder->proofOfDelivery)
        <p><strong>Delivered at:</strong> {{ $deliveryOrder->proofOfDelivery->delivered_at }}</p>
        <p><strong>Recipient:</strong> {{ $deliveryOrder->proofOfDelivery->recipient_name }}</p>
    @endif
    @if($deliveryOrder->failedDelivery)
        <p><strong>Failed reason:</strong> {{ $deliveryOrder->failedDelivery->reason }}</p>
        <p>{{ $deliveryOrder->failedDelivery->remarks }}</p>
    @endif
</div>
@endsection
