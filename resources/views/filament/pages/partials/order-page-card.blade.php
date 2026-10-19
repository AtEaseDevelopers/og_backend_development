{{-- An order of the Create order page that is not open: its colour, customer and figures; Edit opens it (the open one folds into its own card). --}}
@php
    $s = $order['summary'] ?? [];
@endphp
<div class="ow-order-card" style="--ow-order-color: {{ $order['color'] }}; --ow-order-tint: {{ $order['tint'] }}" wire:key="ow-order-card-{{ $order['index'] }}">
    <div class="ow-order-card-main">
        <span class="ow-order-dot" aria-hidden="true"></span>
        <strong>Order {{ $order['number'] }}</strong>
        <span>{{ $s['customer'] ?? 'No customer yet' }}</span>
        <span class="ow-note">· {{ $s['records'] ?? 0 }} consignor &amp; consignee{{ ($s['records'] ?? 0) === 1 ? '' : 's' }}@if (($s['terms'] ?? []) !== []) · {{ implode(', ', $s['terms']) }}@endif</span>
        @if ($s['pay_now'] ?? false)<span class="ow-pill ow-pill-done">Payment recorded on save</span>@endif
    </div>
    <div class="ow-order-card-side">
        <strong>{{ ($s['priced'] ?? false) && ($s['total'] ?? 0) > 0 ? 'RM '.number_format((float) $s['total'], 2) : 'Not priced' }}</strong>
        <button type="button" wire:click="openOrder({{ $order['index'] }})" class="ow-btn ow-btn-sm">Edit</button>
        <button type="button" wire:click="removeOrder({{ $order['index'] }})" wire:confirm="Remove order {{ $order['number'] }} from this page?" class="ow-btn ow-btn-sm ow-btn-danger">Remove</button>
    </div>
</div>
