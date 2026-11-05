<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement of account</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .head { width: 100%; border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 14px; }
        .head td { vertical-align: top; }
        .company { font-size: 14px; font-weight: bold; }
        .muted { color: #6b7280; }
        .doc-title { font-size: 17px; font-weight: bold; text-align: right; letter-spacing: .04em; }
        .meta { width: 100%; margin-bottom: 12px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .label { color: #6b7280; width: 110px; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { background: #f3f4f6; text-align: left; padding: 5px; font-size: 9px; text-transform: uppercase; color: #374151; border-bottom: 1px solid #d1d5db; }
        table.lines td { padding: 5px; border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; white-space: nowrap; }
        table.aging { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.aging th, table.aging td { border: 1px solid #d1d5db; padding: 5px; text-align: center; }
        table.aging th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="company">{{ $branch?->company_name ?: 'O&G Transport' }}</div>
                @if ($branch?->company_no)<div class="muted">({{ $branch->company_no }})</div>@endif
                @if ($branch?->address)<div class="muted">{!! nl2br(e($branch->address)) !!}</div>@endif
            </td>
            <td><div class="doc-title">STATEMENT OF ACCOUNT</div></td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td style="width:55%">
                <table>
                    <tr><td class="label">Customer</td><td><strong>{{ $customer->company_name }}</strong></td></tr>
                    @if ($customer->code)<tr><td class="label">Account no.</td><td>{{ $customer->code }}</td></tr>@endif
                    @if ($customer->brn)<tr><td class="label">BRN</td><td>{{ $customer->brn }}</td></tr>@endif
                    @if ($customer->address)<tr><td class="label">Address</td><td>{!! nl2br(e($customer->address)) !!}</td></tr>@endif
                </table>
            </td>
            <td>
                <table>
                    <tr><td class="label">Period</td><td>{{ $statement['from']->format('d/m/Y') }} – {{ $statement['to']->format('d/m/Y') }}</td></tr>
                    <tr><td class="label">Credit term</td><td>{{ $customer->credit_term_days ? $customer->credit_term_days.' days' : '—' }}</td></tr>
                    <tr><td class="label">Balance due</td><td><strong>RM {{ number_format($statement['closing'], 2) }}</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr><th>Date</th><th>Type</th><th>Reference</th><th>Due date</th><th class="num">Debit (RM)</th><th class="num">Credit (RM)</th><th class="num">Balance (RM)</th></tr>
        </thead>
        <tbody>
            <tr><td>{{ $statement['from']->format('d/m/Y') }}</td><td colspan="5">Opening balance</td><td class="num">{{ number_format($statement['opening'], 2) }}</td></tr>
            @foreach ($statement['rows'] as $row)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td>{{ $row['reference'] }}</td>
                    <td>{{ $row['due_date'] ? \Illuminate\Support\Carbon::parse($row['due_date'])->format('d/m/Y') : '' }}</td>
                    <td class="num">{{ $row['debit'] ? number_format($row['debit'], 2) : '' }}</td>
                    <td class="num">{{ $row['credit'] ? number_format($row['credit'], 2) : '' }}</td>
                    <td class="num">{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @endforeach
            <tr><td colspan="6"><strong>Closing balance</strong></td><td class="num"><strong>{{ number_format($statement['closing'], 2) }}</strong></td></tr>
        </tbody>
    </table>

    <table class="aging">
        <tr>@foreach (\App\Support\StatementData::AGING_BUCKETS as $label)<th>{{ $label }}</th>@endforeach<th>Total outstanding</th></tr>
        <tr>@foreach (array_keys(\App\Support\StatementData::AGING_BUCKETS) as $key)<td>{{ number_format($statement['aging'][$key], 2) }}</td>@endforeach<td><strong>{{ number_format($statement['outstanding'], 2) }}</strong></td></tr>
    </table>

    <p class="muted" style="margin-top:18px">This is a computer-generated statement. Please contact us within 14 days if any entry is incorrect.</p>
</body>
</html>
