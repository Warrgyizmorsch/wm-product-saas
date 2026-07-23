<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetCurrency Middleware
 *
 * Seeds session('active_currency') from the authenticated tenant's saved currency
 * setting so that `active_currency()` and `format_currency()` automatically use
 * the currency that was chosen when the tenant was created -- without requiring the
 * user to click the header currency dropdown.
 *
 * If the tenant has no saved currency, the system falls back to the config base
 * currency (USD). The session is set once per session (not on every request) to
 * avoid unnecessary writes, and only when the session value is absent.
 */
class SetCurrency
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();
        $settings = $tenant?->settings ?? [];
        $code = strtoupper($settings['currency'] ?? '');

        // Validate the tenant's currency exists in our supported list.
        $supported = config('currency.currencies', []);
        if ($code && isset($supported[$code])) {
            // Always sync the session from tenant settings so that any change
            // made in the Tenant Edit screen takes effect immediately.
            $request->session()->put('active_currency', $code);
        }

        return $next($request);
    }
}
