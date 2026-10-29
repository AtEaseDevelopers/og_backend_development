@extends('layouts.portal')

@section('title', 'Choose company account')

@section('content')
<h1>Choose company account</h1>
<p class="muted" style="margin-top:-.5rem">Your login can hold several company accounts (BRN), in any O&amp;G branch. Only approved accounts can be opened.</p>

@if ($errors->any())
    <div class="card errors">@foreach ($errors->all() as $error)<div class="error" style="margin:0">{{ $error }}</div>@endforeach</div>
@endif

<div class="card">
    <div class="table-scroll">
    <table>
        <thead><tr><th>Company</th><th>BRN</th><th>Branch</th><th>Access</th><th></th></tr></thead>
        <tbody>
        @forelse ($links as $customer)
            @php $status = $customer->pivot->status ?? 'pending'; @endphp
            <tr>
                <td><strong>{{ $customer->company_name }}</strong>@if ($customer->id === $currentId) <span class="muted">(current)</span>@endif</td>
                <td>{{ $customer->brn ?: '—' }}</td>
                <td>{{ $customer->branch?->name ?? '—' }}</td>
                <td>{!! \App\Support\PortalBadge::html(match ($status) {
                    'approved' => ['label' => 'Approved', 'tone' => 'green'],
                    'rejected' => ['label' => 'Rejected', 'tone' => 'red'],
                    default => ['label' => 'Pending approval', 'tone' => 'amber'],
                }) !!}</td>
                <td style="text-align:right">
                    @if ($approvedIds->contains($customer->id))
                        <form method="POST" action="{{ route('portal.select-company.store') }}">
                            @csrf
                            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                            <button type="submit" class="small">Open</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No company accounts yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="card">
    <h3>Request access to another company (BRN)</h3>
    <p class="muted" style="margin-top:-.5rem">If the BRN is already registered with that branch, you are added to it; otherwise a new account is created. O&amp;G approves each request.</p>
    <form method="POST" action="{{ route('portal.select-company.request') }}">
        @csrf
        <div class="grid-form">
            <div><label>Company name *</label><input name="company_name" value="{{ old('company_name') }}" required></div>
            <div><label>BRN *</label><input name="brn" value="{{ old('brn') }}" required></div>
            <div><label>TIN</label><input name="tin" value="{{ old('tin') }}"></div>
            <div><label>Contact number</label><input name="phone" value="{{ old('phone') }}"></div>
            <div><label>O&amp;G branch *</label>
                <select name="branch_id" required>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label>Company address</label>
        <textarea name="address" rows="2">{{ old('address') }}</textarea>
        <div class="actions"><button type="submit">Request access</button></div>
    </form>
</div>
@endsection
