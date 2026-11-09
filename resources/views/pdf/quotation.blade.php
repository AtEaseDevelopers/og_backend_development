<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->orderNumber() }} {{ ($showAmounts ?? true) ? 'Order Quotation' : 'Order Form' }}</title>
    {{-- the Order Form (user, 29 Oct 2026): the letterhead of the quotation, then the order in boxes — customer, consignor
         and consignee side by side, delivery details, the items with their prices, instructions and the signatures --}}
    @include('pdf.partials.common')
    <style>
        /* larger type and tighter margins (client, 5 Nov 2026: fill the page) */
        @page { margin: 172px 30px 40px 30px; }
        body { font-size: 10.5px; line-height: 1.35; }
        header { position: fixed; top: -156px; left: 0; right: 0; }
        .lh-name { font-size: 17px; font-weight: bold; }
        .lh-small { font-size: 10px; line-height: 1.35; }
        .lh-rule { border-top: 4px solid #000; margin: 5px 0 6px; }
        .lh-meta { width: 100%; border-collapse: collapse; }
        .lh-meta td { padding: 0; vertical-align: bottom; }
        .lh-meta .k { font-weight: bold; font-size: 10px; }
        .lh-title { font-size: 19px; font-weight: bold; text-align: right; }
        /* the Code 128 barcode of the document number (user, 30 Oct 2026) is styled in pdf.partials.common */

        /* the header blocks without grid lines, as the consignment note (client, 6 Nov 2026): only the items are a table */
        table.box { width: 100%; border-collapse: collapse; margin: 0 0 10px; page-break-inside: avoid; }
        table.box td, table.box th { border: 0; padding: 2px 8px 2px 0; vertical-align: top; }
        table.box th { font-size: 10.5px; text-align: left; text-transform: uppercase; letter-spacing: .02em; border-bottom: 1px solid #000; padding: 0 0 3px; }
        /* labels bold, values in normal text (user, 6 Nov 2026) */
        table.box td.k { width: 17%; font-size: 9.5px; font-weight: bold; text-transform: uppercase; white-space: nowrap; }
        /* compact blocks (client, 7 Nov 2026): the billing side and the order side are separate tables, so a wrapped
           value on one side leaves no gap on the other */
        .two-info { width: 100%; border-collapse: collapse; margin: 0 0 10px; page-break-inside: avoid; }
        .two-info > tbody > tr > td { width: 50%; padding: 0; vertical-align: top; }
        .two-info > tbody > tr > td + td { padding-left: 10px; }
        .two-info table.box { margin: 0; }
        .two-info table.box td { padding: 1px 6px 1px 0; }
        .two-info table.box td.k { width: 28%; }
        .two-info table.box th { padding-bottom: 2px; }
        .nowrap { white-space: nowrap; }
        table.box td.v { font-weight: normal; }
        .two { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0 0 8px; }
        .two > tbody > tr > td { width: 50%; padding: 0; vertical-align: top; }
        .two > tbody > tr > td + td { padding-left: 8px; }
        .two table.box { margin: 0; }
        .two table.box td.k { width: 30%; }
        /* the pick-up / delivery mode as plain text, not a button (client, 6 Nov 2026) */
        .tag { font-size: 9.5px; font-weight: normal; text-transform: none; margin-left: 4px; }
        .tag:before { content: '· '; }
        .terms { margin-top: 12px; page-break-inside: avoid; }
        .terms .h { font-weight: bold; font-size: 10.5px; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 3px; margin-bottom: 4px; }
        .terms ol { margin: 0; padding-left: 16px; }
        .terms li { margin-bottom: 2px; }

        /* table.items: pdf.partials.common (the order form's items table, used by every document) */

        .notes { margin: 0 0 10px; }
        .notes .h { font-weight: bold; font-size: 10px; text-transform: uppercase; margin-bottom: 2px; }
        /* signatures (user, 30 Oct 2026): room to sign, the labels centred under the lines */
        table.sign { width: 100%; border-collapse: collapse; margin-top: 28px; page-break-inside: avoid; }
        table.sign td { width: 50%; padding: 0 18px; vertical-align: bottom; }
        table.sign .line { border-top: 1px solid #000; padding-top: 4px; font-size: 10px; text-align: center; }
        .muted { color: #444; font-weight: normal; }
    </style>
</head>
<body>
    @php
        $company = $quotation->company;
        $branch = $quotation->branch;
        $customer = $quotation->customer;
        $enquiry = $quotation->portalEnquiry;
        $salesperson = $quotation->salesperson;

        $companyName = strtoupper($company?->name ?? $branch?->company_name ?? 'O&G TRANSPORT');
        $regNo = $company?->brn ?? $branch?->company_no ?? '';
        $address = strtoupper($company?->address ?? $branch?->address ?? '');
        $phone = $company?->phone ?? $branch?->phone ?? '';
        $email = $company?->email ?? $branch?->email ?? '';
        $sstNo = ($company?->tin ?: $branch?->sst_no) ?? '';

        // the Order Form (no prices) and the Order Quotation (with prices) share this layout (user, 30 Oct 2026)
        $showAmounts = $showAmounts ?? true;
        // the Order Quotation becomes the Order Form once the customer has confirmed (client, 7 Nov 2026)
        $isConfirmed = $quotation->confirmed_at !== null
            || in_array($quotation->status, [\App\Enums\QuotationStatus::Accepted, \App\Enums\QuotationStatus::Confirmed, \App\Enums\QuotationStatus::Converted], true);
        $documentTitle = $isConfirmed ? 'ORDER FORM' : 'ORDER QUOTATION';
        $orderDate = optional($quotation->confirmed_at ?? $quotation->quoted_at ?? $quotation->created_at)->format('d/m/Y');
        $issuedBy = $quotation->issued_by_name ?: ($salesperson?->name ?? '');
        $rm = fn ($amount) => 'RM '.number_format((float) $amount, 2, '.', ',');
        $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
        $contact = fn (?string $name, ?string $phone) => trim(implode(' · ', array_filter([$name, $phone]))) ?: '—';

        $orderType = $quotation->orderType();
        $paymentTerm = trim(($orderType?->getLabel() ?? ($quotation->terms_of_payment ?: '—'))
            .($orderType === \App\Enums\OrderType::Term && $customer?->credit_term_days ? ' · '.$customer->credit_term_days.' days' : ''));
        $paymentMethod = $quotation->payment_method ? ucwords(str_replace('_', ' ', (string) $quotation->payment_method)) : null;
        $receivedThrough = $enquiry?->received_through ? ucwords(str_replace('_', ' ', (string) $enquiry->received_through)) : null;

        // consignor: picked up, or brought to a store by the customer; consignee: delivered, or collected at a store
        $isStore = $quotation->service_type === \App\Enums\ServiceType::Store;
        $store = $quotation->store ?? $quotation->storeBranch;
        $consigneeStore = $quotation->consigneeStore;
        $consignorMode = $isStore ? 'Customer Drop Off' : 'Pick Up';
        $consigneeMode = $consigneeStore ? 'Self Collection' : 'Deliver';
        // a Customer Drop Off keeps the consignor's own address (user, 3 Nov 2026); the store is named on its own line
        $consignorAddress = $quotation->pickup_location ?: ($quotation->customer_address ?: $customer?->address);
        $dropOffStore = $isStore ? trim((string) (method_exists($store, 'label') ? $store?->label() : ($store?->name ?? ''))) : '';
        $consigneeAddress = $consigneeStore
            ? trim($consigneeStore->label().($consigneeStore->address ? "\n".$consigneeStore->address : ''))
            : ($quotation->drop_off_location ?: ($quotation->consignee_address ?: $quotation->destinations->sortBy('sequence')->first()?->address));
        $destination = $quotation->toLocation?->name ?? $quotation->destinations->sortBy('sequence')->first()?->city;
        $dropOffType = collect($quotation->destination_types ?? [])->first()['drop_off_type'] ?? $quotation->destinations->first()?->drop_off_type;
        $dropOffLabel = $dropOffType && $dropOffType !== 'default' ? (\App\Enums\DropOffType::tryFrom((string) $dropOffType)?->getLabel() ?? ucfirst((string) $dropOffType)) : null;
        $stops = \App\Support\OrderStops::split($quotation->stops);
        // a pick-up / drop-off point's lines: party (PIC · tel), address, DO
        $stopLines = function (array $stop, ?string $fallbackName): string {
            $pic = trim(implode(' · ', array_filter([$stop['pic_name'] ?? null, $stop['pic_phone'] ?? null])));
            $lines = ['<b>'.e(($stop['name'] ?? null) ?: ($fallbackName ?: '—')).'</b>'.($pic !== '' ? ' <span class="muted">('.e($pic).')</span>' : '')];
            $lines[] = nl2br(e($stop['location'] ?: '—'));
            if ($stop['do_number'] ?? null) {
                $lines[] = '<span class="muted">DO '.e($stop['do_number']).'</span>';
            }

            return implode('<br>', $lines);
        };
        $doNumbers = array_values(array_unique(array_filter(array_merge([(string) $quotation->customer_do_number], \App\Support\OrderStops::doNumbers($quotation->stops)))));

        $lines = $quotation->lines->sortBy('id')->values();
        // the UOM column: a product's unit (its sub-category for a measurement, Unit for an item, Trip for a lorry), not its code
        $products = \App\Domains\MasterData\Models\Uom::query()->withoutGlobalScopes()->whereIn('code', $lines->pluck('uom')->filter()->all())->get(['code', 'category', 'sub_category'])->keyBy('code');
        $unitOf = function (?string $uom) use ($products): string {
            $product = $uom ? $products->get($uom) : null;

            return match (true) {
                // an unknown product code (CODE_LIKE_THIS) is a unit too; a real unit text (pcs, pallet) is printed as it is
                $product === null => $uom === null || $uom === '' ? '—' : (preg_match('/^[A-Z0-9_]+$/', $uom) && str_contains($uom, '_') ? 'Unit' : (\App\Support\QuantityLabel::unit($uom) ?? $uom)),
                $product->category === \App\Domains\MasterData\Models\Uom::LORRY => 'Trip',
                $product->category === \App\Domains\MasterData\Models\Uom::MEASUREMENT && filled($product->sub_category) => ucfirst(strtolower((string) $product->sub_category)),
                default => 'Unit',
            };
        };
        $minimumTopUp = (float) $quotation->destinations->sum('minimum_charge_applied');
        $subtotal = (float) ($quotation->subtotal ?: $lines->sum('line_total'));
        $sst = (float) $quotation->tax_amount;
        $sstRate = rtrim(rtrim(number_format(\App\Support\QuotationMatrix::sstRate(), 2, '.', ''), '0'), '.');
        $total = (float) ($quotation->total_amount ?: $subtotal + $minimumTopUp + $sst);
        // paid or not, with the payment method (client, 6 Nov 2026): paid once the amount recorded covers the total
        $paidAmount = max((float) $quotation->paid_amount, (float) ($quotation->proformaInvoice?->paid_amount ?? 0));
        $paymentStatus = $total > 0 && $paidAmount >= $total ? 'PAID' : ($paidAmount > 0 ? 'PARTLY PAID' : 'UNPAID');
    @endphp

    {{-- letterhead, repeated on every page (the quotation's) --}}
    <header>
        <table class="lh-meta"><tr><td style="vertical-align:top">
        <div class="lh-name">{{ $companyName }}</div>
        <div class="lh-small">
            @if ($regNo)(REG NO. : {{ $regNo }} )<br>@endif
            @if ($address){{ $address }}<br>@endif
            {{-- no fax on any document (client, 5 Nov 2026) --}}
            @if ($phone)Tel : {{ $phone }}<br>@endif
            <table class="lh-meta"><tr>
                <td class="lh-small">@if ($email)Email : {{ $email }}@endif</td>
                <td class="lh-small">@if ($sstNo)SST No. : {{ $sstNo }}@endif</td>
            </tr></table>
        </div>
        </td><td class="lh-qr"><img src="{{ \App\Support\BarcodeImage::code128DataUri($quotation->orderNumber()) }}" alt="Barcode {{ $quotation->orderNumber() }}"><div class="lh-qr-text">{{ $quotation->orderNumber() }}</div></td></tr></table>
        <div class="lh-rule"></div>
        {{-- the number is under the barcode only; the page number is stamped bottom right (client, 5 Nov 2026);
             the order type (Cash, Credit / Term, COD) at the left in the title's size, paid or not with the payment
             method under it; the title at the right (client, 6 Nov 2026) --}}
        {{-- the title at the left, the order type (CASH, CREDIT / TERM, COD) at the right; no payment line (client, 7 Nov 2026) --}}
        {{-- the payment term (CASH, CREDIT / TERM, COD) at the right of the title, as the CSN (client, 7 Nov 2026) --}}
        <table class="lh-meta"><tr>
            <td class="lh-title" style="text-align:left">{{ $documentTitle }}</td>
            <td class="lh-title">{{ strtoupper($orderType?->getLabel() ?? ($quotation->terms_of_payment ?: '')) }}</td>
        </tr></table>
    </header>

    {{-- Billing Information (client, 7 Nov 2026): the billing customer at the left (code + name, Reg. No., address, PIC,
         Tel, Email) and the order details at the right, each its own compact table --}}
    <table class="two-info"><tbody><tr>
        <td>
            @include('pdf.partials.bill-to', ['code' => $customer?->code, 'name' => $customer?->company_name, 'reg_no' => $customer?->brn, 'address' => $quotation->customer_address ?: $customer?->address, 'pic' => $quotation->attention ?: $customer?->attention, 'phone' => $quotation->customer_pic_phone ?: $customer?->phone, 'email' => $customer?->email])
        </td>
        <td>
            <table class="box">
                <tr><th colspan="2">Order</th></tr>
                <tr><td class="k">Date</td><td class="v">{{ $orderDate }}</td></tr>
                <tr><td class="k">Enquiry</td><td class="v">{{ $enquiry?->reference_no ?? '—' }}@if ($receivedThrough) <span class="muted">· {{ $receivedThrough }}</span>@endif</td></tr>
                <tr><td class="k">Salesperson</td><td class="v">{{ $salesperson?->name ?? '—' }}@if ($quotation->saLocation?->code) <span class="muted">· {{ $quotation->saLocation->code }}</span>@endif</td></tr>
                {{-- "Expected pick up" (client, 6 Nov 2026): a dash when the customer drops the goods off --}}
                <tr><td class="k">Expected pick up</td><td class="v">{{ $isStore ? '—' : ($quotation->expected_delivery_date ?: '—') }}</td></tr>
            </table>
        </td>
    </tr></tbody></table>

    <table class="two"><tbody><tr>
        <td>
            <table class="box">
                <tr><th colspan="2">Consignor [{{ $consignorMode }}]@if ($stops['multi_pickup'] && $stops['pickup_stops'] !== []) <span class="tag">{{ count($stops['pickup_stops']) }} pick-up addresses</span>@endif</th></tr>
                <tr><td class="k">Name</td><td class="v">{{ $quotation->consignor_name ?: ($quotation->portal_enquiry_id ? '—' : ($customer?->company_name ?? '—')) }}</td></tr>
                <tr><td class="k">PIC / Tel</td><td class="v">{{ $contact($quotation->consignor_pic_name, $quotation->consignor_pic_phone) }}</td></tr>
                @if ($stops['multi_pickup'] && $stops['pickup_stops'] !== [])
                    {{-- one consignor with several pick-up addresses (user, 30 Oct 2026): one numbered row each, address and DO --}}
                    @foreach ($stops['pickup_stops'] as $stop)
                        <tr><td class="k">Pick-up {{ $loop->iteration }}</td><td class="v">{!! nl2br(e($stop['location'] ?: '—')) !!}@if ($stop['do_number'])<br><span class="muted">DO {{ $stop['do_number'] }}</span>@endif</td></tr>
                    @endforeach
                @else
                <tr><td class="k">Address</td><td class="v">{!! nl2br(e($consignorAddress ?: '—')) !!}</td></tr>
                @endif
                @if ($dropOffStore !== '')<tr><td class="k">Drop off at</td><td class="v">{{ $dropOffStore }}</td></tr>@endif
                @if ($quotation->fromLocation?->name)<tr><td class="k">Area</td><td class="v">{{ $quotation->fromLocation->name }}</td></tr>@endif
            </table>
        </td>
        <td>
            <table class="box">
                <tr><th colspan="2">Consignee [{{ $consigneeMode }}]@if ($stops['multi_dropoff'] && $stops['dropoff_stops'] !== []) <span class="tag">{{ count($stops['dropoff_stops']) }} consignees</span>@endif</th></tr>
                @if ($stops['multi_dropoff'] && $stops['dropoff_stops'] !== [])
                    @foreach ($stops['dropoff_stops'] as $stop)
                        <tr><td class="k">Consignee {{ $loop->iteration }}</td><td class="v">{!! $stopLines($stop, $quotation->consignee_name ?: ($quotation->destinations->sortBy('sequence')->first()?->consignee_name ?? null)) !!}</td></tr>
                    @endforeach
                @else
                <tr><td class="k">Name</td><td class="v">{{ $quotation->consignee_name ?: ($quotation->destinations->sortBy('sequence')->first()?->consignee_name ?? '—') }}</td></tr>
                <tr><td class="k">PIC / Tel</td><td class="v">{{ $contact($quotation->consignee_pic_name, $quotation->consignee_pic_phone) }}</td></tr>
                <tr><td class="k">{{ $consigneeStore ? 'Store' : 'Address' }}</td><td class="v">{!! nl2br(e($consigneeAddress ?: '—')) !!}</td></tr>
                @endif
                <tr><td class="k">Destination</td><td class="v">{{ $destination ?? '—' }}@if ($dropOffLabel) <span class="muted">· {{ $dropOffLabel }}</span>@endif</td></tr>
            </table>
        </td>
    </tr></tbody></table>

    <table class="box">
        <tr>
            <td class="k">DO number</td>
            <td class="v">{{ $doNumbers === [] ? '—' : implode(', ', $doNumbers) }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="c" style="width:5%">No.</th>
                <th>Item</th>
                <th class="num" style="width:9%">Qty</th>
                <th class="c" style="width:12%">UOM</th>
                @if ($showAmounts)
                    <th class="num" style="width:15%">Unit price (RM)</th>
                    <th class="num" style="width:15%">Amount (RM)</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $i => $line)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $line->item_name }}@if ($line->dimensions || $line->weight)<div class="muted">{{ trim(implode(' · ', array_filter([$line->dimensions, $line->weight ? $line->weight.' kg' : null]))) }}</div>@endif@if ($line->handling_notes)<div class="muted">{{ $line->handling_notes }}</div>@endif</td>
                    <td class="num">{{ $qty($line->quantity) }}</td>
                    <td class="c">{{ $unitOf($line->uom) }}</td>
                    @if ($showAmounts)
                        <td class="num">{{ $line->unit_price !== null ? number_format((float) $line->unit_price, 2) : '—' }}</td>
                        <td class="num">{{ $line->line_total !== null ? number_format((float) $line->line_total, 2) : '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $showAmounts ? 6 : 4 }}" class="c muted">No items</td></tr>
            @endforelse
            {{-- Subtotal, SST, then Total, always (client, 6 Nov 2026) --}}
            @if ($showAmounts)
                <tr class="tot"><td colspan="3" class="blank"></td><td colspan="2" class="num lbl">Subtotal</td><td class="num amt">{{ number_format($subtotal, 2) }}</td></tr>
                @if ($minimumTopUp > 0)<tr class="tot"><td colspan="3" class="blank"></td><td colspan="2" class="num lbl">Minimum charge top-up</td><td class="num amt">{{ number_format($minimumTopUp, 2) }}</td></tr>@endif
                <tr class="tot"><td colspan="3" class="blank"></td><td colspan="2" class="num lbl">SST {{ $sst > 0 ? $sstRate.'%' : '' }}</td><td class="num amt">{{ number_format($sst, 2) }}</td></tr>
            @endif
            @if ($showAmounts)
                <tr class="tot total"><td colspan="3" class="blank"></td><td colspan="2" class="num lbl">TOTAL</td><td class="num amt">{{ $rm($total) }}</td></tr>
            @endif
        </tbody>
    </table>

    {{-- no signatures (client, 6 Nov 2026); terms and conditions — sample wording until the client provides theirs --}}
    <div class="terms">
        <div class="h">Terms &amp; conditions</div>
        <ol>
            <li>This quotation is valid for 30 days from the date above and is subject to lorry availability.</li>
            <li>Prices exclude SST unless stated; a minimum charge per pick-up applies.</li>
            <li>Goods are carried at the owner's risk; please declare fragile or hazardous items and insure where required.</li>
            <li>Additional charges apply for waiting time, non-standard locations, construction sites and outskirt areas.</li>
            <li>Payment is due as per the payment term above; cash orders are payable before delivery.</li>
        </ol>
        <div class="muted" style="margin-top:3px;font-size:9px">(Sample terms — the client's actual terms and conditions to follow.)</div>
    </div>
</body>
</html>
