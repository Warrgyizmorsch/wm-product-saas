<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="SaaS ERP admin dashboard">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', config('app.name', 'SaaS ERP'))</title>
<link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">
<link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendors/css/vendors.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendors/css/daterangepicker.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/theme.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/erp.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/production.css') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Mulish:ital,wght@0,200..1000;1,200..1000&family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap"
    rel="stylesheet">
{{-- Global design system (ui-reference/DESIGN.md) — keep it after the theme CSS so it wins. --}}
<link rel="stylesheet"
    href="{{ asset('assets/css/apex-ui.css') }}?v={{ @filemtime(public_path('assets/css/apex-ui.css')) }}">
<script>
    (function () {
        if (window.navigator && navigator.serviceWorker) {
            navigator.serviceWorker.getRegistrations().then(function (registrations) {
                registrations.forEach(function (registration) {
                    registration.unregister();
                });
            });
        }
    })();
    (function () {
        var savedSkin = localStorage.getItem('app-skin-dark') || localStorage.getItem('app-skin');
        if (savedSkin === 'app-skin-dark') {
            document.documentElement.classList.add('app-skin-dark');
        }
        // Header colour picker → one source of truth for the primary colour.
        // Sets --bs-primary and its RGB triplet, because Bootstrap/theme
        // styles (sidebar highlights, soft backgrounds) use rgba(var(--bs-primary-rgb), a).
        window.erpApplyPrimaryColor = function (hex) {
            var root = document.documentElement.style;
            root.setProperty('--bs-primary', hex);
            var m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex || '');
            if (m) {
                root.setProperty('--bs-primary-rgb', parseInt(m[1], 16) + ', ' + parseInt(m[2], 16) + ', ' + parseInt(m[3], 16));
            }
        };
        var savedColor = localStorage.getItem('erp_primary_color');
        if (savedColor) {
            window.erpApplyPrimaryColor(savedColor);
        }
    })();
    window.AppCurrency = {
        code: @json(active_currency()),
        symbol: @json(active_currency_symbol()),
        rate: {{ active_currency_rate() }},
        position: @json(active_currency_info()['position'] ?? 'prefix'),
        convertFromBase: function (amount) {
            return (parseFloat(amount) || 0) * this.rate;
        },
        convertToBase: function (amount) {
            return (parseFloat(amount) || 0) / (this.rate || 1);
        },
        format: function (amountInBase, decimals = 2, includeSymbol = true) {
            const val = this.convertFromBase(amountInBase);
            const isNeg = val < 0;
            const locale = this.code === 'INR' ? 'en-IN' : 'en-US';
            const formatted = Math.abs(val).toLocaleString(locale, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            if (!includeSymbol) return (isNeg ? '-' : '') + formatted;
            if (this.position === 'suffix') return (isNeg ? '-' : '') + formatted + ' ' + this.symbol;
            return (isNeg ? '-' : '') + this.symbol + formatted;
        }
    };
</script>



<style>
    /* Global Table Scroll Retention: Never allow dropdowns to reset horizontal scroll position */
    .table-responsive,
    .table-responsive:has(.dropdown.show),
    .table-responsive:has(.action-dropdown-wrapper.show) {
        overflow-x: auto !important;
    }

    .modal {
        z-index: 1060 !important;
    }

    .modal-backdrop {
        z-index: 1050 !important;
    }

    .dark-light-theme .light-button {
        display: none;
    }

    .header-color-picker-label {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        margin: 0;
        padding: 0;
        cursor: pointer;
        line-height: 1;
    }

    .header-color-preview-circle {
        display: block;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background-color: var(--bs-primary, #6337fa);
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15) inset, 0 1px 2px rgba(0, 0, 0, 0.08);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        flex-shrink: 0;
    }

    .header-color-picker-label:hover .header-color-preview-circle {
        transform: scale(1.08);
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.2) inset, 0 2px 4px rgba(0, 0, 0, 0.12);
    }

    .header-color-picker-input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100% !important;
        height: 100% !important;
        opacity: 0 !important;
        cursor: pointer !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        background: transparent !important;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
    }

    .header-color-picker-input::-webkit-color-swatch-wrapper {
        padding: 0;
    }

    .header-color-picker-input::-webkit-color-swatch {
        border: none;
        border-radius: 50%;
    }

    .header-color-picker-input::-moz-color-swatch {
        border: none;
        border-radius: 50%;
    }

    html.app-skin-dark .header-color-picker-wrapper {
        background-color: rgba(255, 255, 255, 0.05) !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
    }

    html.app-skin-dark .header-color-preview-circle {
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.25) inset, 0 1px 2px rgba(0, 0, 0, 0.3);
    }
</style>
@stack('styles')