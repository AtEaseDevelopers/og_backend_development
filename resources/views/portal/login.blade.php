@extends('layouts.portal')

@section('title', 'Login')

@section('content')
<div class="card" style="max-width:420px;margin:2rem auto">
    <h1>Customer Login</h1>
    <form method="POST" action="{{ route('portal.login') }}">
        @csrf
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required>
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit" class="btn" style="width:100%">Sign in</button>
    </form>
    <p class="muted" style="margin-top:1rem">Demo: portal@demo.local / password</p>
</div>
@endsection
