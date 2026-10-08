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
        table.lines th { background: #f3f4f6; text-align: left; padding: 6px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .04em; color: #374151; border-bottom: 1px solid #d1d5db; }
        table.lines td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
        .num, table.lines th.num { text-align: right; white-space: nowrap; }
        .totals { width: 45%; margin-left: 55%; margin-top: 10px; border-collapse: collapse; }
        .totals td { padding: 4px 6px; }
        .totals .grand td { border-top: 2px solid #111827; font-weight: bold; font-size: 12px; }
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
                @if ($branch?->phone)<div class="muted">Tel: {{ $branch->phone }}</div>@endif
            </td>
            <td>
                <div class="doc-title">PROFORMA INVOICE</div>
                <div style="text-align:right;margin-top:4px"><span class="stamp">NOT A TAX INVOICE</span></div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td style="width:55%">
                <table>
                    <tr><td class="label">Bill to</td><td><strong>{{ $proforma->customer?->company_name ?? '—' }}</strong></td></tr>
                    @if ($proforma->customer?->address)<tr><td class="label"></td><td>{!! nl2br(e($proforma->customer->address)) !!}</td></tr>@endif
                    @if ($quotation?->customer_do_number)<tr><td class="label">Customer DO</td><td>{{ $quotation->customer_do_number }}</td></tr>@endif
                    @if ($quotation?->consignee_name)<tr><td class="label">Consignee</td><td>{{ $quotation->consignee_name }}</td></tr>@endif
                </table>
            </td>
            <td>
                <table>
                    <tr><td class="label">Proforma no.</td><td><strong>{{ $proforma->number }}</strong></td></tr>
                    <tr><td class="label">Date</td><td>{{ ($proforma->issued_at ?? $proforma->created_at)?->format('d/m/Y') }}</td></tr>
                    @if ($quotation)
                        <tr><td class="label">Order no.</td><td>{{ $quotation->orderNumber() }}</td></tr>
                        <tr><td class="label">Quotation</td><td>{{ $quotation->number }} (v{{ $quotation->version }})</td></tr>
                        <tr><td class="label">Payment term</td><td>{{ $quotation->orderType()?->getLabel() ?? '—' }}</td></tr>
                        <tr><td class="label">Salesperson</td><td>{{ $quotation->salesperson?->name ?? '—' }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:52%">Item / service</th>
                <th style="width:15%">Route</th>
                <th class="num" style="width:6%">Qty</th>
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

    <table class="totals">
        <tr><td>Total</td><td class="num">RM {{ number_format((float) $proforma->total_amount, 2) }}</td></tr>
        <tr><td>Paid</td><td class="num">RM {{ number_format((float) $proforma->paid_amount, 2) }}</td></tr>
        <tr class="grand"><td>Amount due</td><td class="num">RM {{ number_format($outstanding, 2) }}</td></tr>
    </table>

    <div class="box">
        <strong>Payment instructions</strong><br>
        {!! nl2br(e($proforma->payment_instructions ?: 'Please make payment to the account(s) provided by our counter and upload your payment proof in the Customer Portal.')) !!}
    </div>

    <p class="muted" style="margin-top:14px">This proforma invoice is issued for payment purposes. The official Invoice / Cash Bill is issued once payment is approved.</p>
</body>
</html>
