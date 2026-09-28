<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\DealStatus;
use App\Domains\CRM\Services\DealStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DealStatusApiController extends Controller
{
    public function __construct(
        private readonly DealStatusService $service
    ) {}

    private function resolveTenantId(): int
    {
        return (int)(auth()->user()?->tenant_id ?? (tenant_id() ?? 1));
    }

    /**
     * GET /api/crm/deal-statuses
     * List all deal pipeline stages (with probability, color, order).
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $query = DealStatus::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where('is_active', true);

        if ($request->filled('search')) {
            $search = trim((string)$request->input('search'));
            $query->where('name', 'like', "%{$search}%");
        }

        $statuses = $query->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (DealStatus $s) {
                return [
                    'id'           => $s->id,
                    'name'         => $s->name,
                    'color'        => $s->color ?? 'bg-primary',
                    'probability'  => (int)($s->probability ?? 50),
                    'sort_order'   => (int)$s->sort_order,
                    'is_protected' => (bool)($s->is_protected || in_array($s->name, DealStatus::PROTECTED_STATUSES, true)),
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
     * POST /api/crm/deal-statuses
     * Create a new custom deal stage.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('deal_statuses', 'name')->where(function ($query) use ($tenantId) {
                    return $query->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'));
                }),
            ],
            'color'       => 'nullable|string|max:50',
            'sort_order'  => 'nullable|integer|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (in_array(trim($validated['name']), DealStatus::PROTECTED_STATUSES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Stage "' . $validated['name'] . '" already exists as a protected system status.',
            ], 422);
        }

        $status = $this->service->createStatus([
            'tenant_id'   => $tenantId,
            'name'        => trim($validated['name']),
            'color'       => $validated['color'] ?? 'bg-primary',
            'sort_order'  => $validated['sort_order'] ?? 0,
            'probability' => isset($validated['probability']) ? (int)$validated['probability'] : 50,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deal stage created successfully.',
            'data'    => [
                'id'           => $status->id,
                'name'         => $status->name,
                'color'        => $status->color,
                'probability'  => $status->probability,
                'sort_order'   => $status->sort_order,
                'is_protected' => false,
            ],
        ], 201);
    }

    /**
     * GET /api/crm/deal-statuses/{id}
     * Get details of a single deal stage.
     */
    public function show(DealStatus $dealStatus): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $dealStatus->id,
                'name'         => $dealStatus->name,
                'color'        => $dealStatus->color ?? 'bg-primary',
                'probability'  => (int)($dealStatus->probability ?? 50),
                'sort_order'   => (int)$dealStatus->sort_order,
                'is_protected' => (bool)($dealStatus->is_protected || in_array($dealStatus->name, DealStatus::PROTECTED_STATUSES, true)),
                'is_active'    => (bool)$dealStatus->is_active,
            ],
        ]);
    }

    /**
     * PUT / PATCH /api/crm/deal-statuses/{id}
     * Update an existing deal stage.
     */
    public function update(Request $request, DealStatus $dealStatus): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('deal_statuses', 'name')
                    ->ignore($dealStatus->id)
                    ->where(function ($query) use ($tenantId) {
                        return $query->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'));
                    }),
            ],
            'color'       => 'nullable|string|max:50',
            'sort_order'  => 'nullable|integer|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $res = $this->service->updateStatus($dealStatus, [
            'name'        => trim($validated['name']),
            'color'       => $validated['color'] ?? $dealStatus->color,
            'sort_order'  => isset($validated['sort_order']) ? (int)$validated['sort_order'] : $dealStatus->sort_order,
            'probability' => isset($validated['probability']) ? (int)$validated['probability'] : $dealStatus->probability,
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
                'id'          => $dealStatus->id,
                'name'        => $dealStatus->name,
                'color'       => $dealStatus->color,
                'probability' => $dealStatus->probability,
                'sort_order'  => $dealStatus->sort_order,
            ],
        ]);
    }

    /**
     * DELETE /api/crm/deal-statuses/{id}
     * Delete a deal stage (Won / Lost are system protected).
     */
    public function destroy(DealStatus $dealStatus): JsonResponse
    {
        $res = $this->service->deleteStatus($dealStatus);

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
     * POST /api/crm/deal-statuses/reorder
     * Reorder deal stages sequence: { "order": [1, 3, 2] }
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
            'message' => $res['message'] ?? 'Deal stages reordered successfully.',
        ]);
    }
}
