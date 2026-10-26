<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['number'] }}</title>
    {{-- the client's Job Sheet layout (user, 27 Oct 2026): one document (page) per job sheet code --}}
    <style>
        @page { margin: 34px 36px 40px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; line-height: 1.3; }
        .doc { page-break-after: always; }
        .doc:last-child { page-break-after: auto; }
        .top { width: 100%; border-collapse: collapse; }
        .top td { padding: 0; vertical-align: top; }
        .company { font-size: 11px; font-weight: bold; }
        .printed { text-align: right; font-weight: bold; }
        .title { margin: 8px 0 6px; font-size: 11px; font-weight: bold; text-decoration: underline; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .meta td { padding: 1px 0; vertical-align: top; white-space: nowrap; }
        .meta .k { font-weight: bold; padding-right: 3px; }
        table.rows { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.rows th { font-weight: bold; text-decoration: underline; text-align: left; padding: 2px 4px 4px 0; vertical-align: bottom; }
        table.rows td { padding: 4px 4px 4px 0; vertical-align: top; }
        table.rows .num { text-align: right; padding-right: 0; white-space: nowrap; }
        table.rows .consignor { font-weight: bold; }
        table.rows .consignee { font-weight: bold; font-style: italic; }
        .totals td { padding-top: 6px; font-weight: bold; }
        .totals .num { border-bottom: 3px double #000; }
        .sign { margin-top: 16px; font-weight: bold; }
        .sign span { display: inline-block; width: 220px; border-bottom: 1px solid #000; }
    </style>
</head>
<body>
    @foreach ($groups as $group)
        <div class="doc">
            <table class="top">
                <tr>
                    <td class="company">{{ $companyName }}</td>
                    <td class="printed">Date: {{ $printedAt }} Page: {{ $loop->iteration }}</td>
                </tr>
            </table>
            <div class="title">JOB SHEET</div>
            <table class="meta">
                <tr>
                    <td><span class="k">Job No. :</span>{{ $sheet['number'] }}{{ $group['code'] }}</td>
                    <td><span class="k">Job Date :</span>{{ $sheet['date'] }}</td>
                    <td><span class="k">Location :</span>{{ $group['code'] }}</td>
                    <td></td>
                </tr>
                <tr>
                    <td><span class="k">Lorry No. :</span>{{ $sheet['lorry'] }}</td>
                    <td><span class="k">Type :</span>{{ $sheet['type'] }}</td>
                    <td colspan="2"><span class="k">Lorry Company :</span>{{ $sheet['lorry_company'] }}</td>
                </tr>
                <tr>
                    <td colspan="2"><span class="k">Driver Name :</span>{{ $sheet['driver'] }}</td>
                    <td><span class="k">Driver Tel. :</span>{{ $sheet['driver_tel'] }}</td>
                    <td style="text-align:right"><span class="k">Branch :</span>{{ $sheet['branch'] }}</td>
                </tr>
            </table>

            <table class="rows">
                <thead>
                    <tr>
                        <th style="width:5%" class="num">No.</th>
                        <th style="width:15%">CSM No.</th>
                        <th style="width:14%">Bill No.</th>
                        <th style="width:9%">From</th>
                        <th style="width:9%">To</th>
                        <th style="width:16%">Consignor</th>
                        <th style="width:16%">Consignee</th>
                        <th style="width:9%">Qty U/M</th>
                        <th style="width:8%" class="num">Advance<br>Taken</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($group['rows'] as $i => $row)
                        <tr>
                            <td class="num" style="padding-right:6px">{{ $i + 1 }}</td>
                            <td>{{ $row['csn'] }}</td>
                            <td>{{ $row['bill'] }}</td>
                            <td>{{ $row['from'] }}</td>
                            <td>{{ $row['to'] }}</td>
                            <td class="consignor">{{ \Illuminate\Support\Str::limit(strtoupper($row['consignor']), 18, '') }}</td>
                            <td class="consignee">{{ \Illuminate\Support\Str::limit(strtoupper($row['consignee']), 18, '') }}</td>
                            <td>{{ number_format($row['qty'], 2) }} {{ \Illuminate\Support\Str::limit($row['uom'], 8, '') }}</td>
                            <td class="num">{{ $row['advance'] !== null ? number_format($row['advance'], 2) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">No CSN on this job sheet.</td></tr>
                    @endforelse
                    <tr class="totals">
                        <td colspan="7" style="text-align:right">Total :</td>
                        <td class="num" style="text-align:left">{{ number_format($group['qty_total'], 2) }}</td>
                        <td class="num">{{ number_format($group['advance_total'], 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="sign">Signature &nbsp;: <span></span></div>
        </div>
    @endforeach
</body>
</html>
