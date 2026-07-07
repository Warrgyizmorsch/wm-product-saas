<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $currentLanguage['dir'] ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('ui.login') }} - {{ config('app.name', 'SaaS ERP') }}</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/theme.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/erp.css') }}">
</head>
@php
    $branding = tenant_branding();
@endphp
<body>
    <main class="auth-creative-wrapper">
        <div class="auth-creative-inner">
            <div class="creative-card-wrapper">
                <div class="card my-4 overflow-hidden" style="z-index: 1">
                    <div class="row flex-1 g-0">
                        <div class="col-lg-6 h-100 my-auto order-1 order-lg-0">
                            <div class="wd-50 bg-white p-2 rounded-circle shadow-lg position-absolute translate-middle top-50 start-50 d-none d-lg-block">
                                <img src="{{ $branding['abbr_logo'] }}" alt="{{ $branding['name'] }}" class="img-fluid">
                            </div>
                            <div class="creative-card-body card-body p-sm-5">
                                <h2 class="fs-20 fw-bolder mb-4">{{ __('ui.welcome_back') }}</h2>
                                <h4 class="fs-13 fw-bold mb-2">{{ __('ui.login_subtitle') }}</h4>
                                <p class="fs-12 fw-medium text-muted">{{ __('ui.login_tagline') }}</p>

                                @if ($errors->any())
                                    <div class="alert alert-danger" role="alert">
                                        {{ $errors->first() }}
                                    </div>
                                @endif

                                @if (session('success'))
                                    <div class="alert alert-success" role="alert">
                                        {{ session('success') }}
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('login.store') }}" class="w-100 mt-4 pt-2">
                                    @csrf

                                    <div class="mb-4">
                                        <input type="email" name="email" value="{{ old('email') }}"
                                            class="form-control @error('email') is-invalid @enderror"
                                            placeholder="{{ __('ui.email') }}" required autofocus>
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <input type="password" name="password"
                                            class="form-control @error('password') is-invalid @enderror"
                                            placeholder="{{ __('ui.password') }}" required>
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="rememberMe" name="remember">
                                                <label class="custom-control-label c-pointer" for="rememberMe">{{ __('ui.remember_me') }}</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-5">
                                        <button type="submit" class="btn btn-lg btn-primary w-100">{{ __('ui.login') }}</button>
                                    </div>
                                </form>

                                <div class="mt-5 text-muted fs-11">
                                    <i class="feather-lock"></i> {{ __('ui.secure_connection') }} &middot; {{ __('ui.authorized_personnel_only') }}
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 bg-primary order-0 order-lg-1">
                            <div class="h-100 d-flex align-items-center justify-content-center">
                                <img src="{{ asset('assets/images/auth/auth-user.png') }}" alt="" class="img-fluid">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
