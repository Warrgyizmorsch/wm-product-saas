<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $currentLanguage['dir'] ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('ui.login') }} - {{ config('app.name', 'SaaS ERP') }}</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400..700&family=Plus+Jakarta+Sans:wght@500..800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #5b3df5;
            --brand-2: #7c5cff;
            --accent: #14c8a8;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --field: #f8fafc;
            --danger: #e11d48;
            --success: #059669;
            --night: #0a0f1f;
            --font: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-display: 'Plus Jakarta Sans', 'DM Sans', sans-serif;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            font-family: var(--font);
            color: var(--ink);
            background: #fff;
            -webkit-font-smoothing: antialiased;
        }

        .auth { display: grid; grid-template-columns: minmax(420px, 1fr) 1.15fr; min-height: 100vh; }

        /* ---------- Form side ---------- */
        .auth-form-side {
            position: relative;
            display: flex;
            flex-direction: column;
            padding: 32px clamp(24px, 6vw, 88px);
            background:
                radial-gradient(600px 300px at 0% 0%, rgba(91, 61, 245, .07), transparent 60%),
                radial-gradient(500px 300px at 100% 100%, rgba(20, 200, 168, .06), transparent 60%),
                #fff;
        }
        .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--ink); }
        .brand img { width: 40px; height: 40px; object-fit: contain; border-radius: 12px; background: #fff; box-shadow: 0 6px 18px rgba(15, 23, 42, .1); padding: 4px; }
        .brand span { font-family: var(--font-display); font-weight: 800; font-size: 18px; letter-spacing: -.01em; }

        .form-wrap { flex: 1; display: flex; align-items: center; justify-content: center; }
        .form-card { width: 100%; max-width: 400px; }

        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 12px; border-radius: 999px;
            background: rgba(91, 61, 245, .08); color: var(--brand);
            font-size: 12px; font-weight: 600;
        }
        .badge .pulse { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); position: relative; }
        .badge .pulse::after {
            content: ''; position: absolute; inset: 0; border-radius: 50%; background: var(--accent);
            animation: ping 1.8s cubic-bezier(0, 0, .2, 1) infinite;
        }

        h1.title {
            font-family: var(--font-display);
            font-size: clamp(28px, 3vw, 34px);
            line-height: 1.15; letter-spacing: -.025em;
            margin: 18px 0 8px; font-weight: 800;
        }
        h1.title .wave { display: inline-block; transform-origin: 70% 70%; animation: wave 2.4s ease-in-out 1s 2; }
        .subtitle { margin: 0 0 6px; font-weight: 600; font-size: 15px; }
        .tagline { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.6; }

        .alert {
            display: flex; gap: 10px; align-items: flex-start;
            margin-top: 22px; padding: 12px 14px; border-radius: 12px;
            font-size: 13.5px; line-height: 1.5;
            animation: shake .45s ease both;
        }
        .alert svg { flex: none; margin-top: 1px; }
        .alert-danger { background: #fff1f3; color: #9f1239; border: 1px solid #fecdd3; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; animation-name: rise; }

        form { margin-top: 28px; }
        .field { position: relative; margin-bottom: 18px; }
        .field input {
            width: 100%; height: 56px;
            padding: 20px 46px 6px 46px;
            font: 500 15px var(--font); color: var(--ink);
            background: var(--field);
            border: 1.5px solid var(--line); border-radius: 14px;
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .field input:hover { border-color: #cbd5e1; }
        .field input:focus { background: #fff; border-color: var(--brand); box-shadow: 0 0 0 4px rgba(91, 61, 245, .12); }
        .field input:-webkit-autofill { -webkit-box-shadow: 0 0 0 40px #fff inset; -webkit-text-fill-color: var(--ink); }
        .field label {
            position: absolute; left: 46px; top: 50%; transform: translateY(-50%);
            color: var(--muted); font-size: 15px; pointer-events: none;
            transition: top .18s ease, font-size .18s ease, color .18s ease;
        }
        .field input:focus + label,
        .field input:not(:placeholder-shown) + label,
        .field input:-webkit-autofill + label { top: 17px; font-size: 11.5px; font-weight: 600; letter-spacing: .02em; }
        .field input:focus + label { color: var(--brand); }
        .field .icon {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; transition: color .2s; pointer-events: none;
        }
        .field input:focus ~ .icon { color: var(--brand); }
        .field.is-invalid input { border-color: var(--danger); background: #fffafb; }
        .field.is-invalid input:focus { box-shadow: 0 0 0 4px rgba(225, 29, 72, .12); }
        .field-error { color: var(--danger); font-size: 12.5px; margin: 6px 4px 0; }

        .toggle-pass {
            position: absolute; right: 8px; top: 28px; transform: translateY(-50%);
            width: 38px; height: 38px; border: 0; border-radius: 10px;
            background: transparent; color: #94a3b8; cursor: pointer;
            display: grid; place-items: center; transition: background .2s, color .2s;
        }
        .toggle-pass:hover { background: #eef2ff; color: var(--brand); }
        .toggle-pass .eye-off { display: none; }
        .toggle-pass[aria-pressed="true"] .eye { display: none; }
        .toggle-pass[aria-pressed="true"] .eye-off { display: block; }

        .caps {
            display: none; align-items: center; gap: 6px;
            color: #b45309; font-size: 12.5px; margin: 6px 4px 0;
        }
        .caps.show { display: flex; animation: rise .25s ease both; }

        .row-between { display: flex; align-items: center; justify-content: space-between; margin: 4px 0 26px; }
        .check { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; font-size: 14px; color: #334155; }
        .check input { position: absolute; opacity: 0; width: 0; height: 0; }
        .check .box {
            width: 20px; height: 20px; border-radius: 6px; border: 1.5px solid #cbd5e1; background: #fff;
            display: grid; place-items: center; transition: all .2s;
        }
        .check .box svg { opacity: 0; transform: scale(.4); transition: all .2s cubic-bezier(.34, 1.56, .64, 1); }
        .check input:checked + .box { background: var(--brand); border-color: var(--brand); }
        .check input:checked + .box svg { opacity: 1; transform: scale(1); }
        .check input:focus-visible + .box { box-shadow: 0 0 0 4px rgba(91, 61, 245, .18); }

        .btn-primary {
            position: relative; overflow: hidden;
            width: 100%; height: 54px; border: 0; border-radius: 14px; cursor: pointer;
            font: 700 15px var(--font-display); letter-spacing: .01em; color: #fff;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 55%, #9b7bff 100%);
            background-size: 200% 200%;
            box-shadow: 0 10px 24px -8px rgba(91, 61, 245, .6), inset 0 1px 0 rgba(255, 255, 255, .2);
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: transform .2s ease, box-shadow .2s ease, background-position .5s ease;
        }
        .btn-primary::before {
            content: ''; position: absolute; top: 0; left: -120%; width: 60%; height: 100%;
            background: linear-gradient(100deg, transparent, rgba(255, 255, 255, .35), transparent);
            transform: skewX(-20deg); transition: left .7s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); background-position: 100% 0; box-shadow: 0 16px 32px -10px rgba(91, 61, 245, .7); }
        .btn-primary:hover::before { left: 140%; }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary:focus-visible { outline: 3px solid rgba(91, 61, 245, .35); outline-offset: 3px; }
        .btn-primary .arrow { transition: transform .25s ease; }
        .btn-primary:hover .arrow { transform: translateX(4px); }
        .btn-primary .spinner {
            display: none; width: 18px; height: 18px; border-radius: 50%;
            border: 2.5px solid rgba(255, 255, 255, .35); border-top-color: #fff;
            animation: spin .7s linear infinite;
        }
        .btn-primary.loading { pointer-events: none; }
        .btn-primary.loading .spinner { display: block; }
        .btn-primary.loading .arrow { display: none; }

        .trust {
            margin-top: 28px; padding-top: 22px; border-top: 1px dashed var(--line);
            display: flex; align-items: center; gap: 10px; color: var(--muted); font-size: 12.5px;
        }
        .trust .shield {
            width: 32px; height: 32px; border-radius: 10px; flex: none;
            display: grid; place-items: center; background: #ecfdf5; color: var(--success);
        }
        .foot { color: #94a3b8; font-size: 12px; text-align: center; padding-top: 16px; }

        /* Staggered entrance */
        .reveal { opacity: 0; animation: rise .7s cubic-bezier(.2, .8, .2, 1) forwards; animation-delay: calc(var(--d, 0) * 80ms + 100ms); }

        /* ---------- Showcase side ---------- */
        .showcase {
            position: relative; overflow: hidden; isolation: isolate;
            background: var(--night); color: #fff;
            display: flex; flex-direction: column; justify-content: safe center;
            padding: 48px clamp(32px, 5vw, 80px);
            max-height: 100vh; overflow-y: auto; position: sticky; top: 0; scrollbar-width: none;
        }
        .showcase .grid-bg {
            position: absolute; inset: 0; z-index: -2;
            background-image:
                linear-gradient(rgba(255, 255, 255, .05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .05) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(ellipse 80% 70% at 50% 40%, #000 30%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse 80% 70% at 50% 40%, #000 30%, transparent 75%);
            animation: grid-pan 30s linear infinite;
        }
        .blob { position: absolute; z-index: -3; border-radius: 50%; filter: blur(80px); opacity: .55; }
        .blob.b1 { width: 460px; height: 460px; background: #5b3df5; top: -120px; right: -80px; animation: drift1 18s ease-in-out infinite alternate; }
        .blob.b2 { width: 380px; height: 380px; background: #14c8a8; bottom: -140px; left: -60px; opacity: .35; animation: drift2 22s ease-in-out infinite alternate; }
        .blob.b3 { width: 280px; height: 280px; background: #e04dd8; top: 40%; left: 45%; opacity: .22; animation: drift3 26s ease-in-out infinite alternate; }

        .stage { position: relative; width: min(100%, 480px); aspect-ratio: 1 / .9; margin: 0 auto 24px; flex: none; }
        .orbit-wrap { position: absolute; inset: 0; display: grid; place-items: center; }
        .ring {
            position: absolute; border-radius: 50%; border: 1px dashed rgba(255, 255, 255, .14);
        }
        .ring.r1 { width: 46%; aspect-ratio: 1; animation: spin 40s linear infinite; }
        .ring.r2 { width: 74%; aspect-ratio: 1; border-style: solid; border-color: rgba(255, 255, 255, .07); }
        .ring.r1::after {
            content: ''; position: absolute; top: -4px; left: 50%; width: 8px; height: 8px; border-radius: 50%;
            background: var(--accent); box-shadow: 0 0 14px var(--accent);
        }
        .core {
            position: relative; width: 26%; aspect-ratio: 1; border-radius: 50%;
            display: grid; place-items: center; text-align: center;
            background: radial-gradient(circle at 30% 30%, #3ff0cf, #0fa88c 70%);
            box-shadow: 0 0 0 10px rgba(20, 200, 168, .1), 0 0 60px rgba(20, 200, 168, .55);
            animation: breathe 4s ease-in-out infinite;
        }
        .core::before, .core::after {
            content: ''; position: absolute; inset: -14px; border-radius: 50%;
            border: 1.5px solid rgba(20, 200, 168, .5);
            animation: ripple 3s ease-out infinite;
        }
        .core::after { animation-delay: 1.5s; }
        .core b { font-family: var(--font-display); font-size: clamp(16px, 2vw, 22px); line-height: 1; color: #04241e; }
        .core small { display: block; font-size: 9px; letter-spacing: .22em; font-weight: 700; color: #064e42; margin-top: 3px; }

        svg.links { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
        svg.links line { stroke: rgba(255, 255, 255, .16); stroke-width: 1; stroke-dasharray: 4 6; }
        svg.links circle.packet { fill: var(--accent); filter: drop-shadow(0 0 4px var(--accent)); }

        .node {
            position: absolute; transform: translate(-50%, -50%);
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 14px; border-radius: 12px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            font-size: 13px; font-weight: 600; white-space: nowrap;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .25);
            opacity: 0; animation: pop .6s cubic-bezier(.34, 1.56, .64, 1) forwards, float 6s ease-in-out infinite;
            animation-delay: calc(var(--i) * 120ms + 500ms), calc(var(--i) * -900ms);
            transition: background .25s, border-color .25s;
        }
        .node:hover { background: rgba(91, 61, 245, .35); border-color: rgba(124, 92, 255, .7); }
        .node .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--c, var(--accent)); box-shadow: 0 0 10px var(--c, var(--accent)); }

        .kpi {
            position: absolute; z-index: 2;
            padding: 12px 14px; border-radius: 14px; min-width: 150px;
            background: rgba(255, 255, 255, .92); color: var(--ink);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, .5);
            opacity: 0; animation: pop .7s cubic-bezier(.34, 1.56, .64, 1) forwards, float 7s ease-in-out infinite;
        }
        .kpi .k-label { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
        .kpi .k-value { font-family: var(--font-display); font-size: 20px; font-weight: 800; margin-top: 2px; display: flex; align-items: baseline; gap: 8px; }
        .kpi .k-up { font-size: 11px; font-weight: 700; color: var(--success); background: #d1fae5; padding: 2px 6px; border-radius: 6px; }
        .kpi.k1 { bottom: 0; left: -4%; animation-delay: 1.3s, -2s; }
        .kpi.k2 { top: 0; right: -4%; animation-delay: 1.5s, -4s; }
        .kpi .bars { display: flex; align-items: flex-end; gap: 4px; height: 28px; margin-top: 8px; }
        .kpi .bars i {
            flex: 1; border-radius: 3px 3px 0 0; background: linear-gradient(var(--brand-2), var(--brand));
            transform-origin: bottom; transform: scaleY(0);
            animation: grow .8s cubic-bezier(.2, .8, .2, 1) forwards; animation-delay: calc(1.6s + var(--n) * 70ms);
        }
        .kpi svg.spark { display: block; margin-top: 6px; }
        .kpi svg.spark path {
            stroke-dasharray: 220; stroke-dashoffset: 220;
            animation: draw 1.6s ease forwards 1.8s;
        }

        .headline {
            font-family: var(--font-display); font-weight: 800;
            font-size: clamp(30px, 3.4vw, 46px); line-height: 1.08; letter-spacing: -.03em;
            margin: 0;
        }
        .headline .grad {
            background: linear-gradient(90deg, #3ff0cf, #7c5cff, #e04dd8, #3ff0cf);
            background-size: 300% 100%;
            -webkit-background-clip: text; background-clip: text; color: transparent;
            animation: shimmer 8s linear infinite;
        }
        .lede { margin: 16px 0 0; max-width: 520px; color: rgba(255, 255, 255, .68); font-size: 15px; line-height: 1.65; }

        .features { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 28px; max-width: 560px; }
        .feature {
            display: flex; gap: 12px; align-items: flex-start;
            padding: 12px 14px; border-radius: 14px;
            background: rgba(255, 255, 255, .04); border: 1px solid rgba(255, 255, 255, .08);
            transition: transform .25s ease, background .25s ease, border-color .25s ease;
        }
        .feature:hover { transform: translateY(-3px); background: rgba(255, 255, 255, .07); border-color: rgba(255, 255, 255, .16); }
        .feature .f-ico { width: 34px; height: 34px; border-radius: 10px; flex: none; display: grid; place-items: center; background: var(--bg); color: var(--fg); }
        .feature b { display: block; font-size: 13.5px; }
        .feature span { display: block; font-size: 12.5px; color: rgba(255, 255, 255, .55); margin-top: 2px; }

        /* ---------- Keyframes ---------- */
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        @keyframes pop { from { opacity: 0; scale: .7; } to { opacity: 1; scale: 1; } }
        @keyframes float { 0%, 100% { translate: 0 0; } 50% { translate: 0 -8px; } }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes ping { 75%, 100% { transform: scale(2.4); opacity: 0; } }
        @keyframes wave { 0%, 60%, 100% { transform: rotate(0); } 10%, 30% { transform: rotate(14deg); } 20% { transform: rotate(-8deg); } 40% { transform: rotate(-4deg); } 50% { transform: rotate(10deg); } }
        @keyframes shake { 0%, 100% { transform: none; } 20%, 60% { transform: translateX(-6px); } 40%, 80% { transform: translateX(6px); } }
        @keyframes breathe { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        @keyframes ripple { from { transform: scale(.85); opacity: .8; } to { transform: scale(1.7); opacity: 0; } }
        @keyframes grow { to { transform: scaleY(1); } }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        @keyframes shimmer { to { background-position: 300% 0; } }
        @keyframes grid-pan { to { background-position: 44px 44px; } }
        @keyframes drift1 { to { transform: translate(-120px, 140px) scale(1.15); } }
        @keyframes drift2 { to { transform: translate(160px, -100px) scale(1.2); } }
        @keyframes drift3 { to { transform: translate(-180px, 80px) scale(.85); } }

        /* ---------- Responsive ---------- */
        @media (max-width: 1100px) {
            .auth { grid-template-columns: 1fr 1fr; }
            .features { grid-template-columns: 1fr; }
            .kpi.k1 { display: none; }
        }
        @media (max-height: 860px) and (min-width: 861px) {
            .stage { width: min(100%, 400px); }
            .features { display: none; }
        }
        @media (max-width: 860px) {
            .auth { grid-template-columns: 1fr; }
            .showcase { display: none; }
            .auth-form-side { min-height: 100vh; padding: 24px 16px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .001ms !important; animation-iteration-count: 1 !important; transition-duration: .001ms !important; }
            .reveal, .node, .kpi { opacity: 1; }
        }
    </style>
</head>
@php
    $branding = tenant_branding();
    $modules = [
        ['CRM', 16, 20, '#3ff0cf'],
        ['Sales', 50, 6, '#7c5cff'],
        ['Inventory', 9, 52, '#f59e0b'],
        ['Accounting', 90, 46, '#3ff0cf'],
        ['HRMS', 52, 94, '#e04dd8'],
        ['Production', 84, 80, '#7c5cff'],
    ];
    $features = [
        ['CRM', 'ui.login_feature_crm_desc', 'rgba(63,240,207,.12)', '#3ff0cf', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
        ['Inventory', 'ui.login_feature_inventory_desc', 'rgba(245,158,11,.14)', '#fbbf24', '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12"/>'],
        ['Production', 'ui.login_feature_production_desc', 'rgba(124,92,255,.18)', '#a78bfa', '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'],
        ['HRMS', 'ui.login_feature_hrms_desc', 'rgba(224,77,216,.15)', '#f0abfc', '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/>'],
    ];
@endphp
<body>
    <main class="auth">
        {{-- ================= Form side ================= --}}
        <section class="auth-form-side">
            <a href="{{ url('/') }}" class="brand reveal" style="--d:0">
                <img src="{{ $branding['abbr_logo'] }}" alt="">
                <span>{{ $branding['name'] }}</span>
            </a>

            <div class="form-wrap">
                <div class="form-card">
                    <span class="badge reveal" style="--d:1"><span class="pulse"></span>{{ $loginBadge ?? __('ui.login_badge') }}</span>

                    <h1 class="title reveal" style="--d:2">{{ __('ui.welcome_back') }} <span class="wave" aria-hidden="true">👋</span></h1>
                    <p class="subtitle reveal" style="--d:3">{{ __('ui.login_subtitle') }}</p>
                    <p class="tagline reveal" style="--d:3">{{ __('ui.login_tagline') }}</p>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ $loginAction ?? route('login.store') }}" id="loginForm" novalidate>
                        @csrf

                        <div class="field reveal @error('email') is-invalid @enderror" style="--d:4">
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                placeholder=" " autocomplete="username" required autofocus
                                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            <label for="email">{{ __('ui.email') }}</label>
                            <svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m22 7-10 6L2 7"/></svg>
                            @error('email')
                                <p class="field-error" id="email-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field reveal @error('password') is-invalid @enderror" style="--d:5">
                            <input type="password" id="password" name="password"
                                placeholder=" " autocomplete="current-password" required
                                @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            <label for="password">{{ __('ui.password') }}</label>
                            <svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <button type="button" class="toggle-pass" aria-pressed="false" aria-label="{{ __('ui.login_show_password') }}" title="{{ __('ui.login_show_password') }}">
                                <svg class="eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22"/></svg>
                            </button>
                            <p class="caps" id="capsWarning" role="status">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg>
                                {{ __('ui.login_caps_lock') }}
                            </p>
                            @error('password')
                                <p class="field-error" id="password-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="row-between reveal" style="--d:6">
                            <label class="check" for="rememberMe">
                                <input type="checkbox" id="rememberMe" name="remember" @checked(old('remember'))>
                                <span class="box"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                                {{ __('ui.remember_me') }}
                            </label>
                        </div>

                        <div class="reveal" style="--d:7">
                            <button type="submit" class="btn-primary" id="loginBtn">
                                <span class="spinner" aria-hidden="true"></span>
                                <span class="btn-label" data-loading="{{ __('ui.login_signing_in') }}">{{ __('ui.login') }}</span>
                                <svg class="arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </form>

                    <div class="trust reveal" style="--d:8">
                        <span class="shield">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                        </span>
                        <span>{{ __('ui.secure_connection') }} &middot; {{ __('ui.authorized_personnel_only') }}</span>
                    </div>
                </div>
            </div>

            <p class="foot">&copy; {{ date('Y') }} {{ $branding['name'] }}</p>
        </section>

        {{-- ================= Showcase side ================= --}}
        <aside class="showcase" aria-hidden="true">
            <div class="grid-bg"></div>
            <div class="blob b1"></div>
            <div class="blob b2"></div>
            <div class="blob b3"></div>

            <div class="stage">
                <div class="orbit-wrap">
                    <div class="ring r2"></div>
                    <div class="ring r1"></div>
                </div>

                <svg class="links" viewBox="0 0 100 100" preserveAspectRatio="none">
                    @foreach ($modules as $i => [$label, $x, $y])
                        <line x1="50" y1="50" x2="{{ $x }}" y2="{{ $y }}" vector-effect="non-scaling-stroke"/>
                        <circle class="packet" r="0.9">
                            <animateMotion dur="{{ 2.4 + $i * 0.35 }}s" begin="{{ $i * 0.4 }}s" repeatCount="indefinite"
                                path="M{{ $x }},{{ $y }} L50,50"/>
                        </circle>
                    @endforeach
                </svg>

                <div class="orbit-wrap">
                    <div class="core"><div><b>One</b><small>CORE</small></div></div>
                </div>

                @foreach ($modules as $i => [$label, $x, $y, $color])
                    <span class="node" style="left: {{ $x }}%; top: {{ $y }}%; --i: {{ $i }}; --c: {{ $color }}"><span class="dot"></span>{{ $label }}</span>
                @endforeach

                <div class="kpi k1">
                    <div class="k-label">Sales</div>
                    <div class="k-value">₹ 4.82L <span class="k-up">▲ 24%</span></div>
                    <svg class="spark" width="140" height="30" viewBox="0 0 140 30" fill="none">
                        <defs><linearGradient id="sg" x1="0" x2="1"><stop offset="0" stop-color="#14c8a8"/><stop offset="1" stop-color="#5b3df5"/></linearGradient></defs>
                        <path d="M2 24 L20 20 L36 22 L52 14 L70 16 L88 9 L106 11 L122 5 L138 3" stroke="url(#sg)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <div class="kpi k2">
                    <div class="k-label">Production</div>
                    <div class="k-value">98.4% <span class="k-up">▲ 6%</span></div>
                    <div class="bars">
                        @foreach ([40, 65, 50, 80, 70, 95, 85] as $n => $h)
                            <i style="height: {{ $h }}%; --n: {{ $n }}"></i>
                        @endforeach
                    </div>
                </div>
            </div>

            <h2 class="headline">
                {{ __('ui.login_headline_line1') }}<br>
                <span class="grad">{{ __('ui.login_headline_line2') }}</span>
            </h2>
            <p class="lede">{{ __('ui.login_tagline') }}</p>

            <div class="features">
                @foreach ($features as [$name, $descKey, $bg, $fg, $icon])
                    <div class="feature">
                        <span class="f-ico" style="--bg: {{ $bg }}; --fg: {{ $fg }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
                        </span>
                        <div><b>{{ $name }}</b><span>{{ __($descKey) }}</span></div>
                    </div>
                @endforeach
            </div>
        </aside>
    </main>

    <script>
        (function () {
            var form = document.getElementById('loginForm');
            var btn = document.getElementById('loginBtn');
            var pass = document.getElementById('password');
            var toggle = document.querySelector('.toggle-pass');
            var caps = document.getElementById('capsWarning');
            var label = btn.querySelector('.btn-label');
            var labelText = label.textContent;

            toggle.addEventListener('click', function () {
                var show = pass.type === 'password';
                pass.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                pass.focus();
            });

            function checkCaps(e) {
                if (e.getModifierState) caps.classList.toggle('show', e.getModifierState('CapsLock'));
            }
            pass.addEventListener('keydown', checkCaps);
            pass.addEventListener('keyup', checkCaps);
            pass.addEventListener('blur', function () { caps.classList.remove('show'); });

            form.addEventListener('submit', function (e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    var bad = form.querySelector(':invalid');
                    bad.closest('.field').classList.add('is-invalid');
                    bad.style.animation = 'none';
                    bad.offsetHeight;
                    bad.style.animation = 'shake .45s ease both';
                    bad.focus();
                    return;
                }
                btn.classList.add('loading');
                label.textContent = label.dataset.loading;
            });

            form.querySelectorAll('input').forEach(function (input) {
                input.addEventListener('input', function () {
                    var field = input.closest('.field');
                    if (field && input.checkValidity()) field.classList.remove('is-invalid');
                });
            });

            // Restore the button if the page is shown again from the back/forward cache.
            window.addEventListener('pageshow', function () {
                btn.classList.remove('loading');
                label.textContent = labelText;
            });
        })();
    </script>
</body>
</html>
