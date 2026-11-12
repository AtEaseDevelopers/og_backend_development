<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $proforma->number }}</title>
    @include('pdf.partials.common')
    <style>
        @page { margin: 26px 28px 40px; }
        body { font-size: 10.5px; line-height: 1.35; }
        /* the top as the order quotation's (client, 6 Nov 2026) */
        .lh-name { font-size: 17px; font-weight: bold; }
        .lh-small { font-size: 10px; line-height: 1.35; }
        .lh-rule { border-top: 4px solid #000; margin: 5px 0 6px; }
        .lh-meta { width: 100%; border-collapse: collapse; }
        .lh-meta td { padding: 0; vertical-align: bottom; }
        .lh-meta .k { font-weight: bold; font-size: 10px; }
        .lh-title { font-size: 19px; font-weight: bold; text-align: right; }
        .muted { color: #6b7280; }
        .meta { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        /* labels bold, values in normal text (user, 6 Nov 2026) */
        .label { width: 110px; font-weight: bold; }
        .meta table td { font-weight: normal; }
        .meta table td.label { font-weight: bold; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; table-layout: fixed; }
        /* a bordered grid (user, 30 Oct 2026) */
        table.lines th { background: #f3f4f6; text-align: left; padding: 6px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .04em; color: #374151; border: 1px solid #111827; }
        table.lines td { padding: 6px; border: 1px solid #111827; }
        .num, table.lines th.num { text-align: right; white-space: nowrap; }
        .totals { width: 45%; margin-left: 55%; margin-top: 10px; border-collapse: collapse; }
        .totals td { padding: 4px 6px; border: 1px solid #111827; }
        .totals .grand td { font-weight: bold; font-size: 12px; background: #f3f4f6; }
        .box { margin-top: 18px; border: 1px solid #d1d5db; border-radius: 4px; padding: 8px 10px; }
        .stamp { display: inline-block; padding: 2px 8px; border: 1px solid #1d4ed8; color: #1d4ed8; font-weight: bold; font-size: 9px; letter-spacing: .06em; }
    </style>
</head>
<body>
    @php
        $orderType = $quotation?->orderType();
        $paymentMethod = $quotation?->payment_method ? ucwords(str_replace('_', ' ', (string) $quotation->payment_method)) : null;
        $paidAmount = max((float) ($quotation?->paid_amount ?? 0), (float) $proforma->paid_amount);
        $paymentStatus = (float) $proforma->total_amount > 0 && $paidAmount >= (float) $proforma->total_amount ? 'PAID' : ($paidAmount > 0 ? 'PARTLY PAID' : 'UNPAID');
    @endphp
    {{-- the top as the order quotation's (client, 6 Nov 2026): letterhead, the number under its barcode, the order type
         left and the title right, paid or not with the payment method --}}
    <table class="lh-meta"><tr><td style="vertical-align:top">
        <div class="lh-name">{{ strtoupper($branch?->company_name ?: 'O&G Transport') }}</div>
        <div class="lh-small">
            @if ($branch?->company_no)(REG NO. : {{ $branch->company_no }} )<br>@endif
            @if ($branch?->address){{ strtoupper($branch->address) }}<br>@endif
            @if ($branch?->phone)Tel : {{ $branch->phone }}<br>@endif
            <table class="lh-meta"><tr>
                <td class="lh-small">@if ($branch?->email)Email : {{ $branch->email }}@endif</td>
                <td class="lh-small">@if ($branch?->sst_no)SST No. : {{ $branch->sst_no }}@endif</td>
            </tr></table>
        </div>
    </td><td class="lh-qr"><img src="{{ \App\Support\BarcodeImage::code128DataUri($proforma->number) }}" alt="Barcode {{ $proforma->number }}"><div class="lh-qr-text">{{ $proforma->number }}</div></td></tr></table>
    <div class="lh-rule"></div>
    {{-- the title at the left, no order type or payment line (client, 7 Nov 2026) --}}
    <table class="lh-meta"><tr>
        <td class="lh-title" style="text-align:left">PROFORMA INVOICE</td>
        <td style="text-align:right;vertical-align:bottom"><span class="stamp">NOT A TAX INVOICE</span></td>
    </tr></table>
    <div style="height:10px"></div>

    <table class="meta">
        <tr><th colspan="2" class="bt-heading">Billing Information</th></tr>
        <tr class="bt-body">
            <td style="width:58%;padding-right:12px">
                    @php $customer = $proforma->customer; @endphp
                    {{-- the standard billing block (client, 7 Nov 2026) --}}
                    @include('pdf.partials.bill-to', ['heading' => false, 'code' => $customer?->code, 'name' => $customer?->company_name, 'reg_no' => $customer?->brn, 'address' => $quotation?->customer_address ?: $customer?->address, 'pic' => $quotation?->attention ?: $customer?->attention, 'phone' => $quotation?->customer_pic_phone ?: $customer?->phone, 'email' => $customer?->email])
                @if ($quotation?->customer_do_number || $quotation?->consignor_name || $quotation?->consignee_name || $quotation?->toLocation?->name)
                <table class="bt">
                    @if ($quotation?->customer_do_number)<tr><td class="bt-k">Customer DO</td><td class="bt-v">{{ $quotation->customer_do_number }}</td></tr>@endif
                    @if ($quotation?->consignor_name)<tr><td class="bt-k">Consignor</td><td class="bt-v">{{ $quotation->consignor_name }}@if ($quotation->fromLocation?->name) <span class="muted">· {{ $quotation->fromLocation->name }}</span>@endif</td></tr>@endif
                    @if ($quotation?->consignee_name || $quotation?->toLocation?->name)<tr><td class="bt-k">Consignee</td><td class="bt-v">{{ $quotation?->consignee_name ?: '—' }}@if ($quotation?->toLocation?->name) <span class="muted">· {{ $quotation->toLocation->name }}</span>@endif</td></tr>@endif
                </table>
                @endif
            </td>
            <td>
                <table>
                    <tr><td class="dk">Date</td><td class="dv">{{ ($proforma->issued_at ?? $proforma->created_at)?->format('d/m/Y') }}</td></tr>
                    @if ($quotation)
                        <tr><td class="dk">Order no.</td><td class="dv">{{ $quotation->orderNumber() }}</td></tr>
                        {{-- no Record no., Payment term or CSN rows (client, 6 Nov 2026) --}}
                        <tr><td class="dk">Salesperson</td><td class="dv">{{ $quotation->salesperson?->name ?? '—' }}@if ($quotation->salesperson?->phone) <span class="muted">· {{ $quotation->salesperson->phone }}</span>@endif</td></tr>
                        @if ($quotation->expected_delivery_date)<tr><td class="dk">Expected delivery</td><td class="dv">{{ $quotation->expected_delivery_date }}</td></tr>@endif
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- the items in the order form's format (client, 5 Nov 2026); Paid and Balance due only once a payment was recorded --}}
    @php $sst = (float) ($proforma->quotation?->tax_amount ?? 0); $sstRate = rtrim(rtrim(number_format(\App\Support\QuotationMatrix::sstRate(), 2, '.', ''), '0'), '.'); @endphp
    <table class="items">
        <thead>
            <tr>
                <th class="c" style="width:5%">No.</th>
                <th>Item</th>
                <th class="num" style="width:9%">Qty</th>
                <th class="c" style="width:12%">UOM</th>
                <th class="num" style="width:15%">Unit price (RM)</th>
                <th class="num" style="width:15%">Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $i => $line)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $line['item'] }}@if ($line['measure'] ?? null)<div class="muted">{{ $line['measure'] }}</div>@endif@if ($line['handling'] ?? null)<div class="muted">{{ $line['handling'] }}</div>@endif</td>
                    <td class="num">{{ $line['qty'] }}</td>
                    <td class="c">{{ $line['uom'] ?? '—' }}</td>
                    <td class="num">{{ $line['unit'] }}</td>
                    <td class="num">{{ $line['total'] }}</td>
                </tr>
            @empty
                <tr><td class="c">1</td><td>Transport charges</td><td class="num">—</td><td class="c">—</td><td class="num">—</td><td class="num">{{ number_format((float) $proforma->total_amount - $sst, 2) }}</td></tr>
            @endforelse
            {{-- Subtotal, SST, then Total (client, 6 Nov 2026): no Paid / Balance due lines --}}
            <tr class="tot"><td colspan="3" class="blank"></td><td colspan="2" class="num lbl">Sub Total (Excluding Tax)</td><td class="num amt">{{ number_format((float) $proforma->total_amount - $sst, 2) }}</td></tr>
            <tr class="tot"><td colspan="3" class="blank"></td><td colspan="2" class="num lbl">SST {{ $sstRate }}%</td><td class="num amt">{{ number_format($sst, 2) }}</td></tr>
            <tr class="tot total"><td colspan="3" class="blank"></td><td colspan="2" class="num lbl">Total</td><td class="num amt">RM {{ number_format((float) $proforma->total_amount, 2) }}</td></tr>
        </tbody>
    </table>

    {{-- the client's PI instructions to follow (6 Nov 2026): the payment instructions for now, the rest left blank --}}
    <div class="box">
        <strong>Payment instructions</strong><br>
        {!! nl2br(e($proforma->payment_instructions ?: 'Please make payment to the account(s) provided by our counter and upload your payment proof in the Customer Portal.')) !!}
    </div>

</body>
</html>
