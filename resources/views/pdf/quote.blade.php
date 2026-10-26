<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    {{-- the client's quotation format (user, 26 Oct 2026: as their "Quotation of Transportation Charges"):
         letterhead + Ref No. / Page No. / QUOTATION on every page, the rates in a typewriter style --}}
    <style>
        @page { margin: 168px 36px 46px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; line-height: 1.3; }
        header { position: fixed; top: -150px; left: 0; right: 0; }
        .lh-name { font-size: 17px; font-weight: bold; }
        .lh-small { font-size: 9px; line-height: 1.35; }
        .lh-rule { border-top: 4px solid #000; margin: 5px 0 6px; }
        .lh-meta { width: 100%; border-collapse: collapse; }
        .lh-meta td { padding: 0; vertical-align: bottom; }
        .lh-meta .k { font-weight: bold; font-size: 10px; }
        .lh-title { font-size: 19px; font-weight: bold; text-align: right; }
        .pagenum:before { content: counter(page); }

        .party { width: 100%; border-collapse: collapse; margin-top: 2px; }
        .party td { vertical-align: top; padding: 0; font-weight: bold; font-size: 10px; }
        .party .gap { height: 10px; }
        .re { margin: 10px 0 12px; font-size: 12px; font-weight: bold; text-decoration: underline; }

        /* the rates: bold Courier, as the original (text kept to Courier's characters: < instead of ≤) */
        .tw, .tw td { font-family: Courier, monospace; font-weight: bold; font-size: 9.5px; }
        .tw-line { font-family: Courier, monospace; font-weight: bold; font-size: 9.5px; margin: 0 0 10px; white-space: pre-wrap; }
        table.tw { width: 100%; border-collapse: collapse; margin: 0 0 14px; page-break-inside: avoid; table-layout: fixed; }
        table.tw td { padding: 1px 4px 1px 0; vertical-align: top; white-space: nowrap; overflow: hidden; }
        table.tw td.label { white-space: normal; }
        table.tw tr.head td { padding-bottom: 6px; }
        table.tw .under { display: block; letter-spacing: 0; overflow: hidden; }
        table.tw tr.row td { padding-top: 3px; padding-bottom: 3px; }
        table.tw td.num { text-align: right; padding-right: 10px; }
        table.tw.items tr.first td { padding-top: 7px; }
        table.tw.items td { padding-top: 1px; padding-bottom: 1px; }
        table.tw td.dest { padding-top: 10px; text-decoration: underline; }
        .notes { font-family: Courier, monospace; font-weight: bold; font-size: 9.5px; margin-top: 8px; }
        .notes div { margin-bottom: 2px; }
    </style>
</head>
<body>
    @php
        $branch = $quote->branch;
        $companyName = strtoupper($company?->name ?? $branch?->company_name ?? 'O&G TRANSPORT');
        $regNo = $company?->brn ?? $branch?->company_no ?? '';
        $address = strtoupper($company?->address ?? $branch?->address ?? '');
        $phone = $company?->phone ?? $branch?->phone ?? '';
        $email = $company?->email ?? $branch?->email ?? '';
        $fax = $branch?->fax ?? '';
        $sstNo = ($company?->tin ?: $branch?->sst_no) ?? '';
        $issuedBy = $salesperson?->name ?? $quote->creator?->name ?? '';
        $date = $quote->quote_date;
        $effective = $date ? $date->format('jS F Y') : null;
        $dash = fn (string $text, string $char) => str_repeat($char, max(4, min(48, mb_strlen($text))));
    @endphp

    {{-- letterhead, repeated on every page --}}
    <header>
        <div class="lh-name">{{ $companyName }}</div>
        <div class="lh-small">
            @if ($regNo)(REG NO. : {{ $regNo }} )<br>@endif
            @if ($address){{ $address }}<br>@endif
            @if ($phone || $fax)@if ($phone)Tel : {{ $phone }}@endif @if ($fax)&nbsp; Fax : {{ $fax }}@endif<br>@endif
            <table class="lh-meta"><tr>
                <td class="lh-small">@if ($email)Email : {{ $email }}@endif</td>
                <td class="lh-small">@if ($sstNo)SST No. : {{ $sstNo }}@endif</td>
            </tr></table>
        </div>
        <div class="lh-rule"></div>
        <table class="lh-meta"><tr>
            <td class="k" style="width:28%">Ref No. : {{ $quote->number }}</td>
            <td class="k" style="width:32%">Page No. : <span class="pagenum"></span></td>
            <td class="lh-title">QUOTATION</td>
        </tr></table>
    </header>

    {{-- the customer (or lead), date and issued by: first page only --}}
    <table class="party">
        <tr>
            <td style="width:62%">
                {{ strtoupper($party['name']) }}
                @if ($party['address'])<br>{!! nl2br(e(strtoupper($party['address']))) !!}@endif
            </td>
            <td>
                <table style="border-collapse:collapse">
                    <tr><td style="width:5.5rem">Date</td><td>: {{ $date?->format('d/m/Y') }}</td></tr>
                    <tr><td colspan="2" class="gap"></td></tr>
                    <tr><td>Issued by:</td><td>{{ $issuedBy }}</td></tr>
                </table>
            </td>
        </tr>
        <tr><td colspan="2" class="gap"></td></tr>
        <tr>
            <td colspan="2">
                Email : {{ $party['email'] }}<br>
                Tel : {{ $party['phone'] }}&nbsp;&nbsp;Fax : {{ $party['fax'] }}<br>
                Attn : {{ strtoupper((string) $party['attention']) }}
            </td>
        </tr>
    </table>

    <div class="re">RE : Quotation of Transportation Charges{{ $effective ? ' (Effective '.$effective.')' : '' }}</div>

    <div class="tw-line">***** PRICE NOT INCLUDE 6% SST *****</div>

    {{-- the client's quotation (user, 26 Oct 2026): ITEM | CHARGES (RM) | QUANTITY, a line per quantity range --}}
    @if ($tables['groups'] !== [])
        <table class="tw items">
            <tr class="head">
                <td style="width:58%">ITEM<span class="under">----</span></td>
                <td style="width:17%" class="num">CHARGES (RM)<span class="under">------------</span></td>
                <td style="width:25%">&nbsp;&nbsp;QUANTITY<span class="under">&nbsp;&nbsp;--------</span></td>
            </tr>
            @foreach ($tables['groups'] as $group)
                @if ($group['destination'])
                    <tr><td colspan="3" class="dest">TO {{ $group['destination'] }}</td></tr>
                @endif
                @foreach ($group['products'] as $product)
                    @foreach ($product['lines'] as $n => $line)
                        <tr @class(['first' => $n === 0])>
                            <td class="label">{{ $n === 0 ? $product['name'] : '' }}</td>
                            <td class="num">{{ $line['charge'] ?? '-' }}</td>
                            <td>&nbsp;&nbsp;{{ $line['quantity'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
            @endforeach
        </table>
    @endif

    {{-- chartered lorries: the only table with destinations, 4 destinations per table as the client's quotation --}}
    @if ($tables['lorry'])
        @foreach (array_chunk(array_keys($tables['lorry']['columns']), 4) as $chunk)
            @php $filler = 60 - 15 * count($chunk); @endphp
            <table class="tw">
                <tr class="head">
                    <td class="label" style="width:40%">CHARTERED LORRY<span class="under">---------------</span></td>
                    @foreach ($chunk as $c)
                        <td class="num" style="width:15%">{{ $tables['lorry']['columns'][$c] }}<span class="under">{{ $dash($tables['lorry']['columns'][$c], '-') }}</span></td>
                    @endforeach
                    @if ($filler > 0)<td style="width:{{ $filler }}%"></td>@endif
                </tr>
                @foreach ($tables['lorry']['rows'] as $row)
                    <tr class="row">
                        <td class="label">{{ $row['label'] }}</td>
                        @foreach ($chunk as $c)
                            <td class="num">{{ $row['prices'][$c] ?? '' }}</td>
                        @endforeach
                        @if ($filler > 0)<td></td>@endif
                    </tr>
                @endforeach
            </table>
        @endforeach
    @endif

    @if ($tables['groups'] === [] && ! $tables['lorry'])
        <div class="tw-line">No transport charges listed.</div>
    @endif

    <div class="notes">
        @foreach ($minimums as $line)
            <div>* {{ $line }}</div>
        @endforeach
        <div>* ADDITIONAL CHARGES APPLY TO DELIVERIES TO SITE LOCATIONS AND OUTSKIRT AREAS.</div>
        <div>* MAXIMUM TIME FOR LOADING AND UNLOADING IS 2 HOURS.</div>
        <div style="margin-top:8px">Note:</div>
        <div>1. Sales &amp; Service Tax (SST) is not included in the transportation charges stated.</div>
        <div>2. Compensation for lost/damaged goods is capped at 50 times the transportation charge.</div>
        <div>3. Only claims reported within 7 days of delivery will be considered.</div>
        <div style="margin-top:8px">* This is a computer generated document. No signature is required. *</div>
        <div style="margin-top:14px">***** PRICE NOT INCLUDE 6% SST *****{{ $effective ? '     (Effective '.$effective.')' : '' }}</div>
    </div>
</body>
</html>
