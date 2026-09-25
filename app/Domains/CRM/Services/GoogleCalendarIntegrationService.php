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
     * Get Google OAuth Login URL
     */
    public function getAuthUrl(?string $next = null): string
    {
        $redirectUrl = $next ?: url('/crm/activities');
        if (!str_starts_with($redirectUrl, 'http')) {
            $redirectUrl = url($redirectUrl);
        }
        $userId = auth()->id() ?? 1;
        return $this->baseUrl . '/auth/login?user_id=' . $userId . '&next=' . urlencode($redirectUrl);
    }

    /**
     * Schedule Meeting / Call Event via Google Workspace API
     */
    public function createEvent(array $params): array
    {
        $userId = auth()->id() ?? 1;

        $payload = [
            'user_id' => $userId,
            'summary' => $params['summary'] ?? 'CRM Scheduled Call',
            'description' => $params['description'] ?? '',
            'start_time' => Carbon::parse($params['start_time'])->toIso8601String(),
            'end_time' => Carbon::parse($params['end_time'] ?? Carbon::parse($params['start_time'])->addMinutes(30))->toIso8601String(),
            'attendees' => array_values(array_filter($params['attendees'] ?? [])),
            'timezone' => $params['timezone'] ?? 'Asia/Kolkata',
            'create_meet_link' => (bool) ($params['create_meet_link'] ?? false),
            'deal_id' => !empty($params['deal_id']) ? (int) $params['deal_id'] : null,
        ];

        try {
            $url = $this->baseUrl . '/workspace/calendar/create-event?user_id=' . $userId;
            $response = Http::timeout(10)->post($url, $payload);

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
    public function getUpcomingEvents(?int $userId = null, int $limit = 20): array
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl . '/workspace/calendar/events', [
                'user_id' => $userId,
                'limit' => $limit
            ]);

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
    public function isAccountConnected(?int $userId = null): bool
    {
        if (session('google_calendar_connected')) {
            return true;
        }

        $userId = $userId ?? (auth()->id() ?? 1);
        try {
            $response = Http::timeout(3)->get($this->baseUrl . '/workspace/calendar/events', [
                'user_id' => $userId,
                'limit' => 1
            ]);
            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['authenticated']) || !empty($data['connected']) || (isset($data['total']) && $data['total'] > 0)) {
                    session(['google_calendar_connected' => true]);
                    return true;
                }
            }
        } catch (\Throwable $e) {}

        return false;
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
