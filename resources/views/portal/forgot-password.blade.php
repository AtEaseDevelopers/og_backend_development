@extends('layouts.portal')

@section('title', 'Forgot password')

@section('content')
<div class="card" style="max-width:420px;margin:2rem auto">
    <h1>Forgot password</h1>
    <p class="muted" style="margin-top:-.5rem">Enter your login email and we will send you a link to choose a new password.</p>
    <form method="POST" action="{{ route('portal.password.email') }}">
        @csrf
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email') <div class="error">{{ $message }}</div> @enderror
        <div class="actions">
            <a href="{{ route('portal.login') }}">Back to login</a>
            <button type="submit">Email reset link</button>
        </div>
    </form>
</div>
@endsection
