<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Models\LeadHistory;
use App\Domains\CRM\Services\GoogleCalendarIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class GoogleCalendarController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarIntegrationService $calendarService
    ) {}

    /**
     * Schedule Google Meeting or Call Event
     */
    public function scheduleEvent(Request $request)
    {
        $validated = $request->validate([
            'summary' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'start_time' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'create_meet_link' => 'nullable|boolean',
            'lead_id' => 'nullable|exists:leads,id',
            'deal_id' => 'nullable|exists:crm_deals,id',
            'attendees' => 'nullable|array',
            'attendees.*' => 'nullable|email',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        // Construct Start & End Datetimes
        $dateStr = $validated['start_date'];
        $timeStr = $validated['start_time'] ?? '10:00';
        $startTime = Carbon::parse("{$dateStr} {$timeStr}");
        $duration = (int) ($validated['duration_minutes'] ?? 30);
        $endTime = (clone $startTime)->addMinutes($duration);

        $createMeetLink = filter_var($request->input('create_meet_link', false), FILTER_VALIDATE_BOOLEAN);

        // Compile Attendees list
        $attendees = $validated['attendees'] ?? [];
        if (!empty($validated['lead_id'])) {
            $lead = Lead::find($validated['lead_id']);
            if ($lead) {
                if ($lead->email && !in_array($lead->email, $attendees)) {
                    $attendees[] = $lead->email;
                }
                if ($lead->company_email && !in_array($lead->company_email, $attendees)) {
                    $attendees[] = $lead->company_email;
                }
            }
        }
        if (!empty($validated['deal_id'])) {
            $deal = CrmDeal::find($validated['deal_id']);
            if ($deal && $deal->contact && $deal->contact->email && !in_array($deal->contact->email, $attendees)) {
                $attendees[] = $deal->contact->email;
            }
        }

        // Call Google Calendar Integration Service
        $result = $this->calendarService->createEvent([
            'summary' => $validated['summary'],
            'description' => $validated['description'] ?? '',
            'start_time' => $startTime->toIso8601String(),
            'end_time' => $endTime->toIso8601String(),
            'attendees' => $attendees,
            'create_meet_link' => $createMeetLink,
            'lead_id' => $validated['lead_id'] ?? null,
            'deal_id' => $validated['deal_id'] ?? null,
        ]);

        // Save Followup Activity Record
        $followupType = $createMeetLink ? 'Meeting' : 'Call';
        $followup = new LeadFollowup();
        $followup->tenant_id = $tenantId;
        $followup->lead_id = $validated['lead_id'] ?? null;
        if (\Schema::hasColumn('lead_followups', 'crm_deal_id')) {
            $followup->crm_deal_id = $validated['deal_id'] ?? null;
        }
        $followup->followup_date = $startTime;
        $followup->type = $followupType;
        $followup->status = 'Pending';
        $followup->notes = ($validated['description'] ?? '') . ($result['meet_link'] ? "\nGoogle Meet: " . $result['meet_link'] : '');
        if (\Schema::hasColumn('lead_followups', 'google_event_id')) {
            $followup->google_event_id = $result['google_event_id'] ?? null;
        }
        if (\Schema::hasColumn('lead_followups', 'google_meet_link')) {
            $followup->google_meet_link = $result['meet_link'] ?? null;
        }
        if (\Schema::hasColumn('lead_followups', 'is_google_meet')) {
            $followup->is_google_meet = $createMeetLink;
        }
        $followup->save();

        // Log History Event if linked to Lead
        if (!empty($validated['lead_id']) && ($lead = Lead::find($validated['lead_id']))) {
            $eventDesc = $createMeetLink 
                ? "Scheduled Google Meet Video Call on " . $startTime->format('d/m/Y h:i A') . " (Link: {$result['meet_link']})"
                : "Scheduled Call Reminder on " . $startTime->format('d/m/Y h:i A') . " (Google Calendar Event Synced)";

            LeadHistory::logEvent(
                $lead,
                'activity_scheduled',
                null,
                $followupType,
                $eventDesc
            );
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $createMeetLink ? 'Google Meet Video Call scheduled & link generated!' : 'Google Calendar Call Reminder scheduled successfully!',
                'meet_link' => $result['meet_link'] ?? null,
                'followup' => $followup
            ]);
        }

        return redirect()->back()->with('success', $createMeetLink 
            ? 'Google Meet Video Call scheduled & link generated successfully!' 
            : 'Google Calendar Call Reminder scheduled successfully!');
    }

    /**
     * Fetch Google Calendar events for AJAX Calendar View
     */
    public function fetchEvents(Request $request): JsonResponse
    {
        $events = $this->calendarService->getUpcomingEvents(auth()->id(), 50);
        return response()->json(['success' => true, 'events' => $events]);
    }

    /**
     * Redirect to Google OAuth authorization flow
     */
    public function connectGoogleAccount()
    {
        $authUrl = $this->calendarService->getAuthUrl('/crm/activities');
        return redirect()->away($authUrl);
    }
}
