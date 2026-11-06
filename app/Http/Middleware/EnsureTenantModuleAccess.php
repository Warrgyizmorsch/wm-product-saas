<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantModuleAccess
{
    public const GATED_MODULES = [
        'crm', 'inventory', 'sales', 'purchase', 'production', 'hrms', 'accounting', 'projects', 'visitor',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $module = $request->segment(1);
        if ($module === 'api' || $module === 'v1') {
            $module = $request->segment(2);
            if ($module === 'v1') {
                $module = $request->segment(3);
            }
        }

        if (! in_array($module, self::GATED_MODULES, true)) {
            return $next($request);
        }

        $allowedModules = tenant_allowed_modules();

        abort_if(
            $allowedModules !== null && ! in_array($module, $allowedModules, true),
            403,
            'This module is not included in your plan.'
        );

        return $next($request);
    }
}
