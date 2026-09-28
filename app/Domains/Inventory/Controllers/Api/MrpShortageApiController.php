<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Services\MrpShortageService;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class MrpShortageApiController extends Controller
{
    public function __construct(
        private readonly MrpShortageService $mrpShortageService,
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
     * GET /api/inventory/mrp-shortage/calculate
     * Calculates multi-level BOM explosion and net stock shortages.
     */
    public function calculate(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $selectedWarehouseId = $request->filled('warehouse_id') ? (int)$request->input('warehouse_id') : null;

        $demandInputs = [];

        if ($request->filled('items') && is_array($request->input('items'))) {
            foreach ($request->input('items') as $item) {
                if (!empty($item['product_id']) && !empty($item['quantity'])) {
                    $demandInputs[] = [
                        'product_id'   => (int)$item['product_id'],
                        'quantity'     => (float)$item['quantity'],
                        'warehouse_id' => !empty($item['warehouse_id']) ? (int)$item['warehouse_id'] : $selectedWarehouseId,
                        'source_ref'   => $item['source_ref'] ?? 'Manual Demand',
                    ];
                }
            }
        } elseif ($request->filled('product_id') && $request->filled('quantity')) {
            $demandInputs[] = [
                'product_id'   => (int)$request->input('product_id'),
                'quantity'     => (float)$request->input('quantity'),
                'warehouse_id' => $selectedWarehouseId,
                'source_ref'   => $request->input('source_ref', 'Manual Demand'),
            ];
        } else {
            // Automatically gather demand from all pending Material Requisitions (MRs)
            $pendingMrs = $this->mrpShortageService->getPendingMaterialRequirements($tenantId);
            foreach ($pendingMrs as $mr) {
                foreach ($mr->items as $item) {
                    if (!$item->product_id) continue;
                    $orderedQty = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
                    $demandInputs[] = [
                        'product_id'   => $item->product_id,
                        'quantity'     => $orderedQty,
                        'warehouse_id' => $selectedWarehouseId,
                        'source_ref'   => "MR #{$mr->requirement_number}",
                    ];
                }
            }
        }

        if (empty($demandInputs)) {
            return response()->json([
                'success' => true,
                'message' => 'No active demand inputs found for shortage calculation',
                'summary' => [
                    'total_demanded_items'    => 0,
                    'mfg_products_count'      => 0,
                    'subassemblies_count'     => 0,
                    'shortage_items_count'    => 0,
                    'estimated_pr_total_cost' => 0.0,
                ],
                'data' => [
                    'tree'         => [],
                    'consolidated' => [],
                ],
            ]);
        }

        $calculationResult = $this->mrpShortageService->calculateShortages(
            $demandInputs,
            $tenantId,
            $selectedWarehouseId
        );

        return response()->json([
            'success' => true,
            'summary' => $calculationResult['summary'] ?? [],
            'data'    => [
                'tree'         => $calculationResult['tree'] ?? [],
                'consolidated' => array_values($calculationResult['consolidated'] ?? []),
            ],
        ]);
    }

    /**
     * POST /api/inventory/mrp-shortage/generate-pr
     * Generates a consolidated Purchase Requisition from shortage items.
     */
    public function generatePr(Request $request): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $userId = auth()->id() ?: 1;

        $validator = Validator::make($request->all(), [
            'warehouse_id'         => ['nullable', 'integer'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'integer'],
            'items.*.quantity'     => ['nullable', 'numeric', 'min:0'],
            'items.*.shortage_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost'    => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $warehouseId = $validated['warehouse_id'] ?? null;
        if (empty($warehouseId)) {
            $defaultWh = Warehouse::where('tenant_id', $tenantId)->where('is_default', 1)->first();
            $warehouseId = $defaultWh ? $defaultWh->id : Warehouse::where('tenant_id', $tenantId)->value('id');
        }

        $itemsToProcure = [];
        foreach ($validated['items'] as $itemData) {
            $productId   = (int)$itemData['product_id'];
            $inputQty    = isset($itemData['quantity']) && $itemData['quantity'] !== '' ? (float)$itemData['quantity'] : null;
            $shortageQty = isset($itemData['shortage_qty']) ? (float)$itemData['shortage_qty'] : 0.0;
            $finalQty    = $inputQty !== null ? $inputQty : $shortageQty;

            if ($finalQty <= 0) {
                continue;
            }

            $itemsToProcure[] = [
                'product_id'   => $productId,
                'quantity'     => $finalQty,
                'unit_cost'    => (float)($itemData['unit_cost'] ?? 0.0),
                'warehouse_id' => $warehouseId,
            ];
        }

        if (empty($itemsToProcure)) {
            return response()->json([
                'success' => false,
                'message' => 'No items with positive shortage quantity provided.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $prPrefix = 'PR-' . date('Ymd') . '-';
            $latestPr = PurchaseRequisition::where('tenant_id', $tenantId)
                ->where('requisition_number', 'like', "{$prPrefix}%")
                ->orderByDesc('id')
                ->first();

            $nextSeq = 1;
            if ($latestPr && preg_match('/-(\d+)$/', $latestPr->requisition_number, $matches)) {
                $nextSeq = ((int)$matches[1]) + 1;
            }
            $prNumber = $prPrefix . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);

            $notes = trim(($validated['notes'] ?? '') . "\n[Automated MRP Net Shortage Planning Generator via REST API]");

            $pr = PurchaseRequisition::create([
                'tenant_id'          => $tenantId,
                'company_id'         => $companyId,
                'branch_id'          => $branchId,
                'requisition_number' => $prNumber,
                'status'             => 'Draft',
                'requested_by'       => $userId,
                'requisition_date'   => now()->toDateString(),
                'expected_date'      => now()->addDays(7)->toDateString(),
                'notes'              => trim($notes),
            ]);

            foreach ($itemsToProcure as $item) {
                $product = Product::find($item['product_id']);
                $unitCost = $item['unit_cost'] > 0 ? $item['unit_cost'] : ($product?->cost_price ?: $product?->unit_cost ?: 0.0);

                PurchaseRequisitionItem::create([
                    'tenant_id'               => $tenantId,
                    'company_id'              => $companyId,
                    'branch_id'               => $branchId,
                    'purchase_requisition_id' => $pr->id,
                    'product_id'              => $item['product_id'],
                    'warehouse_id'            => $item['warehouse_id'] ?? $warehouseId,
                    'quantity'                => $item['quantity'],
                    'estimated_cost'          => $item['quantity'] * $unitCost,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Purchase Requisition {$pr->requisition_number} generated successfully with " . count($itemsToProcure) . " item(s).",
                'data'    => $pr->load(['items.product']),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Purchase Requisition: ' . $e->getMessage(),
            ], 500);
        }
    }
}
