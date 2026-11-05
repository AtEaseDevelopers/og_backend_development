@extends('layouts.portal')

@section('title', 'Documents')

@section('content')
<div class="page-head">
    <h1>Financial documents</h1>
    <a class="btn secondary" href="{{ route('portal.statement') }}">Statement of account</a>
</div>

<div class="tabs">
    @foreach ($tabs as $key => $label)
        <a class="{{ $tab === $key ? 'active' : '' }}" href="{{ route('portal.documents.index', array_filter(['tab' => $key, 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])) }}">{{ $label }}</a>
    @endforeach
</div>

<form class="card filters" method="GET">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div><label>Search</label><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Document number{{ $tab === 'einvoices' ? ' / UUID' : '' }}"></div>
    <div><label>From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div><label>To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div class="actions" style="margin-bottom:.9rem"><a href="{{ route('portal.documents.index', ['tab' => $tab]) }}">Reset</a><button type="submit">Filter</button></div>
</form>

<div class="card">
    <div class="table-scroll">
    <table>
        @if ($tab === 'receipts')
            <thead><tr><th>Receipt</th><th>Date</th><th>For</th><th style="text-align:right">Amount</th><th></th></tr></thead>
            <tbody>
            @forelse ($records as $receipt)
                <tr>
                    <td>{{ $receipt->number }}</td>
                    <td>{{ $receipt->created_at->format('d/m/Y') }}</td>
                    <td>{{ $receipt->payment?->invoice?->number ?? $receipt->payment?->quotation?->number ?? '—' }}</td>
                    <td style="text-align:right">RM {{ number_format((float) $receipt->amount, 2) }}</td>
                    <td style="text-align:right"><a href="{{ route('portal.documents.receipt', $receipt) }}">Download PDF</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No receipts in this period.</td></tr>
            @endforelse
            </tbody>
        @elseif ($tab === 'proformas')
            <thead><tr><th>Proforma</th><th>Date</th><th>Order</th><th style="text-align:right">Total</th><th style="text-align:right">Paid</th><th></th></tr></thead>
            <tbody>
            @forelse ($records as $proforma)
                <tr>
                    <td>{{ $proforma->number }}</td>
                    <td>{{ ($proforma->issued_at ?? $proforma->created_at)?->format('d/m/Y') }}</td>
                    <td>@if ($proforma->quotation)<a href="{{ route('portal.quotations.show', $proforma->quotation_id) }}">{{ $proforma->quotation->number }}</a>@else — @endif</td>
                    <td style="text-align:right">RM {{ number_format((float) $proforma->total_amount, 2) }}</td>
                    <td style="text-align:right">RM {{ number_format((float) $proforma->paid_amount, 2) }}</td>
                    <td style="text-align:right"><a href="{{ route('portal.documents.proforma', $proforma) }}">Download PDF</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No proforma invoices in this period.</td></tr>
            @endforelse
            </tbody>
        @elseif ($tab === 'einvoices')
            <thead><tr><th>Invoice</th><th>Date</th><th>LHDN UUID</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($records as $invoice)
                @php $einvoice = $invoice->einvoiceSubmission; @endphp
                <tr>
                    <td>{{ $invoice->number }}</td>
                    <td>{{ $invoice->invoice_date?->format('d/m/Y') }}</td>
                    <td style="font-family:monospace;font-size:.85rem;word-break:break-all">{{ $einvoice->uuid ?: '—' }}</td>
                    <td>{{ ucfirst((string) $einvoice->status) }}</td>
                    <td style="text-align:right;white-space:nowrap">
                        @if ($einvoice->uuid)<a href="{{ route('portal.documents.einvoice', $invoice) }}">Download e-invoice</a>@endif
                        @if ($einvoice->buyer_token)<br><a href="{{ route('einvoice.buyer.show', $einvoice->buyer_token) }}" target="_blank" rel="noopener">Update buyer info</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No e-invoices in this period.</td></tr>
            @endforelse
            </tbody>
        @else
            <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th>Order</th><th>Status</th><th style="text-align:right">Total</th><th style="text-align:right">Balance</th><th></th></tr></thead>
            <tbody>
            @forelse ($records as $invoice)
                @php $balance = max(0, (float) $invoice->total_amount - (float) $invoice->payments->where('status', 'completed')->sum('amount')); @endphp
                <tr>
                    <td>{{ $invoice->number }}<br><span class="muted">{{ $invoice->isCashBill() ? 'Cash bill' : 'Invoice' }}</span></td>
                    <td>{{ $invoice->invoice_date?->format('d/m/Y') }}</td>
                    <td>{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</td>
                    <td>@if ($invoice->quotation)<a href="{{ route('portal.quotations.show', $invoice->quotation_id) }}">{{ $invoice->quotation->number }}</a>@else — @endif</td>
                    <td>{{ ucfirst(str_replace('_', ' ', (string) $invoice->status)) }}</td>
                    <td style="text-align:right">RM {{ number_format((float) $invoice->total_amount, 2) }}</td>
                    <td style="text-align:right">RM {{ number_format($balance, 2) }}</td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('portal.documents.invoice', $invoice) }}">Download PDF</a>
                        @if ($invoice->einvoiceSubmission?->uuid)<br><a href="{{ route('portal.documents.einvoice', $invoice) }}">E-invoice</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No invoices in this period.</td></tr>
            @endforelse
            </tbody>
        @endif
    </table>
    </div>
    {{ $records->links('pagination::simple-default') }}
</div>
@endsection
