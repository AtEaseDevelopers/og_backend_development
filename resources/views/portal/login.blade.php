@extends('layouts.portal')

@section('title', 'Login')

@section('content')
<div class="card" style="max-width:420px;margin:2rem auto">
    <h1>Customer Login</h1>
    <form method="POST" action="{{ url()->current() }}">
        @csrf
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email') <div class="error">{{ $message }}</div> @enderror
        @error('token') <div class="error">{{ $message }}</div> @enderror

        <label>Password</label>
        <input type="password" name="password" required>

        <label style="display:flex;align-items:center;margin-bottom:1rem"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>

        <button type="submit" style="width:100%">Sign in</button>
    </form>

    @if (session('unverified_email') && Route::has('portal.verify-email.resend'))
        <form method="POST" action="{{ route('portal.verify-email.resend') }}" style="margin-top:1rem">
            @csrf
            <input type="hidden" name="email" value="{{ session('unverified_email') }}">
            <button type="submit" class="secondary" style="width:100%">Resend verification email</button>
        </form>
    @endif

    <p style="margin:1rem 0 0;display:flex;justify-content:space-between;font-size:.9rem">
        @if (Route::has('portal.password.request'))
            <a href="{{ route('portal.password.request') }}">Forgot password?</a>
        @else
            <span></span>
        @endif
        <a href="{{ route('portal.register') }}">Register your company</a>
    </p>
</div>
@endsection
