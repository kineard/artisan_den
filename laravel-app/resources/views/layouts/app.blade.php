<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Artisan Den')</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .topbar { background: #0f172a; color: #f8fafc; border-bottom: 1px solid #1e293b; }
        .topbar-inner { max-width: 1180px; margin: 0 auto; padding: 12px 18px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .brand { font-weight: 700; letter-spacing: .02em; margin-right: 8px; }
        .nav { display: flex; gap: 8px; flex-wrap: wrap; }
        .nav a { color: #cbd5e1; text-decoration: none; padding: 6px 10px; border-radius: 8px; border: 1px solid transparent; }
        .nav a:hover { color: #fff; border-color: #334155; }
        .nav a.active { color: #fff; background: #1e293b; border-color: #334155; }
        .spacer { flex: 1 1 auto; }
        .meta { font-size: 12px; color: #94a3b8; }
        .logout-form { margin: 0; }
        .logout-btn { border: 1px solid #334155; background: #111827; color: #e2e8f0; padding: 6px 10px; border-radius: 8px; cursor: pointer; }
        .logout-btn:hover { background: #1f2937; color: #fff; }

        .wrap { max-width: 1180px; margin: 0 auto; padding: 20px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .row > * { flex: 1 1 140px; }
        .grid { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        label { display: block; font-size: 13px; color: #334155; margin-bottom: 4px; }
        input, select, button { width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #cbd5e1; }
        button { background: #2563eb; color: #fff; border: none; font-weight: 600; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .muted { color: #64748b; font-size: 13px; }
        .ok { background: #ecfeff; color: #155e75; padding: 10px; border-radius: 8px; border: 1px solid #a5f3fc; margin-bottom: 10px; }
        .bad { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; border-bottom: 1px solid #e2e8f0; padding: 8px; font-size: 13px; vertical-align: top; }
        th { background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; }
        .tag { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .tag-ok { background: #dcfce7; color: #166534; }
        .tag-low { background: #fef9c3; color: #854d0e; }
        .tag-out { background: #fee2e2; color: #991b1b; }
        .actions { min-width: 280px; }
        @yield('page_styles')
    </style>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">Artisan Den</div>
        <nav class="nav">
            <a href="{{ route('kpi.index') }}" class="{{ request()->routeIs('kpi.*') ? 'active' : '' }}">KPI</a>
            <a href="{{ route('inventory.index') }}" class="{{ request()->routeIs('inventory.*') ? 'active' : '' }}">Inventory</a>
            <a href="{{ route('timeclock.index') }}" class="{{ request()->routeIs('timeclock.*') ? 'active' : '' }}">Time Clock</a>
        </nav>
        <div class="spacer"></div>
        <div class="meta">
            {{ session('user_name', 'User') }} · {{ strtoupper(session('user_role', '')) }}
        </div>
        <form method="POST" action="{{ route('auth.logout') }}" class="logout-form">
            @csrf
            <button type="submit" class="logout-btn">Logout</button>
        </form>
    </div>
</header>

<main class="wrap">
    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="bad">{{ $errors->first() }}</div>
    @endif

    @yield('content')
</main>
</body>
</html>

