<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Customer Portal') — O&G Transport</title>
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
        .brand { font-size: 1.4rem; font-weight: 700; letter-spacing: .02em; }
        .brand span { color: var(--accent); }
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
<div class="wrap">
    <header>
        <div class="brand">O<span>&</span>G Transport Portal</div>
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
</body>
</html>
