<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ProductionApiSecretMiddleware
 *
 * Mandatory security gate for all /api/v1/production/* endpoints.
 * Requires a valid X-API-SECRET header matching the environment secret.
 * Uses constant-time comparison to prevent timing side-channel attacks.
 */
class ProductionApiSecretMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredSecret = config('production.api_secret');

        // Fail-safe: if the secret is not configured in the environment, deny access
        if (empty($configuredSecret) || !is_string($configuredSecret)) {
            return $this->deniedResponse();
        }

        $providedSecret = $request->header('X-API-SECRET');

        if (empty($providedSecret) || !is_string($providedSecret)) {
            return $this->deniedResponse();
        }

        // Constant-time string comparison to prevent timing attacks
        if (!hash_equals($configuredSecret, $providedSecret)) {
            return $this->deniedResponse();
        }

        return $next($request);
    }

    /**
     * Return generic unauthorized response to avoid leaking configuration details.
     */
    private function deniedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'API access denied.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
