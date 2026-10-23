<?php

namespace App\Services\Firebase;

use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * Cache key for Google OAuth2 access token.
     */
    private const ACCESS_TOKEN_CACHE_KEY = 'firebase_fcm_http_v1_access_token';

    /**
     * Check if Firebase credentials are configured and valid.
     */
    public static function isConfigured(): bool
    {
        $credentials = self::getCredentials();
        return !empty($credentials['client_email']) && !empty($credentials['private_key']) && !empty($credentials['project_id']);
    }

    /**
     * Resolve and parse the Firebase service account JSON credentials.
     */
    public static function getCredentials(): array
    {
        $configuredPath = config('firebase.credentials_path');

        $pathsToTry = array_filter([
            $configuredPath,
            !empty($configuredPath) && !str_starts_with($configuredPath, '/') && !preg_match('/^[A-Za-z]:\\\\/', $configuredPath)
                ? base_path($configuredPath)
                : null,
            storage_path('app/firebase/firebase_credentials.json'),
            storage_path('app/firebase/firebase_credential.json'),
            base_path('firebase_credentials.json'),
            base_path('firebase_credential.json'),
        ]);

        foreach ($pathsToTry as $path) {
            if ($path && file_exists($path) && is_readable($path)) {
                try {
                    $json = json_decode(file_get_contents($path), true);
                    if (is_array($json) && !empty($json['client_email']) && !empty($json['private_key'])) {
                        $projectId = config('firebase.project_id') ?: ($json['project_id'] ?? null);
                        return array_merge($json, [
                            'project_id' => $projectId,
                            'resolved_path' => $path,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("FcmService: Error reading Firebase credentials at {$path}: " . $e->getMessage());
                }
            }
        }

        return [];
    }

    /**
     * Get OAuth2 Bearer Access Token for FCM HTTP v1 API.
     * Caches token for 55 minutes to minimize auth overhead.
     */
    public static function getAccessToken(): ?string
    {
        return Cache::remember(self::ACCESS_TOKEN_CACHE_KEY, 3300, function () {
            $credentials = self::getCredentials();
            if (empty($credentials['client_email']) || empty($credentials['private_key'])) {
                Log::info("FcmService: Cannot generate FCM access token. Credentials missing or incomplete.");
                return null;
            }

            try {
                $now = time();
                $header = ['alg' => 'RS256', 'typ' => 'JWT'];
                $payload = [
                    'iss'   => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud'   => 'https://oauth2.googleapis.com/token',
                    'iat'   => $now,
                    'exp'   => $now + 3600,
                ];

                $base64Header = self::base64UrlEncode(json_encode($header));
                $base64Payload = self::base64UrlEncode(json_encode($payload));
                $signatureData = $base64Header . '.' . $base64Payload;

                $privateKey = $credentials['private_key'];
                $binarySignature = '';

                $success = openssl_sign($signatureData, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256);
                if (!$success) {
                    Log::error("FcmService: OpenSSL failed to sign Firebase JWT assertion.");
                    return null;
                }

                $base64Signature = self::base64UrlEncode($binarySignature);
                $jwtAssertion = $signatureData . '.' . $base64Signature;

                $tokenResponse = Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwtAssertion,
                ]);

                if ($tokenResponse->successful()) {
                    return $tokenResponse->json('access_token');
                }

                Log::error("FcmService: Google OAuth2 token request failed: " . $tokenResponse->body());
                return null;
            } catch (\Throwable $e) {
                Log::error("FcmService: Exception while generating FCM access token: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Send push notification to a single FCM device token.
     *
     * @param string $token Device FCM registration token
     * @param string $title Notification title
     * @param string $body Notification body text
     * @param array $data Extra custom key-value payload (all values will be stringified)
     * @param array $options Additional options (sound, channel_id, action_url, icon)
     * @return array ['success' => bool, 'message_id' => ?string, 'error' => ?string, 'unregistered' => bool]
     */
    public static function sendToToken(
        string $token,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        $token = trim($token);
        if (empty($token)) {
            return ['success' => false, 'error' => 'Empty device token', 'unregistered' => false];
        }

        $credentials = self::getCredentials();
        $projectId = $credentials['project_id'] ?? null;
        if (!$projectId) {
            Log::info("FcmService: Notification skipped. Firebase project_id not found.");
            return ['success' => false, 'error' => 'Firebase not configured', 'unregistered' => false];
        }

        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'Unable to acquire FCM access token', 'unregistered' => false];
        }

        $sound = $options['sound'] ?? config('firebase.defaults.sound', 'default');
        $channelId = $options['channel_id'] ?? config('firebase.defaults.channel_id', 'default_channel');
        $icon = $options['icon'] ?? config('firebase.defaults.icon', '/assets/images/brand/logo-icon.png');
        $actionUrl = $options['action_url'] ?? ($data['action_url'] ?? null);

        // Sanitize data payload: FCM v1 requires all values in data to be strings
        $sanitizedData = self::sanitizeDataPayload(array_merge($data, [
            'title' => $title,
            'body' => $body,
            'action_url' => (string) ($actionUrl ?? ''),
        ]));

        $messagePayload = [
            'token' => $token,
            'notification' => [
                'title' => (string) $title,
                'body' => (string) $body,
            ],
            'data' => $sanitizedData,
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'sound' => $sound,
                    'channel_id' => $channelId,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'default_sound' => true,
                    'default_vibrate_timings' => true,
                ],
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => $sound,
                        'badge' => 1,
                        'content-available' => 1,
                    ],
                ],
            ],
            'webpush' => [
                'notification' => [
                    'title' => (string) $title,
                    'body' => (string) $body,
                    'icon' => $icon,
                    'badge' => $icon,
                ],
                'fcm_options' => [
                    'link' => (string) ($actionUrl ?: '/'),
                ],
            ],
        ];

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        try {
            $response = Http::withToken($accessToken)
                ->timeout(6)
                ->post($url, ['message' => $messagePayload]);

            if ($response->successful()) {
                $resData = $response->json();
                return [
                    'success' => true,
                    'message_id' => $resData['name'] ?? 'sent',
                    'error' => null,
                    'unregistered' => false,
                ];
            }

            $statusCode = $response->status();
            $responseBody = $response->json() ?? [];
            $errorMessage = $responseBody['error']['message'] ?? $response->body();
            $status = $responseBody['error']['status'] ?? '';

            $isUnregistered = in_array($status, ['NOT_FOUND', 'UNREGISTERED']) ||
                str_contains(strtolower($errorMessage), 'not registered') ||
                str_contains(strtolower($errorMessage), 'unregistered') ||
                str_contains(strtolower($errorMessage), 'invalid registration token');

            if ($isUnregistered) {
                self::deactivateToken($token);
            }

            Log::warning("FcmService: FCM send failed (Status {$statusCode}): {$errorMessage}");

            return [
                'success' => false,
                'message_id' => null,
                'error' => $errorMessage,
                'unregistered' => $isUnregistered,
            ];
        } catch (\Throwable $e) {
            Log::warning("FcmService: Exception while sending FCM message: " . $e->getMessage());
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'unregistered' => false,
            ];
        }
    }

    /**
     * Send push notification to multiple tokens.
     */
    public static function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        $results = [
            'total' => count($tokens),
            'success_count' => 0,
            'failure_count' => 0,
            'responses' => [],
        ];

        $uniqueTokens = array_unique(array_filter($tokens));

        foreach ($uniqueTokens as $token) {
            $res = self::sendToToken($token, $title, $body, $data, $options);
            $results['responses'][$token] = $res;
            if ($res['success']) {
                $results['success_count']++;
            } else {
                $results['failure_count']++;
            }
        }

        return $results;
    }

    /**
     * Send push notification to a specific User instance or User ID.
     */
    public static function sendToUser(
        User|int $user,
        string $title,
        string $body,
        ?string $actionUrl = null,
        array $extraData = [],
        array $options = []
    ): array {
        $userModel = $user instanceof User ? $user : User::find($user);
        if (!$userModel) {
            return ['success' => false, 'error' => 'User not found'];
        }

        $tokens = $userModel->getActiveFcmTokens();
        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No active FCM tokens found for user'];
        }

        $mergedOptions = array_merge($options, [
            'action_url' => $actionUrl,
        ]);

        return self::sendToTokens($tokens, $title, $body, $extraData, $mergedOptions);
    }

    /**
     * Send push notification to multiple user IDs.
     *
     * @param int[] $userIds
     */
    public static function sendToUsers(
        array $userIds,
        string $title,
        string $body,
        ?string $actionUrl = null,
        array $extraData = [],
        array $options = []
    ): array {
        $allTokens = [];
        $uniqueUserIds = array_unique(array_filter($userIds));

        $users = User::whereIn('id', $uniqueUserIds)->get();
        foreach ($users as $user) {
            $tokens = $user->getActiveFcmTokens();
            foreach ($tokens as $t) {
                $allTokens[] = $t;
            }
        }

        return self::sendToTokens(array_unique($allTokens), $title, $body, $extraData, array_merge($options, [
            'action_url' => $actionUrl,
        ]));
    }

    /**
     * Send push notification to an FCM Topic (e.g. 'all_employees', 'tenant_1').
     */
    public static function sendToTopic(
        string $topic,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        $topic = trim($topic);
        if (empty($topic)) {
            return ['success' => false, 'error' => 'Empty topic name'];
        }

        $credentials = self::getCredentials();
        $projectId = $credentials['project_id'] ?? null;
        if (!$projectId) {
            return ['success' => false, 'error' => 'Firebase not configured'];
        }

        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'Unable to acquire FCM access token'];
        }

        $sound = $options['sound'] ?? config('firebase.defaults.sound', 'default');
        $channelId = $options['channel_id'] ?? config('firebase.defaults.channel_id', 'default_channel');
        $icon = $options['icon'] ?? config('firebase.defaults.icon', '/assets/images/brand/logo-icon.png');
        $actionUrl = $options['action_url'] ?? ($data['action_url'] ?? null);

        $sanitizedData = self::sanitizeDataPayload(array_merge($data, [
            'title' => $title,
            'body' => $body,
            'action_url' => (string) ($actionUrl ?? ''),
        ]));

        $messagePayload = [
            'topic' => $topic,
            'notification' => [
                'title' => (string) $title,
                'body' => (string) $body,
            ],
            'data' => $sanitizedData,
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'sound' => $sound,
                    'channel_id' => $channelId,
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => $sound,
                        'badge' => 1,
                    ],
                ],
            ],
        ];

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        try {
            $response = Http::withToken($accessToken)
                ->timeout(6)
                ->post($url, ['message' => $messagePayload]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json('name'),
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'message_id' => null,
                'error' => $response->json('error.message') ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mark an invalid or expired token as inactive in database.
     */
    public static function deactivateToken(string $token): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('user_device_tokens')) {
                $hash = hash('sha256', trim($token));
                UserDeviceToken::where('token_hash', $hash)
                    ->orWhere('fcm_token', $token)
                    ->update(['is_active' => false]);
            }
        } catch (\Throwable $e) {
            Log::warning("FcmService: Error deactivating stale token: " . $e->getMessage());
        }
    }

    /**
     * Helper to stringify all data values for FCM v1 compatibility.
     */
    private static function sanitizeDataPayload(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $sanitized[(string) $key] = '';
            } elseif (is_bool($value)) {
                $sanitized[(string) $key] = $value ? '1' : '0';
            } elseif (is_array($value) || is_object($value)) {
                $sanitized[(string) $key] = json_encode($value);
            } else {
                $sanitized[(string) $key] = (string) $value;
            }
        }
        return $sanitized;
    }

    /**
     * Helper for Base64 URL Safe Encoding without padding.
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
