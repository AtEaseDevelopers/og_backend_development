<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['number'] }}</title>
    {{-- the client's Job Sheet layout (user, 27 Oct 2026): one document (page) per job sheet code --}}
    @include('pdf.partials.common')
    <style>
        @page { margin: 30px 30px 40px 30px; }
        body { font-size: 9.5px; line-height: 1.3; }
        .doc { page-break-after: always; }
        .doc:last-child { page-break-after: auto; }
        .top { width: 100%; border-collapse: collapse; }
        .top td { padding: 0; vertical-align: top; }
        .company { font-size: 12px; font-weight: bold; }
        .printed { text-align: right; font-weight: bold; }
        .title { margin: 8px 0 6px; font-size: 12px; font-weight: bold; text-decoration: underline; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        /* labels bold, values in normal text (user, 6 Nov 2026) */
        .meta td { padding: 1px 0; vertical-align: top; white-space: nowrap; font-weight: normal; }
        .meta .k { font-weight: bold; padding-right: 3px; }
        /* the rows in the order form's grid (client, 5 Nov 2026) */
        table.rows { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.rows th { font-weight: bold; text-align: left; padding: 4px 5px; vertical-align: bottom; border: 1px solid #000; background: #f0f0f0; text-transform: uppercase; }
        table.rows td { padding: 4px 5px; vertical-align: top; border: 1px solid #000; }
        table.rows { table-layout: fixed; }
        table.rows .num { text-align: right; white-space: nowrap; }
        /* one row for each No.: a long name is cut, never wrapped */
        table.rows td.one { white-space: nowrap; overflow: hidden; }
        table.rows .consignor { font-weight: bold; }
        table.rows .consignee { font-weight: bold; font-style: italic; }
        .totals td { padding-top: 6px; font-weight: bold; }
        .totals .num { border-bottom: 3px double #000; }
    </style>
</head>
<body>
    @foreach ($groups as $group)
        <div class="doc">
            <table class="top">
                <tr>
                    <td class="company">{{ $companyName }}</td>
                    <td class="printed">Date: {{ $printedAt }}</td>
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
                        {{-- the drivers' six columns (client, 16 Nov 2026): no Bill No., From or To, so each No. stays on
                             one row on a portrait page; "Advance Taken" is "COD (RM)" (13 Nov 2026) --}}
                        <th style="width:5%" class="num">No.</th>
                        <th style="width:17%">CSN No.</th>
                        <th style="width:28%">Consignor</th>
                        <th style="width:28%">Consignee</th>
                        <th style="width:11%">Qty U/M</th>
                        <th style="width:11%" class="num">COD (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($group['rows'] as $i => $row)
                        <tr>
                            <td class="num">{{ $i + 1 }}</td>
                            <td class="one">{{ $row['csn'] }}</td>
                            <td class="consignor one">{{ \Illuminate\Support\Str::limit(strtoupper($row['consignor']), 30, '') }}</td>
                            <td class="consignee one">{{ \Illuminate\Support\Str::limit(strtoupper($row['consignee']), 30, '') }}</td>
                            {{-- the unit, never a product code (client, 5 Nov 2026) --}}
                            <td class="one">{{ number_format($row['qty'], 2) }} {{ \App\Support\QuantityLabel::unit($row['uom']) ?? 'Unit' }}</td>
                            <td class="num">{{ $row['advance'] !== null ? number_format($row['advance'], 2) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No CSN on this job sheet.</td></tr>
                    @endforelse
                    <tr class="totals">
                        <td colspan="4" style="text-align:right">Total :</td>
                        <td class="num" style="text-align:left">{{ number_format($group['qty_total'], 2) }}</td>
                        <td class="num">RM {{ number_format($group['advance_total'], 2) }}</td>
                    </tr>
                </tbody>
            </table>

        </div>
    @endforeach
</body>
</html>
