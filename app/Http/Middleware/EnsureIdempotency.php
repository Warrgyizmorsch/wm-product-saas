<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureIdempotency
 *
 * Provides transactional idempotency for critical API write operations
 * against duplicate network retries or double-tap submissions from mobile clients.
 *
 * Controlled via 'X-Idempotency-Key' or 'Idempotency-Key' HTTP header.
 */
class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->header('Idempotency-Key');

        // Idempotency is opt-in per request header and applies only to mutating HTTP methods
        if (empty($idempotencyKey) || !is_string($idempotencyKey) || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $tenantId = tenant_id() ?? ($request->user()?->tenant_id ?? 0);
        $userId   = $request->user()?->id ?? 0;
        $hash     = hash('sha256', $idempotencyKey . '|' . $request->method() . '|' . $request->path());

        $cacheKey = "idempotency:t_{$tenantId}:u_{$userId}:{$hash}";
        $lockKey  = "lock_idempotency:t_{$tenantId}:u_{$userId}:{$hash}";

        $payloadHash = hash('sha256', (string) $request->getContent());

        // 1. If an idempotency record already exists, return the cached response
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            // If reused with a different payload, reject with 409 Conflict
            if (isset($cached['payload_hash']) && !hash_equals((string) $cached['payload_hash'], $payloadHash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Idempotency-Key reused with a different request payload.',
                ], Response::HTTP_CONFLICT);
            }

            return response($cached['content'], (int) $cached['status'])
                ->header('Content-Type', 'application/json')
                ->header('X-Idempotent-Replay', 'true');
        }

        // 2. Lock to avoid race conditions on concurrent identical retries
        $acquired = Cache::add($lockKey, true, 15);
        if (!$acquired) {
            return response()->json([
                'success' => false,
                'message' => 'A request with this Idempotency-Key is currently being processed.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $response = $next($request);

            // 3. Only cache successful 2xx state-changing responses
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
