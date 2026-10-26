<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $currentLanguage['dir'] ?? 'ltr' }}">

<head>
    @include('layouts.partials.head')
</head>

<body>
    {{-- Where you are (tenant, company, branch, FY, apps) — resolved once, shared by the shell partials. --}}
    @php($shell = app(\App\Support\ShellContext::class)->resolve())

    @include('partials.duralux.sidebar')
    @include('partials.duralux.header')

    <main class="nxl-container">
        <div class="nxl-content">
            @if(request()->routeIs('production.*') || request()->is('production*'))
                @include('partials.duralux.production-workflow-strip')
            @endif

            {{-- Context bar: page title + status chips on the left, page actions on the right (ui-reference "SecondaryActionBar"). --}}
            <div class="page-header ax-context-bar">
                <div class="page-header-left d-flex align-items-center gap-2 min-w-0">
                    @hasSection('page-back-button')
                        <div class="me-1">
                            @yield('page-back-button')
                        </div>
                    @endif
                    <div class="page-header-title d-flex flex-wrap align-items-center gap-2 min-w-0">
                        <h5 class="ax-page-title mb-0">@yield('page-title', __('ui.dashboard'))</h5>
                        {{-- Ledger amounts carry no symbol of their own; say once, per page, what they are in. --}}
                        @if (request()->routeIs('accounting.*') && !request()->routeIs('accounting.exchange-rates.*') && company())
                            @php($reportingCurrency = company_currency())
                            <span class="ax-ref ax-tone-positive" title="{{ $reportingCurrency['name'] }} — the company's base currency">Amounts in {{ $reportingCurrency['code'] }} ({{ $reportingCurrency['symbol'] }})</span>
                        @endif
                        @yield('page-badge')
                    </div>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex d-md-none">
                            <a href="javascript:void(0)" class="page-header-right-close-toggle">
                                <i class="feather-arrow-left me-2"></i>
                                <span>{{ __('ui.back') }}</span>
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            @yield('page-actions')
                        </div>
                    </div>
                    <div class="d-md-none d-flex align-items-center">
                        <a href="javascript:void(0)" class="page-header-right-open-toggle">
                            <i class="feather-align-right fs-20"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="main-content">
                @if (tenant()?->subscription_status === \App\Models\Tenant::SUBSCRIPTION_PAST_DUE && auth()->user()?->tenant_id !== null)
                    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                        <span><i class="feather-alert-triangle me-2"></i>Your subscription renewal payment failed. Please renew soon to keep using the workspace — your data is safe.</span>
                        @can('viewSubscription', tenant())
                            <a href="{{ route('platform.subscription.index') }}" class="btn btn-sm btn-warning">Go to billing</a>
                        @endcan
                    </div>
                @endif
                @if(session('success'))
                    <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
                @endif
                @if(session('error'))
                    <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
                @endif
                @if(session('warning'))
                    <x-ui.toast :auto="true" type="warning" title="{{ session('warning') }}" />
                @endif
                @yield('content')
            </div>
        </div>

        @include('partials.duralux.footer')
    </main>

    @include('layouts.partials.scripts')
</body>

</html>