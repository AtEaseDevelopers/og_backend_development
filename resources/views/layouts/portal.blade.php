<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Customer Portal') — O&G Transport</title>
    <link rel="icon" href="{{ asset('images/logo-og-circle.png') }}" type="image/png">
    <style>
        :root {
            --ink: #1c1917;
            --muted: #57534e;
            --surface: #fafaf9;
            --accent: #b45309;
            --line: #e7e5e4;
        }
        body { margin: 0; font-family: "Segoe UI", "Helvetica Neue", sans-serif; background: linear-gradient(160deg, #fff7ed 0%, #fafaf9 45%, #e7e5e4 100%); color: var(--ink); min-height: 100vh; }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 1.5rem; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .brand { display: flex; align-items: center; gap: .75rem; }
        .brand img { height: 2.75rem; width: auto; object-fit: contain; }
        .brand-text { font-size: 1.15rem; font-weight: 700; letter-spacing: .02em; line-height: 1.1; }
        .brand-text span { color: var(--accent); }
        .brand-account { font-size: .8rem; color: var(--muted); font-weight: 400; }
        nav { display: flex; align-items: center; flex-wrap: wrap; gap: .35rem 1rem; }
        nav a { text-decoration: none; color: var(--ink); font-size: .92rem; }
        nav a.active { color: var(--accent); font-weight: 600; }
        a { color: var(--accent); }
        .btn, button { display: inline-block; background: var(--accent); color: white; border: 0; padding: .65rem 1rem; border-radius: .35rem; cursor: pointer; font-weight: 600; font-size: .92rem; text-decoration: none; }
        .btn.secondary, button.secondary { background: transparent; color: var(--accent); border: 1px solid var(--accent); }
        .btn.small, button.small { padding: .3rem .65rem; font-size: .8rem; }
        .btn.danger, button.danger { background: transparent; color: #b91c1c; border: 1px solid #fca5a5; }
        .card { background: rgba(255,255,255,.88); border: 1px solid var(--line); border-radius: .75rem; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 10px 30px rgba(28,25,23,.04); }
        .card h3 { margin-top: 0; }
        label { display: block; font-size: .85rem; color: var(--muted); margin-bottom: .35rem; }
        input, select, textarea { width: 100%; padding: .65rem .75rem; border: 1px solid var(--line); border-radius: .4rem; margin-bottom: .9rem; box-sizing: border-box; background: white; font: inherit; }
        input[type=checkbox] { width: auto; margin: 0 .4rem 0 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .65rem .4rem; border-bottom: 1px solid var(--line); font-size: .92rem; vertical-align: top; }
        th { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
        .table-scroll { overflow-x: auto; }
        .flash { background: #ecfccb; border: 1px solid #bef264; padding: .75rem 1rem; border-radius: .5rem; margin-bottom: 1rem; }
        .flash.warn { background: #fef3c7; border-color: #fde68a; }
        .errors { border-color: #fca5a5; background: #fef2f2; }
        .error { color: #b91c1c; font-size: .85rem; margin-top: -.6rem; margin-bottom: .8rem; }
        h1 { font-size: 1.6rem; margin: 0 0 1rem; }
        .muted { color: var(--muted); }
        .badge { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .78rem; font-weight: 600; white-space: nowrap; }
        .grid-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .75rem 1rem; }
        .grid-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0 1rem; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .stat { background: white; border: 1px solid var(--line); border-radius: .75rem; padding: 1rem; }
        .stat strong { display: block; font-size: 1.5rem; margin-top: .25rem; }
        .tabs { display: flex; gap: .25rem; flex-wrap: wrap; border-bottom: 1px solid var(--line); margin-bottom: 1rem; }
        .tabs a { padding: .55rem .9rem; text-decoration: none; color: var(--muted); border-bottom: 2px solid transparent; }
        .tabs a.active { color: var(--accent); border-color: var(--accent); font-weight: 600; }
        .filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0 .75rem; align-items: end; }
        .actions { display: flex; justify-content: flex-end; gap: .75rem; flex-wrap: wrap; align-items: center; }
        .photos { display: flex; flex-wrap: wrap; gap: .5rem; }
        .photos img { width: 96px; height: 96px; object-fit: cover; border-radius: .4rem; border: 1px solid var(--line); background: #fff; }
        .page-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: .5rem; }
        .pagination { display: flex; gap: .35rem; flex-wrap: wrap; margin-top: 1rem; }
        nav[role=navigation] svg { width: 1rem; height: 1rem; }
        .wa-btn { position: fixed; left: 1.25rem; bottom: 1.25rem; z-index: 40; display: inline-flex; align-items: center; gap: .45rem; padding: .6rem .95rem; border-radius: 999px; background: #16a34a; color: #fff; font-weight: 600; font-size: .85rem; text-decoration: none; box-shadow: 0 6px 18px rgba(28,25,23,.18); }
        @media (max-width: 640px) { .wrap { padding: 1rem; } h1 { font-size: 1.35rem; } }
    </style>
    @include('filament.hooks.searchable-select')
</head>
<body>
@php
    $portalCustomer = auth()->check() ? \App\Support\PortalSelection::customer() : null;
    $portalBranch = $portalCustomer ? \App\Support\PortalSelection::branch() : null;
    $waNumber = preg_replace('/\D+/', '', (string) (config('og.portal_whatsapp') ?: $portalBranch?->phone));
    if ($waNumber !== '' && str_starts_with($waNumber, '0')) { $waNumber = '6'.$waNumber; }
    $waText = trim($__env->yieldContent('whatsapp_text')) ?: 'Hi O&G Transport, I would like a quotation'.($portalCustomer ? ' for '.$portalCustomer->company_name : '').'.';
    $is = fn (string ...$names) => request()->routeIs(...$names) ? 'active' : '';
@endphp
<div class="wrap" style="padding-bottom:5rem">
    <header>
        <div class="brand">
            <img src="{{ asset('images/logo-og-circle.png') }}" alt="O&G Transport">
            <div class="brand-text">O<span>&</span>G Transport Portal
                @if ($portalCustomer)
                    <div class="brand-account">{{ $portalCustomer->company_name }} · {{ $portalCustomer->brn }} · {{ $portalBranch?->code }}</div>
                @endif
            </div>
        </div>
        <nav>
            @auth
                @if ($portalCustomer)
                    <a class="{{ $is('portal.dashboard') }}" href="{{ route('portal.dashboard') }}">Dashboard</a>
                    <a class="btn small" href="{{ route('portal.enquiry.create') }}">+ New order</a>
                @endif
                <form action="{{ route('portal.logout') }}" method="POST" style="display:inline">
                    @csrf
                    <button type="submit" class="secondary small">Logout</button>
                </form>
            @else
                <a href="{{ route('portal.login') }}">Login</a>
                <a href="{{ route('portal.register') }}">Register</a>
            @endauth
        </nav>
    </header>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    @yield('content')
</div>

@if ($waNumber !== '')
    <a class="wa-btn" href="https://wa.me/{{ $waNumber }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.3-.4.8-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7.5-.1 1.5-.6 1.8-1.2.2-.6.2-1.1.1-1.2l-.2-.2Z"/></svg>
        WhatsApp us
    </a>
@endif

<button type="button" id="og-back-to-top" aria-label="Back to top" title="Back to top"
        style="position:fixed;right:1.25rem;bottom:1.25rem;z-index:40;display:none;align-items:center;gap:.3rem;padding:.5rem .8rem .5rem .65rem;border-radius:9999px;border:0;background:var(--accent);color:#fff;font-size:.75rem;font-weight:600;box-shadow:0 6px 18px rgba(28,25,23,.18);cursor:pointer">
    <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15" aria-hidden="true"><path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z" clip-rule="evenodd"/></svg>
    <span>Top</span>
</button>
<script>
    (function () {
        const btn = document.getElementById('og-back-to-top');
        let ticking = false;
        function update() {
            const doc = document.documentElement;
            const y = window.scrollY;
            const nearBottom = window.innerHeight + y >= doc.scrollHeight - 120;
            btn.style.display = (y > Math.min(400, window.innerHeight * 0.6) || (nearBottom && y > 150)) ? 'inline-flex' : 'none';
            ticking = false;
        }
        window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
        window.addEventListener('resize', update);
        btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        update();
    })();
</script>
</body>
</html>
