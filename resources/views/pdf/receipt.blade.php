<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->number }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111827; }
        .head { width: 100%; border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 14px; }
        .head td { vertical-align: top; }
        .company { font-size: 14px; font-weight: bold; }
        .muted { color: #6b7280; }
        .doc-title { font-size: 18px; font-weight: bold; text-align: right; letter-spacing: .04em; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .label { color: #6b7280; width: 130px; }
        .amount { margin-top: 10px; border: 2px solid #111827; padding: 10px 14px; font-size: 14px; font-weight: bold; }
        .sign { margin-top: 60px; width: 220px; border-top: 1px solid #111827; padding-top: 4px; text-align: center; }
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
            <td><div class="doc-title">OFFICIAL RECEIPT</div></td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td style="width:55%">
                <table>
                    <tr><td class="label">Received from</td><td><strong>{{ $customer?->company_name ?? '—' }}</strong></td></tr>
                    @if ($customer?->brn)<tr><td class="label">BRN</td><td>{{ $customer->brn }}</td></tr>@endif
                    @if ($customer?->address)<tr><td class="label">Address</td><td>{!! nl2br(e($customer->address)) !!}</td></tr>@endif
                </table>
            </td>
            <td>
                <table>
                    <tr><td class="label">Receipt no.</td><td><strong>{{ $receipt->number }}</strong></td></tr>
                    <tr><td class="label">Date</td><td>{{ $receipt->created_at?->format('d/m/Y') }}</td></tr>
                    <tr><td class="label">Payment method</td><td>{{ \App\Enums\PaymentMethod::tryFrom((string) ($payment?->method instanceof \BackedEnum ? $payment->method->value : $payment?->method))?->getLabel() ?? '—' }}</td></tr>
                    @if ($payment?->reference)<tr><td class="label">Reference</td><td>{{ $payment->reference }}</td></tr>@endif
                    @if ($payment?->invoice)<tr><td class="label">For invoice</td><td>{{ $payment->invoice->number }}</td></tr>@endif
                    @if ($payment?->quotation)<tr><td class="label">For order</td><td>{{ $payment->quotation->number }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <div class="amount">Amount received: RM {{ number_format((float) $receipt->amount, 2) }}</div>
    <p class="muted">{{ \App\Support\AmountInWords::ringgit((float) $receipt->amount) }}</p>

    <div class="sign">Authorised signature</div>
    <p class="muted" style="margin-top:24px">This is a computer-generated receipt.</p>
</body>
</html>
