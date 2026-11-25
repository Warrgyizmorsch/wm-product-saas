<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Set Password - {{ config('app.name', 'SaaS ERP') }}</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Plus+Jakarta+Sans:wght@600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #2563eb;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --field: #f8fafc;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            font-family: 'DM Sans', sans-serif;
            background: #f8fafc; color: var(--ink);
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%; max-width: 440px;
            background: #fff; border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
            border: 1px solid var(--line); padding: 36px 32px;
        }
        h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 24px; font-weight: 800; margin: 0 0 8px;
        }
        p.desc {
            color: var(--muted); font-size: 14px; line-height: 1.5; margin: 0 0 24px;
        }
        .alert {
            padding: 12px 14px; border-radius: 10px; font-size: 13.5px;
            background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;
        }
        input[type="email"], input[type="password"] {
            width: 100%; height: 48px; padding: 10px 14px;
            font-size: 14px; border: 1.5px solid var(--line);
            border-radius: 10px; background: var(--field); outline: none;
            transition: border-color .15s;
        }
        input:focus { border-color: var(--brand); background: #fff; }
        button {
            width: 100%; height: 48px; margin-top: 8px;
            background: var(--brand); color: #fff; border: none;
            border-radius: 10px; font-weight: 700; font-size: 15px;
            cursor: pointer; transition: background .15s;
        }
        button:hover { background: #1d4ed8; }
        .back-link {
            text-align: center; margin-top: 20px; font-size: 13.5px;
        }
        .back-link a { color: var(--brand); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Set Your Password</h1>
        <p class="desc">Please create a new password to secure your account and access the portal.</p>

        @if ($errors->any())
            <div class="alert">
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.update.reset') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">New Password</label>
                <input id="password" type="password" name="password" required placeholder="Minimum 8 characters">
            </div>

            <div class="form-group">
                <label for="password-confirm">Confirm New Password</label>
                <input id="password-confirm" type="password" name="password_confirmation" required placeholder="Repeat password">
            </div>

            <button type="submit">Save Password & Continue</button>
        </form>

        <div class="back-link">
            <a href="{{ route('login') }}">Back to Login</a>
        </div>
    </div>
</body>
</html>
