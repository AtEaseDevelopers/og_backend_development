@extends('layouts.portal')

@section('title', 'Online payment')

@section('content')
<div class="card" style="max-width:480px;margin:2rem auto;text-align:center">
    <div class="badge" style="background:#fef3c7;color:#92400e">Sandbox — no money is charged</div>
    <h1 style="margin-top:1rem">{{ $payment->gateway }}</h1>
    <p class="muted">Merchant: O&amp;G Transport</p>
    <div class="grid-info" style="text-align:left;margin:1rem 0">
        <div><span class="muted">Order</span><br><strong>{{ $payment->quotation?->number }}</strong></div>
        <div><span class="muted">Reference</span><br><strong>{{ $payment->reference }}</strong></div>
        <div><span class="muted">Amount</span><br><strong>RM {{ number_format((float) $payment->amount, 2) }}</strong></div>
    </div>

    @if ($payment->status !== \App\Domains\Billing\Models\OnlinePayment::STATUS_INITIATED)
        <p class="flash warn">This payment is already {{ strtolower($payment->statusLabel()) }}.</p>
        <a class="btn" href="{{ route('portal.quotations.show', $payment->quotation_id) }}">Back to order</a>
    @else
        <form method="POST" action="{{ route('portal.online-payments.result', $payment->reference) }}" class="actions" style="justify-content:center">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <button type="submit" name="result" value="cancelled" class="secondary">Cancel</button>
            <button type="submit" name="result" value="failed" class="danger">Simulate decline</button>
            <button type="submit" name="result" value="paid">Pay RM {{ number_format((float) $payment->amount, 2) }}</button>
        </form>
    @endif
</div>
@endsection
