<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Services\LeadFollowupService;
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
                    'id'                     => $followup->id,
                    'crm_deal_id'            => $followup->crm_deal_id,
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

        $tenantId = $deal->tenant_id ?? (tenant_id() ?? 1);
        $companyId = $deal->company_id ?? 1;
        $branchId = $deal->branch_id;

        $taggedUserIds = !empty($validated['tagged_user_ids']) ? array_values(array_filter($validated['tagged_user_ids'])) : null;
        $primaryTaggedId = !empty($taggedUserIds) ? $taggedUserIds[0] : null;

        $followup = LeadFollowup::create([
            'tenant_id'        => $tenantId,
            'company_id'       => $companyId,
            'branch_id'        => $branchId,
            'crm_deal_id'      => $deal->id,
            'lead_id'          => $deal->lead?->id,
            'followup_date'    => !empty($validated['followup_date']) ? Carbon::parse($validated['followup_date']) : now(),
            'type'             => $validated['type'] ?? 'Meeting',
            'title'            => $validated['title'] ?? ("Deal Meeting - " . $deal->title),
            'duration_minutes' => $validated['duration_minutes'] ?? 30,
            'guest_emails'     => $validated['guest_emails'] ?? null,
            'status'           => ($actionMode === 'schedule') ? 'Pending' : ($validated['status'] ?? 'Completed'),
            'notes'            => $validated['notes'] ?? null,
            'tagged_user_id'   => $primaryTaggedId,
            'tagged_user_ids'  => $taggedUserIds,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deal activity saved successfully.',
            'data'    => [
                'id'            => $followup->id,
                'crm_deal_id'   => $followup->crm_deal_id,
                'type'          => $followup->type,
                'title'         => $followup->title,
                'status'        => $followup->status,
                'followup_date' => $followup->followup_date?->toIso8601String(),
            ],
        ], 201);
    }
}
