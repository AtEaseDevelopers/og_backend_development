@php
    $letterhead = $letterhead ?? [];
    $meta = $meta ?? [];
    $billTo = $bill_to ?? [];
    $lines = $lines ?? [];
    $totals = $totals ?? [];
    $amountInWords = $amount_in_words ?? '';
    $bankAccounts = $bank_accounts ?? [];
    $einvoiceQr = $einvoice_qr ?? null;
    $forPdf = $forPdf ?? false;

    $isCashBill = ($meta['type'] ?? '') === 'Cash Bill';
    $companyName = $letterhead['company_name'] ?? 'O&G Transport';
    // the barcode of the invoice number (user, 30 Oct 2026): scanned into the system's search fields
    $qr = \App\Support\BarcodeImage::code128DataUri($meta['number'] ?? null);
@endphp

{{-- the invoice / cash bill (client, 6 Nov 2026, after their AutoCount sample): the order form's letterhead and billing
     block; one line per consignment note in date order with its customer D/O, from / to area, consignee, item and amount;
     Sub total, SST, Total; the notes, the e-Invoice QR and the terms & conditions as the sample --}}
<div class="inv-document">
    <table class="lh-meta"><tr><td style="vertical-align:top">
        <div class="lh-name">{{ strtoupper($companyName) }}</div>
        <div class="lh-small">
            @if(filled($letterhead['reg_no'] ?? null))(REG NO. : {{ $letterhead['reg_no'] }} )<br>@endif
            @if(filled($letterhead['address'] ?? null)){{ strtoupper(str_replace("\n", ', ', $letterhead['address'])) }}<br>@endif
            @if(filled($letterhead['phone'] ?? null))Tel : {{ $letterhead['phone'] }}<br>@endif
            <table class="lh-meta"><tr>
                <td class="lh-small">@if(filled($letterhead['email'] ?? null))Email : {{ $letterhead['email'] }}@endif</td>
                <td class="lh-small">@if(filled($letterhead['sst_no'] ?? null))SST No. : {{ $letterhead['sst_no'] }}@endif</td>
            </tr></table>
        </div>
    </td><td class="lh-qr">@if($qr)<img src="{{ $qr }}" alt="Barcode {{ $meta['number'] ?? '' }}"><div class="lh-qr-text">{{ $meta['number'] ?? '—' }}</div>@endif</td></tr></table>
    <div class="lh-rule"></div>
    <table class="lh-meta"><tr>
        {{-- one layout for the three payment types (user, 11 Nov 2026): the title at the left, the payment type at the right --}}
        <td class="lh-title" style="text-align:left">{{ $meta['title'] ?? ($isCashBill ? 'CASH BILL' : 'INVOICE') }}</td>
        @if (filled($meta['payment_label'] ?? null))<td class="lh-title">{{ $meta['payment_label'] }}</td>@endif
    </tr></table>

    {{-- the billing customer and the invoice details: no invoice number, type or payment term here (client, 6 Nov 2026) --}}
    <table class="inv-parties">
        <tr><th colspan="2" class="bt-heading">Billing Information</th></tr>
        <tr class="bt-body">
            <td class="inv-party-col">
                @include('pdf.partials.bill-to', ['heading' => false, 'code' => (($meta['account_no'] ?? '—') !== '—') ? $meta['account_no'] : null, 'name' => $billTo['name'] ?? null, 'reg_no' => $billTo['reg_no'] ?? null, 'address' => $billTo['address'] ?? null, 'pic' => $billTo['attention'] ?? null, 'phone' => $billTo['phone'] ?? null, 'email' => $billTo['email'] ?? null])
            </td>
            <td class="inv-meta-col">
                <table class="inv-meta-table">
                    <tr><td class="inv-meta-k">Date</td><td class="inv-meta-v">{{ $meta['invoice_date'] ?? '—' }}</td></tr>
                    @if(filled($meta['due_date'] ?? null) && ($meta['due_date'] !== ($meta['invoice_date'] ?? null)))
                        <tr><td class="inv-meta-k">Due date</td><td class="inv-meta-v">{{ $meta['due_date'] }}</td></tr>
                    @endif
                    @if(filled($meta['salesperson'] ?? null))<tr><td class="inv-meta-k">Salesperson</td><td class="inv-meta-v">{{ $meta['salesperson'] }}</td></tr>@endif
                    @if(filled($meta['order_no'] ?? null))<tr><td class="inv-meta-k">Order no.</td><td class="inv-meta-v">{{ $meta['order_no'] }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    @if (! empty($other))
        @include('documents.partials.other-lines', ['lines' => $lines, 'remarks' => $remarks ?? null])
    @else
        @include('documents.partials.billing-lines', ['lines' => $lines])
    @endif

    @include('documents.partials.billing-totals', ['totals' => $totals, 'amountInWords' => $amountInWords])

    {{-- the notes and the e-Invoice QR (LHDN validated link), as the sample --}}
    <table class="inv-notes-row">
        <tr>
            <td class="inv-notes">
                <div class="inv-notes-title">Notes :</div>
                <div>1. All cheques should be crossed and made payable to: <strong>{{ $companyName }}</strong></div>
                @if($bankAccounts !== [])
                    <div>2. All payment made by TRANSFER should be made payable to: <strong>{{ collect($bankAccounts)->map(fn ($a) => ($a['bank'] ?? 'Bank').': '.($a['account'] ?? '—'))->implode(' / ') }}</strong></div>
                @else
                    <div>2. All payment made by TRANSFER should be made payable to the bank account provided by our counter.</div>
                @endif
                <div>3. Once payment is made, please forward the bank slip to us: <strong>{{ $letterhead['email'] ?? '—' }}@if(filled($letterhead['phone'] ?? null)) / {{ $letterhead['phone'] }}@endif</strong></div>
                <div class="inv-generated">THIS IS COMPUTER GENERATED<br>DOCUMENT NO SIGNATURE REQUIRED</div>
            </td>
            @if (empty($other))
            <td class="inv-einv">
                @if($einvoiceQr)
                    <img src="{{ $einvoiceQr }}" alt="e-Invoice QR" class="inv-einv-qr">
                @else
                    <div class="inv-einv-box"></div>
                @endif
                <div class="inv-einv-label">LHDN Validated Link</div>
            </td>
            @endif
        </tr>
    </table>

    {{-- the terms & conditions, as the client's sample (6 Nov 2026); they are the transport terms, so not on an
         Other Invoice --}}
    @if (empty($other))
    <div class="inv-terms">
        <div class="inv-terms-title">Terms &amp; Conditions</div>
        <div>1. Our company only provides Transportation Services. Customer should be held responsible for any contravention of Law, Customs Duties, Delay, Robbery or Accidents. Our company will not be responsible for it.</div>
        <div>2. Coverage insurance for goods in transit must be done by customer personally. Our company will not be responsible for it.</div>
        <div>3. Claim is not allowable if: a) The damage or loss was caused wholly or partly by the negligent or wrongful act of the claimant. b) Possession of the property lost or damaged was not incident to service or not reasonable or proper under the circumstances.</div>
        <div>4. Any claim is not allowed to be deducted in our transport charges.</div>
        <div>5. We will only be responsible for the compensation of goods lost or damaged if that is caused by the negligence of our transportation services. The maximum amount of the compensation payable to claimant would be the tenfold of the transportation charges. Other unreasonable requests will not be acceptable.</div>
        <div>6. Transportation charges exclude insurance coverage.</div>
        <div>7. Any claim must be notified within 7 working days from the date of goods delivered. After the expiry of this period, our company retains the right to refuse any request made by customers.</div>
        <div>8. Delivery dates provided are only estimates; our company shall not be liable for any delay in delivery.</div>
    </div>
    @endif
</div>
