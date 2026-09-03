<?php

namespace App\Http\Middleware;

use App\Core\Company\CompanyContext;
use App\Core\Company\CompanyResolver;
use App\Support\Companying;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompany
{
    public function __construct(
        private readonly CompanyResolver $companies,
        private readonly CompanyContext $context,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = tenant_id();

        $company = $tenantId !== null ? $this->companies->resolve($request, $tenantId) : null;

        $this->context->set($company);
        app(Companying::class)->set($company);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
            app(Companying::class)->clear();
        }
    }
}
