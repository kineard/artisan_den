<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Artisan Den - Login</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        .wrap { max-width: 420px; margin: 64px auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; }
        h1 { margin-top: 0; }
        label { display: block; font-size: 13px; margin-bottom: 4px; color: #334155; }
        input, button { width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 8px; border: 1px solid #cbd5e1; }
        button { border: none; background: #2563eb; color: #fff; font-weight: 600; cursor: pointer; }
        .bad { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:10px; border-radius:8px; margin-bottom:10px; }
        .muted { color:#64748b; font-size: 13px; }
        code { background:#f1f5f9; padding:2px 5px; border-radius:4px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Sign in</h1>
    @if ($errors->any())
        <div class="bad">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('auth.login.post') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <button type="submit">Login</button>
    </form>

    <p class="muted">Default local users:</p>
    <p class="muted"><code>admin@artisan.local</code> / <code>admin1234</code></p>
    <p class="muted"><code>manager@artisan.local</code> / <code>manager1234</code></p>
    <p class="muted"><code>employee@artisan.local</code> / <code>employee1234</code></p>
</div>
</body>
</html>

