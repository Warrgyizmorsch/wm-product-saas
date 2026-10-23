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
        $isMeeting = strcasecmp((string)$activityType, 'Meeting') === 0;

        $tenantId = $lead->tenant_id ?? (auth()->user()?->tenant_id ?? (tenant_id() ?? 1));
        $tenantObj = tenant() ?? \App\Models\Tenant::find($tenantId);
        $settings = is_array($tenantObj?->settings) ? $tenantObj->settings : [];
        $isGeoVerificationEnabled = !empty($settings['field_visit_verification']);

        $hasGeoData = $request->filled('latitude')
            || $request->filled('longitude')
            || $request->filled('location_address')
            || $request->hasFile('selfie')
            || $request->filled('selfie_base64');

        // Validation Rule: Agar activity type Meeting nahi hai (e.g. Call, Email), to GPS data submit nahi ho sakta
        if (!$isMeeting && $hasGeoData) {
            return response()->json([
                'success' => false,
                'message' => "Geo-verification (latitude, longitude, selfie) is only allowed for 'Meeting' activity type. For {$activityType}, GPS coordinates cannot be submitted.",
                'errors'  => [
                    'type' => [
                        "Activity type '{$activityType}' does not support geo-verification. Only 'Meeting' activities can have GPS and selfie verification."
                    ],
                ],
            ], 422);
        }

        // Validation Rule: Agar tenant setting me geo-verification disabled hai, to GPS data submit nahi ho sakta
        if (!$isGeoVerificationEnabled && $hasGeoData) {
            return response()->json([
                'success' => false,
                'message' => 'Geo-verification is disabled in CRM settings. Latitude, longitude, and selfie cannot be submitted. Only status and notes can be updated.',
                'errors'  => [
                    'geo_verification' => [
                        'Field visit geo-verification is disabled in CRM settings for this tenant. Only status and notes can be updated.'
                    ],
                ],
            ], 422);
        }

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
     * Helper to safely resolve a LeadFollowup model instance from variadic route parameters.
     * Supports both direct /api/crm/followups/{id} and nested /api/crm/leads/{lead}/followups/{id}.
     */
    private function resolveFollowupInstance(mixed ...$args): LeadFollowup
    {
        foreach ($args as $arg) {
            if ($arg instanceof LeadFollowup) {
                return $arg;
            }
        }

        foreach (array_reverse($args) as $arg) {
            if (is_numeric($arg)) {
                $found = LeadFollowup::withoutGlobalScope('branch')->find((int)$arg);
                if ($found) {
                    return $found;
                }
            }
        }

        abort(404, 'Followup record not found.');
    }

    /**
     * GET /api/crm/my-meetings or GET /api/crm/meetings
     * List all meetings for the logged-in user categorized into:
     * - today / todays: today's pending scheduled meetings
     * - planned / next_meetings: future pending scheduled meetings
     * - overdue: missed pending meetings scheduled before today
     * - activity_logs / history: completed / actioned meeting logs
     * Each meeting includes complete Lead and Deal information.
     */
    public function myMeetings(Request $request): JsonResponse
    {
        $user = auth()->user();
        $userId = (int)($request->input('user_id') ?: ($user?->id ?? 1));
        $tenantId = $user?->tenant_id ?? (tenant_id() ?? 1);

        $tenantObj = tenant() ?? \App\Models\Tenant::find($tenantId);
        $settings = is_array($tenantObj?->settings) ? $tenantObj->settings : [];
        $isGeoVerificationEnabled = !empty($settings['field_visit_verification']);

        $query = LeadFollowup::withoutGlobalScope('branch')
            ->where('tenant_id', $tenantId)
            ->with([
                'lead' => function ($q) {
                    $q->withoutGlobalScope('branch')->with('owner:id,name,email');
                },
                'deal' => function ($q) {
                    $q->withoutGlobalScope('branch')->with('owner:id,name,email', 'account:id,name', 'contact:id,name');
                },
                'taggedUser:id,name,email',
            ]);

        // Filter by activity type: optional ('Meeting', 'Call', etc.). If omitted or 'all', returns all activities
        $type = $request->input('type');
        if ($type && strtolower($type) !== 'all') {
            $query->where('type', $type);
        }

        // Optional filter by Lead ID
        if ($leadId = $request->input('lead_id')) {
            $query->where('lead_id', (int)$leadId);
        }

        // Optional filter by Deal ID
        if ($dealId = $request->input('deal_id') ?: $request->input('crm_deal_id')) {
            $query->where('crm_deal_id', (int)$dealId);
        }

        // Ownership logic:
        // 1. If it's a Deal followup (crm_deal_id is present) -> user must be the Deal owner
        // 2. If it's a Lead followup (lead_id is present and crm_deal_id is null) -> user must be the Lead owner
        // 3. Or user is explicitly tagged/assigned to this meeting
        $query->where(function ($q) use ($userId) {
            $q->where(function ($sub) use ($userId) {
                $sub->whereNotNull('crm_deal_id')
                    ->whereHas('deal', function ($dq) use ($userId) {
                        $dq->withoutGlobalScope('branch')->where('owner_id', $userId);
                    });
            })
            ->orWhere(function ($sub) use ($userId) {
                $sub->whereNotNull('lead_id')
                    ->whereNull('crm_deal_id')
                    ->whereHas('lead', function ($lq) use ($userId) {
                        $lq->withoutGlobalScope('branch')->where('lead_owner_id', $userId);
                    });
            })
            ->orWhere('tagged_user_id', $userId)
            ->orWhereJsonContains('tagged_user_ids', $userId)
            ->orWhereJsonContains('tagged_user_ids', (string)$userId);
        });

        $meetings = $query->orderBy('followup_date', 'asc')->get();

        $transformMeeting = function (LeadFollowup $item) use ($isGeoVerificationEnabled) {
            $isMeeting = strcasecmp((string)$item->type, 'Meeting') === 0;

            // Extract outcome if present in notes or status
            $outcome = null;
            if (preg_match('/Outcome:\s*([^—\n]+)/i', (string)$item->notes, $matches)) {
                $outcome = trim($matches[1]);
            }

            $leadData = null;
            if ($item->lead) {
                $l = $item->lead;
                $leadData = [
                    'id'               => $l->id,
                    'lead_number'      => $l->lead_number,
                    'company_name'     => $l->company_name,
                    'contact_person'   => $l->contact_person,
                    'phone'            => $l->phone ?: $l->company_phone,
                    'email'            => $l->email ?: $l->company_email,
                    'status'           => $l->status,
                    'priority'         => $l->priority,
                    'segment'          => $l->segment,
                    'industry_type'    => $l->industry_type,
                    'expected_amount'  => $l->expected_amount !== null ? (float)$l->expected_amount : null,
                    'lead_owner'       => [
                        'id'    => $l->owner?->id ?? $l->lead_owner_id,
                        'name'  => $l->owner?->name,
                        'email' => $l->owner?->email,
                    ],
                ];
            }

            $dealData = null;
            if ($item->deal) {
                $d = $item->deal;
                $dealData = [
                    'id'              => $d->id,
                    'deal_number'     => $d->deal_number,
                    'title'           => $d->title,
                    'stage'           => $d->stage,
                    'estimated_value' => $d->estimated_value !== null ? (float)$d->estimated_value : null,
                    'customer_name'   => $d->account?->name ?: ($d->contact?->name ?? null),
                    'deal_owner'      => [
                        'id'    => $d->owner?->id ?? $d->owner_id,
                        'name'  => $d->owner?->name,
                        'email' => $d->owner?->email,
                    ],
                ];
            }

            return [
                'id'                           => $item->id,
                'lead_id'                      => $item->lead_id,
                'crm_deal_id'                  => $item->crm_deal_id,
                'type'                         => $item->type ?? 'Meeting',
                'title'                        => $item->title,
                'status'                       => $item->status ?? 'Pending',
                'outcome'                      => $outcome,
                'followup_date'                => $item->followup_date?->toIso8601String(),
                'followup_date_formatted'      => $item->followup_date?->format('Y-m-d H:i'),
                'duration_minutes'             => $item->duration_minutes ?: 30,
                'notes'                        => $item->notes,
                'is_google_meet'               => (bool)$item->is_google_meet,
                'google_meet_link'             => $item->google_meet_link,
                'guest_emails'                 => $item->guest_emails,
                'is_geo_verification_required' => $isMeeting && $isGeoVerificationEnabled,
                'latitude'                     => $item->latitude ? (float)$item->latitude : null,
                'longitude'                    => $item->longitude ? (float)$item->longitude : null,
                'location_address'             => $item->location_address,
                'selfie_url'                   => $item->selfie_url,
                'location_verified_at'         => $item->location_verified_at?->toIso8601String(),
                'is_geo_verified'              => !empty($item->latitude) && !empty($item->selfie_path),
                'lead'                         => $leadData,
                'deal'                         => $dealData,
                'tagged_users'                 => $item->tagged_users->map(fn($u) => [
                    'id'    => $u->id,
                    'name'  => $u->name,
                    'email' => $u->email,
                ]),
                'created_at'                   => $item->created_at?->toIso8601String(),
            ];
        };

        $startOfToday = now()->startOfDay();
        $endOfToday   = now()->endOfDay();

        $todayList   = [];
        $plannedList = [];
        $overdueList = [];
        $historyList = [];

        foreach ($meetings as $m) {
            $data = $transformMeeting($m);
            $status = $m->status ?: 'Pending';
            $dueDate = $m->followup_date;

            if ($status === 'Pending') {
                if ($dueDate && $dueDate->lt($startOfToday)) {
                    $overdueList[] = $data;
                } elseif ($dueDate && $dueDate->betweenIncluded($startOfToday, $endOfToday)) {
                    $todayList[] = $data;
                } else {
                    $plannedList[] = $data;
                }
            } else {
                $historyList[] = $data;
            }
        }

        return response()->json([
            'success'          => true,
            'user'             => [
                'id'    => $user?->id ?? $userId,
                'name'  => $user?->name ?? 'User #' . $userId,
                'email' => $user?->email,
            ],
            'geo_verification' => [
                'is_enabled'                   => $isGeoVerificationEnabled,
                'field_visit_verification'     => $isGeoVerificationEnabled,
                'requires_selfie_and_location' => $isGeoVerificationEnabled,
            ],
            'counts'           => [
                'today'         => count($todayList),
                'planned'       => count($plannedList),
                'overdue'       => count($overdueList),
                'activity_logs' => count($historyList),
                'total'         => count($meetings),
            ],
            'activities'       => [
                'today'         => $todayList,
                'planned'       => $plannedList,
                'overdue'       => $overdueList,
                'activity_logs' => $historyList,
            ],
        ]);
    }

    /**
     * GET /api/crm/followups/{followup} or GET /api/crm/leads/{lead}/followups/{followup}
     * Get a single followup detail.
     */
    public function show(mixed ...$args): JsonResponse
    {
        $followup = $this->resolveFollowupInstance(...$args);

        if ($followup->lead) {
            $this->authorize('view', $followup->lead);
        } elseif ($followup->deal) {
            $this->authorize('view', $followup->deal);
        }

        $tenantId = $followup->tenant_id ?? (auth()->user()?->tenant_id ?? (tenant_id() ?? 1));
        $tenantObj = tenant() ?? \App\Models\Tenant::find($tenantId);
        $settings = is_array($tenantObj?->settings) ? $tenantObj->settings : [];
        $isGeoVerificationEnabled = !empty($settings['field_visit_verification']);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                           => $followup->id,
                'lead_id'                      => $followup->lead_id,
                'crm_deal_id'                  => $followup->crm_deal_id,
                'type'                         => $followup->type ?? 'Meeting',
                'title'                        => $followup->title,
                'status'                       => $followup->status ?? 'Pending',
                'followup_date'                => $followup->followup_date?->toIso8601String(),
                'followup_date_formatted'      => $followup->followup_date?->format('Y-m-d H:i'),
                'duration_minutes'             => $followup->duration_minutes ?: 30,
                'notes'                        => $followup->notes,
                'is_google_meet'               => (bool)$followup->is_google_meet,
                'google_meet_link'             => $followup->google_meet_link,
                'guest_emails'                 => $followup->guest_emails,
                'is_geo_verification_required' => strcasecmp((string)$followup->type, 'Meeting') === 0 && $isGeoVerificationEnabled,
                'latitude'                     => $followup->latitude ? (float)$followup->latitude : null,
                'longitude'                    => $followup->longitude ? (float)$followup->longitude : null,
                'location_address'             => $followup->location_address,
                'selfie_path'                  => $followup->selfie_path,
                'selfie_url'                   => $followup->selfie_url,
                'location_verified_at'         => $followup->location_verified_at?->toIso8601String(),
                'is_geo_verified'              => !empty($followup->latitude) && !empty($followup->selfie_path),
                'tagged_users'                 => $followup->tagged_users->map(fn($u) => [
                    'id'    => $u->id,
                    'name'  => $u->name,
                    'email' => $u->email,
                ]),
                'lead'                         => $followup->lead ? [
                    'id'             => $followup->lead->id,
                    'lead_number'    => $followup->lead->lead_number,
                    'company_name'   => $followup->lead->company_name,
                    'contact_person' => $followup->lead->contact_person,
                    'phone'          => $followup->lead->phone ?: $followup->lead->company_phone,
                    'email'          => $followup->lead->email ?: $followup->lead->company_email,
                    'status'         => $followup->lead->status,
                ] : null,
                'deal'                         => $followup->deal ? [
                    'id'          => $followup->deal->id,
                    'deal_number' => $followup->deal->deal_number,
                    'title'       => $followup->deal->title,
                    'stage'       => $followup->deal->stage,
                ] : null,
            ],
        ]);
    }

    /**
     * PATCH /api/crm/followups/{followup}/status or PATCH /api/crm/leads/{lead}/followups/{followup}/status
     * Mark followup completed / cancelled / rescheduled with optional GPS and Selfie.
     */
    public function updateStatus(Request $request, mixed ...$args): JsonResponse
    {
        $followup = $this->resolveFollowupInstance(...$args);

        if ($followup->lead) {
            $this->authorize('update', $followup->lead);
        } elseif ($followup->deal) {
            $this->authorize('update', $followup->deal);
        }

        // Defensive fallback: If Postman or client sent body as raw Text without application/json header
        if (!$request->has('status') && !empty($request->getContent())) {
            $rawContent = $request->getContent();
            $cleanContent = preg_replace('/,\s*([\]}])/m', '$1', $rawContent);
            $decoded = json_decode($cleanContent, true) ?: json_decode($rawContent, true);
            if (is_array($decoded)) {
                $request->merge($decoded);
            }
        }

        $rawStatus = (string)$request->input('status');
        $cleanStatus = ucwords(str_replace('_', ' ', strtolower(trim($rawStatus))));
        $defaultOutcome = null;

        if ($cleanStatus === 'Connected') {
            $normalizedStatus = 'Completed';
            $defaultOutcome = 'Connected';
        } elseif ($cleanStatus === 'Not Connected') {
            $normalizedStatus = 'Not Connected';
            $defaultOutcome = 'Not Connected';
        } else {
            $normalizedStatus = $cleanStatus;
        }

        $request->merge(['status' => $normalizedStatus]);

        $tenantId = $followup->tenant_id ?? (auth()->user()?->tenant_id ?? (tenant_id() ?? 1));
        $tenantObj = tenant() ?? \App\Models\Tenant::find($tenantId);
        $settings = is_array($tenantObj?->settings) ? $tenantObj->settings : [];
        $isGeoVerificationEnabled = !empty($settings['field_visit_verification']);

        $hasGeoData = $request->filled('latitude')
            || $request->filled('longitude')
            || $request->filled('location_address')
            || $request->hasFile('selfie')
            || $request->filled('selfie_base64');

        $activityType = $followup->type ?? 'Meeting';
        $isMeeting = strcasecmp((string)$activityType, 'Meeting') === 0;

        // Validation Rule: Agar activity type 'Meeting' NAHI hai (e.g. Call, Email), to latitude, longitude, selfie submit nahi ho sakte
        if (!$isMeeting && $hasGeoData) {
            return response()->json([
                'success' => false,
                'message' => "Geo-verification (latitude, longitude, selfie) is only allowed for 'Meeting' activity type. For {$activityType}, GPS coordinates cannot be submitted.",
                'errors'  => [
                    'type' => [
                        "Activity type '{$activityType}' does not support geo-verification. Only 'Meeting' activities can have GPS and selfie verification."
                    ],
                ],
            ], 422);
        }

        // Web Rule: Agar CRM setting me geo-verification disable hai, to latitude, longitude, selfie submit nahi ho sakte
        if (!$isGeoVerificationEnabled && $hasGeoData) {
            return response()->json([
                'success' => false,
                'message' => 'Geo-verification is disabled in CRM settings. Latitude, longitude, and selfie cannot be submitted. Only status and notes can be updated.',
                'errors'  => [
                    'geo_verification' => [
                        'Field visit geo-verification is disabled in CRM settings for this tenant. Only status and notes can be updated.'
                    ],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'status'               => 'required|string|in:Completed,Not Connected,Cancelled,Rescheduled,Pending',
            'notes'                => 'nullable|string',
            'outcome'              => 'nullable|string',
            'tagged_user_ids'      => 'nullable|array',
            'tagged_user_ids.*'    => 'nullable|exists:users,id',
            'latitude'             => 'nullable|numeric|between:-90,90',
            'longitude'            => 'nullable|numeric|between:-180,180',
            'location_address'     => 'nullable|string|max:500',
            'selfie'               => 'nullable|file|image|mimes:jpeg,png,jpg,webp|max:5120',
            'selfie_base64'        => 'nullable|string',
        ]);

        $targetStatus = $validated['status'];
        $isMeeting = strcasecmp((string)$followup->type, 'Meeting') === 0;
        $isCompletingMeeting = in_array($targetStatus, ['Completed', 'Not Connected']) && $isMeeting;

        if ($isGeoVerificationEnabled && $isCompletingMeeting) {
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

        $outcome = !empty($validated['outcome']) ? $validated['outcome'] : $defaultOutcome;
        $statusNotes = trim($validated['notes'] ?? '');
        if (!empty($outcome)) {
            $statusNotes = "Outcome: " . $outcome . ($statusNotes ? " — " . $statusNotes : "");
        }

        $newNotes = $followup->notes ?? '';
        if ($statusNotes !== '') {
            $newNotes = $newNotes ? ($newNotes . "\n\n[" . now()->format('Y-m-d H:i') . " Status Update: {$validated['status']}] " . $statusNotes) : $statusNotes;
        }

        $verificationData = $isGeoVerificationEnabled ? $this->followupService->extractVerificationPayload($request, $followup) : [];

        $payload = array_merge([
            'status' => $validated['status'],
            'notes'  => $newNotes,
        ], $verificationData);

        if (!empty($validated['tagged_user_ids'])) {
            $payload['tagged_user_ids'] = $validated['tagged_user_ids'];
        }

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
                'outcome'              => $outcome,
                'type'                 => $followup->type,
                'notes'                => $followup->notes,
                'latitude'             => $followup->latitude ? (float)$followup->latitude : null,
                'longitude'            => $followup->longitude ? (float)$followup->longitude : null,
                'location_address'     => $followup->location_address,
                'selfie_path'          => $followup->selfie_path,
                'selfie_url'           => $followup->selfie_url,
                'location_verified_at' => $followup->location_verified_at?->toIso8601String(),
                'is_geo_verified'      => !empty($followup->latitude) && !empty($followup->selfie_path),
                'followup_date'        => $followup->followup_date?->toIso8601String(),
            ],
        ]);
    }

    /**
     * PUT/PATCH/POST /api/crm/followups/{followup} or /api/crm/leads/{lead}/followups/{followup}
     * Update or reschedule a followup/activity record.
     */
    public function update(Request $request, mixed ...$args): JsonResponse
    {
        $followup = $this->resolveFollowupInstance(...$args);

        if ($followup->lead) {
            $this->authorize('update', $followup->lead);
        } elseif ($followup->deal) {
            $this->authorize('update', $followup->deal);
        }

        $targetStatus = $request->input('status', $followup->status);
        $activityType = $request->input('type', $followup->type);
        $isGeoRequired = $this->followupService->isGeoVerificationRequired($targetStatus, $activityType, $followup);

        $tenantId = $followup->tenant_id ?? (auth()->user()?->tenant_id ?? (tenant_id() ?? 1));
        $tenantObj = tenant() ?? \App\Models\Tenant::find($tenantId);
        $settings = is_array($tenantObj?->settings) ? $tenantObj->settings : [];
        $isGeoVerificationEnabled = !empty($settings['field_visit_verification']);

        // Web Rule: Agar CRM setting me geo-verification disable hai, to latitude, longitude, selfie submit nahi ho sakte
        if (!$isGeoVerificationEnabled) {
            $hasGeoData = $request->filled('latitude')
                || $request->filled('longitude')
                || $request->filled('location_address')
                || $request->hasFile('selfie')
                || $request->filled('selfie_base64');

            if ($hasGeoData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Geo-verification is disabled in CRM settings. Latitude, longitude, and selfie cannot be submitted. Only status and notes can be updated.',
                    'errors'  => [
                        'geo_verification' => [
                            'Field visit geo-verification is disabled in CRM settings for this tenant. Only status and notes can be updated.'
                        ],
                    ],
                ], 422);
            }
        }

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
            'lead_status'       => 'nullable|string|max:50',
            'priority'          => 'nullable|string|max:50',
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

        if ($followup->lead) {
            if (!empty($validated['lead_status'])) {
                $followup->lead->status = $validated['lead_status'];
            }
            if (!empty($validated['priority'])) {
                $followup->lead->priority = $validated['priority'];
            }
            if ($followup->lead->isDirty()) {
                $followup->lead->save();
            }
        }

        $verificationData = $isGeoVerificationEnabled ? $this->followupService->extractVerificationPayload($request, $followup) : [];
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
     * DELETE /api/crm/followups/{followup} or DELETE /api/crm/leads/{lead}/followups/{followup}
     * Delete a followup record.
     */
    public function destroy(mixed ...$args): JsonResponse
    {
        $followup = $this->resolveFollowupInstance(...$args);

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
