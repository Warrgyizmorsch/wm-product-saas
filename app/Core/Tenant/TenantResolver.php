<?php

namespace App\Core\Tenant;

use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $tenantKey = $request->header(config('tenancy.header'));

        if ($tenantKey) {
            return $this->findByKey($tenantKey);
        }

        $sessionTenantSlug = $request->hasSession() ? $request->session()->get('tenant_slug') : null;

        if ($sessionTenantSlug) {
            return Tenant::query()
                ->where('slug', $sessionTenantSlug)
                ->where('status', 'active')
                ->first();
        }

        $host = $request->getHost();

        if (in_array($host, config('tenancy.central_domains'), true)) {
            return $this->resolveLocalFallback();
        }

        return Tenant::query()
            ->where('domain', $host)
            ->orWhere('slug', $this->subdomainFromHost($host))
            ->first();
    }

    private function findByKey(string $tenantKey): ?Tenant
    {
        return Tenant::query()
            ->where('slug', $tenantKey)
            ->orWhere('domain', $tenantKey)
            ->first();
    }

    private function resolveLocalFallback(): ?Tenant
    {
        if (! app()->environment('local')) {
            return null;
        }

        $slug = config('tenancy.local_fallback_slug');

        if ($slug) {
            $tenant = Tenant::query()
                ->where('slug', $slug)
                ->where('status', 'active')
                ->first();

            if ($tenant !== null) {
                return $tenant;
            }
        }

        return Tenant::query()
            ->where('status', 'active')
            ->first();
    }

    private function subdomainFromHost(string $host): string
    {
        return explode('.', $host)[0];
    }
}
