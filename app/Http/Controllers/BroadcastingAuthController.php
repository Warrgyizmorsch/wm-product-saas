<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadcastingAuthController extends Controller
{
    /**
     * Authenticate private Pusher channel subscriptions for authenticated tenant users.
     */
    public function authenticate(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $socketId = (string)$request->input('socket_id', '');
        $channelName = (string)$request->input('channel_name', '');

        if ($socketId === '' || $channelName === '') {
            return response()->json(['message' => 'Missing socket_id or channel_name.'], 422);
        }

        $authorized = false;

        if (preg_match('/^private-user-(\d+)$/', $channelName, $matches)) {
            $authorized = ((int)$user->id === (int)$matches[1]);
        } elseif (preg_match('/^private-visitor-tenant-(\d+)$/', $channelName, $matches)) {
            $authorized = ((int)$user->tenant_id === (int)$matches[1]) || (bool)$user->is_super_admin;
        }

        if (!$authorized) {
            return response()->json(['message' => 'Forbidden channel access.'], 403);
        }

        $key = config('services.pusher.app_key') ?: env('PUSHER_APP_KEY', '');
        $secret = config('services.pusher.app_secret') ?: env('PUSHER_APP_SECRET', '');

        if (empty($key) || empty($secret)) {
            return response()->json(['message' => 'Pusher credentials not configured.'], 503);
        }

        $stringToSign = "{$socketId}:{$channelName}";
        $signature = hash_hmac('sha256', $stringToSign, $secret);

        return response()->json([
            'auth' => "{$key}:{$signature}",
        ]);
    }
}
