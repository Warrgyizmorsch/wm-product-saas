<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Billing-only lockout: once a tenant's subscription has lapsed (grace ran
 * out, or a cancelled period ended — see TenantSubscriptionService::reconcile)
 * its users can still sign in, but only billing and their own account work
 * until someone renews. Nothing is deleted. Platform users (no tenant_id) are
 * never locked out.
 */
class EnsureBillingActive
{
    /** Still reachable while locked. */
    private const ALLOWED_ROUTES = [
        'logout',
        'tenant.switch',
        'company.switch',
        'branch.switch',
        'profile.*',
        'account.*',
        'notifications.*',
        'platform.subscription.*',
        'platform.billing.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();
        $user = $request->user();

        if ($tenant === null || ! $tenant->isBillingLocked() || $user === null || $user->tenant_id === null
            || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        $message = 'Your subscription has lapsed — renew it to use the workspace again. Your data is safe.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_PAYMENT_REQUIRED);
        }

        if ($user->can('viewSubscription', $tenant)) {
            return redirect()->route('platform.subscription.index')->with('error', $message);
        }

        return response()->view('modules.platform.subscription.locked', ['tenant' => $tenant], Response::HTTP_PAYMENT_REQUIRED);
    }
}
