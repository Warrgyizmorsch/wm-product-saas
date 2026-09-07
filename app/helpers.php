<?php

use App\Core\Branch\BranchContext;
use App\Core\Company\CompanyContext;
use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}

if (! function_exists('tenant_id')) {
    function tenant_id(): ?int
    {
        return app(TenantContext::class)->id();
    }
}

if (! function_exists('current_tenant_id')) {
    function current_tenant_id(): ?int
    {
        return auth()->user()?->tenant_id ?? tenant_id();
    }
}

if (! function_exists('require_tenant_id')) {
    function require_tenant_id(): int
    {
        $tenantId = current_tenant_id();

        abort_if($tenantId === null, 403, 'Tenant context is required.');

        return (int) $tenantId;
    }
}

if (! function_exists('tenant_context')) {
    function tenant_context(): TenantContext
    {
        return app(TenantContext::class);
    }
}

if (! function_exists('tenant_allowed_modules')) {
    /**
     * @return list<string>|null null means unrestricted (no plan gating; tenant
     *     has no plan_id linked, or that plan's features column is null)
     */
    function tenant_allowed_modules(): ?array
    {
        $plan = tenant()?->planCatalog;

        if ($plan === null || $plan->features === null) {
            return null;
        }

        return $plan->features;
    }
}

if (! function_exists('company')) {
    function company(): ?Company
    {
        return app(CompanyContext::class)->company();
    }
}

if (! function_exists('company_id')) {
    function company_id(): ?int
    {
        return app(CompanyContext::class)->id();
    }
}

if (! function_exists('current_company_id')) {
    function current_company_id(): ?int
    {
        return auth()->user()?->company_id ?? company_id();
    }
}

if (! function_exists('require_company_id')) {
    function require_company_id(): int
    {
        $companyId = current_company_id();

        abort_if($companyId === null, 422, 'Company context is required.');

        return (int) $companyId;
    }
}

if (! function_exists('company_context')) {
    function company_context(): CompanyContext
    {
        return app(CompanyContext::class);
    }
}

if (! function_exists('branch')) {
    function branch(): ?Branch
    {
        return app(BranchContext::class)->branch();
    }
}

if (! function_exists('branch_id')) {
    function branch_id(): ?int
    {
        return app(BranchContext::class)->id();
    }
}

if (! function_exists('current_branch_id')) {
    function current_branch_id(): ?int
    {
        return auth()->user()?->branch_id ?? branch_id();
    }
}

if (! function_exists('require_branch_id')) {
    function require_branch_id(): int
    {
        $branchId = current_branch_id();

        abort_if($branchId === null, 422, 'Branch context is required.');

        return (int) $branchId;
    }
}

if (! function_exists('branch_context')) {
    function branch_context(): BranchContext
    {
        return app(BranchContext::class);
    }
}

if (! function_exists('tenant_branding')) {
    function tenant_branding(?Tenant $tenant = null): array
    {
        $tenant ??= tenant();

        $settings = $tenant?->settings ?? [];
        $name = $settings['display_name'] ?? $tenant?->name ?? config('app.name', 'SaaS ERP');
        $fullLogo = $settings['logo_full'] ?? null;
        $abbrLogo = $settings['logo_abbr'] ?? $settings['logo_full'] ?? null;

        return [
            'name' => $name,
            'full_logo' => tenant_branding_url($fullLogo, 'assets/images/logo-full.png'),
            'abbr_logo' => tenant_branding_url($abbrLogo, 'assets/images/logo-abbr.png'),
            'has_full_logo' => filled($fullLogo),
            'has_abbr_logo' => filled($abbrLogo),
        ];
    }
}

if (! function_exists('tenant_branding_url')) {
    function tenant_branding_url(?string $path, string $fallback): string
    {
        if (blank($path)) {
            return asset($fallback);
        }

        if (Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'storage/')) {
            return asset($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return asset($path);
    }
}

if (! function_exists('active_currency')) {
    function active_currency(): string
    {
        $currencies = config('currency.currencies', []);
        $sessionCurrency = session('active_currency');

        if ($sessionCurrency && isset($currencies[$sessionCurrency])) {
            return $sessionCurrency;
        }

        return config('currency.base', 'USD');
    }
}

if (! function_exists('active_currency_info')) {
    function active_currency_info(): array
    {
        $code = active_currency();
        $currencies = config('currency.currencies', []);

        return $currencies[$code] ?? [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'rate' => 1.0,
            'position' => 'prefix',
        ];
    }
}

if (! function_exists('active_currency_symbol')) {
    function active_currency_symbol(): string
    {
        return active_currency_info()['symbol'] ?? '$';
    }
}

if (! function_exists('active_currency_rate')) {
    function active_currency_rate(): float
    {
        return (float) (active_currency_info()['rate'] ?? 1.0);
    }
}

if (! function_exists('convert_from_base')) {
    function convert_from_base($amountInBase): float
    {
        if ($amountInBase === null || $amountInBase === '') {
            return 0.0;
        }

        return (float) $amountInBase * active_currency_rate();
    }
}

if (! function_exists('convert_to_base')) {
    function convert_to_base($amountInActiveCurrency): float
    {
        if ($amountInActiveCurrency === null || $amountInActiveCurrency === '') {
            return 0.0;
        }

        $rate = active_currency_rate();
        if ($rate <= 0) {
            return (float) $amountInActiveCurrency;
        }

        return (float) $amountInActiveCurrency / $rate;
    }
}

if (! function_exists('format_indian_number')) {
    function format_indian_number(float|int $value, int $decimals = 2): string
    {
        $absVal = abs((float) $value);
        $parts = explode('.', sprintf('%.' . $decimals . 'f', $absVal));
        $intPart = $parts[0];
        $decPart = isset($parts[1]) && $decimals > 0 ? '.' . $parts[1] : '';

        if (strlen($intPart) <= 3) {
            $formattedInt = $intPart;
        } else {
            $lastThree = substr($intPart, -3);
            $rest = substr($intPart, 0, -3);
            $restFormatted = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $formattedInt = $restFormatted . ',' . $lastThree;
        }

        return $formattedInt . $decPart;
    }
}

if (! function_exists('format_currency')) {
    function format_currency($amountInBase, int $decimals = 2, bool $includeSymbol = true): string
    {
        if ($amountInBase === null || $amountInBase === '') {
            $val = 0.0;
        } else {
            $val = convert_from_base($amountInBase);
        }

        $code = active_currency();
        $isNegative = $val < 0;

        if ($code === 'INR') {
            $formatted = format_indian_number($val, $decimals);
        } else {
            $formatted = number_format(abs($val), $decimals);
        }

        if (! $includeSymbol) {
            return ($isNegative ? '-' : '') . $formatted;
        }

        $info = active_currency_info();
        $symbol = $info['symbol'] ?? '$';
        $position = $info['position'] ?? 'prefix';

        if ($position === 'suffix') {
            return ($isNegative ? '-' : '') . $formatted . ' ' . $symbol;
        }

        return ($isNegative ? '-' : '') . $symbol . $formatted;
    }
}



