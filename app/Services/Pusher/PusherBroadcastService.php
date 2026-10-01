<?php

namespace App\Services\Pusher;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PusherBroadcastService
{
    /**
     * Broadcast an event to one or more channels via Pusher Cloud REST API.
     *
     * @param string|array $channels
     * @param string $eventName
     * @param array $data
     * @return bool
     */
    public static function broadcast(string|array $channels, string $eventName, array $data): bool
    {
        $appId = config('services.pusher.app_id') ?: env('PUSHER_APP_ID');
        $key = config('services.pusher.app_key') ?: env('PUSHER_APP_KEY');
        $secret = config('services.pusher.app_secret') ?: env('PUSHER_APP_SECRET');
        $cluster = config('services.pusher.cluster') ?: env('PUSHER_APP_CLUSTER', 'ap2');

        if (empty($appId) || empty($key) || empty($secret)) {
            // Silently skip if Pusher keys are not set in .env
            Log::info("PusherBroadcastService: Pusher credentials not configured. Event '{$eventName}' broadcast skipped.");
            return false;
        }

        $channelsArray = is_array($channels) ? array_values($channels) : [$channels];

        $payload = [
            'name'     => $eventName,
            'channels' => $channelsArray,
            'data'     => json_encode($data),
        ];

        $body = json_encode($payload);
        $bodyMd5 = md5($body);
        $timestamp = time();
        $authVersion = '1.0';
        $path = "/apps/{$appId}/events";

        $queryString = "auth_key={$key}&auth_timestamp={$timestamp}&auth_version={$authVersion}&body_md5={$bodyMd5}";
        $stringToSign = "POST\n{$path}\n{$queryString}";
        $authSignature = hash_hmac('sha256', $stringToSign, $secret);

        $url = "https://api-{$cluster}.pusher.com{$path}?{$queryString}&auth_signature={$authSignature}";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(3)->withBody($body, 'application/json')->post($url);

            if ($response->successful()) {
                return true;
            }

            Log::warning("Pusher broadcast failed with status {$response->status()}: " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::warning("Pusher broadcast exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Pusher client configuration for frontend scripts.
     */
    public static function getClientConfig(): array
    {
        return [
            'key'     => config('services.pusher.app_key') ?: env('PUSHER_APP_KEY', ''),
            'cluster' => config('services.pusher.cluster') ?: env('PUSHER_APP_CLUSTER', 'ap2'),
            'enabled' => !empty(config('services.pusher.app_key') ?: env('PUSHER_APP_KEY')),
        ];
    }
}
