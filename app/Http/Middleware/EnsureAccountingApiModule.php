<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureTenantModuleAccess keys off the first URL segment, which is "api" here,
 * so an API route has to check the plan for itself.
 */
class EnsureAccountingApiModule
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = tenant_allowed_modules();

        if ($allowed !== null && ! in_array('accounting', $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Accounting is not included in your plan.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
