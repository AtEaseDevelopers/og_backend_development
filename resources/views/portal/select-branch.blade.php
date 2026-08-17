@extends('layouts.portal')

@section('title', 'Choose branch')

@section('content')
<div class="card">
    <h1>Choose a branch</h1>
    <p class="muted">Pick the branch you want to work with. Next you will choose or register a company.</p>

    <div class="picker-grid" style="margin-top:1.5rem">
        @forelse ($branches as $branch)
            <form method="POST" action="{{ route('portal.select-branch.store') }}">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <button type="submit" class="picker-card">
                    <div class="picker-card-top">
                        <span class="picker-code">{{ $branch->code }}</span>
                        <span class="picker-arrow">→</span>
                    </div>
                    <div class="picker-title">{{ $branch->name }}</div>
                    <div class="picker-sub">
                        @if (strtoupper($branch->code) === 'KL')
                            HQ branch
                        @else
                            Branch office
                        @endif
                    </div>
                </button>
            </form>
        @empty
            <p class="muted">No branches available for your account. Contact O&amp;G admin.</p>
        @endforelse
    </div>
</div>

<style>
    .picker-grid { display:grid; gap:1rem; grid-template-columns:repeat(2,minmax(0,1fr)); }
    @media (max-width:640px) { .picker-grid { grid-template-columns:1fr; } }
    .picker-card {
        width:100%; text-align:left; background:white; border:1px solid var(--line);
        border-radius:.75rem; padding:1.25rem; cursor:pointer; box-shadow:0 8px 24px rgba(28,25,23,.04);
    }
    .picker-card:hover { border-color:var(--accent); }
    .picker-card-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:.75rem; }
    .picker-code {
        display:inline-flex; align-items:center; justify-content:center; width:3rem; height:3rem;
        border-radius:.5rem; background:#fff7ed; color:var(--accent); font-weight:700;
    }
    .picker-arrow { color:var(--muted); }
    .picker-title { font-weight:600; margin-bottom:.35rem; }
    .picker-sub { color:var(--muted); font-size:.9rem; }
</style>
@endsection
