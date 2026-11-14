{{-- 403 raised by PlanFeatureGate: the tenant's plan leaves this feature out (view-only). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Not in your plan - {{ config('app.name', 'SaaS ERP') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400..700&family=Plus+Jakarta+Sans:wght@700..800&display=swap" rel="stylesheet">
    <style>
        :root { --brand: #4f46e5; --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --bg: #f8fafc; --card: #fff; }
        @media (prefers-color-scheme: dark) {
            :root { --ink: #f1f5f9; --muted: #94a3b8; --line: #1e293b; --bg: #020617; --card: #0f172a; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: var(--bg); color: var(--ink); font-family: 'DM Sans', system-ui, sans-serif; }
        .card { width: 100%; max-width: 480px; background: var(--card); border: 1px solid var(--line); border-radius: 18px; padding: 36px 32px; text-align: center; box-shadow: 0 20px 40px -24px rgba(15, 23, 42, .35); }
        .icon { width: 56px; height: 56px; margin: 0 auto 18px; border-radius: 16px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #4338ca, #818cf8); box-shadow: 0 10px 22px -10px rgba(79, 70, 229, .8); }
        .tag { display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--brand); margin-bottom: 8px; }
        h1 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 22px; margin: 0 0 10px; letter-spacing: -.01em; }
        p { margin: 0 0 24px; color: var(--muted); font-size: 14.5px; line-height: 1.6; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; height: 42px; padding: 0 18px; border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; border: 1px solid var(--line); color: var(--ink); background: transparent; }
        .btn-primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        .btn-primary:hover { background: #4338ca; }
    </style>
</head>
<body>
    <main class="card">
        <div class="icon" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <span class="tag">Not in your plan</span>
        <h1>{{ $label }} needs an upgrade</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            <a href="{{ url()->previous() }}" class="btn">Go back</a>
            @if ($canUpgrade)
                <a href="{{ route('platform.subscription.index') }}" class="btn btn-primary">See plans</a>
            @else
                <span class="btn" style="cursor: default">Ask your workspace owner to upgrade</span>
            @endif
        </div>
    </main>
</body>
</html>
