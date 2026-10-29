@extends('layouts.portal')

@section('title', 'Register')

@section('content')
<div class="card" style="max-width:680px;margin:1rem auto">
    <h1>Register your company</h1>
    <p class="muted" style="margin-top:-.5rem">Register with your company's BRN. We will email you a link to verify your address; O&amp;G then reviews and approves your account. If your company is already registered with us, your login is added to the same BRN account once approved.</p>

    @if ($errors->any())
        <div class="card errors">@foreach ($errors->all() as $error)<div class="error" style="margin:0">{{ $error }}</div>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('portal.register') }}">
        @csrf
        <h3>Company</h3>
        <div class="grid-form">
            <div><label>Company name *</label><input name="company_name" value="{{ old('company_name') }}" required></div>
            <div><label>BRN (business registration no.) *</label><input name="brn" value="{{ old('brn') }}" required></div>
            <div><label>TIN</label><input name="tin" value="{{ old('tin') }}"></div>
            <div><label>O&amp;G branch you deal with *</label>
                <select name="branch_id" required>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label>Company address *</label>
        <textarea name="address" rows="2" required>{{ old('address') }}</textarea>

        <h3>Person in charge (your login)</h3>
        <div class="grid-form">
            <div><label>PIC name *</label><input name="name" value="{{ old('name') }}" required></div>
            <div><label>Contact number *</label><input name="phone" value="{{ old('phone') }}" required></div>
            <div><label>Email *</label><input type="email" name="email" value="{{ old('email') }}" required></div>
        </div>
        <div class="grid-form">
            <div><label>Password * (at least 8 characters)</label><input type="password" name="password" required></div>
            <div><label>Confirm password *</label><input type="password" name="password_confirmation" required></div>
        </div>
        <div class="actions">
            <a href="{{ route('portal.login') }}">Already registered? Sign in</a>
            <button type="submit">Submit for approval</button>
        </div>
    </form>
</div>
@endsection
