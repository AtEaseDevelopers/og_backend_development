<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Lorry Schedule {{ $schedule['date']->format('d.m.Y') }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 6px; text-decoration: underline; }
        h1 span { margin-left: 24px; }
        h2 { font-size: 13px; margin: 14px 0 4px; }
        h3 { font-size: 11px; margin: 10px 0 2px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 2px 6px; vertical-align: top; }
        th { text-align: left; font-size: 9px; text-transform: uppercase; color: #555; border-bottom: 1px solid #999; }
        td.no { width: 21%; white-space: nowrap; font-family: DejaVu Sans Mono, monospace; font-size: 10px; }
        td.lorry { width: 13%; white-space: nowrap; font-weight: bold; background: #f7a24a; }
        td.lorry.other { background: #4fb3ff; }
        td.from { width: 12%; white-space: nowrap; }
        td.to { font-weight: bold; }
        td.cnt { width: 18%; white-space: nowrap; text-align: right; color: #555; font-size: 10px; }
        tr.row td { border-bottom: 1px dotted #ddd; }
        .empty { color: #777; font-style: italic; padding: 6px 0; }
        .foot { margin-top: 10px; font-size: 9px; color: #777; }
    </style>
</head>
<body>
@php $date = $schedule['date']; @endphp

@forelse ($schedule['branches'] as $group)
    <h1>DATE : {{ $date->format('d.m.Y') }} ({{ strtoupper($date->format('D')) }}) <span>O&amp;G {{ $group['code'] }}</span></h1>
    <table>
        <thead>
            <tr><th>Job sheet</th><th>Lorry</th><th>From</th><th>To</th><th>Drops</th></tr>
        </thead>
        <tbody>
            @foreach ($group['trips'] as $trip)
                <tr class="row">
                    <td class="no">{{ $trip['number'] }}@if ($trip['trip_label'] !== 'Trip 1') <small>({{ $trip['trip_label'] }})</small>@endif</td>
                    <td class="lorry">{{ $trip['lorry'] }}@if ($trip['shared']) <small>[{{ $trip['lorry_branch'] }}]</small>@endif</td>
                    <td class="from">{{ $trip['origin'] }}</td>
                    <td class="to">{{ $trip['destinations'] }}@if ($trip['driver']) <small style="font-weight:normal;color:#555">— {{ $trip['driver'] }}</small>@endif</td>
                    <td class="cnt">{{ $trip['task_count'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach (collect($group['other_orders'])->groupBy('source_branch') as $sourceBranch => $orders)
        <h3>O&amp;G {{ $sourceBranch }} ORDER</h3>
        <table>
            <tbody>
                @foreach ($orders as $order)
                    <tr class="row">
                        <td class="no">{{ $order['job_sheet'] }}</td>
                        <td class="lorry other">{{ $order['lorry'] }}</td>
                        <td class="from">{{ $order['origin'] }}</td>
                        <td class="to">{{ $order['destination'] }} <small style="font-weight:normal">({{ $order['customer'] }})</small></td>
                        <td class="cnt">{{ $order['csn'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if (! $loop->last)<div style="page-break-after: always"></div>@endif
@empty
    <h1>DATE : {{ $date->format('d.m.Y') }} ({{ strtoupper($date->format('D')) }})</h1>
    <p class="empty">No lorry trips scheduled.</p>
@endforelse

<div class="foot">Generated {{ now()->format('d/m/Y H:i') }} · {{ $schedule['total_trips'] }} trips · {{ $schedule['total_tasks'] }} deliveries</div>
</body>
</html>
