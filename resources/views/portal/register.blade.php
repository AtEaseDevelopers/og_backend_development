@extends('layouts.portal')

@section('title', 'Register')

@section('content')
@php $branches = \App\Domains\MasterData\Models\Branch::where('is_active', true)->get(); @endphp
<div class="card" style="max-width:560px;margin:1rem auto">
    <h1>Register Company</h1>
    <form method="POST" action="{{ route('portal.register') }}">
        @csrf
        <label>Your Name</label>
        <input name="name" value="{{ old('name') }}" required>
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required>
        @error('email') <div class="error">{{ $message }}</div> @enderror
        <label>Password</label>
        <input type="password" name="password" required>
        <label>Confirm Password</label>
        <input type="password" name="password_confirmation" required>
        <label>Company Name</label>
        <input name="company_name" value="{{ old('company_name') }}" required>
        <label>BRN</label>
        <input name="brn" value="{{ old('brn') }}" required>
        <label>TIN</label>
        <input name="tin" value="{{ old('tin') }}">
        <label>Phone</label>
        <input name="phone" value="{{ old('phone') }}">
        <label>Address</label>
        <textarea name="address" rows="2">{{ old('address') }}</textarea>
        <label>Branch</label>
        <select name="branch_id" required>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
            @endforeach
        </select>
        <button class="btn" type="submit">Submit for approval</button>
    </form>
</div>
@endsection
