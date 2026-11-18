<?php

namespace App\Http\Middleware;

use App\Models\TwilioConfiguration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates X-Twilio-Signature on Twilio webhooks against the auth token of
 * the TwilioConfiguration that owns the request's AccountSid, and hands that
 * configuration to the controller as the `twilioConfig` request attribute so
 * one tenant's webhook never uses another tenant's settings.
 *
 * Signature = base64(HMAC-SHA1(auth_token, url . sorted POST key/value pairs)).
 */
class VerifyTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = (string) $request->header('X-Twilio-Signature', '');
        $accountSid = (string) $request->input('AccountSid', '');

        if ($signature === '' || $accountSid === '') {
            return $this->reject('missing signature or AccountSid');
        }

        $config = TwilioConfiguration::withoutGlobalScopes()
            ->where('is_active', true)
            ->where('account_sid', $accountSid)
            ->first();

        $authToken = trim((string) ($config?->auth_token ?? ''));
        if ($authToken === '') {
            return $this->reject('unknown account');
        }

        $params = $request->isMethod('post') ? $request->request->all() : [];
        ksort($params);
        $data = '';
        foreach ($params as $key => $value) {
            $data .= $key.(is_array($value) ? implode('', $value) : $value);
        }

        foreach ($this->candidateUrls($request) as $url) {
            $expected = base64_encode(hash_hmac('sha1', $url.$data, $authToken, true));
            if (hash_equals($expected, $signature)) {
                $request->attributes->set('twilioConfig', $config);

                return $next($request);
            }
        }

        return $this->reject('signature mismatch');
    }

    /**
     * The URL Twilio signed. Behind a TLS-terminating proxy Laravel may see
     * http:// while Twilio called https://, so both schemes are tried.
     *
     * @return array<int, string>
     */
    private function candidateUrls(Request $request): array
    {
        $url = $request->fullUrl();

        return array_values(array_unique([
            $url,
            preg_replace('#^http://#', 'https://', $url),
        ]));
    }

    private function reject(string $reason): Response
    {
        Log::warning('[Twilio Webhook] Rejected: '.$reason);

        return response('<Response/>', 403, ['Content-Type' => 'text/xml']);
    }
}
