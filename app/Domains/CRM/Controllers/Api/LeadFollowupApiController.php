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
                    'id'                      => $followup->id,
                    'lead_id'                 => $followup->lead_id,
                    'crm_deal_id'             => $followup->crm_deal_id,
                    'type'                    => $followup->type ?? 'Call',
                    'title'                   => $followup->title,
                    'status'                  => $followup->status ?? 'Pending',
                    'followup_date'           => $followup->followup_date ? $followup->followup_date->toIso8601String() : null,
                    'followup_date_formatted' => $followup->followup_date ? $followup->followup_date->format('Y-m-d H:i') : null,
                    'duration_minutes'        => $followup->duration_minutes,
                    'notes'                   => $followup->notes,
                    'is_google_meet'          => (bool)$followup->is_google_meet,
                    'google_meet_link'        => $followup->google_meet_link,
                    'guest_emails'            => $followup->guest_emails,
                    'latitude'                => $followup->latitude ? (float)$followup->latitude : null,
                    'longitude'               => $followup->longitude ? (float)$followup->longitude : null,
                    'location_address'        => $followup->location_address,
                    'selfie_path'             => $followup->selfie_path,
                    'selfie_url'              => $followup->selfie_url,
                    'location_verified_at'    => $followup->location_verified_at?->toIso8601String(),
                    'tagged_users'            => $followup->tagged_users->map(fn($u) => [
                        'id'    => $u->id,
                        'name'  => $u->name,
                        'email' => $u->email,
                    ]),
                    'created_at'              => $followup->created_at?->toIso8601String(),
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

        $rules = [
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
            'latitude'             => 'nullable|numeric|between:-90,90',
            'longitude'            => 'nullable|numeric|between:-180,180',
            'location_address'     => 'nullable|string|max:500',
            'selfie'               => 'nullable|file|image|mimes:jpeg,png,jpg,webp|max:5120',
            'selfie_base64'        => 'nullable|string',
        ];

        $actionMode = $request->input('action_mode', 'log_note');
        $activityType = $request->input('type', 'Call');
        $activityStatus = $request->input('status', ($actionMode === 'schedule' ? 'Pending' : 'Connected'));

        if ($actionMode === 'log_note' && $this->followupService->isGeoVerificationRequired($activityStatus, $activityType)) {
            $rules['latitude'] = 'required|numeric|between:-90,90';
            $rules['longitude'] = 'required|numeric|between:-180,180';
            if (!$request->hasFile('selfie') && !$request->filled('selfie_base64')) {
                $rules['selfie_base64'] = 'required|string';
            }
        }

        $messages = [
            'latitude.required'      => 'Live GPS latitude is required to log this field meeting.',
            'longitude.required'     => 'Live GPS longitude is required to log this field meeting.',
            'selfie_base64.required' => 'A live front camera selfie is required to log this field meeting.',
            'selfie.required'        => 'A live front camera selfie is required to log this field meeting.',
        ];

        $validated = $request->validate($rules, $messages);

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

        $verificationData = $this->followupService->extractVerificationPayload($request);

        if ($actionMode === 'schedule') {
            $scheduleType = $validated['type'] ?? 'Call';
            $dueDate      = $validated['followup_date'] ?? date('Y-m-d H:i:s');
            $notes        = $validated['notes'] ?? '';

            $schedulePayload = array_merge([
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
            ], $verificationData);

            $createdFollowup = $this->followupService->storeFollowup($lead, $schedulePayload);

            try {
                $lead->next_followup_date = Carbon::parse($dueDate);
                $lead->save();
            } catch (\Throwable $e) {}

            return response()->json([
                'success' => true,
                'message' => 'Followup scheduled successfully.',
                'data'    => [
                    'id'                   => $createdFollowup->id,
                    'type'                 => $createdFollowup->type,
                    'title'                => $createdFollowup->title,
                    'status'               => $createdFollowup->status,
                    'followup_date'        => $createdFollowup->followup_date?->toIso8601String(),
                    'google_meet_link'     => $createdFollowup->google_meet_link,
                    'latitude'             => $createdFollowup->latitude ? (float)$createdFollowup->latitude : null,
                    'longitude'            => $createdFollowup->longitude ? (float)$createdFollowup->longitude : null,
                    'location_address'     => $createdFollowup->location_address,
                    'selfie_path'          => $createdFollowup->selfie_path,
                    'selfie_url'           => $createdFollowup->selfie_url,
                    'location_verified_at' => $createdFollowup->location_verified_at?->toIso8601String(),
                ],
            ], 201);
        } else {
            // Log past interaction
            $pastType   = $validated['type'] ?? 'Call';
            $pastStatus = $validated['status'] ?? 'Connected';
            $pastNotes  = trim($validated['notes'] ?? '');

            $pastLogPayload = array_merge([
                'type'            => $pastType,
                'status'          => $pastStatus,
                'followup_date'   => $validated['followup_date'] ?? date('Y-m-d H:i:s'),
                'notes'           => $pastNotes,
                'tagged_user_ids' => null,
            ], $verificationData);

            $pastLog = $this->followupService->storeFollowup($lead, $pastLogPayload);

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
                        'id'                   => $pastLog->id,
                        'type'                 => $pastLog->type,
                        'status'               => $pastLog->status,
                        'followup_date'        => $pastLog->followup_date?->toIso8601String(),
                        'notes'                => $pastLog->notes,
                        'latitude'             => $pastLog->latitude ? (float)$pastLog->latitude : null,
                        'longitude'            => $pastLog->longitude ? (float)$pastLog->longitude : null,
                        'location_address'     => $pastLog->location_address,
                        'selfie_path'          => $pastLog->selfie_path,
                        'selfie_url'           => $pastLog->selfie_url,
                        'location_verified_at' => $pastLog->location_verified_at?->toIso8601String(),
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
     * Mark followup completed / cancelled / rescheduled with optional GPS and Selfie.
     */
    public function updateStatus(Request $request, LeadFollowup $followup): JsonResponse
    {
        if ($followup->lead) {
            $this->authorize('update', $followup->lead);
        } elseif ($followup->deal) {
            $this->authorize('update', $followup->deal);
        }

        $validated = $request->validate([
            'status'           => 'required|string|in:Completed,Not Connected,Cancelled,Rescheduled,Pending',
            'notes'            => 'nullable|string',
            'outcome'          => 'nullable|string',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
            'location_address' => 'nullable|string|max:500',
            'selfie'           => 'nullable|file|image|mimes:jpeg,png,jpg,webp|max:5120',
            'selfie_base64'    => 'nullable|string',
        ]);

        $targetStatus = $validated['status'];
        if ($this->followupService->isGeoVerificationRequired($targetStatus, $followup->type, $followup)) {
            $hasSelfie = !empty($followup->selfie_path) || $request->hasFile('selfie') || $request->filled('selfie_base64');
            $hasLat = $request->filled('latitude') || $followup->latitude !== null;
            $hasLng = $request->filled('longitude') || $followup->longitude !== null;

            $errors = [];
            if (!$hasLat) {
                $errors['latitude'] = ['Live GPS latitude is required to complete this meeting.'];
            }
            if (!$hasLng) {
                $errors['longitude'] = ['Live GPS longitude is required to complete this meeting.'];
            }
            if (!$hasSelfie) {
                $errors['selfie'] = ['A live front camera selfie is required to complete this meeting.'];
            }

            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Geo-verification coordinates and selfie are required for field meetings.',
                    'errors'  => $errors,
                ], 422);
            }
        }

        $statusNotes = trim($validated['notes'] ?? '');
        if (!empty($validated['outcome'])) {
            $statusNotes = "Outcome: " . $validated['outcome'] . ($statusNotes ? " — " . $statusNotes : "");
        }

        $newNotes = $followup->notes ?? '';
        if ($statusNotes !== '') {
            $newNotes = $newNotes ? ($newNotes . "\n\n[" . now()->format('Y-m-d H:i') . " Status Update: {$validated['status']}] " . $statusNotes) : $statusNotes;
        }

        $verificationData = $this->followupService->extractVerificationPayload($request, $followup);

        $payload = array_merge([
            'status' => $validated['status'],
            'notes'  => $newNotes,
        ], $verificationData);

        $this->followupService->updateOrReschedule($followup, $payload, false);
        $followup->refresh();

        return response()->json([
            'success' => true,
            'message' => "Followup marked as {$followup->status}.",
            'data'    => [
                'id'                   => $followup->id,
                'lead_id'              => $followup->lead_id,
                'crm_deal_id'          => $followup->crm_deal_id,
                'status'               => $followup->status,
                'type'                 => $followup->type,
                'notes'                => $followup->notes,
                'latitude'             => $followup->latitude ? (float)$followup->latitude : null,
                'longitude'            => $followup->longitude ? (float)$followup->longitude : null,
                'location_address'     => $followup->location_address,
                'selfie_path'          => $followup->selfie_path,
                'selfie_url'           => $followup->selfie_url,
                'location_verified_at' => $followup->location_verified_at?->toIso8601String(),
                'followup_date'        => $followup->followup_date?->toIso8601String(),
            ],
        ]);
    }

    /**
     * PUT/PATCH/POST /api/crm/followups/{followup}
     * Update or reschedule a followup/activity record.
     */
    public function update(Request $request, LeadFollowup $followup): JsonResponse
    {
        if ($followup->lead) {
            $this->authorize('update', $followup->lead);
        } elseif ($followup->deal) {
            $this->authorize('update', $followup->deal);
        }

        $targetStatus = $request->input('status', $followup->status);
        $activityType = $request->input('type', $followup->type);
        $isGeoRequired = $this->followupService->isGeoVerificationRequired($targetStatus, $activityType, $followup);

        $rules = [
            'status'            => 'nullable|string|in:Pending,Completed,Not Connected,Cancelled,Rescheduled',
            'notes'             => 'nullable|string',
            'type'              => 'nullable|string|in:Call,Email,Meeting,Demo,Task',
            'followup_date'     => 'nullable|date',
            'tagged_user_id'    => 'nullable|exists:users,id',
            'tagged_user_ids'   => 'nullable|array',
            'tagged_user_ids.*' => 'nullable|exists:users,id',
            'is_reschedule'     => 'nullable|boolean',
            'latitude'          => 'nullable|numeric|between:-90,90',
            'longitude'         => 'nullable|numeric|between:-180,180',
            'location_address'  => 'nullable|string|max:500',
            'selfie'            => 'nullable|file|image|mimes:jpeg,png,jpg,webp|max:5120',
            'selfie_base64'     => 'nullable|string',
        ];

        if ($isGeoRequired) {
            $hasSelfie = !empty($followup->selfie_path) || $request->hasFile('selfie') || $request->filled('selfie_base64');
            $hasLat = $request->filled('latitude') || $followup->latitude !== null;
            $hasLng = $request->filled('longitude') || $followup->longitude !== null;

            $errors = [];
            if (!$hasLat) {
                $errors['latitude'] = ['Live GPS latitude is required to complete this meeting.'];
            }
            if (!$hasLng) {
                $errors['longitude'] = ['Live GPS longitude is required to complete this meeting.'];
            }
            if (!$hasSelfie) {
                $errors['selfie'] = ['A live front camera selfie is required to complete this meeting.'];
            }

            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Geo-verification coordinates and selfie are required for field meetings.',
                    'errors'  => $errors,
                ], 422);
            }
        }

        $validated = $request->validate($rules);
        $verificationData = $this->followupService->extractVerificationPayload($request, $followup);
        $merged = array_merge($validated, $verificationData);

        $msg = $this->followupService->updateOrReschedule($followup, $merged, $request->boolean('is_reschedule'));
        $followup->refresh();

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data'    => [
                'id'                   => $followup->id,
                'lead_id'              => $followup->lead_id,
                'crm_deal_id'          => $followup->crm_deal_id,
                'status'               => $followup->status,
                'type'                 => $followup->type,
                'title'                => $followup->title,
                'notes'                => $followup->notes,
                'followup_date'        => $followup->followup_date?->toIso8601String(),
                'latitude'             => $followup->latitude ? (float)$followup->latitude : null,
                'longitude'            => $followup->longitude ? (float)$followup->longitude : null,
                'location_address'     => $followup->location_address,
                'selfie_path'          => $followup->selfie_path,
                'selfie_url'           => $followup->selfie_url,
                'location_verified_at' => $followup->location_verified_at?->toIso8601String(),
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
        } elseif ($followup->deal) {
            $this->authorize('update', $followup->deal);
        }

        $this->followupService->deleteFollowup($followup);

        return response()->json([
            'success' => true,
            'message' => 'Followup removed successfully.',
        ]);
    }
}
