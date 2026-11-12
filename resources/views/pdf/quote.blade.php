<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    {{-- the client's quotation format (user, 26 Oct 2026: as their "Quotation of Transportation Charges"):
         letterhead + Ref No. / Page No. / QUOTATION on every page, the rates in a typewriter style --}}
    @include('pdf.partials.common')
    <style>
        @page { margin: 172px 30px 40px 30px; }
        body { font-size: 10.5px; line-height: 1.3; }
        header { position: fixed; top: -156px; left: 0; right: 0; }
        .lh-name { font-size: 17px; font-weight: bold; }
        .lh-small { font-size: 10px; line-height: 1.35; }
        .lh-rule { border-top: 4px solid #000; margin: 5px 0 6px; }
        .lh-meta { width: 100%; border-collapse: collapse; }
        .lh-meta td { padding: 0; vertical-align: bottom; }
        .lh-meta .k { font-weight: bold; font-size: 10px; }
        .lh-title { font-size: 19px; font-weight: bold; text-align: right; }
        /* the barcode of the document number (user, 30 Oct 2026) is styled in pdf.partials.common */

        .party { width: 100%; border-collapse: collapse; margin-top: 2px; table-layout: fixed; }
        .party td.who { word-wrap: break-word; }
        .party td { vertical-align: top; padding: 0; font-weight: normal; font-size: 10.5px; }
        .party td.who { padding-right: 12px; }
        .party .gap { height: 10px; }
        .re { margin: 4px 0 8px; font-size: 12px; font-weight: bold; text-decoration: underline; }

        /* the rates in the order quotation's items table style: the same font, size and cells on every document
           (client, 13 Nov 2026; the typewriter font is gone) */
        .tw, .tw td { font-size: 10.5px; font-weight: normal; }
        .tw-line { font-size: 10.5px; margin: 0 0 6px; white-space: pre-wrap; }
        table.tw { width: 100%; border-collapse: collapse; margin: 0 0 14px; page-break-inside: avoid; table-layout: fixed; }
        table.tw.rates { page-break-inside: auto; }
        table.tw.rates tr { page-break-inside: avoid; }
        /* a bordered grid (user, 29 Oct 2026: easier to read); the typewriter underlines are hidden */
        table.tw td { border: 1px solid #000; padding: 2px 6px; line-height: 1.15; vertical-align: top; white-space: nowrap; overflow: hidden; }
        table.tw td.label { white-space: normal; }
        /* a darker header bar (client, 6 Nov 2026), the product rows a step lighter */
        table.tw tr.head td { background: #f0f0f0; font-size: 10px; font-weight: bold; text-transform: uppercase; white-space: normal; } /* a long location name wraps, never clips (7 Nov 2026) */
        table.tw .under { display: none; }
        table.tw td.num { text-align: right; }
        table.tw.items tr.first td { padding-top: 7px; }
        table.tw.items td { padding-top: 1px; padding-bottom: 1px; }
        table.tw tr.group td { background: #f7f7f7; font-weight: bold; }
        table.tw td.qty { padding-left: 14px; }
        table.tw tr.minimum td { font-style: italic; font-weight: normal; }
        .notes { font-size: 10.5px; margin-top: 8px; }
        .keep { page-break-inside: avoid; }
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
        $sstNo = ($company?->tin ?: $branch?->sst_no) ?? '';
        // the contact person at the foot (client, 6 Nov 2026): the chosen staff, their phone from Master Data
        $contact = $contact ?? $quote->creator;
        $issuedBy = $contact?->name ?? '';
        $contactPhone = $contact?->phone;
        // the prices as numbers, RM in the column heading (client, 6 Nov 2026)
        $cell = fn ($value) => preg_replace('/\bRM\s*/', '', (string) $value);
        $date = $quote->quote_date;
        // the client's two types (user, 29 Oct 2026): the price list carries the effective date and the SST line; the customer
        // quotation opens with "Please find…" and closes with the terms and contact person
        $priceList = $quote->isPriceList();
        $effective = $priceList && $date ? $date->format('d/m/Y') : null; // dd/mm/yyyy everywhere (client, 5 Nov 2026)
        $dash = fn (string $text, string $char) => str_repeat($char, max(4, min(48, mb_strlen($text))));
    @endphp

    {{-- letterhead, repeated on every page --}}
    <header>
        <table class="lh-meta"><tr><td style="vertical-align:top">
        <div class="lh-name">{{ $companyName }}</div>
        <div class="lh-small">
            @if ($regNo)(REG NO. : {{ $regNo }} )<br>@endif
            @if ($address){{ $address }}<br>@endif
            @if ($phone)Tel : {{ $phone }}<br>@endif
            <table class="lh-meta"><tr>
                <td class="lh-small">@if ($email)Email : {{ $email }}@endif</td>
                <td class="lh-small">@if ($sstNo)SST No. : {{ $sstNo }}@endif</td>
            </tr></table>
        </div>
        </td><td class="lh-qr"><img src="{{ \App\Support\BarcodeImage::code128DataUri($quote->number) }}" alt="Barcode {{ $quote->number }}"><div class="lh-qr-text">{{ $quote->number }}</div></td></tr></table>
        <div class="lh-rule"></div>
        {{-- the number is under the barcode only; the page number is stamped bottom right (client, 5 Nov 2026) --}}
        <table class="lh-meta"><tr>
            <td class="lh-title" style="text-align:left">QUOTATION</td>
        </tr></table>
    </header>

    {{-- the customer (or lead), date and issued by: first page only --}}
    <table class="party">
        <tr><th colspan="2" class="bt-heading">Billing Information</th></tr>
        <tr class="bt-body">
            <td style="width:62%" class="who">
                {{-- the standard billing block (client, 7 Nov 2026) --}}
                @include('pdf.partials.bill-to', ['heading' => false, 'addressWidth' => 34, 'code' => $party['code'] ?? null, 'name' => strtoupper($party['name']), 'reg_no' => $party['reg_no'] ?? null, 'address' => $party['address'] ? strtoupper(\App\Support\AddressLines::wrap($party['address'])) : null, 'pic' => strtoupper((string) $party['attention']) ?: null, 'phone' => $party['phone'], 'email' => $party['email']])
            </td>
            <td>
                <table style="border-collapse:collapse">
                    {{-- "Issued by" replaced by who created it and the contact person (client, 6 Nov 2026) --}}
                    <tr><td class="dk">Date</td><td class="dv">{{ $date?->format('d/m/Y') }}</td></tr>
                    <tr><td class="dk">Created by</td><td class="dv">{{ $quote->creator?->name ?? '—' }}</td></tr>
                    <tr><td class="dk">Contact person</td><td class="dv">{{ $issuedBy ?: '—' }}@if ($contactPhone) ( {{ $contactPhone }} )@endif</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="re">RE : Quotation Of Transport Charges{{ $effective ? ' (Effective '.$effective.')' : '' }}</div>

    <div class="tw-line">{{ $priceList ? '***** PRICE NOT INCLUDE 6% SST *****' : 'Please find the transportation charges for the following:-' }}</div>

    {{-- the client's quotations (user, 29 Oct 2026): ITEM and a column per destination (4 per table), each product a
         heading underlined with = and a line per quantity range; a product with one price a single line --}}
    @if ($tables['groups'] !== [])
    {{-- 4 locations per table (client, 6 Nov 2026): the 5th starts a table on the next page; the columns narrower,
         RM in the heading --}}
    @php
        // 4 location columns a table, each the same width and counted from the right (client, 13 Nov 2026): with fewer
        // locations the item column takes the room, so there is no empty boxed column and every table's prices line
        // up — the chartered lorries too; a 5th location is on its own table, the earlier ones are not shown again
        $pageColumns = fn (array $columns): array => array_chunk($columns, 4) ?: [[]];
        $colWidth = 12;
        $labelWidth = fn (array $chunk): int => 100 - $colWidth * count($chunk);
    @endphp
    @foreach ($pageColumns($tables['columns']) as $chunk)
            <table class="tw rates" @unless ($loop->first) style="page-break-before: always" @endunless>
                <tr class="head">
                    <td class="label" style="width:{{ $labelWidth($chunk) }}%">ITEM<span class="under">----</span></td>
                    @foreach ($chunk as $c)
                        <td class="num" style="width:{{ $colWidth }}%">{{ $c }} (RM)<span class="under">{{ $dash($c, '-') }}</span></td>
                    @endforeach
                </tr>
                @foreach ($tables['groups'] as $group)
                    @php $singleLine = ($group['rows'][0]['label'] ?? null) === ''; @endphp
                    <tr class="group">
                        <td class="label" colspan="{{ $singleLine ? 1 : count($chunk) + 1 }}">{{ $group['name'] }}@unless ($singleLine)<span class="under">{{ $dash($group['name'], '=') }}</span>@endunless</td>
                        @if ($singleLine)
                            @foreach ($chunk as $c)
                                <td class="num">{!! nl2br(e($cell($group['rows'][0]['prices'][$c] ?? '-'))) !!}</td>
                            @endforeach
                        @endif
                    </tr>
                    {{-- the range rows, then the product's drop-off minimum when it has one (client, 30 Oct 2026) --}}
                    @foreach (array_slice($group['rows'], $singleLine ? 1 : 0) as $row)
                        <tr @class(['row', 'minimum' => $row['minimum'] ?? false])>
                            <td class="label qty">{{ $row['label'] }}</td>
                            @foreach ($chunk as $c)
                                <td class="num">{!! nl2br(e($cell($row['prices'][$c] ?? '-'))) !!}</td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </table>
    @endforeach
    @endif

    {{-- the chartered lorry block and the notes under it stay on one page (user, 30 Oct 2026: no page with only
         the closing line on it): when they do not fit under the products they move to the next page together --}}
    <div class="keep">
    {{-- chartered lorries: their own block, as the client's CHARTED LORRY, with its own destination columns --}}
    @if ($tables['lorry'])
    @foreach ($pageColumns($tables['lorry']['columns']) as $chunk)
            <table class="tw" @unless ($loop->first) style="page-break-before: always" @endunless>
                <tr class="head">
                    <td class="label" style="width:{{ $labelWidth($chunk) }}%">CHARTERED LORRY<span class="under">---------------</span></td>
                    @foreach ($chunk as $c)
                        <td class="num" style="width:{{ $colWidth }}%">{{ $c }} (RM)<span class="under">{{ $dash($c, '-') }}</span></td>
                    @endforeach
                </tr>
                @foreach ($tables['lorry']['rows'] as $row)
                    <tr class="row">
                        <td class="label">{{ $row['label'] }}</td>
                        @foreach ($chunk as $c)
                            <td class="num">{!! nl2br(e($cell($row['prices'][$c] ?? '-'))) !!}</td>
                        @endforeach
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
        <div>* ADDITIONAL SURCHARGE WILL BE APPLY FOR DELIVERIES TO NON-STANDARD LOCATION, CONSTRUCTION SITE AND OUTSKIRT AREA.</div>
        {{-- the closing lines of the client's quotation (user, 29 Oct 2026) --}}
        @php
            $closing = array_values(array_filter([
                $party['credit_term_days'] ? 'Terms of payment: '.$party['credit_term_days'].' Days' : null,
                // the contact person is under the date now (user, 6 Nov 2026), not repeated here
                'If you have any doubts, please contact us.',
            ]));
        @endphp
        @unless ($priceList)
            @foreach ($closing as $i => $line)
                <div @if ($i === 0) style="margin-top:8px" @endif>{{ $line }}</div>
            @endforeach
        @endunless
    </div>
    </div>
</body>
</html>
