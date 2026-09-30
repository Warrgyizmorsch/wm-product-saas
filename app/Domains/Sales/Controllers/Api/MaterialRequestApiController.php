<?php

namespace App\Domains\Sales\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Sales\Repositories\MaterialRequestRepository;
use App\Domains\Sales\Services\MaterialRequestService;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionRequisitionSlipItem;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Exports\MaterialRequestExport;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

class MaterialRequestApiController extends Controller
{
    public function __construct(
        private readonly MaterialRequestRepository $requestRepo,
        private readonly MaterialRequestService $requestService,
    ) {}

    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/sales/material-requests
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $perPage    = min((int)$request->input('per_page', 15), 100);
        $slips      = $this->requestRepo->getPaginatedSlips($tenantId, $request->all(), $perPage);

        return response()->json([
            'success' => true,
            'data'    => $slips->items(),
            'meta'    => [
                'current_page' => $slips->currentPage(),
                'last_page'    => $slips->lastPage(),
                'per_page'     => $slips->perPage(),
                'total'        => $slips->total(),
            ],
        ]);
    }

    /**
     * GET /api/sales/material-requests/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $slip = ProductionRequisitionSlip::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['order.product', 'items.product', 'items.uom', 'items.warehouse'])
            ->find($id);

        if (!$slip) {
            return response()->json([
                'success' => false,
                'message' => 'Material Request Slip not found',
            ], 404);
        }

        $items = $slip->items->groupBy('product_id')->map(function ($itemsForProduct) use ($tenantId) {
            $first = $itemsForProduct->first();
            
            $totalPlanned  = (float) $itemsForProduct->sum('quantity_planned');
            $totalReserved = (float) $itemsForProduct->sum('quantity_reserved');
            $totalIssued   = (float) $itemsForProduct->sum('quantity_issued');
            
            $warehouseId = $first->warehouse_id ?? Warehouse::where('tenant_id', $tenantId)->orderByDesc('is_default')->first()?->id;
            $availableStock = $warehouseId ? StockService::getAvailableStock($first->product_id, $warehouseId) : 0.0;

            $item = clone $first;
            $item->id = $first->id;
            $item->all_item_ids = $itemsForProduct->pluck('id')->toArray();
            $item->quantity_planned = $totalPlanned;
            $item->quantity_reserved = $totalReserved;
            $item->quantity_issued = $totalIssued;
            $item->available_stock = $availableStock;

            return $item;
        })->values();

        $warehouses = Warehouse::where('tenant_id', $tenantId)->get(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data'    => [
                'slip'       => $slip,
                'items'      => $items,
                'warehouses' => $warehouses,
            ],
        ]);
    }

    /**
     * POST /api/sales/material-requests/items/{itemId}/reserve
     */
    public function reserve(Request $request, int $itemId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity'     => 'required|numeric|min:0.0001',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        [$tenantId] = $this->resolveTenantContext();

        try {
            $reserved = $this->requestService->reserve(
                $tenantId,
                $itemId,
                (float) $request->input('quantity'),
                $request->input('warehouse_id')
            );

            return response()->json([
                'success' => true,
                'message' => "Reserved {$reserved} units successfully.",
                'data'    => ['reserved_quantity' => $reserved],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/sales/material-requests/items/{itemId}/issue
     */
    public function issue(Request $request, int $itemId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity'     => 'required|numeric|min:0.0001',
            'warehouse_id' => 'nullable|integer',
            'remarks'      => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        [$tenantId] = $this->resolveTenantContext();

        try {
            $issued = $this->requestService->issue(
                $tenantId,
                $itemId,
                (float) $request->input('quantity'),
                $request->input('warehouse_id'),
                $request->input('remarks')
            );

            return response()->json([
                'success' => true,
                'message' => "Issued {$issued} units successfully.",
                'data'    => ['issued_quantity' => $issued],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/sales/material-requests/items/{itemId}/create-pr
     */
    public function createPurchaseRequisition(Request $request, int $itemId): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        try {
            $item = ProductionRequisitionSlipItem::find($itemId);
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Material Request item not found',
                ], 404);
            }
            $allItemIds = ProductionRequisitionSlipItem::where('production_requisition_slip_id', $item->production_requisition_slip_id)
                ->where('product_id', $item->product_id)
                ->pluck('id')
                ->toArray();

            $pr = $this->requestService->createBulkPurchaseRequisition(
                $tenantId,
                $allItemIds,
                $request->input('warehouse_id'),
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => "Purchase Requisition {$pr->requisition_number} created successfully.",
                'data'    => $pr,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/sales/material-requests/{id}/bulk-action
     */
    public function bulkAction(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action_type'   => 'required|string|in:reserve,issue,indent',
            'warehouse_id'  => 'nullable|integer',
            'item_ids'      => 'required|array',
            'item_ids.*'    => 'string',
            'action_qtys'   => 'nullable|array',
            'remarks'       => 'nullable|string',
            'notes'         => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        [$tenantId]  = $this->resolveTenantContext();
        $actionType  = $request->input('action_type');
        $warehouseId = $request->input('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $itemIds     = $request->input('item_ids', []);
        $actionQtys  = $request->input('action_qtys', []);
        $remarks     = $request->input('remarks');
        $notes       = $request->input('notes');

        if ($actionType === 'indent') {
            try {
                $expandedItemIds = [];
                foreach ($itemIds as $idStr) {
                    foreach (explode(',', (string) $idStr) as $parsedId) {
                        if (is_numeric($parsedId) && (int)$parsedId > 0) {
                            $expandedItemIds[] = (int)$parsedId;
                        }
                    }
                }
                $expandedItemIds = array_unique($expandedItemIds);

                $pr = $this->requestService->createBulkPurchaseRequisition(
                    $tenantId,
                    $expandedItemIds,
                    $warehouseId,
                    $notes
                );

                return response()->json([
                    'success' => true,
                    'message' => "Purchase Requisition {$pr->requisition_number} created with " . count($expandedItemIds) . " item(s) successfully.",
                    'data'    => $pr,
                ]);
            } catch (Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        $processedCount = 0;
        $errors = [];

        foreach ($itemIds as $idKey) {
            $idStr = (string) $idKey;
            $firstId = (int) explode(',', $idStr)[0];

            if ($firstId <= 0) {
                continue;
            }

            $qty = 0.0;
            if (isset($actionQtys[$idStr])) {
                $qty = (float) $actionQtys[$idStr];
            } elseif (isset($actionQtys[$firstId])) {
                $qty = (float) $actionQtys[$firstId];
            }

            if ($qty <= 0) {
                continue;
            }

            try {
                if ($actionType === 'reserve') {
                    $this->requestService->reserve($tenantId, $firstId, $qty, $warehouseId);
                    $processedCount++;
                } elseif ($actionType === 'issue') {
                    $this->requestService->issue($tenantId, $firstId, $qty, $warehouseId, $remarks);
                    $processedCount++;
                }
            } catch (Exception $e) {
                $errors[] = "Item #{$firstId}: " . $e->getMessage();
            }
        }

        if ($processedCount > 0) {
            return response()->json([
                'success' => true,
                'message' => "Bulk '{$actionType}' processed for {$processedCount} item(s).",
                'errors'  => $errors,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No items were processed. ' . implode('; ', $errors),
        ], 422);
    }

    /**
     * GET /api/sales/material-requests/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return Excel::download(
            new MaterialRequestExport($tenantId, $request->all()),
            'material_requests_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
