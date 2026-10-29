<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    {{-- the client's quotation format (user, 26 Oct 2026: as their "Quotation of Transportation Charges"):
         letterhead + Ref No. / Page No. / QUOTATION on every page, the rates in a typewriter style --}}
    <style>
        @page { margin: 226px 36px 46px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; line-height: 1.3; }
        header { position: fixed; top: -208px; left: 0; right: 0; }
        .lh-name { font-size: 17px; font-weight: bold; }
        .lh-small { font-size: 9px; line-height: 1.35; }
        .lh-rule { border-top: 4px solid #000; margin: 5px 0 6px; }
        .lh-meta { width: 100%; border-collapse: collapse; }
        .lh-meta td { padding: 0; vertical-align: bottom; }
        .lh-meta .k { font-weight: bold; font-size: 10px; }
        .lh-title { font-size: 19px; font-weight: bold; text-align: right; }
        /* a QR of the document number (user, 30 Oct 2026): scanned into the system's search fields */
        .lh-qr { margin-bottom: 2px; }
        .lh-qr img { width: 54px; height: 54px; }
        .pagenum:before { content: counter(page); }

        .party { width: 100%; border-collapse: collapse; margin-top: 2px; }
        .party td { vertical-align: top; padding: 0; font-weight: bold; font-size: 10px; }
        .party .gap { height: 10px; }
        .re { margin: 10px 0 12px; font-size: 12px; font-weight: bold; text-decoration: underline; }

        /* the rates: bold Courier, as the original (text kept to Courier's characters: < instead of ≤) */
        .tw, .tw td { font-family: Courier, monospace; font-weight: bold; font-size: 9.5px; }
        .tw-line { font-family: Courier, monospace; font-weight: bold; font-size: 9.5px; margin: 0 0 10px; white-space: pre-wrap; }
        table.tw { width: 100%; border-collapse: collapse; margin: 0 0 14px; page-break-inside: avoid; table-layout: fixed; }
        /* a bordered grid (user, 29 Oct 2026: easier to read); the typewriter underlines are hidden */
        table.tw td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; white-space: nowrap; overflow: hidden; }
        table.tw td.label { white-space: normal; }
        table.tw tr.head td { background: #f0f0f0; }
        table.tw .under { display: none; }
        table.tw td.num { text-align: right; }
        table.tw.items tr.first td { padding-top: 7px; }
        table.tw.items td { padding-top: 1px; padding-bottom: 1px; }
        table.tw tr.group td { background: #f7f7f7; }
        table.tw td.qty { padding-left: 14px; }
        .notes { font-family: Courier, monospace; font-weight: bold; font-size: 9.5px; margin-top: 8px; }
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
        $fax = $branch?->fax ?? '';
        $sstNo = ($company?->tin ?: $branch?->sst_no) ?? '';
        $issuedBy = $salesperson?->name ?? $quote->creator?->name ?? '';
        $date = $quote->quote_date;
        // the client's two types (user, 29 Oct 2026): the price list carries the effective date and the SST line; the customer
        // quotation opens with "Please find…" and closes with the terms and contact person
        $priceList = $quote->isPriceList();
        $effective = $priceList && $date ? $date->format('jS F Y') : null;
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
            <td class="lh-title"><div class="lh-qr"><img src="{{ \App\Support\QrCodeImage::dataUri($quote->number) }}" alt="QR {{ $quote->number }}"></div>QUOTATION</td>
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

    <div class="tw-line">{{ $priceList ? '***** PRICE NOT INCLUDE 6% SST *****' : 'Please find the transportation charges for the following:-' }}</div>

    {{-- the client's quotations (user, 29 Oct 2026): ITEM and a column per destination (4 per table), each product a
         heading underlined with = and a line per quantity range; a product with one price a single line --}}
    @if ($tables['groups'] !== [])
    @foreach (array_chunk($tables['columns'], 4) ?: [[]] as $chunk)
        @php $colWidth = count($chunk) ? round(58 / count($chunk), 2) : 58; @endphp
            <table class="tw rates">
                <tr class="head">
                    <td class="label" style="width:42%">ITEM<span class="under">----</span></td>
                    @foreach ($chunk as $c)
                        <td class="num" style="width:{{ $colWidth }}%">{{ $c }}<span class="under">{{ $dash($c, '-') }}</span></td>
                    @endforeach
                </tr>
                @foreach ($tables['groups'] as $group)
                    @php $singleLine = count($group['rows']) === 1 && $group['rows'][0]['label'] === ''; @endphp
                    <tr class="group">
                        <td class="label" colspan="{{ $singleLine ? 1 : count($chunk) + 1 }}">{{ $group['name'] }}@unless ($singleLine)<span class="under">{{ $dash($group['name'], '=') }}</span>@endunless</td>
                        @if ($singleLine)
                            @foreach ($chunk as $c)
                                <td class="num">{!! nl2br(e($group['rows'][0]['prices'][$c] ?? '-')) !!}</td>
                            @endforeach
                        @endif
                    </tr>
                    @unless ($singleLine)
                        @foreach ($group['rows'] as $row)
                            <tr class="row">
                                <td class="label qty">{{ $row['label'] }}</td>
                                @foreach ($chunk as $c)
                                    <td class="num">{!! nl2br(e($row['prices'][$c] ?? '-')) !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endunless
                @endforeach
            </table>
    @endforeach
    @endif

    {{-- the chartered lorry block and the notes under it stay on one page (user, 30 Oct 2026: no page with only
         the closing line on it): when they do not fit under the products they move to the next page together --}}
    <div class="keep">
    {{-- chartered lorries: their own block, as the client's CHARTED LORRY, with its own destination columns --}}
    @if ($tables['lorry'])
    @foreach (array_chunk($tables['lorry']['columns'], 4) ?: [[]] as $chunk)
        @php $colWidth = count($chunk) ? round(58 / count($chunk), 2) : 58; @endphp
            <table class="tw">
                <tr class="head">
                    <td class="label" style="width:42%">CHARTERED LORRY<span class="under">---------------</span></td>
                    @foreach ($chunk as $c)
                        <td class="num" style="width:{{ $colWidth }}%">{{ $c }}<span class="under">{{ $dash($c, '-') }}</span></td>
                    @endforeach
                </tr>
                @foreach ($tables['lorry']['rows'] as $row)
                    <tr class="row">
                        <td class="label">{{ $row['label'] }}</td>
                        @foreach ($chunk as $c)
                            <td class="num">{!! nl2br(e($row['prices'][$c] ?? '-')) !!}</td>
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
                $issuedBy ? 'Contact Person : '.strtoupper($issuedBy).($salesperson?->phone ? ' ( '.$salesperson->phone.' )' : '') : null,
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
