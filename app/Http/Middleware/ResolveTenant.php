<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        abort_if($tenant === null, Response::HTTP_NOT_FOUND, 'Tenant not found.');
        abort_if($tenant->status !== 'active', Response::HTTP_FORBIDDEN, 'Tenant is not active.');

        app(Tenancy::class)->setTenant($tenant);

        try {
            return $next($request);
        } finally {
            app(Tenancy::class)->clear();
        }
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $tenantKey = $request->header(config('tenancy.header'));

        if ($tenantKey) {
            return Tenant::query()
                ->where('slug', $tenantKey)
                ->orWhere('domain', $tenantKey)
                ->first();
        }

        $host = $request->getHost();

        if (in_array($host, config('tenancy.central_domains'), true)) {
            return null;
        }

        return Tenant::query()
            ->where('domain', $host)
            ->orWhere('slug', explode('.', $host)[0])
            ->first();
    }
}
