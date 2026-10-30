<?php

namespace App\Http\Middleware;

use App\Services\Access\AccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the /admin platform console — see AccessService::canUsePlatformConsole
 * for who qualifies (platform super/admin role, or tenant-less platform staff).
 *
 * Runs outside the `tenant` middleware: no TenantContext is set here, so the
 * BelongsToTenant scope is a no-op and console queries see every tenant's rows.
 */
class EnsurePlatformAdmin
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $this->access->canUsePlatformConsole($user),
            Response::HTTP_FORBIDDEN,
            'The platform console is for platform staff only.'
        );

        return $next($request);
    }
}
