@php
    $billing = match ($billing_type ?? null) {
        'cash_bill' => 'Cash Bill',
        'cod' => 'C.O.D.',
        'term' => 'Term',
        default => strtoupper((string) ($billing_type ?? '')),
    };

    $issued = $issued_at
        ? (\Illuminate\Support\Carbon::parse($issued_at)->format('d/m/Y'))
        : now()->format('d/m/Y');
@endphp

<div style="background:#fff;border:1px solid #d6d3d1;border-radius:6px;padding:18px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#1c1917;line-height:1.5;">
    <div style="display:flex;justify-content:space-between;gap:16px;border-bottom:2px solid #0abab5;padding-bottom:10px;margin-bottom:12px;">
        <div>
            <div style="font-size:16px;font-weight:700;">CONSIGNMENT NOTE</div>
            <div style="color:#57534e;margin-top:4px;">O&amp;G Transport</div>
        </div>
        <div style="text-align:right;">
            <div><strong>No:</strong> {{ $number }}</div>
            <div><strong>Date:</strong> {{ $issued }}</div>
            @if(! empty($from_location) || ! empty($to_location))
                <div><strong>Route:</strong> {{ $from_location ?: '—' }} → {{ $to_location ?: '—' }}</div>
            @endif
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px;">
        <div>
            <div style="font-weight:700;margin-bottom:6px;color:#0abab5;">CONSIGNOR</div>
            <div>{{ $consignor_name ?: ($customer_name ?: '—') }}</div>
            @if($customer_brn ?? null)
                <div style="color:#57534e;">Reg: {{ $customer_brn }}</div>
            @endif
            <div style="margin-top:6px;white-space:pre-line;">{{ $consignor_address ?: '—' }}</div>
        </div>
        <div>
            <div style="font-weight:700;margin-bottom:6px;color:#0abab5;">CONSIGNEE</div>
            <div>{{ $consignee_name ?: '—' }}</div>
            @if($consignee_pic ?? null)
                <div>Attn: {{ $consignee_pic }} @if($consignee_phone ?? null) ({{ $consignee_phone }}) @endif</div>
            @endif
            <div style="margin-top:6px;white-space:pre-line;">{{ $delivery_address ?: '—' }}</div>
            <div style="color:#57534e;">
                {{ collect([$delivery_postcode ?? null, $delivery_city ?? null, $delivery_state ?? null])->filter()->implode(', ') }}
            </div>
        </div>
    </div>

    <table style="width:100%;border-collapse:collapse;margin-bottom:12px;">
        <thead>
            <tr style="background:#f5f5f4;">
                <th style="border:1px solid #e7e5e4;padding:6px;text-align:left;">Description</th>
                <th style="border:1px solid #e7e5e4;padding:6px;">UOM</th>
                <th style="border:1px solid #e7e5e4;padding:6px;text-align:right;">Qty</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td style="border:1px solid #e7e5e4;padding:6px;">{{ $line['item_name'] ?? '—' }}</td>
                    <td style="border:1px solid #e7e5e4;padding:6px;text-align:center;">{{ $line['uom'] ?? '—' }}</td>
                    <td style="border:1px solid #e7e5e4;padding:6px;text-align:right;">{{ $line['quantity'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="border:1px solid #e7e5e4;padding:10px;color:#78716c;text-align:center;">
                        Enter quantity, unit measure, and description to preview cargo details.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="display:flex;justify-content:flex-end;gap:16px;margin-bottom:10px;flex-wrap:wrap;">
        @if((float) ($transport_charges ?? 0) > 0)
            <div>Transport: <strong>RM {{ number_format((float) $transport_charges, 2) }}</strong></div>
        @endif
        <div>Sub total: <strong>RM {{ number_format((float) $subtotal, 2) }}</strong></div>
        @if((float) ($discount ?? 0) > 0)
            <div>Discount: <strong>RM {{ number_format((float) $discount, 2) }}</strong></div>
        @endif
        @if((float) ($tax_amount ?? 0) > 0)
            <div>Tax: <strong>RM {{ number_format((float) $tax_amount, 2) }}</strong></div>
        @endif
        <div>A/C amount: <strong>RM {{ number_format((float) $total_amount, 2) }}</strong></div>
    </div>

    @if($marking ?? null)
        <div style="color:#57534e;margin-bottom:6px;"><strong>Marking:</strong> {{ $marking }}</div>
    @endif

    @if($remarks ?? null)
        <div style="border-top:1px solid #e7e5e4;padding-top:8px;color:#57534e;">
            <strong>Remark:</strong> {{ $remarks }}
        </div>
    @endif
</div>
