<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->orderNumber() }}</title>
    {{-- the Order Form (user, 29 Oct 2026): the letterhead of the quotation, then the order in boxes — customer, consignor
         and consignee side by side, delivery details, the items with their prices, instructions and the signatures --}}
    <style>
        @page { margin: 168px 36px 46px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #000; line-height: 1.35; }
        header { position: fixed; top: -150px; left: 0; right: 0; }
        .lh-name { font-size: 17px; font-weight: bold; }
        .lh-small { font-size: 9px; line-height: 1.35; }
        .lh-rule { border-top: 4px solid #000; margin: 5px 0 6px; }
        .lh-meta { width: 100%; border-collapse: collapse; }
        .lh-meta td { padding: 0; vertical-align: bottom; }
        .lh-meta .k { font-weight: bold; font-size: 10px; }
        .lh-title { font-size: 19px; font-weight: bold; text-align: right; }
        /* a QR of the document number (user, 30 Oct 2026): scanned into the system's search fields */
        /* a Code 128 barcode of the document number (user, 30 Oct 2026, in place of the QR) */
        .lh-qr { width: 190px; text-align: right; vertical-align: top; padding-top: 4px; }
        .lh-qr img { width: 180px; height: 40px; }
        .lh-qr-text { font-size: 8px; letter-spacing: .4px; margin-top: 1px; }
        .pagenum:before { content: counter(page); }

        table.box { width: 100%; border-collapse: collapse; margin: 0 0 8px; page-break-inside: avoid; }
        table.box td, table.box th { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        table.box th { background: #f0f0f0; font-size: 9px; text-align: left; text-transform: uppercase; letter-spacing: .02em; }
        table.box td.k { width: 17%; color: #333; font-size: 8.5px; text-transform: uppercase; white-space: nowrap; }
        table.box td.v { font-weight: bold; }
        .two { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0 0 8px; }
        .two > tbody > tr > td { width: 50%; padding: 0; vertical-align: top; }
        .two > tbody > tr > td + td { padding-left: 8px; }
        .two table.box { margin: 0; }
        .two table.box td.k { width: 30%; }
        .tag { display: inline-block; padding: 0 5px; border: 1px solid #000; border-radius: 3px; font-size: 8px; font-weight: bold; text-transform: uppercase; margin-left: 4px; }

        table.items { width: 100%; border-collapse: collapse; margin: 0 0 8px; }
        table.items th, table.items td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        table.items th { background: #f0f0f0; font-size: 9px; text-transform: uppercase; }
        table.items td.num, table.items th.num { text-align: right; white-space: nowrap; }
        table.items td.c, table.items th.c { text-align: center; white-space: nowrap; }
        table.items tr.total td { font-weight: bold; background: #f7f7f7; }
        table.items td.blank { border-left: 0; border-bottom: 0; }

        .notes { margin: 0 0 10px; }
        .notes .h { font-weight: bold; font-size: 9px; text-transform: uppercase; margin-bottom: 2px; }
        /* signatures (user, 30 Oct 2026): room to sign, the labels centred under the lines */
        table.sign { width: 100%; border-collapse: collapse; margin-top: 28px; page-break-inside: avoid; }
        table.sign td { width: 50%; padding: 0 18px; vertical-align: bottom; }
        table.sign .line { border-top: 1px solid #000; padding-top: 4px; font-size: 9px; text-align: center; }
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
        $fax = $branch?->fax ?? '';
        $sstNo = ($company?->tin ?: $branch?->sst_no) ?? '';

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
        $consignorAddress = $isStore
            ? trim((method_exists($store, 'label') ? $store?->label() : ($store?->name ?? 'Store')).($store?->address ? "\n".$store->address : ''))
            : ($quotation->pickup_location ?: ($quotation->customer_address ?: $customer?->address));
        $consigneeAddress = $consigneeStore
            ? trim($consigneeStore->label().($consigneeStore->address ? "\n".$consigneeStore->address : ''))
            : ($quotation->drop_off_location ?: ($quotation->consignee_address ?: $quotation->destinations->sortBy('sequence')->first()?->address));
        $destination = $quotation->toLocation?->name ?? $quotation->destinations->sortBy('sequence')->first()?->city;
        $dropOffType = collect($quotation->destination_types ?? [])->first()['drop_off_type'] ?? $quotation->destinations->first()?->drop_off_type;
        $dropOffLabel = $dropOffType && $dropOffType !== 'default' ? (\App\Enums\DropOffType::tryFrom((string) $dropOffType)?->getLabel() ?? ucfirst((string) $dropOffType)) : null;
        $stops = \App\Support\OrderStops::split($quotation->stops);
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
        $total = (float) ($quotation->total_amount ?: $subtotal + $minimumTopUp);
    @endphp

    {{-- letterhead, repeated on every page (the quotation's) --}}
    <header>
        <table class="lh-meta"><tr><td style="vertical-align:top">
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
        </td><td class="lh-qr"><img src="{{ \App\Support\BarcodeImage::code128DataUri($quotation->orderNumber()) }}" alt="Barcode {{ $quotation->orderNumber() }}"><div class="lh-qr-text">{{ $quotation->orderNumber() }}</div></td></tr></table>
        <div class="lh-rule"></div>
        <table class="lh-meta"><tr>
            <td class="k" style="width:34%">Order No. : {{ $quotation->orderNumber() }}</td>
            <td class="k" style="width:30%">Page No. : <span class="pagenum"></span></td>
            <td class="lh-title">ORDER FORM</td>
        </tr></table>
    </header>

    <table class="box">
        <tr><th colspan="4">Customer &amp; order</th></tr>
        <tr>
            <td class="k">Customer</td>
            <td class="v" style="width:50%">{{ $customer?->company_name ?? '—' }}@if ($customer?->code) <span class="muted">({{ $customer->code }})</span>@endif</td>
            <td class="k">Date</td>
            <td class="v">{{ $orderDate }}</td>
        </tr>
        <tr>
            <td class="k">Billing address</td>
            <td class="v">{!! nl2br(e($quotation->customer_address ?: ($customer?->address ?? '—'))) !!}</td>
            <td class="k">Payment term</td>
            <td class="v">{{ $paymentTerm }}@if ($paymentMethod) <span class="muted">· {{ $paymentMethod }}</span>@endif</td>
        </tr>
        <tr>
            <td class="k">Attn / contact</td>
            <td class="v">{{ $contact($quotation->attention ?: $customer?->attention, $quotation->customer_pic_phone ?: $customer?->phone) }}</td>
            <td class="k">Enquiry</td>
            <td class="v">{{ $enquiry?->reference_no ?? '—' }}@if ($receivedThrough) <span class="muted">· {{ $receivedThrough }}</span>@endif</td>
        </tr>
        <tr>
            <td class="k">Salesperson</td>
            <td class="v">{{ $salesperson?->name ?? '—' }}@if ($quotation->saLocation?->code) <span class="muted">· {{ $quotation->saLocation->code }}</span>@endif</td>
            <td class="k">Expected delivery</td>
            <td class="v">{{ $quotation->expected_delivery_date ?: '—' }}</td>
        </tr>
    </table>

    <table class="two"><tbody><tr>
        <td>
            <table class="box">
                <tr><th colspan="2">Consignor <span class="tag">{{ $consignorMode }}</span></th></tr>
                <tr><td class="k">Name</td><td class="v">{{ $quotation->consignor_name ?: ($quotation->portal_enquiry_id ? '—' : ($customer?->company_name ?? '—')) }}</td></tr>
                <tr><td class="k">PIC / Tel</td><td class="v">{{ $contact($quotation->consignor_pic_name, $quotation->consignor_pic_phone) }}</td></tr>
                <tr><td class="k">{{ $isStore ? 'Store' : 'Address' }}</td><td class="v">{!! nl2br(e($consignorAddress ?: '—')) !!}
                    @if ($stops['multi_pickup'])
                        @foreach ($stops['pickup_stops'] as $stop)<div class="muted">+ @if ($stop['name'] ?? null)<b>{{ $stop['name'] }}</b>@if (($stop['pic_name'] ?? null) || ($stop['pic_phone'] ?? null)) ({{ trim(implode(' · ', array_filter([$stop['pic_name'] ?? null, $stop['pic_phone'] ?? null]))) }})@endif · @endif{{ $stop['location'] ?: '—' }}@if ($stop['do_number']) · DO {{ $stop['do_number'] }}@endif</div>@endforeach
                    @endif
                </td></tr>
                @if ($quotation->fromLocation?->name)<tr><td class="k">Area</td><td class="v">{{ $quotation->fromLocation->name }}</td></tr>@endif
            </table>
        </td>
        <td>
            <table class="box">
                <tr><th colspan="2">Consignee <span class="tag">{{ $consigneeMode }}</span></th></tr>
                <tr><td class="k">Name</td><td class="v">{{ $quotation->consignee_name ?: ($quotation->destinations->sortBy('sequence')->first()?->consignee_name ?? '—') }}</td></tr>
                <tr><td class="k">PIC / Tel</td><td class="v">{{ $contact($quotation->consignee_pic_name, $quotation->consignee_pic_phone) }}</td></tr>
                <tr><td class="k">{{ $consigneeStore ? 'Store' : 'Address' }}</td><td class="v">{!! nl2br(e($consigneeAddress ?: '—')) !!}
                    @if ($stops['multi_dropoff'])
                        @foreach ($stops['dropoff_stops'] as $stop)<div class="muted">+ @if ($stop['name'] ?? null)<b>{{ $stop['name'] }}</b>@if (($stop['pic_name'] ?? null) || ($stop['pic_phone'] ?? null)) ({{ trim(implode(' · ', array_filter([$stop['pic_name'] ?? null, $stop['pic_phone'] ?? null]))) }})@endif · @endif{{ $stop['location'] ?: '—' }}@if ($stop['do_number']) · DO {{ $stop['do_number'] }}@endif</div>@endforeach
                    @endif
                </td></tr>
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
                <th class="num" style="width:15%">Unit price (RM)</th>
                <th class="num" style="width:15%">Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $i => $line)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $line->item_name }}@if ($line->dimensions || $line->weight)<div class="muted">{{ trim(implode(' · ', array_filter([$line->dimensions, $line->weight ? $line->weight.' kg' : null]))) }}</div>@endif@if ($line->handling_notes)<div class="muted">{{ $line->handling_notes }}</div>@endif</td>
                    <td class="num">{{ $qty($line->quantity) }}</td>
                    <td class="c">{{ $unitOf($line->uom) }}</td>
                    <td class="num">{{ $line->unit_price !== null ? number_format((float) $line->unit_price, 2) : '—' }}</td>
                    <td class="num">{{ $line->line_total !== null ? number_format((float) $line->line_total, 2) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="c muted">No items</td></tr>
            @endforelse
            @if ($minimumTopUp > 0)
                <tr><td colspan="3" class="blank"></td><td colspan="2" class="num">Subtotal</td><td class="num">{{ number_format($subtotal, 2) }}</td></tr>
                <tr><td colspan="3" class="blank"></td><td colspan="2" class="num">Minimum charge top-up</td><td class="num">{{ number_format($minimumTopUp, 2) }}</td></tr>
            @endif
            <tr class="total"><td colspan="3" class="blank"></td><td colspan="2" class="num">TOTAL</td><td class="num">{{ $rm($total) }}</td></tr>
        </tbody>
    </table>

    <table class="sign"><tr>
        <td>
            <div style="height:70px"></div>
            <div class="line">Prepared by{{ $issuedBy ? ' : '.$issuedBy : '' }}@if ($salesperson?->phone) ( {{ $salesperson->phone }} )@endif</div>
        </td>
        <td>
            <div style="height:70px"></div>
            <div class="line">Customer</div>
        </td>
    </tr></table>
</body>
</html>
