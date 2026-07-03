<?php

use App\Core\Tenant\TenantContext;
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
