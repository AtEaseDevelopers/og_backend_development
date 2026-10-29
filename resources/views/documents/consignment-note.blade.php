@php
    $letterhead = $letterhead ?? [];
    $meta = $meta ?? [];
    $billTo = $bill_to ?? [];
    $consignor = $consignor ?? [];
    $consignees = $consignees ?? [];
    $route = $route ?? [];
    $lines = $lines ?? [];
    $footer = $footer ?? [];
    $forPdf = $forPdf ?? false;
    $documentTitle = $document_title ?? 'Consignment Note';

    $logoSrc = $forPdf
        ? ($letterhead['logo_src'] ?? null)
        : ($letterhead['logo_url'] ?? null);

    $nl = fn (?string $value): string => filled($value) ? nl2br(e($value)) : '—';
    // the QR of the document number (user, 30 Oct 2026: a QR only, no barcode) — scanned into the system's search fields
    $qr = \App\Support\QrCodeImage::dataUri($meta['qr_value'] ?? ($meta['number'] ?? null));
@endphp

{{-- the client's CSN layout (user, 30 Oct 2026): no pricing; the billing customer on the left and the consignor on the
     right; the QR of the number, the D/O, job sheet, invoice and lorry numbers; the driver note at Remark; every consignee at
     the bottom with a chop & sign box --}}
<div @class(['csn-document', 'csn-document--pdf' => $forPdf, 'csn-document--no-pricing'])>
    {{-- Letterhead + title --}}
    <table class="csn-doc-header">
        <tr>
            @if($logoSrc)
                <td class="csn-doc-logo-cell"><img src="{{ $logoSrc }}" alt="O&amp;G Transport" class="csn-doc-logo"></td>
            @endif
            <td class="csn-doc-company-cell csn-doc-company-cell-left">
                <div class="csn-doc-company-name">{{ $letterhead['company_name'] ?? 'O&G Transport' }}</div>
                @if(filled($letterhead['reg_no'] ?? null))
                    <div>Co. Reg. {{ $letterhead['reg_no'] }}</div>
                @endif
                @if(filled($letterhead['address'] ?? null))
                    <div>{!! $nl($letterhead['address']) !!}</div>
                @endif
                <div>
                    @if(filled($letterhead['phone'] ?? null))
                        Tel: {{ $letterhead['phone'] }}
                    @endif
                    @if(filled($letterhead['email'] ?? null))
                        @if(filled($letterhead['phone'] ?? null)) &nbsp; @endif
                        E-mail: {{ $letterhead['email'] }}
                    @endif
                </div>
            </td>
            <td class="csn-doc-title-cell">
                @if ($qr)
                    <div class="csn-doc-qr-title"><img src="{{ $qr }}" alt="QR {{ $meta['number'] ?? '' }}"></div>
                @endif
                <div class="csn-doc-title">{{ $documentTitle }}</div>
            </td>
        </tr>
    </table>

    {{-- Bill to (left), consignor (right), the document numbers with the QR --}}
    <table class="csn-doc-parties">
        <tr>
            <td class="csn-doc-party-col csn-doc-party-box">
                <div class="csn-doc-label">Bill To:</div>
                <div class="csn-doc-party-name">{{ $billTo['name'] ?? '—' }}</div>
                @if(filled($billTo['reg_no'] ?? null))<div>Co. Reg. {{ $billTo['reg_no'] }}</div>@endif
                <div class="csn-doc-party-address">{!! $nl($billTo['address'] ?? null) !!}</div>
                @if(filled($billTo['contact'] ?? null))<div>{{ $billTo['contact'] }}</div>@endif
            </td>
            <td class="csn-doc-party-col csn-doc-party-box">
                <div class="csn-doc-label">Consignor:</div>
                <div class="csn-doc-party-name">{{ $consignor['name'] ?? '—' }}</div>
                <div class="csn-doc-party-address">{!! $nl($consignor['address'] ?? null) !!}</div>
                @if(filled($consignor['contact'] ?? null))<div>{{ $consignor['contact'] }}</div>@endif
            </td>
            <td class="csn-doc-meta-col">
                <table class="csn-doc-meta-table">
                    <tr><td class="csn-doc-meta-label">NO</td><td>{{ $meta['number'] ?? '—' }}</td></tr>
                    <tr><td class="csn-doc-meta-label">Date</td><td>{{ $meta['issued_at'] ?? '—' }}</td></tr>
                    <tr><td class="csn-doc-meta-label">{{ $meta['reference_label'] ?? 'D/O No' }}</td><td>{{ $meta['reference_number'] ?? ($meta['do_number'] ?? '—') }}</td></tr>
                    <tr><td class="csn-doc-meta-label">Job Sheet</td><td>{{ $meta['job_sheet_number'] ?? '—' }}</td></tr>
                    <tr><td class="csn-doc-meta-label">Invoice No</td><td>{{ $meta['invoice_number'] ?? '—' }}</td></tr>
                    <tr><td class="csn-doc-meta-label">Lorry No</td><td>{{ $meta['lorry_number'] ?? '—' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="csn-doc-route">
        <strong>From:</strong> {{ $route['from'] ?? '—' }}
        &nbsp;&nbsp;&nbsp;
        <strong>To:</strong> {{ $route['to'] ?? '—' }}
    </div>

    {{-- Line items (no pricing) --}}
    <table class="csn-doc-items">
        <thead>
            <tr>
                <th class="csn-doc-qty">Quantity</th>
                <th class="csn-doc-desc">Description</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td class="csn-doc-qty">{{ $line['quantity'] ?? '—' }}</td>
                    <td class="csn-doc-desc">{{ $line['description'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="csn-doc-qty">—</td>
                    <td class="csn-doc-desc"><div class="csn-doc-muted">No cargo details.</div></td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Remark (the driver note) and marking --}}
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

    {{-- Every consignee with its chop & sign box --}}
    <table class="csn-doc-consignees">
        @foreach (array_chunk($consignees ?: [['name' => '—', 'address' => null, 'contact' => null, 'do_number' => null]], 2) as $pair)
            <tr>
                @foreach ($pair as $consignee)
                    <td class="csn-doc-consignee-box" @if (count($pair) === 1) colspan="2" @endif>
                        <div class="csn-doc-label">Consignee{{ count($consignees) > 1 ? ' '.($loop->parent->index * 2 + $loop->iteration) : '' }}:</div>
                        <div class="csn-doc-party-name">{{ $consignee['name'] ?? '—' }}</div>
                        <div class="csn-doc-party-address">{!! $nl($consignee['address'] ?? null) !!}</div>
                        @if(filled($consignee['contact'] ?? null))<div>{{ $consignee['contact'] }}</div>@endif
                        @if(filled($consignee['do_number'] ?? null))<div><span class="csn-doc-label">D/O No:</span> {{ $consignee['do_number'] }}</div>@endif
                        <div class="csn-doc-sign-area">
                            <div class="csn-doc-sign-line"></div>
                            <div class="csn-doc-sign-note">Please Chop, Sign &amp; Return.</div>
                            <div class="csn-doc-sign-date">Name: ____________________ &nbsp; Date: ______________</div>
                        </div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
</div>
