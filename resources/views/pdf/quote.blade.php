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
        table.tw td { padding: 1px 4px 1px 0; vertical-align: top; white-space: nowrap; }
        table.tw td.label { white-space: normal; }
        table.tw tr.head td { padding-bottom: 6px; }
        table.tw .under { display: block; letter-spacing: 0; overflow: hidden; }
        table.tw tr.row td { padding-top: 3px; padding-bottom: 3px; }
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

    <div class="re">RE : Quotation Of Transport Charges{{ $effective ? ' (Effective '.$effective.')' : '' }}</div>

    <div class="tw-line">***** PRICE NOT INCLUDE 6% SST *****</div>

    @forelse ($tables as $table)
        {{-- fixed widths: the product column 40%, the destinations share the rest evenly (columns line up across tables) --}}
        @php $colWidth = round(60 / max(1, count($table['columns'])), 2); @endphp
        <table class="tw">
            <colgroup>
                <col style="width:40%">
                @foreach ($table['columns'] as $column)<col style="width:{{ $colWidth }}%">@endforeach
            </colgroup>
            <tr class="head">
                <td class="label" style="width:40%">{{ $table['title'] }}<span class="under">{{ $dash(\Illuminate\Support\Str::limit($table['title'], 40, ''), '=') }}</span></td>
                @foreach ($table['columns'] as $c => $column)
                    {{-- a destination this table has no price for: an empty column, kept for the alignment --}}
                    <td style="width:{{ $colWidth }}%">@if (collect($table['rows'])->contains(fn ($row) => $row['prices'][$c] !== null)){{ $column }}<span class="under">{{ $dash($column, '-') }}</span>@endif</td>
                @endforeach
            </tr>
            @foreach ($table['rows'] as $row)
                <tr class="row">
                    <td class="label">{{ $row['label'] }}</td>
                    @foreach ($row['prices'] as $price)
                        <td style="white-space:pre">{{ $price ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @empty
        <div class="tw-line">No transport charges listed.</div>
    @endforelse

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
