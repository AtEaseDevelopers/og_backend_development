@extends('layouts.portal')

@section('title', 'Reset password')

@section('content')
<div class="card" style="max-width:420px;margin:2rem auto">
    <h1>Choose a new password</h1>
    <form method="POST" action="{{ route('portal.password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email', $email) }}" required>
        @error('email') <div class="error">{{ $message }}</div> @enderror
        <label>New password (at least 8 characters)</label>
        <input type="password" name="password" required>
        @error('password') <div class="error">{{ $message }}</div> @enderror
        <label>Confirm new password</label>
        <input type="password" name="password_confirmation" required>
        <div class="actions"><button type="submit">Reset password</button></div>
    </form>
</div>
@endsection
