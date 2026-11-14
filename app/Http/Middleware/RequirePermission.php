<?php

namespace App\Http\Middleware;

use App\Services\Access\AccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate for controllers that have no policy of their
 * own: `->middleware('permission:tenant.settings.manage')`. Checks through
 * AccessService in the current tenant's context.
 */
class RequirePermission
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $this->access->allows($user, $permission, ['tenant_id' => tenant_id()]),
            403
        );

        return $next($request);
    }
}
