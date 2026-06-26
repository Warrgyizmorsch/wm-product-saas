<?php

namespace App\Http\Middleware;

use App\Core\Tenant\TenantContext;
use App\Core\Tenant\TenantResolver;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly TenantContext $context,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenants->resolve($request);

        abort_if($tenant === null, Response::HTTP_NOT_FOUND, 'Tenant not found.');
        abort_if($tenant->status !== 'active', Response::HTTP_FORBIDDEN, 'Tenant is not active.');

        $this->context->set($tenant);
        app(Tenancy::class)->set($tenant);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
            app(Tenancy::class)->clear();
        }
    }
}
