@php
    $letterhead = $letterhead ?? [];
    $meta = $meta ?? [];
    $billTo = $bill_to ?? [];
    $consignor = $consignor ?? [];
    $consignees = $consignees ?? [];
    $consignors = $consignors ?? [];
    $route = $route ?? [];
    $lines = $lines ?? [];
    $footer = $footer ?? [];
    $forPdf = $forPdf ?? false;
    $documentTitle = $document_title ?? 'Consignment Note';
    // two layouts (client, 6 Nov 2026): "stack" — the consignees at the bottom; "side" — each consignee beside the
    // consignor in a tall box to chop and sign
    $layout = ($layout ?? 'stack') === 'side' ? 'side' : 'stack';

    $nl = fn (?string $value): string => filled($value) ? nl2br(e($value)) : '—';
    // the barcode of the document number (user, 30 Oct 2026) — scanned into the system's search fields
    $qr = \App\Support\BarcodeImage::code128DataUri($meta['qr_value'] ?? ($meta['number'] ?? null));
    $consigneeList = $consignees ?: [['name' => '—', 'address' => null, 'contact' => null, 'do_number' => null]];
    $consignorList = $consignors ?: [['name' => '—', 'address' => null, 'contact' => null, 'do_number' => null]];

    $consignorBox = function (array $party, int $n, int $count) use ($nl) {
        $out = '<div class="csn-doc-label">Consignor'.($count > 1 ? ' '.$n : '').':</div>';
        $out .= '<div class="csn-doc-party-name">'.e($party['name'] ?? '—').'</div>';
        if (filled($party['contact'] ?? null)) {
            $out .= '<div>'.e($party['contact']).'</div>';
        }
        if (($party['pickups'] ?? []) !== []) {
            $out .= '<table class="csn-doc-pickups">';
            foreach ($party['pickups'] as $i => $pickup) {
                $out .= '<tr><td class="csn-doc-pickup-no">Pick-up '.($i + 1).'</td><td class="csn-doc-pickup-addr">'.$nl($pickup['address'] ?? null)
                    .(filled($pickup['do_number'] ?? null) ? ' <span class="csn-doc-pickup-do"><span class="csn-doc-label">D/O No:</span> '.e($pickup['do_number']).'</span>' : '').'</td></tr>';
            }
            $out .= '</table>';
        } else {
            $out .= '<div class="csn-doc-party-address">'.$nl($party['address'] ?? null).'</div>';
            if (filled($party['do_number'] ?? null)) {
                $out .= '<div><span class="csn-doc-label">D/O No:</span> '.e($party['do_number']).'</div>';
            }
        }

        return $out;
    };
    $consigneeBox = function (array $consignee, int $n, int $count) use ($nl) {
        $out = '<div class="csn-doc-label">Consignee'.($count > 1 ? ' '.$n : '').':</div>';
        $out .= '<div class="csn-doc-party-name csn-doc-consignee-name">'.e($consignee['name'] ?? '—').'</div>';
        $out .= '<div class="csn-doc-party-address">'.$nl($consignee['address'] ?? null).'</div>';
        if (filled($consignee['contact'] ?? null)) {
            $out .= '<div>'.e($consignee['contact']).'</div>';
        }
        if (filled($consignee['do_number'] ?? null)) {
            $out .= '<div><span class="csn-doc-label">D/O No:</span> '.e($consignee['do_number']).'</div>';
        }
        // chop & sign: room for the chop, then Name, IC No. and Date on their own lines (client, 6 Nov 2026)
        $out .= '<div class="csn-doc-sign-area"><div class="csn-doc-sign-line"></div><div class="csn-doc-sign-note">Please Chop, Sign &amp; Return.</div>'
            .'<div class="csn-doc-sign-field">Name : <span class="csn-doc-sign-blank"></span></div>'
            .'<div class="csn-doc-sign-field">IC No. : <span class="csn-doc-sign-blank"></span></div>'
            .'<div class="csn-doc-sign-field">Date : <span class="csn-doc-sign-blank"></span></div></div>';

        return $out;
    };
@endphp

{{-- the client's CSN layout (user, 30 Oct 2026; client, 6 Nov 2026): the letterhead as the order form's, the payment
     (PAID / COD / CREDIT / TERM) left of the title; billing customer, items, remark, consignor, the route in large type,
     then every consignee with a chop & sign box (Name, IC No., Date) --}}
<div @class(['csn-document', 'csn-document--pdf' => $forPdf, 'csn-document--no-pricing', 'csn-document--side' => $layout === 'side'])>
    {{-- Letterhead as the order form's --}}
    <table class="lh-meta"><tr><td style="vertical-align:top">
        <div class="lh-name">{{ strtoupper($letterhead['company_name'] ?? 'O&G Transport') }}</div>
        <div class="lh-small">
            @if(filled($letterhead['reg_no'] ?? null))(REG NO. : {{ $letterhead['reg_no'] }} )<br>@endif
            @if(filled($letterhead['address'] ?? null)){{ strtoupper(str_replace("\n", ', ', $letterhead['address'])) }}<br>@endif
            @if(filled($letterhead['phone'] ?? null))Tel : {{ $letterhead['phone'] }}<br>@endif
            <table class="lh-meta"><tr>
                <td class="lh-small">@if(filled($letterhead['email'] ?? null))Email : {{ $letterhead['email'] }}@endif</td>
                <td class="lh-small">@if(filled($letterhead['sst_no'] ?? null))SST No. : {{ $letterhead['sst_no'] }}@endif</td>
            </tr></table>
        </div>
    </td><td class="lh-qr">@if ($qr)<img src="{{ $qr }}" alt="Barcode {{ $meta['number'] ?? '' }}"><div class="lh-qr-text">{{ $meta['number'] ?? '' }}</div>@endif</td></tr></table>
    <div class="lh-rule"></div>
    <table class="lh-meta"><tr>
        <td class="lh-title" style="text-align:left">{{ $meta['payment_label'] ?? '' }}</td>
        <td class="lh-title">{{ strtoupper($documentTitle) }}</td>
    </tr></table>

    {{-- Billing customer (code + name, Reg. No., address, PIC, Tel, Email) and the document details --}}
    <table class="csn-doc-parties">
        <tr>
            <td class="csn-doc-party-col">
                @include('pdf.partials.bill-to', ['code' => $billTo['code'] ?? null, 'name' => $billTo['name'] ?? null, 'reg_no' => $billTo['reg_no'] ?? null, 'address' => $billTo['address'] ?? null, 'pic' => $billTo['pic'] ?? null, 'phone' => $billTo['phone'] ?? null, 'email' => $billTo['email'] ?? null])
            </td>
            <td class="csn-doc-meta-col">
                <table class="csn-doc-meta-table">
                    <tr><td class="csn-doc-meta-label">Date</td><td>{{ $meta['issued_at'] ?? '—' }}</td></tr>
                    <tr><td class="csn-doc-meta-label">Invoice No</td><td>{{ $meta['invoice_number'] ?? '—' }}</td></tr>
                    <tr><td class="csn-doc-meta-label">Job Sheet</td><td>{{ $meta['job_sheet_number'] ?? '—' }}</td></tr>
                    <tr><td class="csn-doc-meta-label">Lorry No</td><td>{{ $meta['lorry_number'] ?? '—' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Items (no pricing), in the order form's format --}}
    <table class="csn-doc-items">
        <thead>
            <tr>
                <th class="csn-doc-no">No.</th>
                <th class="csn-doc-desc">Item</th>
                <th class="csn-doc-qty">Qty</th>
                <th class="csn-doc-uom">UOM</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td class="csn-doc-no">{{ $loop->iteration }}</td>
                    <td class="csn-doc-desc">{{ ltrim((string) ($line['description'] ?? '—'), '- ') }}</td>
                    <td class="csn-doc-qty">{{ $line['quantity'] ?? '—' }}</td>
                    <td class="csn-doc-uom">{{ $line['uom'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="csn-doc-no">—</td>
                    <td class="csn-doc-desc"><div class="csn-doc-muted">No cargo details.</div></td>
                    <td class="csn-doc-qty">—</td>
                    <td class="csn-doc-uom">—</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Remark (the driver note) and marking: always there, to write on when empty --}}
    <table class="csn-doc-remark">
        <tr>
            <td>
                <div class="csn-doc-footer-field"><span class="csn-doc-label">Remark:</span> {{ filled($footer['remarks'] ?? null) ? $footer['remarks'] : '' }}</div>
                @if(filled($footer['marking'] ?? null))
                    <div class="csn-doc-footer-field"><span class="csn-doc-label">Marking:</span> {{ $footer['marking'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if ($layout === 'stack')
        {{-- Consignor(s), the route in large type, then every consignee with its chop & sign box --}}
        <table class="csn-doc-consignees csn-doc-consignors">
            @foreach (array_chunk($consignorList, 2) as $pair)
                <tr>
                    @foreach ($pair as $party)
                        <td class="csn-doc-consignee-box csn-doc-consignor-box" @if (count($pair) === 1) colspan="2" @endif>{!! $consignorBox($party, $loop->parent->index * 2 + $loop->iteration, count($consignorList)) !!}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>

        <div class="csn-doc-route">
            <span class="csn-doc-route-label">From:</span> {{ $route['from'] ?? '—' }}
            <span class="csn-doc-route-arrow">&rarr;</span>
            <span class="csn-doc-route-label">To:</span> {{ $route['to'] ?? '—' }}
        </div>

        <table class="csn-doc-consignees">
            @foreach (array_chunk($consigneeList, 2) as $pair)
                <tr>
                    @foreach ($pair as $consignee)
                        <td class="csn-doc-consignee-box" @if (count($pair) === 1) colspan="2" @endif>{!! $consigneeBox($consignee, $loop->parent->index * 2 + $loop->iteration, count($consigneeList)) !!}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @else
        {{-- the route, then the consignor(s) at the left and the consignee(s) beside, tall enough to chop and sign --}}
        <div class="csn-doc-route">
            <span class="csn-doc-route-label">From:</span> {{ $route['from'] ?? '—' }}
            <span class="csn-doc-route-arrow">&rarr;</span>
            <span class="csn-doc-route-label">To:</span> {{ $route['to'] ?? '—' }}
        </div>

        <table class="csn-doc-consignees csn-doc-side">
            <tr>
                <td class="csn-doc-consignee-box csn-doc-consignor-box csn-doc-side-left">
                    @foreach ($consignorList as $party)
                        <div class="csn-doc-side-party">{!! $consignorBox($party, $loop->iteration, count($consignorList)) !!}</div>
                    @endforeach
                </td>
                <td class="csn-doc-consignee-box csn-doc-side-right">
                    @foreach ($consigneeList as $consignee)
                        <div class="csn-doc-side-party">{!! $consigneeBox($consignee, $loop->iteration, count($consigneeList)) !!}</div>
                    @endforeach
                </td>
            </tr>
        </table>
    @endif
</div>
