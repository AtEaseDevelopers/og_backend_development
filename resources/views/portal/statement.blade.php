@extends('layouts.portal')

@section('title', 'Statement of account')

@section('content')
<div class="page-head">
    <div>
        <h1 style="margin-bottom:.25rem">Statement of account</h1>
        <p class="muted" style="margin:0">{{ $statement['from']->format('d M Y') }} – {{ $statement['to']->format('d M Y') }}
            · Credit term {{ $customer->credit_term_days ? $customer->credit_term_days.' days' : '—' }}
            @if ((float) $customer->credit_limit > 0) · Credit limit RM {{ number_format((float) $customer->credit_limit, 2) }}@endif
        </p>
    </div>
    <a class="btn" href="{{ route('portal.statement.pdf', request()->only('month', 'from', 'to')) }}">Download PDF</a>
</div>

<div class="card">
    <form method="GET" class="filters">
        <div><label>Monthly statement</label><input type="month" name="month" value="{{ $month ?? '' }}" onchange="this.form.from.value='';this.form.to.value='';this.form.submit()"></div>
        <div><label>Or from</label><input type="date" name="from" value="{{ request('from') }}"></div>
        <div><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
        <div class="actions" style="margin-bottom:.9rem"><a href="{{ route('portal.statement') }}">This month</a><button type="submit">Show</button></div>
    </form>
</div>

<div class="card">
    <h3>Outstanding &amp; aging <span class="muted" style="font-weight:400;font-size:.85rem">as at {{ $statement['to']->format('d M Y') }}</span></h3>
    <div class="stats" style="margin-bottom:0">
        @foreach (\App\Support\StatementData::AGING_BUCKETS as $key => $label)
            <div class="stat"><span class="muted">{{ $label }}</span><strong style="font-size:1.15rem;{{ $key !== 'current' && $statement['aging'][$key] > 0 ? 'color:#b91c1c' : '' }}">RM {{ number_format($statement['aging'][$key], 2) }}</strong></div>
        @endforeach
        <div class="stat" style="border-color:var(--accent)"><span class="muted">Total outstanding</span><strong style="font-size:1.15rem">RM {{ number_format($statement['outstanding'], 2) }}</strong></div>
    </div>
    @if ($statement['open_invoices'])
        <div class="table-scroll" style="margin-top:1rem">
        <table>
            <thead><tr><th>Unpaid invoice</th><th>Date</th><th>Due</th><th>Days overdue</th><th style="text-align:right">Balance</th><th></th></tr></thead>
            <tbody>
            @foreach ($statement['open_invoices'] as $inv)
                <tr>
                    <td>{{ $inv['number'] }}</td>
                    <td>{{ $inv['date'] ? \Illuminate\Support\Carbon::parse($inv['date'])->format('d/m/Y') : '—' }}</td>
                    <td>{{ $inv['due_date'] ? \Illuminate\Support\Carbon::parse($inv['due_date'])->format('d/m/Y') : '—' }}</td>
                    <td>{{ $inv['days_overdue'] ?: '—' }}</td>
                    <td style="text-align:right">RM {{ number_format($inv['balance'], 2) }}</td>
                    <td style="text-align:right"><a href="{{ route('portal.documents.invoice', $inv['id']) }}">PDF</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

<div class="card">
    <h3>Transactions</h3>
    <div class="table-scroll">
    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Due</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th><th style="text-align:right">Balance</th></tr></thead>
        <tbody>
            <tr><td>{{ $statement['from']->format('d/m/Y') }}</td><td colspan="5"><strong>Opening balance</strong></td><td style="text-align:right">{{ number_format($statement['opening'], 2) }}</td></tr>
            @forelse ($statement['rows'] as $row)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td>{{ $row['reference'] }}</td>
                    <td>{{ $row['due_date'] ? \Illuminate\Support\Carbon::parse($row['due_date'])->format('d/m/Y') : '' }}</td>
                    <td style="text-align:right">{{ $row['debit'] ? number_format($row['debit'], 2) : '' }}</td>
                    <td style="text-align:right">{{ $row['credit'] ? number_format($row['credit'], 2) : '' }}</td>
                    <td style="text-align:right">{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No transactions in this period.</td></tr>
            @endforelse
            <tr><td colspan="6"><strong>Closing balance</strong></td><td style="text-align:right"><strong>{{ number_format($statement['closing'], 2) }}</strong></td></tr>
        </tbody>
    </table>
    </div>
</div>
@endsection
