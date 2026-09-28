<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Services\LeadFollowupService;
use App\Domains\CRM\Services\LeadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class LeadFollowupApiController extends Controller
{
    public function __construct(
        private readonly LeadFollowupService $followupService,
        private readonly LeadService $leadService
    ) {}

    /**
     * GET /api/crm/leads/{lead}/followups
     * List all followups and interaction logs for a lead.
     */
    public function index(Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        $followups = $lead->followups()
            ->with(['taggedUser'])
            ->get()
            ->map(function (LeadFollowup $followup) {
                return [
                    'id'                     => $followup->id,
                    'lead_id'                => $followup->lead_id,
                    'type'                   => $followup->type ?? 'Call',
                    'title'                  => $followup->title,
                    'status'                 => $followup->status ?? 'Pending',
                    'followup_date'          => $followup->followup_date ? $followup->followup_date->toIso8601String() : null,
                    'followup_date_formatted'=> $followup->followup_date ? $followup->followup_date->format('Y-m-d H:i') : null,
                    'duration_minutes'       => $followup->duration_minutes,
                    'notes'                  => $followup->notes,
                    'is_google_meet'         => (bool)$followup->is_google_meet,
                    'google_meet_link'       => $followup->google_meet_link,
                    'guest_emails'           => $followup->guest_emails,
                    'tagged_users'           => $followup->tagged_users->map(fn($u) => [
                        'id'    => $u->id,
                        'name'  => $u->name,
                        'email' => $u->email,
                    ]),
                    'created_at'             => $followup->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'count'   => $followups->count(),
            'data'    => $followups,
        ]);
    }

    /**
     * POST /api/crm/leads/{lead}/followups
     * Store an interaction log or schedule a future followup.
     */
    public function store(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'action_mode'          => 'nullable|string|in:log_note,schedule',
            'type'                 => 'nullable|string|max:50',
            'status'               => 'nullable|string|max:50',
            'notes'                => 'nullable|string',
            'followup_date'        => 'nullable|date',
            'title'                => 'nullable|string|max:255',
            'duration_minutes'     => 'nullable|integer|min:1',
            'guest_emails'         => 'nullable|string|max:500',
            'tagged_user_ids'      => 'nullable|array',
            'tagged_user_ids.*'    => 'integer|exists:users,id',
            'sync_google_calendar' => 'nullable|boolean',
            'create_meet_link'     => 'nullable|boolean',
            'next_activity_type'   => 'nullable|string|max:50',
            'next_followup_date'   => 'nullable|date',
            'next_title'           => 'nullable|string|max:255',
            'lead_status'          => 'nullable|string|max:50',
            'priority'             => 'nullable|string|max:50',
            'segment'              => 'nullable|string|max:50',
        ]);

        $actionMode = $validated['action_mode'] ?? 'log_note';

        if (!empty($validated['lead_status'])) {
            $lead->status = $validated['lead_status'];
        }
        if (!empty($validated['priority'])) {
            $lead->priority = $validated['priority'];
        }
        if (!empty($validated['segment'])) {
            $lead->segment = $validated['segment'];
        }
        if ($lead->isDirty()) {
            $lead->save();
        }

        if ($actionMode === 'schedule') {
            $scheduleType = $validated['type'] ?? 'Call';
            $dueDate      = $validated['followup_date'] ?? date('Y-m-d H:i:s');
            $notes        = $validated['notes'] ?? '';

            $createdFollowup = $this->followupService->storeFollowup($lead, [
                'type'                 => $scheduleType,
                'title'                => $validated['title'] ?? ("Followup with " . ($lead->company_name ?: $lead->contact_person)),
                'duration_minutes'     => $validated['duration_minutes'] ?? 30,
                'guest_emails'         => $validated['guest_emails'] ?? null,
                'status'               => 'Pending',
                'followup_date'        => $dueDate,
                'notes'                => $notes,
                'tagged_user_ids'      => $validated['tagged_user_ids'] ?? null,
                'sync_google_calendar' => !empty($validated['sync_google_calendar']),
                'create_meet_link'     => !empty($validated['create_meet_link']),
            ]);

            try {
                $lead->next_followup_date = Carbon::parse($dueDate);
                $lead->save();
            } catch (\Throwable $e) {}

            return response()->json([
                'success' => true,
                'message' => 'Followup scheduled successfully.',
                'data'    => [
                    'id'               => $createdFollowup->id,
                    'type'             => $createdFollowup->type,
                    'title'            => $createdFollowup->title,
                    'status'           => $createdFollowup->status,
                    'followup_date'    => $createdFollowup->followup_date?->toIso8601String(),
                    'google_meet_link' => $createdFollowup->google_meet_link,
                ],
            ], 201);
        } else {
            // Log past interaction
            $pastType   = $validated['type'] ?? 'Call';
            $pastStatus = $validated['status'] ?? 'Connected';
            $pastNotes  = trim($validated['notes'] ?? '');

            $pastLog = $this->followupService->storeFollowup($lead, [
                'type'            => $pastType,
                'status'          => $pastStatus,
                'followup_date'   => $validated['followup_date'] ?? date('Y-m-d H:i:s'),
                'notes'           => $pastNotes,
                'tagged_user_ids' => null,
            ]);

            $scheduledNext = null;
            if (!empty($validated['next_followup_date'])) {
                $nextType      = $validated['next_activity_type'] ?? 'Call';
                $contextSuffix = "Scheduled " . $nextType . " after " . strtolower($pastType) . " interaction";
                $nextNotes     = !empty($pastNotes) ? ($pastNotes . " | " . $contextSuffix) : $contextSuffix;

                $scheduledNext = $this->followupService->storeFollowup($lead, [
                    'type'                 => $nextType,
                    'title'                => $validated['next_title'] ?? ("Next " . $nextType . " with " . ($lead->company_name ?: $lead->contact_person)),
                    'duration_minutes'     => $validated['duration_minutes'] ?? 30,
                    'guest_emails'         => $validated['guest_emails'] ?? null,
                    'status'               => 'Pending',
                    'followup_date'        => $validated['next_followup_date'],
                    'notes'                => $nextNotes,
                    'tagged_user_ids'      => $validated['tagged_user_ids'] ?? null,
                    'sync_google_calendar' => !empty($validated['sync_google_calendar']),
                    'create_meet_link'     => !empty($validated['create_meet_link']),
                ]);

                try {
                    $lead->next_followup_date = Carbon::parse($validated['next_followup_date']);
                    $lead->save();
                } catch (\Throwable $e) {}
            }

            return response()->json([
                'success' => true,
                'message' => 'Interaction logged successfully.',
                'data'    => [
                    'log'            => [
                        'id'            => $pastLog->id,
                        'type'          => $pastLog->type,
                        'status'        => $pastLog->status,
                        'followup_date' => $pastLog->followup_date?->toIso8601String(),
                        'notes'         => $pastLog->notes,
                    ],
                    'scheduled_next' => $scheduledNext ? [
                        'id'               => $scheduledNext->id,
                        'type'             => $scheduledNext->type,
                        'title'            => $scheduledNext->title,
                        'followup_date'    => $scheduledNext->followup_date?->toIso8601String(),
                        'google_meet_link' => $scheduledNext->google_meet_link,
                    ] : null,
                ],
            ], 201);
        }
    }

    /**
     * PATCH /api/crm/followups/{followup}/status
     * Mark followup completed / cancelled / rescheduled.
     */
    public function updateStatus(Request $request, LeadFollowup $followup): JsonResponse
    {
        if ($followup->lead) {
            $this->authorize('update', $followup->lead);
        }

        $validated = $request->validate([
            'status'  => 'required|string|in:Completed,Cancelled,Rescheduled,Pending',
            'notes'   => 'nullable|string',
            'outcome' => 'nullable|string',
        ]);

        $statusNotes = trim($validated['notes'] ?? '');
        if (!empty($validated['outcome'])) {
            $statusNotes = "Outcome: " . $validated['outcome'] . ($statusNotes ? " — " . $statusNotes : "");
        }

        if ($statusNotes !== '') {
            $existingNotes = $followup->notes ?? '';
            $followup->notes = $existingNotes ? ($existingNotes . "\n\n[" . now()->format('Y-m-d H:i') . " Status Update: {$validated['status']}] " . $statusNotes) : $statusNotes;
        }

        $followup->status = $validated['status'];
        $followup->save();

        return response()->json([
            'success' => true,
            'message' => "Followup marked as {$validated['status']}.",
            'data'    => [
                'id'     => $followup->id,
                'status' => $followup->status,
                'notes'  => $followup->notes,
            ],
        ]);
    }

    /**
     * DELETE /api/crm/followups/{followup}
     * Delete a followup record.
     */
    public function destroy(LeadFollowup $followup): JsonResponse
    {
        if ($followup->lead) {
            $this->authorize('update', $followup->lead);
        }

        $followup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Followup removed successfully.',
        ]);
    }
}
