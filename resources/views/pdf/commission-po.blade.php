<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $po['number'] }}</title>
    {{-- the client's Purchase Order layout (user, 27 Oct 2026): supplier left, PO details right, a job table per lorry --}}
    @include('pdf.partials.common')
    <style>
        @page { margin: 32px 30px 40px 30px; }
        body { font-size: 10.5px; line-height: 1.35; }
        .title { font-size: 17px; font-weight: bold; margin: 0 0 10px; }
        .head { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .head td { vertical-align: top; padding: 0; }
        /* labels bold, values in normal text (user, 6 Nov 2026) */
        .head .k { width: 5.5rem; font-weight: bold; }
        .head .v { font-weight: normal; }
        .supplier { font-weight: normal; }
        .supplier .k { font-weight: bold; }
        .supplier div { margin-bottom: 1px; }
        /* the jobs in the order form's grid (client, 5 Nov 2026) */
        table.jobs { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.jobs th { text-align: left; font-weight: bold; padding: 4px 6px; border: 1px solid #000; background: #f0f0f0; text-transform: uppercase; }
        table.jobs td { padding: 4px 6px; vertical-align: top; border: 1px solid #000; }
        table.jobs th.num, table.jobs td.num { text-align: right; white-space: nowrap; }
        table.jobs th.c, table.jobs td.c { text-align: center; white-space: nowrap; }
        .lorry-line { margin-top: 10px; font-weight: bold; }
        .subtotal { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .subtotal td { padding: 3px 0; font-weight: bold; }
        .subtotal td.num { text-align: right; white-space: nowrap; border-top: 1px solid #000; }
        .subtotal td.label { text-align: right; padding-right: 10px; }
        .total td.num { border-bottom: 3px double #000; }
    </style>
</head>
<body>
    <div class="title">{{ $companyName }}</div>

    <table class="head">
        <tr>
            <td style="width:55%">
                <div class="supplier">
                    <div class="k">Supplier :-</div>
                    <div>{{ $po['supplier_name'] ?: '—' }}</div>
                </div>
            </td>
            <td>
                <table class="head">
                    <tr><td class="k">P/O No</td><td class="v">: {{ $po['number'] }}</td></tr>
                    <tr><td class="k">Date</td><td class="v">: {{ $po['date'] }}</td></tr>
                    <tr><td class="k">Lorry Group</td><td class="v">: {{ $po['lorry_group'] ?: '—' }}</td></tr>
                    <tr><td class="k">Supplier</td><td class="v">: {{ $po['supplier_code'] ?: '—' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @foreach ($sections as $section)
        <div class="lorry-line">Lorry No &nbsp;{{ $section['lorry'] }}</div>
        <table class="jobs">
            <thead>
                <tr>
                    <th class="c" style="width:5%">No.</th>
                    <th style="width:16%">Job No.</th>
                    <th style="width:14%">Job Date</th>
                    <th>Description</th>
                    <th class="num" style="width:16%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        <td class="c">{{ $loop->iteration }}</td>
                        <td>{{ $row['job_no'] }}</td>
                        <td>{{ $row['job_date'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="num">{{ number_format($row['amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No jobs.</td></tr>
                @endforelse
            </tbody>
        </table>
        <table class="subtotal">
            <tr><td class="label">Sub Total RM :</td><td class="num" style="width:16%">{{ number_format($section['subtotal'], 2) }}</td></tr>
        </table>
    @endforeach

    <table class="subtotal total" style="margin-top:14px">
        <tr><td class="label">Total RM :</td><td class="num" style="width:16%">{{ number_format($total, 2) }}</td></tr>
    </table>
</body>
</html>
