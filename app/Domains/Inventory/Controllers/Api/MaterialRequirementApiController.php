<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use App\Domains\Sales\Repositories\MaterialRequirementRepository;
use App\Domains\Sales\Services\MaterialRequirementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MaterialRequirementApiController extends Controller
{
    public function __construct(
        private readonly MaterialRequirementRepository $requirementRepo,
        private readonly MaterialRequirementService $requirementService,
    ) {}

    /**
     * GET /api/inventory/material-requirements
     * List Material Requirements / Store delivery slips with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'status', 'date_from', 'date_to', 'sort_by', 'sort_order'
        ]);

        $perPage = min((int)$request->input('per_page', 15), 100);
        $requirements = $this->requirementRepo->getPaginatedRequirements($filters, $perPage);

        return response()->json([
            'success' => true,
            'data'    => $requirements->items(),
            'meta'    => [
                'current_page' => $requirements->currentPage(),
                'last_page'    => $requirements->lastPage(),
                'per_page'     => $requirements->perPage(),
                'total'        => $requirements->total(),
            ],
        ]);
    }

    /**
     * GET /api/inventory/material-requirements/{id}
     */
    public function show(int $id): JsonResponse
    {
        $requirement = MaterialRequirement::with(['salesOrder.customer', 'items.product', 'items.warehouse'])->find($id);

        if (!$requirement) {
            return response()->json(['success' => false, 'message' => "Material requirement #{$id} not found."], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $requirement,
        ]);
    }

    /**
     * POST /api/inventory/material-requirements
     * Create Store Material Requirement from Sales Order.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sales_order_id'       => ['required', 'exists:sales_orders,id'],
            'requirement_number'   => ['nullable', 'string', 'max:255'],
            'requirement_date'     => ['nullable', 'date'],
            'delivery_date'        => ['nullable', 'date'],
            'notes'                => ['nullable', 'string'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'exists:products,id'],
            'items.*.warehouse_id' => ['required', 'exists:warehouses,id'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.01'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (empty($validated['requirement_number'])) {
            $validated['requirement_number'] = $this->requirementService->getNextRequirementNumber();
        }
        if (empty($validated['requirement_date'])) {
            $validated['requirement_date'] = now()->toDateString();
        }

        $requirement = $this->requirementService->createRequirement($validated, $validated['items']);

        return response()->json([
            'success' => true,
            'message' => 'Material requirement created successfully in Store/Inventory.',
            'data'    => $requirement->load(['salesOrder', 'items.product']),
        ], 201);
    }

    /**
     * POST /api/inventory/material-requirements/{id}/pick
     */
    public function startPicking(int $id): JsonResponse
    {
        $delivery = MaterialRequirement::find($id);
        if (!$delivery) {
            return response()->json(['success' => false, 'message' => "Material requirement #{$id} not found."], 404);
        }

        $this->requirementService->startPicking($delivery);

        return response()->json([
            'success' => true,
            'message' => 'Picking process started. Status updated to Picked.',
            'data'    => $delivery->fresh(),
        ]);
    }

    /**
     * POST /api/inventory/material-requirements/{id}/pack
     */
    public function pack(int $id): JsonResponse
    {
        $delivery = MaterialRequirement::find($id);
        if (!$delivery) {
            return response()->json(['success' => false, 'message' => "Material requirement #{$id} not found."], 404);
        }

        $this->requirementService->pack($delivery);

        return response()->json([
            'success' => true,
            'message' => 'Package packed successfully. Status updated to Packed.',
            'data'    => $delivery->fresh(),
        ]);
    }

    /**
     * POST /api/inventory/material-requirements/{id}/dispatch
     */
    public function dispatch(Request $request, int $id): JsonResponse
    {
        $delivery = MaterialRequirement::with('items.product', 'salesOrder')->find($id);
        if (!$delivery) {
            return response()->json(['success' => false, 'message' => "Material requirement #{$id} not found."], 404);
        }

        $validated = $request->validate([
            'carrier'         => 'nullable|string|max:255',
            'tracking_number' => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        $this->requirementService->dispatch($delivery, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Order dispatched from Store successfully. Stock ledger entries recorded.',
            'data'    => $delivery->fresh(),
        ]);
    }

    /**
     * POST /api/inventory/material-requirements/{id}/deliver
     */
    public function deliver(int $id): JsonResponse
    {
        $delivery = MaterialRequirement::with('items', 'salesOrder')->find($id);
        if (!$delivery) {
            return response()->json(['success' => false, 'message' => "Material requirement #{$id} not found."], 404);
        }

        $this->requirementService->deliver($delivery);

        return response()->json([
            'success' => true,
            'message' => 'Order marked as delivered successfully.',
            'data'    => $delivery->fresh(),
        ]);
    }

    /**
     * POST /api/inventory/material-requirements/{id}/cancel
     */
    public function cancel(int $id): JsonResponse
    {
        $delivery = MaterialRequirement::find($id);
        if (!$delivery) {
            return response()->json(['success' => false, 'message' => "Material requirement #{$id} not found."], 404);
        }

        $this->requirementService->cancel($delivery);

        return response()->json([
            'success' => true,
            'message' => 'Material requirement cancelled successfully.',
            'data'    => $delivery->fresh(),
        ]);
    }
}
