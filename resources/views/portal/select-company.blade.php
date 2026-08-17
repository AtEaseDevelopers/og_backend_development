@extends('layouts.portal')

@section('title', 'Choose company')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
        <div>
            <h1>Choose company</h1>
            <p class="muted">Branch: <strong>{{ $branch->code }}</strong> — {{ $branch->name }}</p>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <a href="{{ route('portal.select-branch.reset') }}" class="btn secondary">Change branch</a>
            <button type="button" class="btn" onclick="document.getElementById('register-panel').classList.toggle('hidden')">
                Register company
            </button>
        </div>
    </div>

    <div id="register-panel" class="card hidden" style="margin-bottom:1.5rem;background:#fffbf5">
        <h2 style="margin-top:0;font-size:1.1rem">Register company</h2>
        <p class="muted">Register your company under this branch with BRN.</p>
        <form method="POST" action="{{ route('portal.select-company.register') }}">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem">
                <div>
                    <label>Company code</label>
                    <input type="text" name="code" value="{{ old('code') }}" required maxlength="40" pattern="[A-Za-z0-9_-]+">
                    @error('code') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label>Company name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="255">
                    @error('name') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label>BRN / company no.</label>
                    <input type="text" name="brn" value="{{ old('brn') }}" required maxlength="100">
                    @error('brn') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label>TIN</label>
                    <input type="text" name="tin" value="{{ old('tin') }}" maxlength="100">
                </div>
                <div>
                    <label>Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" maxlength="50">
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" maxlength="255">
                </div>
                <div style="grid-column:1/-1">
                    <label>Address</label>
                    <textarea name="address" rows="3">{{ old('address') }}</textarea>
                </div>
            </div>
            <div style="margin-top:1rem;display:flex;justify-content:flex-end">
                <button type="submit" class="btn">Register &amp; enter portal</button>
            </div>
        </form>
    </div>

    <div class="picker-grid">
        @forelse ($companies as $company)
            <form method="POST" action="{{ route('portal.select-company.store') }}">
                @csrf
                <input type="hidden" name="company_id" value="{{ $company->id }}">
                <button type="submit" class="picker-card">
                    <div class="picker-card-top">
                        <span class="picker-code">{{ $company->code }}</span>
                        <span class="picker-badge">Registered</span>
                    </div>
                    <div class="picker-title">{{ $company->name }}</div>
                    <div class="picker-sub">BRN: {{ $company->brn }}</div>
                    <div class="picker-link">Click to open this company →</div>
                </button>
            </form>
        @empty
            <div class="picker-empty">
                No companies registered in this branch yet. Click <strong>Register company</strong> to create one with BRN.
            </div>
        @endforelse
    </div>
</div>

<style>
    .hidden { display:none; }
    .picker-grid { display:grid; gap:1rem; grid-template-columns:repeat(2,minmax(0,1fr)); }
    @media (max-width:640px) { .picker-grid { grid-template-columns:1fr; } }
    .picker-card {
        width:100%; text-align:left; background:white; border:1px solid var(--line);
        border-radius:.75rem; padding:1.25rem; cursor:pointer; box-shadow:0 8px 24px rgba(28,25,23,.04);
    }
    .picker-card:hover { border-color:var(--accent); }
    .picker-card-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:.75rem; gap:.75rem; }
    .picker-code {
        display:inline-flex; align-items:center; justify-content:center; min-width:3rem; height:3rem; padding:0 .5rem;
        border-radius:.5rem; background:#fff7ed; color:var(--accent); font-weight:700;
    }
    .picker-badge {
        font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
        background:#ecfccb; color:#3f6212; padding:.25rem .5rem; border-radius:.35rem;
    }
    .picker-title { font-weight:600; margin-bottom:.35rem; }
    .picker-sub { color:var(--muted); font-size:.9rem; }
    .picker-link { color:var(--accent); font-size:.85rem; margin-top:.5rem; font-weight:600; }
    .picker-empty {
        grid-column:1/-1; border:1px dashed var(--line); border-radius:.75rem; padding:2rem; text-align:center; color:var(--muted);
    }
</style>

@if ($errors->any())
<script>document.getElementById('register-panel')?.classList.remove('hidden');</script>
@endif
@endsection
