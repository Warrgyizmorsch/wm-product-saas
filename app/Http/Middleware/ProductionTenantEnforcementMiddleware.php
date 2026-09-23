<?php

namespace App\Http\Middleware;

use App\Core\Tenant\TenantContext;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ProductionTenantEnforcementMiddleware
 *
 * Ensures strict tenant isolation for Production API endpoints.
 * Validates that the authenticated user belongs to the active tenant context.
 */
class ProductionTenantEnforcementMiddleware
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly Tenancy $tenancy,
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $activeTenant = $this->tenantContext->tenant();

        // If no tenant was resolved from headers/host, resolve from user's tenant_id
        if (!$activeTenant && $user->tenant_id) {
            $userTenant = $user->tenant ?? \App\Models\Tenant::find($user->tenant_id);

            if (!$userTenant || !$userTenant->isAccessible()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant is not available.',
                ], Response::HTTP_FORBIDDEN);
            }

            $this->tenantContext->set($userTenant);
            $this->tenancy->set($userTenant);
            $activeTenant = $userTenant;
        }

        // Verify active tenant exists
        if (!$activeTenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Enforce strict tenant isolation: user tenant_id MUST match active tenant id
        if ((int) $user->tenant_id !== (int) $activeTenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Verify tenant accessibility
        if (!$activeTenant->isAccessible()) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant is not available.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
