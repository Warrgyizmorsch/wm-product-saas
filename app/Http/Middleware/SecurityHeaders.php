<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers on every response.
 *
 * The CSP is sent as Content-Security-Policy-Report-Only for now: pages still
 * carry large inline <script> blocks, so an enforced policy would break them.
 * Once those move into Vite bundles, switch the header name to enforce it.
 */
class SecurityHeaders
{
    /** Public pages that customers embed on their own sites in an iframe. */
    private const EMBEDDABLE = ['forms/*', 'embed/*', 'api/crm/public/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);

        if (! $request->is(...self::EMBEDDABLE)) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        }

        if (! $headers->has('Content-Security-Policy') && ! $headers->has('Content-Security-Policy-Report-Only')) {
            $headers->set('Content-Security-Policy-Report-Only', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:",
                "style-src 'self' 'unsafe-inline' https:",
                "img-src 'self' data: blob: https:",
                "font-src 'self' data: https:",
                "connect-src 'self' https: wss:",
                "frame-src 'self' https:",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self' https:",
            ]));
        }

        return $response;
    }
}
