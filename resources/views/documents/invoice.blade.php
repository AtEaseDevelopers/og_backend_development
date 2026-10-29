@php
    $letterhead = $letterhead ?? [];
    $meta = $meta ?? [];
    $billTo = $bill_to ?? [];
    $lines = $lines ?? [];
    $totals = $totals ?? [];
    $amountInWords = $amount_in_words ?? '';
    $disclaimer = $disclaimer ?? null;
    $bankAccounts = $bank_accounts ?? [];
    $forPdf = $forPdf ?? false;

    $logoSrc = $letterhead['logo_src'] ?? null;
    $nl = fn (?string $value): string => filled($value) ? nl2br(e($value)) : '';
    $hasSst = collect($lines)->contains(fn (array $line) => filled($line['sst'] ?? null)) || filled($totals['tax_amount'] ?? null);
    // the QR of the invoice number (user, 30 Oct 2026): scanned into the system's search fields
    $qr = \App\Support\QrCodeImage::dataUri($meta['number'] ?? null);
@endphp

{{-- the invoice laid out in boxes (user, 30 Oct 2026: clearer to read): letterhead + title, Bill To beside the invoice
     details, one line per consignment note with its goods, the totals, the amount in words --}}
<div class="inv-document">
    <table class="inv-header">
        <tr>
            @if($logoSrc)
                <td class="inv-logo-cell"><img src="{{ $logoSrc }}" alt="O&amp;G Transport" class="inv-logo"></td>
            @endif
            <td class="inv-company-cell {{ $logoSrc ? '' : 'inv-company-cell-full' }}">
                <div class="inv-company-name">{{ $letterhead['company_name'] ?? 'O&G Transport' }}</div>
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
                    @if(filled($letterhead['website'] ?? null))
                        @if(filled($letterhead['phone'] ?? null)) &nbsp; @endif
                        Website: {{ $letterhead['website'] }}
                    @endif
                </div>
                @if(filled($letterhead['email'] ?? null))
                    <div>E-mail: {{ $letterhead['email'] }}</div>
                @endif
            </td>
            <td class="inv-title-cell">
                @if($qr)<div class="inv-qr"><img src="{{ $qr }}" alt="QR {{ $meta['number'] }}"></div>@endif
                <div class="inv-title">{{ ($meta['type'] ?? '') === 'Cash Bill' ? 'CASH BILL' : 'INVOICE' }}</div>
                <div class="inv-number">{{ $meta['number'] ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="inv-boxes">
        <tr>
            <td class="inv-box">
                <div class="inv-box-title">Bill To</div>
                <div class="inv-bill-name">{{ $billTo['name'] ?? '—' }}</div>
                @if(filled($billTo['reg_no'] ?? null))
                    <div>Co. Reg. {{ $billTo['reg_no'] }}</div>
                @endif
                <div class="inv-bill-address">{!! $nl($billTo['address'] ?? null) ?: '—' !!}</div>
                @if(filled($billTo['attention'] ?? null) || filled($billTo['phone'] ?? null))
                    <div>@if(filled($billTo['attention'] ?? null))Attn: {{ $billTo['attention'] }}@endif @if(filled($billTo['phone'] ?? null)) &nbsp;Tel: {{ $billTo['phone'] }}@endif</div>
                @endif
                @if(filled($billTo['email'] ?? null))
                    <div>E-mail: {{ $billTo['email'] }}</div>
                @endif
                @if(filled($meta['account_no'] ?? null) && $meta['account_no'] !== '—')
                    <div class="inv-box-muted">Customer A/C No. (debtor code): {{ $meta['account_no'] }}</div>
                @endif
            </td>
            <td class="inv-box inv-box-meta">
                <div class="inv-box-title">Invoice details</div>
                <table class="inv-meta-table">
                    <tr><td class="inv-meta-k">Invoice No.</td><td class="inv-meta-v">{{ $meta['number'] ?? '—' }}</td></tr>
                    @if(filled($meta['type'] ?? null))<tr><td class="inv-meta-k">Type</td><td class="inv-meta-v">{{ $meta['type'] }}</td></tr>@endif
                    <tr><td class="inv-meta-k">Invoice date</td><td class="inv-meta-v">{{ $meta['invoice_date'] ?? '—' }}</td></tr>
                    <tr><td class="inv-meta-k">Payment terms</td><td class="inv-meta-v">{{ $meta['terms'] ?? '—' }}</td></tr>
                    @if(filled($meta['due_date'] ?? null) && ($meta['due_date'] !== ($meta['invoice_date'] ?? null)))
                        <tr><td class="inv-meta-k">Due date</td><td class="inv-meta-v">{{ $meta['due_date'] }}</td></tr>
                    @endif
                    @if(filled($meta['order_no'] ?? null))<tr><td class="inv-meta-k">Order No.</td><td class="inv-meta-v">{{ $meta['order_no'] }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <table class="inv-items inv-items-boxed">
        <thead>
            <tr>
                <th class="inv-col-date">Date</th>
                <th class="inv-col-csn">Consignment Note</th>
                <th class="inv-col-goods">Goods</th>
                <th class="inv-col-ref">DO / Ref.</th>
                <th class="inv-col-dest">From → To</th>
                @if($hasSst)<th class="inv-col-sst">SST (RM)</th>@endif
                <th class="inv-col-amt">Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td class="inv-col-date">{{ $line['date'] ?? '—' }}</td>
                    <td class="inv-col-csn">{{ $line['csn_number'] ?? '—' }}</td>
                    <td class="inv-col-goods">
                        @forelse($line['items'] ?? [] as $item)
                            <div>{{ $item }}</div>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td class="inv-col-ref">
                        @foreach($line['your_ref'] ?? ['—'] as $ref)
                            <div class="inv-ref-line">{{ $ref }}</div>
                        @endforeach
                    </td>
                    <td class="inv-col-dest">{{ trim((($line['from'] ?? '—') === '—' ? '' : $line['from'].' → ').($line['to'] ?? '—')) }}</td>
                    @if($hasSst)<td class="inv-col-sst">{{ $line['sst'] ?? '' }}</td>@endif
                    <td class="inv-col-amt">{{ $line['amount'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $hasSst ? 7 : 6 }}" style="padding: 10px 5px; text-align: center; color: #666;">No invoice lines.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="inv-totals-wrap">
        <table class="inv-totals inv-totals-boxed">
            <tr>
                <td class="inv-totals-label">Total (excluding SST)</td>
                <td class="inv-totals-value">{{ $totals['subtotal_excl_tax'] ?? '' }}</td>
            </tr>
            @if($hasSst)
                <tr>
                    <td class="inv-totals-label">{{ $totals['tax_label'] ?? 'Add SST' }}</td>
                    <td class="inv-totals-value">{{ $totals['tax_amount'] ?? '' }}</td>
                </tr>
            @endif
            <tr class="inv-totals-grand">
                <td class="inv-totals-label">Total</td>
                <td class="inv-totals-value">RM {{ $totals['total'] ?? '' }}</td>
            </tr>
        </table>
    </div>

    @if(filled($amountInWords))
        <div class="inv-words">{{ $amountInWords }}</div>
    @endif

    @if(filled($disclaimer))
        <div class="inv-disclaimer">{{ $disclaimer }}</div>
    @endif

    @if($bankAccounts !== [])
        <table class="inv-banks">
            <tr>
                @foreach($bankAccounts as $account)
                    <td>{{ ($account['bank'] ?? 'Bank').' A/C No. '.($account['account'] ?? '—') }}</td>
                @endforeach
            </tr>
        </table>
    @endif
</div>
