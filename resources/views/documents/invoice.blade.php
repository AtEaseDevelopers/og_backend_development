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
@endphp

<div class="inv-document">
    <table class="inv-header">
        <tr>
            <td class="inv-logo-cell">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="O&amp;G Transport" class="inv-logo">
                @endif
            </td>
            <td class="inv-company-cell">
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
        </tr>
    </table>

    <table class="inv-title-row">
        <tr>
            <td class="inv-bill-to">
                <div class="inv-bill-label">Bill To :</div>
                <div class="inv-bill-name">{{ $billTo['name'] ?? '—' }}</div>
                @if(filled($billTo['reg_no'] ?? null))
                    <div>Co. Reg. {{ $billTo['reg_no'] }}</div>
                @endif
                <div class="inv-bill-address">{!! $nl($billTo['address'] ?? null) ?: '—' !!}</div>
            </td>
            <td class="inv-title-cell">
                <div class="inv-title">INVOICE</div>
                <div class="inv-number">Invoice No: {{ $meta['number'] ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="inv-meta-row">
        <tr>
            <td class="inv-meta-left"><strong>Account No.</strong> : {{ $meta['account_no'] ?? '—' }}</td>
            <td class="inv-meta-center"><strong>Terms</strong> : {{ $meta['terms'] ?? '—' }}</td>
            <td class="inv-meta-right"><strong>Date</strong> : {{ $meta['invoice_date'] ?? '—' }}</td>
        </tr>
    </table>

    <table class="inv-items">
        <thead>
            <tr>
                <th class="inv-col-date">Date</th>
                <th class="inv-col-csn">Consignment Note</th>
                <th class="inv-col-ref">Your Ref.</th>
                <th class="inv-col-dest">From</th>
                <th class="inv-col-dest">To</th>
                <th class="inv-col-sst">SST (RM)</th>
                <th class="inv-col-amt">Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td class="inv-col-date">{{ $line['date'] ?? '—' }}</td>
                    <td class="inv-col-csn">{{ $line['csn_number'] ?? '—' }}</td>
                    <td class="inv-col-ref">
                        @foreach($line['your_ref'] ?? ['—'] as $ref)
                            <div class="inv-ref-line">{{ $ref }}</div>
                        @endforeach
                    </td>
                    <td class="inv-col-dest">{{ $line['from'] ?? '—' }}</td>
                    <td class="inv-col-dest">{{ $line['to'] ?? '—' }}</td>
                    <td class="inv-col-sst">{{ $line['sst'] ?? '' }}</td>
                    <td class="inv-col-amt">{{ $line['amount'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding: 10px 5px; text-align: center; color: #666;">No invoice lines.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="inv-totals-wrap">
        <table class="inv-totals">
            <tr class="inv-totals-divider">
                <td class="inv-totals-label">Total (excluding SST)</td>
                <td class="inv-totals-value">{{ $totals['subtotal_excl_tax'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="inv-totals-label">{{ $totals['tax_label'] ?? 'Add SST' }}</td>
                <td class="inv-totals-value">{{ $totals['tax_amount'] ?? '' }}</td>
            </tr>
            <tr class="inv-totals-grand">
                <td class="inv-totals-label">Total</td>
                <td class="inv-totals-value">{{ $totals['total'] ?? '' }}</td>
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
