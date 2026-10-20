<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    {{-- the earlier quotation layout (pdf/quotation) --}}
    <style>
        @page { margin: 36px 42px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.35;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .upper { text-transform: uppercase; }
        .line { border-top: 1px solid #000; margin: 10px 0 12px; }
        .title {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 0 0 10px;
        }
        .meta-table { width: 100%; margin-bottom: 14px; }
        .meta-table td { vertical-align: top; padding: 0; }
        .customer-block { margin-bottom: 14px; }
        .customer-block div { margin-bottom: 2px; }
        .subject {
            text-decoration: underline;
            font-weight: bold;
            margin: 10px 0 6px;
        }
        .intro { margin-bottom: 10px; }
        table.rates {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.rates th,
        table.rates td {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: top;
        }
        table.rates th {
            font-weight: bold;
            text-align: center;
        }
        table.rates td.item-col { width: 34%; }
        table.rates td.price-col { text-align: center; width: auto; }
        table.rates td.amount-col { text-align: right; white-space: nowrap; }
        .item-sub { font-size: 10px; margin-top: 2px; }
        .footnotes { margin-top: 10px; font-size: 10px; }
        .footnotes div { margin-bottom: 3px; }
        .footer-block { margin-top: 18px; font-size: 11px; }
        .footer-block div { margin-bottom: 4px; }
        .issued-line { margin-top: 8px; }
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
        $sstNo = $company?->tin ?? '';

        $issuedBy = $salesperson?->name ?? $quote->creator?->name ?? auth()->user()?->name ?? '';
        $contactPerson = match (true) {
            $salesperson?->name && $salesperson?->phone => $salesperson->name.' ( '.$salesperson->phone.' )',
            (bool) $salesperson?->name => $salesperson->name,
            default => '',
        };

        $orderType = \App\Enums\OrderType::tryFrom((string) ($quote->form['order_type'] ?? ''));
        $paymentTerms = match (true) {
            $orderType === \App\Enums\OrderType::Term && filled($party['credit_term_days']) => $party['credit_term_days'].' Days',
            $orderType !== null => $orderType->getLabel(),
            default => 'Cash / COD',
        };

        $formatRm = fn ($amount): string => $amount === null || $amount === '' ? '' : 'RM '.number_format((float) $amount, 2, '.', ',');
        $formatQty = fn ($qty): string => rtrim(rtrim(number_format((float) $qty, 3, '.', ','), '0'), '.');
        $grandTotal = round(collect($blocks)->sum('total'), 2);
    @endphp

    {{-- Letterhead --}}
    <div class="center bold upper" style="font-size:12px;">{{ $companyName }}</div>
    @if($regNo)
        <div class="center">(REG NO. : {{ $regNo }})</div>
    @endif
    @if($address)
        <div class="center upper" style="margin-top:4px;">{{ $address }}</div>
    @endif
    <div class="center" style="margin-top:4px;">
        @if($phone) Tel: {{ $phone }} @endif
        @if($email) Email: {{ $email }} @endif
    </div>
    @if($sstNo)
        <div class="center" style="margin-top:4px;">SST No. : {{ $sstNo }}</div>
    @endif

    <div class="line"></div>

    <div class="right title">QUOTATION</div>

    {{-- Ref on the left; date with the page number below it on the right --}}
    <table class="meta-table">
        <tr>
            <td style="width:50%;">Ref No. : {{ $quote->number }}</td>
            <td class="right" style="width:50%;">
                Date : {{ $quote->quote_date?->format('d/m/Y') }}
                <div style="margin-top:2px;">Page No. : 1</div>
            </td>
        </tr>
    </table>

    {{-- Customer / lead --}}
    <div class="customer-block">
        <div class="bold upper">{{ $party['name'] }}</div>
        @if($party['address'])
            <div class="upper">{!! nl2br(e($party['address'])) !!}</div>
        @endif
        <div>Email : {{ $party['email'] }}</div>
        @if($party['phone'])
            <div>Tel : {{ $party['phone'] }}</div>
        @endif
        @if($party['fax'])
            <div>Fax : {{ $party['fax'] }}</div>
        @endif
        @if($party['attention'])
            <div>Attention : {{ strtoupper($party['attention']) }}</div>
        @endif
    </div>

    <div class="issued-line right">Issued by : {{ $issuedBy }}</div>

    <div class="subject">RE : Quotation Of Transport Charges</div>
    <div class="intro">Please find the transportation charges for the following:-</div>

    {{-- Products per destination --}}
    <table class="rates">
        <thead>
            <tr>
                <th class="item-col">Item</th>
                <th class="price-col">Destination</th>
                <th class="price-col">Qty</th>
                <th class="price-col">Unit price</th>
                <th class="price-col">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($blocks as $block)
                @foreach($block['lines'] as $line)
                    <tr>
                        <td class="item-col">{{ $line['item'] }}</td>
                        <td class="price-col">
                            {{ $block['destination'] }}
                            @if($block['consignee'])
                                <div class="item-sub">{{ $block['consignee'] }}</div>
                            @endif
                        </td>
                        <td class="price-col">{{ $formatQty($line['qty']) }}</td>
                        <td class="price-col">{{ $formatRm($line['price']) }}</td>
                        <td class="amount-col">{{ $formatRm($line['amount']) }}</td>
                    </tr>
                @endforeach
                @if($block['minimum'])
                    <tr>
                        <td class="item-col">
                            Minimum charge
                            <div class="item-sub">{{ $block['minimum']['label'] ?? 'Minimum' }} {{ $formatRm($block['minimum']['minimum'] ?? null) }} (top-up)</div>
                        </td>
                        <td class="price-col">{{ $block['destination'] }}</td>
                        <td class="price-col">1</td>
                        <td class="price-col">{{ $formatRm($block['minimum']['top_up']) }}</td>
                        <td class="amount-col">{{ $formatRm($block['minimum']['top_up']) }}</td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td class="item-col" colspan="5">No transport charges listed.</td>
                </tr>
            @endforelse
            @if($blocks !== [])
                <tr>
                    <td class="item-col bold" colspan="4" style="text-align:right;">Total</td>
                    <td class="amount-col bold">{{ $formatRm($grandTotal) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footnotes">
        <div>*Prices are per unit to each destination. A destination below its minimum charge (pick-up, construction site or drop-off) is topped up to it.</div>
    </div>

    {{-- Footer --}}
    <div class="footer-block">
        <div>Terms of payment : {{ $paymentTerms }}</div>
        @if($contactPerson)
            <div>Contact Person : {{ strtoupper($contactPerson) }}</div>
        @endif
        <div style="margin-top:10px;">If you have any doubts, please contact us.</div>
    </div>
</body>
</html>
