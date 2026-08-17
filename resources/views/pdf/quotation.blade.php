<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->number }}</title>
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
        $company = $quotation->company;
        $branch = $quotation->branch;
        $customer = $quotation->customer;
        $salesperson = $quotation->salesperson;

        $companyName = strtoupper($company?->name ?? $branch?->company_name ?? 'O&G TRANSPORT');
        $regNo = $company?->brn ?? $branch?->company_no ?? '';
        $address = strtoupper($company?->address ?? $branch?->address ?? '');
        $phone = $company?->phone ?? $branch?->phone ?? '';
        $email = $company?->email ?? $branch?->email ?? '';
        $sstNo = $company?->tin ?? '';

        $refNo = preg_replace('/\D+/', '', $quotation->number) ?: $quotation->number;
        $quoteDate = optional($quotation->quoted_at ?? $quotation->confirmed_at ?? $quotation->created_at)->format('d/m/Y');
        $issuedBy = $quotation->issued_by_name
            ?? $salesperson?->name
            ?? auth()->user()?->name
            ?? '';

        $attention = $quotation->attention
            ?? $quotation->destinations->first()?->consignee_pic
            ?? $customer?->pics?->firstWhere('is_default', true)?->name
            ?? $customer?->pics?->first()?->name
            ?? '';

        $paymentTerms = $quotation->terms_of_payment
            ?: ($customer?->credit_term_days
                ? $customer->credit_term_days.' Days'
                : 'Cash / COD');

        $customerAddress = $quotation->customer_address ?: $customer?->address;

        $contactPerson = match (true) {
            $salesperson?->name && $salesperson?->phone => $salesperson->name.' ( '.$salesperson->phone.' )',
            (bool) $salesperson?->name => $salesperson->name,
            default => '',
        };

        $formatRm = function ($amount): string {
            if ($amount === null || $amount === '') {
                return '';
            }

            $formatted = number_format((float) $amount, 2, '.', '');

            return 'RM '.str_replace('.', '-', $formatted);
        };

        $destinations = $rateMatrix['destinations'] ?? [];
        $rows = $rateMatrix['rows'] ?? [];
        $columnCount = max(1, count($destinations));
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

    {{-- Ref / page / date --}}
    <table class="meta-table">
        <tr>
            <td style="width:33%;">Ref No. : {{ $refNo }}</td>
            <td class="center" style="width:34%;">Page No. : 1</td>
            <td class="right" style="width:33%;">Date : {{ $quoteDate }}</td>
        </tr>
    </table>

    {{-- Customer --}}
    <div class="customer-block">
        <div class="bold upper">{{ $customer?->company_name ?? '—' }}</div>
        @if($customerAddress)
            <div class="upper">{!! nl2br(e($customerAddress)) !!}</div>
        @endif
        @if($customer?->email)
            <div>Email : {{ $customer->email }}</div>
        @else
            <div>Email :</div>
        @endif
        @if($customer?->phone)
            <div>Tel : {{ $customer->phone }}@if($quotation->customer_phone_alt) &nbsp; {{ $quotation->customer_phone_alt }}@endif</div>
        @endif
        @if($quotation->customer_fax)
            <div>Fax : {{ $quotation->customer_fax }}</div>
        @endif
        @if($attention)
            <div>Attention : {{ strtoupper($attention) }}</div>
        @endif
    </div>

    <div class="issued-line right">Issued by : {{ $issuedBy }}</div>

    <div class="subject">RE : {{ $quotation->title ?: 'Quotation Of Transport Charges' }}</div>
    <div class="intro">Please find the transportation charges for the following:-</div>

    {{-- Rate matrix --}}
    <table class="rates">
        <thead>
            <tr>
                <th class="item-col">Item</th>
                @foreach($destinations as $destinationLabel)
                    <th class="price-col">{{ $destinationLabel }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="item-col">
                        {{ $row['label'] }}
                        @if(! empty($row['sub']))
                            <div class="item-sub">{{ $row['sub'] }}</div>
                        @endif
                    </td>
                    @foreach($row['prices'] as $price)
                        <td class="price-col">{{ $formatRm($price) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="item-col" colspan="{{ $columnCount + 1 }}">No transport charges listed.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Footnotes from quotation notes or defaults --}}
    <div class="footnotes">
        @if($quotation->notes)
            {!! nl2br(e($quotation->notes)) !!}
        @else
            <div>*Minimum charge per point / DO RM 20</div>
            <div>*Minimum charges per pick up RM 60</div>
        @endif
    </div>

    {{-- Footer --}}
    <div class="footer-block">
        <div>Terms of payment : {{ $paymentTerms }}</div>
        @if($contactPerson)
            <div>Contact Person : {{ strtoupper($contactPerson) }}</div>
        @endif
        @if($quotation->valid_until)
            <div>Valid until : {{ $quotation->valid_until->format('d/m/Y') }}</div>
        @endif
        <div style="margin-top:10px;">If you have any doubts, please contact us.</div>
    </div>
</body>
</html>
