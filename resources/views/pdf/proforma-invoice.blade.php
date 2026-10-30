<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $proforma->number }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111827; }
        .head { width: 100%; border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 14px; }
        .head td { vertical-align: top; }
        .company { font-size: 14px; font-weight: bold; }
        .muted { color: #6b7280; }
        .doc-title { font-size: 18px; font-weight: bold; text-align: right; letter-spacing: .04em; }
        .meta { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .label { color: #6b7280; width: 110px; }
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
    <table class="head">
        <tr>
            <td>
                <div class="company">{{ $branch?->company_name ?: 'O&G Transport' }}</div>
                @if ($branch?->company_no)<div class="muted">({{ $branch->company_no }})</div>@endif
                @if ($branch?->address)<div class="muted">{!! nl2br(e($branch->address)) !!}</div>@endif
                @if ($branch?->phone || $branch?->fax)<div class="muted">@if ($branch?->phone)Tel: {{ $branch->phone }}@endif @if ($branch?->fax) &nbsp;Fax: {{ $branch->fax }}@endif</div>@endif
                @if ($branch?->email)<div class="muted">E-mail: {{ $branch->email }}</div>@endif
                @if ($branch?->sst_no)<div class="muted">SST No.: {{ $branch->sst_no }}</div>@endif
            </td>
            <td>
                {{-- a barcode of the proforma number above the title (user, 30 Oct 2026): scanned into the system's search fields --}}
                <div style="text-align:right;margin-bottom:4px"><img src="{{ \App\Support\BarcodeImage::code128DataUri($proforma->number) }}" alt="Barcode {{ $proforma->number }}" style="width:180px;height:40px"><div style="font-size:8px;letter-spacing:.4px">{{ $proforma->number }}</div></div>
                <div class="doc-title">PROFORMA INVOICE</div>
                <div style="text-align:right;margin-top:4px"><span class="stamp">NOT A TAX INVOICE</span></div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td style="width:55%">
                <table>
                    @php $customer = $proforma->customer; @endphp
                    <tr><td class="label">Bill to</td><td><strong>{{ $customer?->company_name ?? '—' }}</strong>@if ($customer?->code) <span class="muted">({{ $customer->code }})</span>@endif</td></tr>
                    @if ($customer?->brn)<tr><td class="label">Co. Reg.</td><td>{{ $customer->brn }}</td></tr>@endif
                    @if ($quotation?->customer_address || $customer?->address)<tr><td class="label">Address</td><td>{!! nl2br(e($quotation?->customer_address ?: $customer->address)) !!}</td></tr>@endif
                    @if ($quotation?->attention || $customer?->attention || $quotation?->customer_pic_phone || $customer?->phone)
                        <tr><td class="label">Attn / Tel</td><td>{{ trim(implode(' · ', array_filter([$quotation?->attention ?: $customer?->attention, $quotation?->customer_pic_phone ?: $customer?->phone]))) }}</td></tr>
                    @endif
                    @if ($customer?->email)<tr><td class="label">E-mail</td><td>{{ $customer->email }}</td></tr>@endif
                    @if ($quotation?->customer_do_number)<tr><td class="label">Customer DO</td><td>{{ $quotation->customer_do_number }}</td></tr>@endif
                    @if ($quotation?->consignor_name)<tr><td class="label">Consignor</td><td>{{ $quotation->consignor_name }}@if ($quotation->fromLocation?->name) <span class="muted">· {{ $quotation->fromLocation->name }}</span>@endif</td></tr>@endif
                    @if ($quotation?->consignee_name || $quotation?->toLocation?->name)<tr><td class="label">Consignee</td><td>{{ $quotation?->consignee_name ?: '—' }}@if ($quotation?->toLocation?->name) <span class="muted">· {{ $quotation->toLocation->name }}</span>@endif</td></tr>@endif
                </table>
            </td>
            <td>
                <table>
                    <tr><td class="label">Proforma no.</td><td><strong>{{ $proforma->number }}</strong></td></tr>
                    <tr><td class="label">Date</td><td>{{ ($proforma->issued_at ?? $proforma->created_at)?->format('d/m/Y') }}</td></tr>
                    @if ($quotation)
                        <tr><td class="label">Order no.</td><td>{{ $quotation->orderNumber() }}</td></tr>
                        <tr><td class="label">Record no.</td><td>{{ $quotation->number }}@if (($quotation->version ?? 1) > 1) (v{{ $quotation->version }})@endif</td></tr>
                        <tr><td class="label">Payment term</td><td>{{ $quotation->orderType()?->getLabel() ?? '—' }}@if ($quotation->payment_method) <span class="muted">· {{ ucwords(str_replace('_', ' ', (string) $quotation->payment_method)) }}</span>@endif</td></tr>
                        <tr><td class="label">Salesperson</td><td>{{ $quotation->salesperson?->name ?? '—' }}@if ($quotation->salesperson?->phone) <span class="muted">· {{ $quotation->salesperson->phone }}</span>@endif</td></tr>
                        @if ($quotation->expected_delivery_date)<tr><td class="label">Expected delivery</td><td>{{ $quotation->expected_delivery_date }}</td></tr>@endif
                        @if ($proforma->consignmentNote?->number)<tr><td class="label">CSN</td><td>{{ $proforma->consignmentNote->number }}</td></tr>@endif
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:48%">Item / service</th>
                <th style="width:15%">Destination</th>
                <th class="num" style="width:10%">Qty</th>
                <th class="num" style="width:11%">Unit (RM)</th>
                <th class="num" style="width:12%">Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line['item'] }}</td>
                    <td>{{ $line['route'] ?? '—' }}</td>
                    <td class="num">{{ $line['qty'] }}</td>
                    <td class="num">{{ $line['unit'] }}</td>
                    <td class="num">{{ $line['total'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Transport charges</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- the amount payable (user, 30 Oct 2026); Paid and Balance due only once a payment was recorded --}}
    <table class="totals">
        @if ((float) $proforma->paid_amount > 0)
            <tr><td>Total</td><td class="num">RM {{ number_format((float) $proforma->total_amount, 2) }}</td></tr>
            <tr><td>Paid</td><td class="num">RM {{ number_format((float) $proforma->paid_amount, 2) }}</td></tr>
            <tr class="grand"><td>Balance due</td><td class="num">RM {{ number_format($outstanding, 2) }}</td></tr>
        @else
            <tr class="grand"><td>Total amount payable</td><td class="num">RM {{ number_format((float) $proforma->total_amount, 2) }}</td></tr>
        @endif
    </table>

    <div class="box">
        <strong>Payment instructions</strong><br>
        {!! nl2br(e($proforma->payment_instructions ?: 'Please make payment to the account(s) provided by our counter and upload your payment proof in the Customer Portal.')) !!}
    </div>

</body>
</html>
