<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $number }}</title>
    @include('pdf.partials.common')
    <style>
        @page { margin: 30px 30px 40px; }
        body { font-size: 11px; line-height: 1.4; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 14px 0 6px; border-bottom: 1px solid #000; padding-bottom: 3px; }
        .muted { color: #444; }
        .meta { width: 100%; margin-bottom: 10px; }
        .meta td { vertical-align: top; padding: 0 0 4px; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 5px 6px; vertical-align: top; }
        table.grid th { text-align: left; font-weight: bold; background: #f0f0f0; text-transform: uppercase; font-size: 10px; }
        table.grid td.c, table.grid th.c { text-align: center; white-space: nowrap; }
        table.grid td.num, table.grid th.num { text-align: right; white-space: nowrap; }
        .block { margin-bottom: 8px; }
        .block div { margin-bottom: 2px; }
    </style>
</head>
<body>
@php
    $payload = $payload ?? [];
    $customer = $enquiry->customer;
    $customerName = $payload['customer_name'] ?? $customer?->company_name ?? '—';
    $billing = $payload['customer_address'] ?? $customer?->address;
    $pic = $payload['customer_pic_name'] ?? $customer?->attention;
    $phone = $payload['customer_pic_phone'] ?? $customer?->phone;
    $items = collect($payload['items'] ?? [])->filter(fn ($item) => is_array($item) && filled($item['item_name'] ?? null))->values();
    $pickups = collect($payload['pickups'] ?? [])->filter(fn ($row) => is_array($row) && filled($row['address'] ?? null))->values();
    if ($pickups->isEmpty() && filled($enquiry->pickup_address)) {
        $pickups = collect([[
            'address' => $enquiry->pickup_address,
            'maps_url' => $enquiry->pickup_maps_url,
            'special_requirements' => $enquiry->special_requirements,
        ]]);
    }
    $destinations = collect($payload['destinations'] ?? [])->filter(fn ($row) => is_array($row) && filled($row['address'] ?? null))->values();
    $contacts = collect($payload['contacts'] ?? [])->filter(fn ($row) => is_array($row) && (filled($row['name'] ?? null) || filled($row['phone'] ?? null)))->values();
    $status = $enquiry->status instanceof \App\Enums\PortalEnquiryStatus ? $enquiry->status->label() : ucfirst((string) $enquiry->status);
@endphp

    <table class="meta">
        <tr>
            <td>
                <h1>O&amp;G Transport</h1>
                <div class="muted">Submitted order</div>
            </td>
            <td style="text-align:right">
                <div><strong>{{ $number }}</strong></div>
                <div>Submitted {{ $enquiry->created_at?->format('d/m/Y H:i') }}</div>
                <div>Status: {{ $status }}</div>
                @if ($enquiry->preferred_delivery_date)
                    <div>Preferred delivery: {{ $enquiry->preferred_delivery_date->format('d/m/Y') }}</div>
                @endif
            </td>
        </tr>
    </table>

    <h2>Customer</h2>
    <div class="block">
        {{-- the billing customer block (client, 5 Nov 2026) --}}
        @include('pdf.partials.bill-to', ['heading' => false, 'code' => $customer?->code, 'name' => $customerName, 'reg_no' => $customer?->brn, 'address' => $billing, 'pic' => $pic, 'phone' => $phone, 'email' => $customer?->email])
    </div>

    <h2>Goods</h2>
    <table class="grid">
        <thead>
            {{-- the order form's columns (client, 5 Nov 2026) --}}
            <tr>
                <th class="c" style="width:5%">No.</th>
                <th>Item</th>
                <th class="num" style="width:9%">Qty</th>
                <th class="c" style="width:12%">UOM</th>
                <th style="width:34%">Details</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($items as $item)
            @php $unit = \App\Support\QuantityLabel::unit($item['uom'] ?? null) ?? ((($item['kind'] ?? '') === 'measurement' && filled($item['sub_category'] ?? null)) ? ucfirst(strtolower($item['sub_category'])) : ((($item['kind'] ?? '') === 'lorry') ? 'Trip' : 'Unit')); $kindLabel = $kinds[$item['kind'] ?? ''] ?? null; @endphp
            <tr>
                <td class="c">{{ $loop->iteration }}</td>
                <td>{{ $item['item_name'] }}@if ($kindLabel)<div class="muted">{{ $kindLabel }}</div>@endif</td>
                <td class="num">{{ rtrim(rtrim(number_format((float) ($item['quantity'] ?? 1), 3, '.', ''), '0'), '.') }}</td>
                <td class="c">{{ $unit }}</td>
                <td>
                    {{ collect([
                        $item['sub_category'] ?? null,
                        $item['dimensions'] ?? null,
                        filled($item['weight'] ?? null) ? $item['weight'].' kg' : null,
                        $item['handling'] ?? null,
                    ])->filter()->implode(' · ') ?: '—' }}
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No goods listed.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>Pickup</h2>
    @forelse ($pickups as $i => $pickup)
        <div class="block">
            <div><strong>Pickup {{ $i + 1 }}</strong></div>
            <div>{{ $pickup['address'] }}</div>
            @if (! empty($pickup['maps_url']))<div class="muted">{{ $pickup['maps_url'] }}</div>@endif
            @if (! empty($pickup['special_requirements']))<div>Special requirements: {{ $pickup['special_requirements'] }}</div>@endif
        </div>
    @empty
        <div class="muted">No pickup address.</div>
    @endforelse

    <h2>Drop-off</h2>
    @forelse ($destinations as $i => $destination)
        @php $dropLabel = $dropOffTypes[$destination['drop_off_type'] ?? ''] ?? ($destination['drop_off_type'] ?? null); @endphp
        <div class="block">
            <div><strong>Destination {{ $i + 1 }}@if (! empty($destination['consignee_name'])) — {{ $destination['consignee_name'] }}@endif</strong></div>
            @if ($dropLabel)<div class="muted">{{ $dropLabel }}</div>@endif
            <div>{{ collect([$destination['address'] ?? null, $destination['postcode'] ?? null, $destination['city'] ?? null, $destination['state'] ?? null])->filter()->implode(', ') }}</div>
            @if (! empty($destination['consignee_phone']))<div>Phone: {{ $destination['consignee_phone'] }}</div>@endif
            @if (! empty($destination['special_requirements']))<div>Special requirements: {{ $destination['special_requirements'] }}</div>@endif
        </div>
    @empty
        <div class="muted">No drop-off destination.</div>
    @endforelse

    @if ($contacts->isNotEmpty())
        <h2>Contact persons</h2>
        @foreach ($contacts as $contact)
            <div class="block">
                {{ collect([$contact['name'] ?? null, $contact['phone'] ?? null, $contact['role'] ?? null])->filter()->implode(' · ') }}
            </div>
        @endforeach
    @endif
</body>
</html>
