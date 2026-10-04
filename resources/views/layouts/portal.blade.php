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
        .wrap { max-width: 960px; margin: 0 auto; padding: 1.5rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .brand { display: flex; align-items: center; gap: .75rem; }
        .brand img { height: 2.75rem; width: auto; object-fit: contain; }
        .brand-text { font-size: 1.15rem; font-weight: 700; letter-spacing: .02em; line-height: 1.1; }
        .brand-text span { color: var(--accent); }
        nav a, .btn { text-decoration: none; color: var(--ink); margin-left: 1rem; font-size: .95rem; }
        .btn, button { background: var(--accent); color: white; border: 0; padding: .65rem 1rem; border-radius: .35rem; cursor: pointer; font-weight: 600; }
        .btn.secondary { background: transparent; color: var(--accent); border: 1px solid var(--accent); }
        .card { background: rgba(255,255,255,.88); border: 1px solid var(--line); border-radius: .75rem; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 10px 30px rgba(28,25,23,.04); }
        label { display: block; font-size: .85rem; color: var(--muted); margin-bottom: .35rem; }
        input, select, textarea { width: 100%; padding: .65rem .75rem; border: 1px solid var(--line); border-radius: .4rem; margin-bottom: .9rem; box-sizing: border-box; background: white; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .65rem .4rem; border-bottom: 1px solid var(--line); font-size: .92rem; }
        .flash { background: #ecfccb; border: 1px solid #bef264; padding: .75rem 1rem; border-radius: .5rem; margin-bottom: 1rem; }
        .error { color: #b91c1c; font-size: .85rem; margin-top: -.6rem; margin-bottom: .8rem; }
        h1 { font-size: 1.6rem; margin: 0 0 1rem; }
        .muted { color: var(--muted); }
    </style>
</head>
<body>
<div class="wrap" style="padding-bottom:4.5rem">
    <header>
        <div class="brand">
            <img src="{{ asset('images/logo-og-circle.png') }}" alt="O&G Transport">
            <div class="brand-text">O<span>&</span>G Transport Portal</div>
        </div>
        <nav>
            @auth
                @if ($branch = \App\Support\PortalSelection::branch())
                    <span class="muted" style="margin-left:1rem;font-size:.85rem">{{ $branch->code }}</span>
                @endif
                @if ($company = \App\Support\PortalSelection::company())
                    <span class="muted" style="margin-left:.5rem;font-size:.85rem">/ {{ $company->code }}</span>
                @endif
                <a href="{{ route('portal.dashboard') }}">Dashboard</a>
                <a href="{{ route('portal.select-branch.reset') }}">Switch branch</a>
                <a href="{{ route('portal.select-company') }}">Switch company</a>
                <a href="{{ route('portal.enquiry.create') }}">New Enquiry</a>
                <form action="{{ route('portal.logout') }}" method="POST" style="display:inline">
                    @csrf
                    <button type="submit" class="btn secondary" style="margin-left:1rem">Logout</button>
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
