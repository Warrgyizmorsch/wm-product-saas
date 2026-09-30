<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\LeadFollowup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GoogleCalendarIntegrationService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.google_workspace.base_url', 'https://love14-deal-health-scoring.hf.space');
    }

    /**
     * Exchange a one-time auth_code for a persistent bearer/session token
     */
    public function exchangeAuthCode(string $authCode): ?string
    {
        try {
            $response = Http::timeout(10)->post($this->baseUrl . '/auth/token', [
                'auth_code' => trim($authCode),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['token'] ?? $data['access_token'] ?? $data['session_token'] ?? (is_string($data) ? $data : null);
                if ($token) {
                    session(['google_token' => $token]);
                    session(['google_calendar_connected' => true]);
                    return $token;
                }
            } else {
                Log::warning('Google Auth Code Exchange failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::error('Google Auth Code Exchange exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Resolve user_id and token from params, request, or session
     */
    public function resolveCredentials(?int $userId = null, ?string $token = null): array
    {
        $resolvedUserId = $userId 
            ?? (request()?->filled('google_user_id') ? (int) request('google_user_id') : null)
            ?? (request()?->filled('user_id') ? (int) request('user_id') : null)
            ?? (session('google_user_id') ? (int) session('google_user_id') : null)
            ?? auth()->id() 
            ?? 1;

        $resolvedToken = $token 
            ?? request()?->input('google_token') 
            ?? request()?->input('token') 
            ?? request()?->input('access_token') 
            ?? session('google_token') 
            ?? session('google_access_token') 
            ?? null;

        // If no token yet but auth_code is available, exchange it now
        if (empty($resolvedToken)) {
            $authCode = request()?->input('auth_code') ?? request()?->input('code') ?? session('google_auth_code');
            if (!empty($authCode)) {
                $resolvedToken = $this->exchangeAuthCode($authCode);
            }
        }

        return [$resolvedUserId, $resolvedToken];
    }

    /**
     * Get Google OAuth Login URL
     */
    public function getAuthUrl(?string $next = null): string
    {
        $redirectUrl = $next ?: url('/crm/activities');
        if (!str_starts_with($redirectUrl, 'http')) {
            $redirectUrl = url($redirectUrl);
        }
        [$userId] = $this->resolveCredentials();
        return $this->baseUrl . '/auth/login?user_id=' . $userId . '&next=' . urlencode($redirectUrl);
    }

    /**
     * Schedule Meeting / Call Event via Google Workspace API
     */
    public function createEvent(array $params): array
    {
        [$userId, $token] = $this->resolveCredentials(
            !empty($params['user_id']) ? (int)$params['user_id'] : null,
            $params['token'] ?? $params['access_token'] ?? null
        );

        $payload = [
            'user_id'          => $userId,
            'token'            => $token,
            'access_token'     => $token,
            'summary'          => $params['summary'] ?? 'CRM Scheduled Call',
            'description'      => $params['description'] ?? '',
            'start_time'       => Carbon::parse($params['start_time'])->toIso8601String(),
            'end_time'         => Carbon::parse($params['end_time'] ?? Carbon::parse($params['start_time'])->addMinutes(30))->toIso8601String(),
            'attendees'        => array_values(array_filter($params['attendees'] ?? [])),
            'timezone'         => $params['timezone'] ?? 'Asia/Kolkata',
            'create_meet_link' => (bool) ($params['create_meet_link'] ?? false),
            'deal_id'          => !empty($params['deal_id']) ? (int) $params['deal_id'] : null,
        ];

        try {
            $queryParams = ['user_id' => $userId];
            if (!empty($token)) {
                $queryParams['token'] = $token;
                $queryParams['access_token'] = $token;
            }

            $url = $this->baseUrl . '/workspace/calendar/create-event?' . http_build_query($queryParams);

            $httpClient = Http::timeout(10);
            if (!empty($token)) {
                $httpClient = $httpClient->withToken($token);
            }

            $response = $httpClient->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $eventData = $data['event'] ?? $data;
                $realMeetLink = $data['meet_link'] ?? $eventData['meet_link'] ?? null;
                return [
                    'success' => true,
                    'google_event_id' => $eventData['event_id'] ?? $data['id'] ?? $data['event_id'] ?? null,
                    'meet_link' => $realMeetLink,
                    'message' => 'Event scheduled on Google Calendar successfully.',
                    'raw' => $data
                ];
            }

            if ($response->status() === 401) {
                Log::info('Google Calendar API notice: User Google Account not authorized yet. Please click Connect Google Account.');
                return [
                    'success' => false,
                    'google_event_id' => null,
                    'meet_link' => null,
                    'error_type' => 'auth_required',
                    'message' => 'Your Google Account is not connected yet. Please connect your Google Calendar in CRM Activities to generate live Google Meet links.',
                    'auth_url' => $this->getAuthUrl(),
                    'raw' => null
                ];
            } else {
                $body = $response->json();
                $msg = $body['message'] ?? $body['detail'] ?? ('Google Calendar service error (' . $response->status() . ').');
                Log::warning('Google Calendar API returned error status', ['status' => $response->status(), 'body' => $response->body()]);
                return [
                    'success' => false,
                    'google_event_id' => null,
                    'meet_link' => null,
                    'error_type' => 'api_error',
                    'message' => $msg,
                    'raw' => null
                ];
            }
        } catch (\Throwable $e) {
            Log::error('Google Calendar API request failed: ' . $e->getMessage());
            return [
                'success' => false,
                'google_event_id' => null,
                'meet_link' => null,
                'error_type' => 'connection_failed',
                'message' => 'Could not connect to Google Calendar service. Please check your internet or connect your Google Workspace account.',
                'raw' => null
            ];
        }
    }

    /**
     * Get upcoming Google Calendar events
     */
    public function getUpcomingEvents(?int $userId = null, int $limit = 20, ?string $token = null): array
    {
        [$userId, $token] = $this->resolveCredentials($userId, $token);

        try {
            $queryParams = [
                'user_id' => $userId,
                'limit'   => $limit,
            ];
            if (!empty($token)) {
                $queryParams['token'] = $token;
                $queryParams['access_token'] = $token;
            }

            $httpClient = Http::timeout(5);
            if (!empty($token)) {
                $httpClient = $httpClient->withToken($token);
            }

            $response = $httpClient->get($this->baseUrl . '/workspace/calendar/events', $queryParams);

            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Throwable $e) {
            Log::info('Google Calendar fetch events offline: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Check if user's Google Account is connected and authorized
     */
    public function isAccountConnected(?int $userId = null, ?string $token = null): bool
    {
        [$userId, $token] = $this->resolveCredentials($userId, $token);

        if (empty($token)) {
            session(['google_calendar_connected' => false]);
            return false;
        }

        if (session('google_calendar_connected') === true) {
            return true;
        }

        try {
            $queryParams = [
                'user_id' => $userId,
                'limit'   => 1,
                'token'   => $token,
                'access_token' => $token,
            ];

            $httpClient = Http::timeout(3)->withToken($token);

            $response = $httpClient->get($this->baseUrl . '/workspace/calendar/events', $queryParams);
            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['authenticated']) || !empty($data['connected']) || (isset($data['total']) && $data['total'] > 0) || is_array($data)) {
                    session(['google_calendar_connected' => true]);
                    return true;
                }
            }
        } catch (\Throwable $e) {}

        return false;
    }

    /**
     * Disconnect / logout user from Google integration
     */
    public function disconnectAccount(): void
    {
        session()->forget([
            'google_token',
            'google_access_token',
            'google_auth_code',
            'google_user_id',
        ]);
        session()->put('google_calendar_connected', false);
        session()->save();

        try {
            Http::timeout(3)->get($this->baseUrl . '/auth/logout');
        } catch (\Throwable $e) {}
    }

    /**
     * Cancel Google Calendar event
     */
    public function cancelEvent(string $eventId): bool
    {
        try {
            $response = Http::timeout(5)->delete($this->baseUrl . '/workspace/calendar/events/' . $eventId);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Google Calendar cancel event failed: ' . $e->getMessage());
            return false;
        }
    }
}
