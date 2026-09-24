<?php

namespace App\Http\Middleware;

use App\Core\Tenant\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * ProductionIdempotencyMiddleware
 *
 * Provides transactional idempotency for critical write operations
 * against duplicate network retries from mobile/MES/IoT clients.
 *
 * Controlled via 'Idempotency-Key' HTTP header.
 */
class ProductionIdempotencyMiddleware
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        // Idempotency is opt-in per request header
        if (empty($idempotencyKey) || !is_string($idempotencyKey)) {
            return $next($request);
        }

        $tenantId = $this->tenantContext->id() ?? (auth()->user()?->tenant_id ?? 0);
        $userId   = auth()->id() ?? 0;
        $hash     = hash('sha256', $idempotencyKey . '|' . $request->method() . '|' . $request->path());
        
        $cacheKey = "prod_idem:t_{$tenantId}:u_{$userId}:{$hash}";
        $lockKey  = "prod_lock:t_{$tenantId}:u_{$userId}:{$hash}";

        $payloadHash = hash('sha256', $request->getContent());

        // Check if an idempotency record already exists
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            // If reused with a different payload, reject with 409 Conflict
            if (isset($cached['payload_hash']) && !hash_equals($cached['payload_hash'], $payloadHash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Idempotency-Key reused with a different request payload.',
                ], Response::HTTP_CONFLICT);
            }

            return response($cached['content'], $cached['status'])
                ->header('Content-Type', 'application/json')
                ->header('X-Idempotent-Replay', 'true');
        }

        // Lock to avoid race conditions on concurrent identical retries
        $acquired = Cache::add($lockKey, true, 30);
        if (!$acquired) {
            return response()->json([
                'success' => false,
                'message' => 'A request with this Idempotency-Key is currently processing.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $response = $next($request);

            // Only cache successful 2xx state-changing responses
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                Cache::put($cacheKey, [
                    'status'       => $response->getStatusCode(),
                    'content'      => $response->getContent(),
                    'payload_hash' => $payloadHash,
                ], now()->addHours(24));
            }

            return $response;
        } finally {
            Cache::forget($lockKey);
        }
    }
}
