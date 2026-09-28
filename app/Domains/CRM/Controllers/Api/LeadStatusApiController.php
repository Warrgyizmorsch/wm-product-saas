<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\LeadStatus;
use App\Domains\CRM\Services\LeadStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class LeadStatusApiController extends Controller
{
    public function __construct(
        private readonly LeadStatusService $service
    ) {}

    /**
     * Resolve Tenant Context
     */
    private function resolveTenantId(): int
    {
        return (int)(auth()->user()?->tenant_id ?? (tenant_id() ?? 1));
    }

    /**
     * GET /api/crm/lead-statuses
     * List all lead statuses (with system protected flag, color, sort order).
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $query = LeadStatus::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where('is_active', true);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('type') && $request->type !== 'all') {
            if ($request->type === 'protected') {
                $query->where(fn ($q) => $q->where('is_protected', true)->orWhereNull('tenant_id'));
            } elseif ($request->type === 'custom') {
                $query->where('is_protected', false)->whereNotNull('tenant_id');
            }
        }

        $statuses = $query->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (LeadStatus $s) {
                return [
                    'id'           => $s->id,
                    'name'         => $s->name,
                    'color'        => $s->color ?? 'bg-primary',
                    'sort_order'   => (int)$s->sort_order,
                    'is_protected' => (bool)($s->is_protected || in_array($s->name, LeadStatus::PROTECTED_STATUSES, true)),
                    'is_active'    => (bool)$s->is_active,
                ];
            });

        return response()->json([
            'success' => true,
            'count'   => $statuses->count(),
            'data'    => $statuses,
        ]);
    }

    /**
     * POST /api/crm/lead-statuses
     * Create a new custom lead status.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('lead_statuses', 'name')->where(function ($query) use ($tenantId) {
                    return $query->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'));
                }),
            ],
            'color'      => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (in_array(trim($validated['name']), LeadStatus::PROTECTED_STATUSES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Status "' . $validated['name'] . '" already exists as a protected system status.',
            ], 422);
        }

        $status = $this->service->createStatus([
            'tenant_id'  => $tenantId,
            'name'       => trim($validated['name']),
            'color'      => $validated['color'] ?? 'bg-primary',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead status created successfully.',
            'data'    => [
                'id'           => $status->id,
                'name'         => $status->name,
                'color'        => $status->color,
                'sort_order'   => $status->sort_order,
                'is_protected' => false,
            ],
        ], 201);
    }

    /**
     * GET /api/crm/lead-statuses/{id}
     * Get details of a single lead status.
     */
    public function show(LeadStatus $leadStatus): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $leadStatus->id,
                'name'         => $leadStatus->name,
                'color'        => $leadStatus->color ?? 'bg-primary',
                'sort_order'   => (int)$leadStatus->sort_order,
                'is_protected' => (bool)($leadStatus->is_protected || in_array($leadStatus->name, LeadStatus::PROTECTED_STATUSES, true)),
                'is_active'    => (bool)$leadStatus->is_active,
            ],
        ]);
    }

    /**
     * PUT / PATCH /api/crm/lead-statuses/{id}
     * Update an existing lead status (system locked statuses cannot be renamed).
     */
    public function update(Request $request, LeadStatus $leadStatus): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('lead_statuses', 'name')
                    ->ignore($leadStatus->id)
                    ->where(function ($query) use ($tenantId) {
                        return $query->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'));
                    }),
            ],
            'color'      => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $res = $this->service->updateStatus($leadStatus, [
            'name'       => trim($validated['name']),
            'color'      => $validated['color'] ?? $leadStatus->color,
            'sort_order' => isset($validated['sort_order']) ? (int)$validated['sort_order'] : $leadStatus->sort_order,
        ]);

        if (!$res['success']) {
            return response()->json([
                'success' => false,
                'message' => $res['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $res['message'],
            'data'    => [
                'id'         => $leadStatus->id,
                'name'       => $leadStatus->name,
                'color'      => $leadStatus->color,
                'sort_order' => $leadStatus->sort_order,
            ],
        ]);
    }

    /**
     * DELETE /api/crm/lead-statuses/{id}
     * Delete a lead status (system protected statuses are locked against deletion).
     */
    public function destroy(LeadStatus $leadStatus): JsonResponse
    {
        $res = $this->service->deleteStatus($leadStatus);

        if (!$res['success']) {
            return response()->json([
                'success' => false,
                'message' => $res['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $res['message'],
        ]);
    }

    /**
     * POST /api/crm/lead-statuses/reorder
     * Reorder statuses array: { "order": [3, 1, 2, 4] }
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer',
        ]);

        $res = $this->service->reorderStatuses($validated['order']);

        return response()->json([
            'success' => true,
            'message' => $res['message'] ?? 'Lead statuses reordered successfully.',
        ]);
    }
}
