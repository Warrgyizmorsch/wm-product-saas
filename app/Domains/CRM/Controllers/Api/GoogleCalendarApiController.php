<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Models\LeadHistory;
use App\Domains\CRM\Services\GoogleCalendarIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class GoogleCalendarApiController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarIntegrationService $calendarService
    ) {}

    /**
     * GET /api/crm/google-calendar/auth-url
     * Get Google OAuth authorization URL to connect Google Workspace account.
     */
    public function authUrl(Request $request): JsonResponse
    {
        $redirectUrl = $request->input('redirect_url') ?: url('/crm/activities');
        $authUrl = $this->calendarService->getAuthUrl($redirectUrl);

        return response()->json([
            'success'   => true,
            'auth_url'  => $authUrl,
            'message'   => 'Open this authorization URL to connect your Google Calendar & Google Meet account.',
        ]);
    }

    /**
     * GET /api/crm/google-calendar/events
     * Fetch upcoming synced events from Google Calendar.
     */
    public function events(Request $request): JsonResponse
    {
        $limit = min((int)$request->input('limit', 50), 100);
        $userId = $request->input('user_id') ? (int)$request->input('user_id') : auth()->id();
        $token = $request->input('token') ?: $request->input('access_token');
        $events = $this->calendarService->getUpcomingEvents($userId, $limit, $token);

        return response()->json([
            'success' => true,
            'count'   => count($events ?? []),
            'events'  => $events ?? [],
        ]);
    }

    /**
     * POST /api/crm/google-calendar/schedule-event
     * Schedule a Google Calendar Meeting / Call Event and generate instant Google Meet link.
     */
    public function scheduleEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'summary'              => 'required|string|max:255',
            'description'          => 'nullable|string',
            'start_date'           => 'required|date',
            'start_time'           => 'nullable|string',
            'duration_minutes'     => 'nullable|integer|min:15|max:480',
            'create_meet_link'     => 'nullable|boolean',
            'sync_google_calendar' => 'nullable|boolean',
            'lead_id'              => 'nullable|exists:leads,id',
            'deal_id'              => 'nullable|exists:crm_deals,id',
            'attendees'            => 'nullable|array',
            'attendees.*'          => 'nullable|email',
            'token'                => 'nullable|string',
            'access_token'         => 'nullable|string',
            'user_id'              => 'nullable',
        ]);

        $tenantId = tenant_id() ?? (auth()->user()?->tenant_id ?? 1);

        $dateStr = $validated['start_date'];
        $timeStr = $validated['start_time'] ?? '10:00';
        $startTime = Carbon::parse("{$dateStr} {$timeStr}");
        $duration = (int) ($validated['duration_minutes'] ?? 30);
        $endTime = (clone $startTime)->addMinutes($duration);

        $createMeetLink = filter_var($request->input('create_meet_link', false), FILTER_VALIDATE_BOOLEAN);

        // Compile Attendees
        $attendees = $validated['attendees'] ?? [];
        if (!empty($validated['lead_id'])) {
            $lead = Lead::find($validated['lead_id']);
            if ($lead) {
                $primaryEmail = !empty($lead->email) ? trim($lead->email) : (!empty($lead->company_email) ? trim($lead->company_email) : null);
                if ($primaryEmail && !in_array($primaryEmail, $attendees)) {
                    $attendees[] = $primaryEmail;
                }
            }
        }
        if (!empty($validated['deal_id'])) {
            $deal = CrmDeal::find($validated['deal_id']);
            if ($deal && $deal->contact && $deal->contact->email && !in_array($deal->contact->email, $attendees)) {
                $attendees[] = $deal->contact->email;
            }
        }

        // Call Google Calendar Service
        $result = $this->calendarService->createEvent([
            'summary'          => $validated['summary'],
            'description'      => $validated['description'] ?? '',
            'start_time'       => $startTime->toIso8601String(),
            'end_time'         => $endTime->toIso8601String(),
            'attendees'        => $attendees,
            'create_meet_link' => $createMeetLink,
            'lead_id'          => $validated['lead_id'] ?? null,
            'deal_id'          => $validated['deal_id'] ?? null,
            'token'            => $validated['token'] ?? $validated['access_token'] ?? $request->input('token'),
            'user_id'          => $validated['user_id'] ?? $request->input('user_id'),
        ]);

        $followupType = $createMeetLink ? 'Meeting' : 'Call';
        $followup = new LeadFollowup();
        $followup->tenant_id = $tenantId;
        $followup->lead_id = $validated['lead_id'] ?? null;
        if (\Schema::hasColumn('lead_followups', 'crm_deal_id')) {
            $followup->crm_deal_id = $validated['deal_id'] ?? null;
        }

        $isGoogleSuccess = !empty($result['success']);
        $realMeetLink = $isGoogleSuccess ? ($result['meet_link'] ?? null) : null;

        $followup->followup_date = $startTime;
        $followup->type = $followupType;
        $followup->title = $validated['summary'];
        $followup->duration_minutes = $duration;
        $followup->status = 'Pending';
        $followup->notes = ($validated['description'] ?? '') . ($realMeetLink ? "\nGoogle Meet: " . $realMeetLink : '');
        if (\Schema::hasColumn('lead_followups', 'google_event_id')) {
            $followup->google_event_id = $isGoogleSuccess ? ($result['google_event_id'] ?? null) : null;
        }
        if (\Schema::hasColumn('lead_followups', 'google_meet_link')) {
            $followup->google_meet_link = $realMeetLink;
        }
        if (\Schema::hasColumn('lead_followups', 'is_google_meet')) {
            $followup->is_google_meet = $createMeetLink && !empty($realMeetLink);
        }
        $followup->save();

        if (!empty($validated['lead_id']) && ($lead = Lead::find($validated['lead_id']))) {
            $lead->next_followup_date = $startTime;
            $lead->save();

            $eventDesc = ($createMeetLink && $realMeetLink)
                ? "Scheduled Google Meet Video Call on " . $startTime->format('d/m/Y h:i A') . " (Link: {$realMeetLink})"
                : "Scheduled {$followupType} on " . $startTime->format('d/m/Y h:i A');

            LeadHistory::logEvent(
                $lead,
                'activity_scheduled',
                null,
                $followupType,
                $eventDesc
            );
        }

        return response()->json([
            'success'          => true,
            'google_synced'    => $isGoogleSuccess,
            'message'          => $isGoogleSuccess
                ? ($createMeetLink ? 'Google Meet Video Call scheduled & link generated!' : 'Google Calendar Call Reminder scheduled successfully!')
                : 'Activity scheduled in CRM (Google Calendar account not connected or pending authorization).',
            'warning'          => !$isGoogleSuccess ? ($result['message'] ?? 'Google Account not connected') : null,
            'auth_url'         => !$isGoogleSuccess ? ($result['auth_url'] ?? null) : null,
            'google_meet_link' => $realMeetLink,
            'data'             => [
                'id'               => $followup->id,
                'lead_id'          => $followup->lead_id,
                'deal_id'          => $followup->crm_deal_id ?? null,
                'title'            => $followup->title,
                'type'             => $followup->type,
                'scheduled_at'     => $followup->followup_date?->toIso8601String(),
                'duration_minutes' => $followup->duration_minutes,
                'google_meet_link' => $realMeetLink,
                'status'           => $followup->status,
            ],
        ], 201);
    }
}
