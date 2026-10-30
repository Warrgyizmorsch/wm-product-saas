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

    /**
     * POST /api/inventory/material-requirements/{id}/release
     * Release Material Requirement for Dispatch.
     */
    public function release(Request $request, int $id): JsonResponse
    {
        $delivery = MaterialRequirement::with(['items.product', 'salesOrder'])->find($id);
        if (!$delivery) {
            return response()->json(['success' => false, 'message' => "Material requirement #{$id} not found."], 404);
        }

        $selectedItemIds = (array) $request->input('selected_items', []);
        $itemWarehouses = (array) $request->input('item_warehouses', []);
        $releaseQuantities = (array) $request->input('release_quantities', []);

        try {
            $result = $this->requirementService->releaseForDispatch($delivery, $selectedItemIds, $itemWarehouses, $releaseQuantities);
            return response()->json([
                'success' => true,
                'message' => "Material requirement {$delivery->requirement_number} successfully released for dispatch.",
                'data'    => $delivery->fresh(['items']),
                'details' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * PATCH /api/inventory/material-requirements/items/{itemId}/warehouse
     */
    public function updateWarehouse(Request $request, int $itemId): JsonResponse
    {
        $item = MaterialRequirementItem::find($itemId);
        if (!$item) {
            return response()->json(['success' => false, 'message' => "Item #{$itemId} not found."], 404);
        }

        $warehouseId = (int) $request->input('warehouse_id');
        $item->update(['warehouse_id' => $warehouseId]);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse updated for item successfully.',
            'available_qty' => \App\Domains\Inventory\Services\StockService::getAvailableStock($item->product_id, $warehouseId),
            'data' => $item->fresh(['warehouse', 'product']),
        ]);
    }

    /**
     * POST /api/inventory/material-requirements/items/{itemId}/reserve
     */
    public function reserveQty(Request $request, int $itemId): JsonResponse
    {
        $item = MaterialRequirementItem::with('materialRequirement')->find($itemId);
        if (!$item) {
            return response()->json(['success' => false, 'message' => "Item #{$itemId} not found."], 404);
        }

        $qtyToReserve = (float) $request->input('quantity_reserve', $request->input('quantity', 0));
        if ($qtyToReserve <= 0) {
            return response()->json(['success' => false, 'message' => 'Reserve quantity must be greater than 0.'], 422);
        }

        try {
            $this->requirementService->reserveQty($item, $qtyToReserve);
            return response()->json([
                'success' => true,
                'message' => "Successfully reserved {$qtyToReserve} unit(s).",
                'data'    => $item->fresh(['product', 'warehouse']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/inventory/material-requirements/items/{itemId}/indent
     */
    public function mockIndent(Request $request, int $itemId): JsonResponse
    {
        $item = MaterialRequirementItem::with('materialRequirement')->find($itemId);
        if (!$item) {
            return response()->json(['success' => false, 'message' => "Item #{$itemId} not found."], 404);
        }

        $qtyToRequest = (float) $request->input('quantity_request', $request->input('quantity', 0));
        if ($qtyToRequest <= 0) {
            return response()->json(['success' => false, 'message' => 'Quantity to request must be greater than 0.'], 422);
        }

        try {
            $this->requirementService->createPurchaseRequisition(
                $item,
                $qtyToRequest,
                $request->input('warehouse_id'),
                $request->input('notes'),
                $request->input('expected_date')
            );

            return response()->json([
                'success' => true,
                'message' => "Purchase Requisition successfully generated for {$qtyToRequest} unit(s).",
                'data'    => $item->fresh(['product', 'warehouse']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/inventory/material-requirements/items/{itemId}/mo
     */
    public function mockMo(Request $request, int $itemId): JsonResponse
    {
        $item = MaterialRequirementItem::with('materialRequirement')->find($itemId);
        if (!$item) {
            return response()->json(['success' => false, 'message' => "Item #{$itemId} not found."], 404);
        }

        $orderedQty = (float) ($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
        $shortageQty = max(0, $orderedQty - (float) $item->quantity_reserved);
        $qtyToMfg = (float) $request->input('quantity_mfg', $request->input('quantity', $shortageQty > 0 ? $shortageQty : $orderedQty));

        if ($qtyToMfg <= 0) {
            return response()->json(['success' => false, 'message' => 'Quantity to manufacture must be greater than 0.'], 422);
        }

        try {
            $this->requirementService->createProductionRequest($item, $qtyToMfg, $request->input('notes'));
            return response()->json([
                'success' => true,
                'message' => "Manufacturing Request submitted successfully for {$qtyToMfg} unit(s). Line status set to Waiting Production.",
                'data'    => $item->fresh(['product', 'warehouse']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/inventory/material-requirements/{id}/ship
     */
    public function ship(Request $request, int $id): JsonResponse
    {
        $delivery = MaterialRequirement::with('items.product')->find($id);
        if (!$delivery) {
            return response()->json(['success' => false, 'message' => "Material requirement #{$id} not found."], 404);
        }

        $allocations = (array) $request->input('allocations', []);
        foreach ($delivery->items as $item) {
            if (!$item->product_id || $item->product?->type === 'Service') continue;

            if ($item->product?->track_serial_number) {
                $serials = array_filter(array_map('trim', $allocations[$item->id]['serials'] ?? []));
                if (count($serials) != (int) $item->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please select exactly ' . (int)$item->quantity . ' serial number(s) for item: ' . $item->product->name
                    ], 422);
                }

                $validCount = \App\Domains\Inventory\Models\SerialNumber::query()
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->whereIn('status', ['Available', 'Reserved'])
                    ->whereIn('serial_number', $serials)
                    ->count();

                if ($validCount != count($serials)) {
                    return response()->json([
                        'success' => false,
                        'message' => "One or more selected serial numbers for product '{$item->product->name}' are invalid or already sold."
                    ], 422);
                }
            }
        }

        try {
            $this->requirementService->ship($delivery, $allocations);
            return response()->json([
                'success' => true,
                'message' => "Material Requirement {$delivery->requirement_number} shipped successfully. Inventory updated.",
                'data'    => $delivery->fresh(['items']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
