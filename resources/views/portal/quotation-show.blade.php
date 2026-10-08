@extends('layouts.portal')

@section('title', $quotation->number)

@php
    use App\Enums\QuotationStatus;
    $status = $quotation->status;
    $proforma = $quotation->proformaInvoice;
    $canDecide = $isLatest && $status->isCustomerActionable();
    $canPay = $proforma && in_array($status, [QuotationStatus::Accepted, QuotationStatus::PendingApproval, QuotationStatus::Confirmed, QuotationStatus::Converted], true)
        && $quotation->outstandingAmount() > 0;
    $steps = [
        ['Enquiry', true],
        ['Quotation', in_array($status, [QuotationStatus::Sent, QuotationStatus::PendingReview, QuotationStatus::Negotiation], true) || $status->isConfirmedOrLater()],
        ['Confirmation', $status->isConfirmedOrLater()],
        ['Proforma', $proforma !== null],
        ['Payment / Release', $quotation->isFullyPaid() || $quotation->isReleased() || $status === QuotationStatus::Converted],
        ['Invoice / Cash Bill', $quotation->invoices->isNotEmpty()],
        ['CSN', $quotation->consignmentNotes->isNotEmpty()],
    ];
@endphp

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
    <div>
        <h1 style="margin-bottom:.25rem">{{ $quotation->number }} <span class="muted" style="font-size:1rem">· version {{ $quotation->version }}</span></h1>
        <p class="muted" style="margin:0">
            {{ $status->label() }} · RM {{ number_format($quotation->total_amount, 2) }}
            @if ($quotation->orderType()) · {{ $quotation->orderType()->getLabel() }} @endif
            @if ($quotation->customer_do_number) · DO {{ $quotation->customer_do_number }} @endif
            @if ($quotation->salesperson) · Salesperson: {{ $quotation->salesperson->name }} @endif
        </p>
    </div>
    <a class="btn secondary" href="{{ route('portal.dashboard') }}">← Back</a>
</div>

@if ($errors->any())
    <div class="card" style="border-color:#fca5a5;background:#fef2f2">
        @foreach ($errors->all() as $error)<div class="error" style="margin:0">{{ $error }}</div>@endforeach
    </div>
@endif

<div class="card" style="display:flex;gap:.5rem;flex-wrap:wrap;font-size:.8rem">
    @foreach ($steps as [$label, $done])
        <span style="padding:.25rem .6rem;border-radius:999px;background:{{ $done ? '#dcfce7' : '#f5f5f4' }};color:{{ $done ? '#166534' : '#78716c' }}">{{ $done ? '✓' : ($loop->index + 1) }} {{ $label }}</span>
    @endforeach
</div>

@if (! $isLatest)
    <div class="flash" style="background:#fef3c7;border-color:#fde68a">This version has been superseded. Please review the latest version below.</div>
@endif

@if ($status === QuotationStatus::Closed)
    <div class="flash" style="background:#f5f5f4;border-color:#d6d3d1">This case was closed: {{ $quotation->closed_reason }}. Contact your salesperson to reopen it.</div>
@endif

<div class="card">
    <h3 style="margin-top:0">Route &amp; destinations</h3>
    @if ($quotation->pickup_location)<p><strong>Pickup:</strong> {{ $quotation->pickup_location }}</p>@endif
    @foreach ($quotation->destinations as $destination)
        <p><strong>{{ $destination->consignee_name }}</strong>
            @if ($destination->drop_off_type) <span class="muted">· {{ ucfirst($destination->drop_off_type) }}</span>@endif
            <br>{{ $destination->address }}</p>
    @endforeach
</div>

<div class="card">
    <h3 style="margin-top:0">Items &amp; pricing</h3>
    <table>
        <thead><tr><th>Item</th><th>Qty</th><th>UOM</th><th>Rate</th><th style="text-align:right">Total</th></tr></thead>
        <tbody>
        @foreach ($quotation->lines as $line)
            <tr>
                <td>{{ $line->item_name }}</td>
                <td>{{ rtrim(rtrim(number_format($line->quantity, 3, '.', ''), '0'), '.') }}</td>
                <td>{{ $line->uom }}</td>
                {{-- a product without a price yet (never sent: sending needs every product priced) shows no amount --}}
                <td>{{ $line->unit_price !== null ? 'RM '.number_format((float) $line->unit_price, 2) : '—' }}</td>
                <td style="text-align:right">{{ $line->unit_price !== null ? 'RM '.number_format((float) $line->line_total, 2) : '—' }}</td>
            </tr>
        @endforeach
        <tr><td colspan="4"><strong>Total</strong></td><td style="text-align:right"><strong>RM {{ number_format($quotation->total_amount, 2) }}</strong></td></tr>
        </tbody>
    </table>
    @if ($quotation->valid_until)<p class="muted" style="margin-bottom:0">Valid until {{ $quotation->valid_until->format('d/m/Y') }}</p>@endif
</div>

@if ($canDecide)
    <div class="card">
        <h3 style="margin-top:0">Your decision</h3>
        <form method="POST" action="{{ route('portal.quotations.confirm', $quotation) }}" style="margin-bottom:1rem;display:flex;justify-content:flex-end;align-items:center;gap:.75rem;flex-wrap:wrap">
            @csrf
            <span class="muted" style="font-size:.85rem">Accepting confirms your order and generates a proforma invoice.</span>
            <button class="btn" type="submit">Accept quotation (version {{ $quotation->version }})</button>
        </form>
        <form method="POST" action="{{ route('portal.quotations.reject', $quotation) }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 2fr auto;gap:0 .75rem;align-items:end">
                <div>
                    <label>Outcome</label>
                    <select name="rejection_category" required>
                        @foreach ($rejectionCategories as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Details (optional)</label>
                    <input name="rejection_reason" placeholder="Tell us what should change">
                </div>
                <div><button class="btn secondary" type="submit" style="margin-bottom:.9rem">Reject / negotiate</button></div>
            </div>
        </form>
    </div>
@endif

@if ($proforma)
    <div class="card">
        <h3 style="margin-top:0">Proforma invoice {{ $proforma->number }}</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.5rem 1rem">
            <div><span class="muted">Total</span><br><strong>RM {{ number_format($proforma->total_amount, 2) }}</strong></div>
            <div><span class="muted">Paid</span><br><strong>RM {{ number_format($quotation->paid_amount, 2) }}</strong></div>
            <div><span class="muted">Outstanding</span><br><strong>RM {{ number_format($quotation->outstandingAmount(), 2) }}</strong></div>
            <div><span class="muted">Status</span><br><strong>{{ $quotation->billingStatus()->getLabel() }}</strong></div>
        </div>
        @if ($proforma->payment_instructions)
            <p class="muted" style="white-space:pre-line;margin-bottom:0;margin-top:.75rem">{{ $proforma->payment_instructions }}</p>
        @endif
    </div>
@endif

@if ($canPay)
    <div class="card">
        <h3 style="margin-top:0">Submit payment</h3>
        <form method="POST" action="{{ route('portal.quotations.payments.store', $quotation) }}" enctype="multipart/form-data">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0 1rem">
                <div><label>Amount (RM) *</label><input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', number_format($quotation->outstandingAmount(), 2, '.', '')) }}" required></div>
                <div><label>Method *</label>
                    <select name="method" required>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('method', $quotation->payment_method) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Payment date</label><input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}"></div>
                <div><label>Bank / account paid to</label><input name="bank_account" value="{{ old('bank_account') }}"></div>
                <div><label>Reference no.</label><input name="reference" value="{{ old('reference') }}"></div>
                <div><label>Payment receipt (JPG / PNG / PDF)</label><input type="file" name="receipt" accept="image/*,.pdf"></div>
            </div>
            <label>Remarks</label><input name="remarks" value="{{ old('remarks') }}">
            <div style="display:flex;justify-content:flex-end">
                <button class="btn" type="submit">Submit payment</button>
            </div>
        </form>
    </div>
@endif

@if ($quotation->paymentSubmissions->isNotEmpty())
    <div class="card">
        <h3 style="margin-top:0">Payment history</h3>
        <table>
            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Note</th></tr></thead>
            <tbody>
            @foreach ($quotation->paymentSubmissions as $submission)
                <tr>
                    <td>{{ $submission->payment_date?->format('d/m/Y') ?? $submission->created_at->format('d/m/Y') }}</td>
                    <td>RM {{ number_format($submission->amount, 2) }}</td>
                    <td>{{ $submission->method()?->getLabel() }}</td>
                    <td>{{ $submission->reference ?: '—' }}</td>
                    <td>{{ $submission->status->getLabel() }}</td>
                    <td class="muted">{{ $submission->status->value === 'rejected' ? 'Rejected: '.$submission->rejection_reason : ($submission->remarks ?? '') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($quotation->invoices->isNotEmpty() || $quotation->consignmentNotes->isNotEmpty())
    <div class="card">
        <h3 style="margin-top:0">Billing &amp; delivery</h3>
        @foreach ($quotation->invoices as $invoice)
            <p style="margin:.25rem 0">{{ $invoice->isCashBill() ? 'Cash Bill' : 'Invoice' }} <strong>{{ $invoice->number }}</strong> · RM {{ number_format($invoice->total_amount, 2) }} · {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</p>
        @endforeach
        @foreach ($quotation->consignmentNotes as $csn)
            <p style="margin:.25rem 0">CSN <strong>{{ $csn->number }}</strong> · {{ $csn->status?->getLabel() }}
                @if ($csn->deliveryOrder?->lorry) · Lorry {{ $csn->deliveryOrder->lorry->registration_no }} @endif
                @if ($csn->deliveryOrder?->tracking_token) · <a href="{{ route('tracking.show', $csn->deliveryOrder->tracking_token) }}">Track</a> @endif
            </p>
        @endforeach
    </div>
@endif

@if ($versions->count() > 1)
    <div class="card">
        <h3 style="margin-top:0">Quotation versions</h3>
        <table>
            <thead><tr><th>Version</th><th>Number</th><th>Status</th><th>Total</th><th></th></tr></thead>
            <tbody>
            @foreach ($versions as $version)
                <tr>
                    <td>v{{ $version->version }}</td>
                    <td>{{ $version->number }}</td>
                    <td>{{ $version->status->label() }}</td>
                    <td>RM {{ number_format($version->total_amount, 2) }}</td>
                    <td>@if ($version->id !== $quotation->id)<a href="{{ route('portal.quotations.show', $version) }}">View</a>@else<span class="muted">current</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($isLatest && ! $status->isTerminal() && ! $status->isConfirmedOrLater())
    <div class="card">
        <h3 style="margin-top:0">Request a change</h3>
        <form method="POST" action="{{ route('portal.quotations.amend', $quotation) }}">
            @csrf
            <textarea name="remarks" rows="3" required placeholder="Describe the changes you need"></textarea>
            <div style="display:flex;justify-content:flex-end">
                <button class="btn secondary" type="submit">Submit amendment request</button>
            </div>
        </form>
    </div>
@endif
@endsection
