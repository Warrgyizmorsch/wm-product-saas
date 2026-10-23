<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Services\LeadFollowupService;
use App\Domains\CRM\Services\GoogleCalendarIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class DealActivityApiController extends Controller
{
    public function __construct(
        private readonly LeadFollowupService $followupService
    ) {}

    /**
     * GET /api/crm/deals/{deal}/followups
     * List all activities and meetings for a deal.
     */
    public function index(CrmDeal $deal): JsonResponse
    {
        $this->authorize('view', $deal);

        $activities = $deal->followups()
            ->with(['taggedUser'])
            ->get()
            ->map(function (LeadFollowup $followup) {
                return [
                    'id'                      => $followup->id,
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
            'count'   => $activities->count(),
            'data'    => $activities,
        ]);
    }

    /**
     * POST /api/crm/deals/{deal}/followups
     * Schedule a meeting/call or log past interaction for a deal.
     */
    public function store(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $validator = Validator::make($request->all(), [
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
            'latitude'             => 'nullable|numeric|between:-90,90',
            'longitude'            => 'nullable|numeric|between:-180,180',
            'location_address'     => 'nullable|string|max:500',
            'selfie'               => 'nullable|file|image|mimes:jpeg,png,jpg,webp|max:5120',
            'selfie_base64'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $actionMode = $validated['action_mode'] ?? 'schedule';
        $targetStatus = ($actionMode === 'schedule') ? 'Pending' : ($validated['status'] ?? 'Completed');
        $activityType = $validated['type'] ?? 'Meeting';

        if ($actionMode === 'log_note' && $this->followupService->isGeoVerificationRequired($targetStatus, $activityType)) {
            $errors = [];
            if (!$request->filled('latitude')) {
                $errors['latitude'] = ['Live GPS latitude is required to log this field meeting.'];
            }
            if (!$request->filled('longitude')) {
                $errors['longitude'] = ['Live GPS longitude is required to log this field meeting.'];
            }
            if (!$request->hasFile('selfie') && !$request->filled('selfie_base64')) {
                $errors['selfie'] = ['A live front camera selfie is required to log this field meeting.'];
            }

            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Geo-verification coordinates and selfie are required for field meetings.',
                    'errors'  => $errors,
                ], 422);
            }
        }

        $verificationData = $this->followupService->extractVerificationPayload($request);

        $tenantId = $deal->tenant_id ?? (tenant_id() ?? 1);
        $companyId = $deal->company_id ?? 1;
        $branchId = $deal->branch_id;

        $taggedUserIds = !empty($validated['tagged_user_ids']) ? array_values(array_filter($validated['tagged_user_ids'])) : null;
        $primaryTaggedId = !empty($taggedUserIds) ? $taggedUserIds[0] : null;

        $followupDateTime = !empty($validated['followup_date']) ? Carbon::parse($validated['followup_date']) : now();
        $durationMinutes = $validated['duration_minutes'] ?? 30;
        $title = $validated['title'] ?? ("Deal Meeting - " . $deal->title);

        $followup = LeadFollowup::create([
            'tenant_id'            => $tenantId,
            'company_id'           => $companyId,
            'branch_id'            => $branchId,
            'crm_deal_id'          => $deal->id,
            'lead_id'              => $deal->lead?->id,
            'followup_date'        => $followupDateTime,
            'type'                 => $activityType,
            'title'                => $title,
            'duration_minutes'     => $durationMinutes,
            'guest_emails'         => $validated['guest_emails'] ?? null,
            'status'               => $targetStatus,
            'notes'                => $validated['notes'] ?? null,
            'tagged_user_id'       => $primaryTaggedId,
            'tagged_user_ids'      => $taggedUserIds,
            'latitude'             => $verificationData['latitude'] ?? null,
            'longitude'            => $verificationData['longitude'] ?? null,
            'location_address'     => $verificationData['location_address'] ?? null,
            'selfie_path'          => $verificationData['selfie_path'] ?? null,
            'location_verified_at' => $verificationData['location_verified_at'] ?? null,
        ]);

        $syncGoogle = !empty($validated['sync_google_calendar']) || !empty($validated['create_meet_link']);
        if ($actionMode === 'schedule' && $syncGoogle) {
            try {
                $createMeet = !empty($validated['create_meet_link']);
                $clientEmail = !empty($deal->contact?->email) ? trim($deal->contact->email) : (!empty($deal->account?->email) ? trim($deal->account->email) : null);
                $attendees = [];
                if ($clientEmail) {
                    $attendees[] = $clientEmail;
                }
                if (auth()->check() && auth()->user()?->email) {
                    $attendees[] = auth()->user()->email;
                }
                if (!empty($taggedUserIds)) {
                    $taggedEmails = \App\Models\User::whereIn('id', $taggedUserIds)->pluck('email')->filter()->toArray();
                    $attendees = array_merge($attendees, $taggedEmails);
                }
                if (!empty($validated['guest_emails'])) {
                    $extraEmails = array_map('trim', explode(',', $validated['guest_emails']));
                    $attendees = array_merge($attendees, $extraEmails);
                }
                $attendees = array_unique(array_values(array_filter($attendees)));

                $calService = app(GoogleCalendarIntegrationService::class);
                $res = $calService->createEvent([
                    'summary'          => $title,
                    'description'      => $validated['notes'] ?? '',
                    'start_time'       => $followupDateTime->toIso8601String(),
                    'end_time'         => (clone $followupDateTime)->addMinutes($durationMinutes)->toIso8601String(),
                    'attendees'        => $attendees,
                    'create_meet_link' => $createMeet,
                    'deal_id'          => $deal->id,
                ]);

                if (!empty($res['success'])) {
                    $followup->google_event_id = $res['google_event_id'] ?? null;
                    $followup->google_meet_link = $createMeet ? ($res['meet_link'] ?? null) : null;
                    $followup->is_google_meet = $createMeet && !empty($res['meet_link']);
                    if ($createMeet && !empty($res['meet_link'])) {
                        $followup->notes = ($followup->notes ? $followup->notes . "\n" : '') . "Google Meet: " . $res['meet_link'];
                    }
                    $followup->save();
                }
            } catch (\Throwable $ex) {
                \Illuminate\Support\Facades\Log::warning('Deal activity calendar sync warning: ' . $ex->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Deal activity saved successfully.',
            'data'    => [
                'id'                   => $followup->id,
                'crm_deal_id'          => $followup->crm_deal_id,
                'type'                 => $followup->type,
                'title'                => $followup->title,
                'status'               => $followup->status,
                'followup_date'        => $followup->followup_date?->toIso8601String(),
                'google_meet_link'     => $followup->google_meet_link,
                'latitude'             => $followup->latitude ? (float)$followup->latitude : null,
                'longitude'            => $followup->longitude ? (float)$followup->longitude : null,
                'location_address'     => $followup->location_address,
                'selfie_path'          => $followup->selfie_path,
                'selfie_url'           => $followup->selfie_url,
                'location_verified_at' => $followup->location_verified_at?->toIso8601String(),
            ],
        ], 201);
    }
}
