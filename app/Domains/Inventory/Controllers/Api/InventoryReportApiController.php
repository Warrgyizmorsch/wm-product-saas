<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryReportApiController extends Controller
{
    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/inventory/reports/low-stock
     */
    public function lowStockReport(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = Product::query()
            ->with(['warehouseStocks.warehouse'])
            ->where('tenant_id', $tenantId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $filteredProducts = $query->sellable()->get()
            ->filter(function ($product) {
                $reorderPoint = (float)($product->reorder_point > 0 ? $product->reorder_point : 10);
                return (float)$product->total_stock <= $reorderPoint;
            })->values();

        $page    = (int)$request->input('page', 1);
        $perPage = min((int)$request->input('per_page', 15), 100);
        $total   = $filteredProducts->count();
        $items   = $filteredProducts->slice(($page - 1) * $perPage, $perPage)->values();

        $pageIds = $items->pluck('id')->toArray();

        $existingPrItems = PurchaseRequisitionItem::with('requisition')
            ->where('tenant_id', $tenantId)
            ->whereIn('product_id', $pageIds)
            ->whereHas('requisition', function ($q) {
                $q->whereIn('status', ['Draft', 'Approved'])
                  ->where('notes', 'like', '%Low Stock Alert Report%');
            })
            ->get()
            ->groupBy('product_id');

        $resultItems = $items->map(function ($p) use ($existingPrItems) {
            $pData = $p->toArray();
            $pData['reorder_point'] = (float)($p->reorder_point > 0 ? $p->reorder_point : 10);
            $pData['total_stock']   = (float)$p->total_stock;
            $pData['shortage_qty']  = max(1.0, $pData['reorder_point'] - $pData['total_stock']);
            $pData['has_active_pr'] = isset($existingPrItems[$p->id]);
            return $pData;
        });

        return response()->json([
            'success' => true,
            'data'    => $resultItems,
            'meta'    => [
                'current_page' => $page,
                'last_page'    => (int)ceil($total / max($perPage, 1)),
                'per_page'     => $perPage,
                'total'        => $total,
            ],
        ]);
    }

    /**
     * POST /api/inventory/reports/low-stock/create-pr
     */
    public function createPrFromLowStock(Request $request): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $itemsData = null;
        if ($request->has('items') && is_array($request->input('items'))) {
            $validator = Validator::make($request->all(), [
                'items'              => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', 'integer'],
                'items.*.quantity'   => ['required', 'numeric', 'min:0.0001'],
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
            }
            $itemsData  = collect($validator->validated()['items'])->keyBy('product_id');
            $productIds = $itemsData->keys()->toArray();
        } else {
            $validator = Validator::make($request->all(), [
                'product_ids'   => ['required', 'array', 'min:1'],
                'product_ids.*' => ['required', 'integer'],
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
            }
            $productIds = array_unique($validator->validated()['product_ids']);
        }

        $products = Product::where('tenant_id', $tenantId)->whereIn('id', $productIds)->get();

        if ($products->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid products found for selected IDs.',
            ], 422);
        }

        $pr = DB::transaction(function () use ($tenantId, $companyId, $branchId, $products, $itemsData) {
            $year    = now()->format('Y');
            $prefix  = "PR-{$year}-";
            $lastPr  = PurchaseRequisition::where('tenant_id', $tenantId)
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();

            $nextNum = 1;
            if ($lastPr) {
                $lastNumStr = str_replace($prefix, '', $lastPr->requisition_number);
                $nextNum    = ((int)$lastNumStr) + 1;
            }
            $requisitionNumber = $prefix . str_pad((string)$nextNum, 6, '0', STR_PAD_LEFT);

            $pr = PurchaseRequisition::create([
                'tenant_id'          => $tenantId,
                'company_id'         => $companyId,
                'branch_id'          => $branchId,
                'requisition_number' => $requisitionNumber,
                'requisition_date'   => now()->toDateString(),
                'status'             => 'Draft',
                'source_type'        => 'direct',
                'notes'              => 'Auto-generated Draft PR from Low Stock Alert Report (' . $products->count() . ' items)',
                'requested_by'       => auth()->id() ?: 1,
            ]);

            foreach ($products as $product) {
                if ($itemsData && isset($itemsData[$product->id])) {
                    $qty = (float)$itemsData[$product->id]['quantity'];
                } else {
                    $reorderPoint = (float)($product->reorder_point > 0 ? $product->reorder_point : 10);
                    $qty          = max(1.0, $reorderPoint - (float)$product->total_stock);
                }

                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $pr->id,
                    'tenant_id'               => $tenantId,
                    'company_id'              => $companyId,
                    'branch_id'               => $branchId,
                    'product_id'              => $product->id,
                    'quantity'                => $qty,
                    'estimated_cost'          => (float)($product->cost_price ?? $product->purchase_price ?? 0),
                ]);
            }

            return $pr->load('items.product');
        });

        return response()->json([
            'success' => true,
            'message' => "Draft Purchase Requisition {$pr->requisition_number} created successfully for {$products->count()} item(s).",
            'data'    => $pr,
        ], 201);
    }

    /**
     * GET /api/inventory/reports/valuation
     */
    public function valuationReport(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $baseQuery = ProductWarehouseStock::query()
            ->with(['product', 'warehouse'])
            ->where('tenant_id', $tenantId)
            ->where('quantity', '>', 0);

        if ($request->filled('warehouse_id')) {
            $baseQuery->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($search = $request->input('search')) {
            $baseQuery->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $allStocks = (clone $baseQuery)->get();

        $totalValuation = 0.0;
        $fgValuation    = 0.0;
        $rmValuation    = 0.0;
        $totalSkus      = $allStocks->count();
        $fgSkus         = 0;
        $rmSkus         = 0;
        $totalQty       = 0.0;
        $fgQty          = 0.0;
        $rmQty          = 0.0;

        foreach ($allStocks as $stock) {
            $unitCost = (float)($stock->unit_cost > 0 ? $stock->unit_cost : ($stock->product->unit_cost ?? 0));
            $val      = (float)$stock->quantity * $unitCost;
            $qty      = (float)$stock->quantity;

            $prodType = strtolower($stock->product->type ?? '');
            $whType   = strtolower($stock->warehouse->type ?? '');
            $whName   = strtolower($stock->warehouse->name ?? '');

            $isFg = in_array($prodType, ['finished_good', 'finished_goods', 'fg'])
                || str_contains($whName, 'finished goods')
                || in_array($whType, ['finished_goods', 'fg']);

            $totalValuation += $val;
            $totalQty       += $qty;

            if ($isFg) {
                $fgValuation += $val;
                $fgQty       += $qty;
                $fgSkus++;
            } else {
                $rmValuation += $val;
                $rmQty       += $qty;
                $rmSkus++;
            }
        }

        $query        = clone $baseQuery;
        $itemCategory = $request->input('item_category', 'all');

        if ($itemCategory === 'fg') {
            $query->where(function ($q) {
                $q->whereHas('product', function ($pq) {
                    $pq->whereIn('type', ['finished_good', 'finished_goods', 'fg', 'Finished Goods', 'Finished Good']);
                })->orWhereHas('warehouse', function ($wq) {
                    $wq->whereIn('type', ['finished_goods', 'fg'])->orWhere('name', 'like', '%Finished Goods%');
                });
            });
        } elseif ($itemCategory === 'rm') {
            $query->where(function ($q) {
                $q->whereHas('product', function ($pq) {
                    $pq->whereIn('type', ['raw_material', 'semi_finished', 'component', 'wip', 'consumable']);
                })->orWhereHas('warehouse', function ($wq) {
                    $wq->whereIn('type', ['raw_material', 'wip'])->orWhere('name', 'like', '%Raw Material%')->orWhere('name', 'like', '%WIP%');
                });
            })->whereDoesntHave('product', function ($pq) {
                $pq->whereIn('type', ['finished_good', 'finished_goods', 'fg', 'Finished Goods', 'Finished Good']);
            });
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $stocks  = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'summary' => [
                'total_valuation' => round($totalValuation, 2),
                'fg_valuation'    => round($fgValuation, 2),
                'rm_valuation'    => round($rmValuation, 2),
                'total_skus'      => $totalSkus,
                'fg_skus'         => $fgSkus,
                'rm_skus'         => $rmSkus,
                'total_quantity'  => round($totalQty, 2),
                'fg_quantity'     => round($fgQty, 2),
                'rm_quantity'     => round($rmQty, 2),
            ],
            'data'    => $stocks->items(),
            'meta'    => [
                'current_page' => $stocks->currentPage(),
                'last_page'    => $stocks->lastPage(),
                'per_page'     => $stocks->perPage(),
                'total'        => $stocks->total(),
            ],
        ]);
    }

    /**
     * GET /api/inventory/reports/valuation/export
     */
    public function exportValuationReport(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = ProductWarehouseStock::query()
            ->with(['product', 'warehouse'])
            ->where('tenant_id', $tenantId)
            ->where('quantity', '>', 0);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($search = $request->input('search')) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $itemCategory = $request->input('item_category', 'all');

        if ($itemCategory === 'fg') {
            $query->where(function ($q) {
                $q->whereHas('product', function ($pq) {
                    $pq->whereIn('type', ['finished_good', 'finished_goods', 'fg', 'Finished Goods', 'Finished Good']);
                })->orWhereHas('warehouse', function ($wq) {
                    $wq->whereIn('type', ['finished_goods', 'fg'])->orWhere('name', 'like', '%Finished Goods%');
                });
            });
        } elseif ($itemCategory === 'rm') {
            $query->where(function ($q) {
                $q->whereHas('product', function ($pq) {
                    $pq->whereIn('type', ['raw_material', 'semi_finished', 'component', 'wip', 'consumable']);
                })->orWhereHas('warehouse', function ($wq) {
                    $wq->whereIn('type', ['raw_material', 'wip'])->orWhere('name', 'like', '%Raw Material%')->orWhere('name', 'like', '%WIP%');
                });
            })->whereDoesntHave('product', function ($pq) {
                $pq->whereIn('type', ['finished_good', 'finished_goods', 'fg', 'Finished Goods', 'Finished Good']);
            });
        }

        $warehouseName = 'All Warehouses';
        if ($request->filled('warehouse_id')) {
            $wh = Warehouse::find($request->input('warehouse_id'));
            if ($wh) {
                $warehouseName = $wh->name;
            }
        }

        $categoryLabel = match ($itemCategory) {
            'fg'    => 'Finished Goods (FG)',
            'rm'    => 'Raw Materials & Components',
            default => 'All Items',
        };

        $fileName = 'Stock_Valuation_Report_' . date('Y-m-d_H-i') . '.csv';

        return response()->streamDownload(function () use ($query, $warehouseName, $categoryLabel) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['STOCK ASSET VALUATION REPORT']);
            fputcsv($handle, ['Generated Date', date('d M Y, h:i A')]);
            fputcsv($handle, ['Warehouse Scope', $warehouseName]);
            fputcsv($handle, ['Category Scope', $categoryLabel]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'S.No',
                'Product Name',
                'SKU Code',
                'Warehouse Location',
                'On Hand Quantity',
                'Unit Cost Rate',
                'Total Asset Value',
            ]);

            $index          = 1;
            $totalQty       = 0.0;
            $totalValuation = 0.0;

            $query->chunk(500, function ($stocks) use ($handle, &$index, &$totalQty, &$totalValuation) {
                foreach ($stocks as $stock) {
                    $unitCost = (float)($stock->unit_cost > 0 ? $stock->unit_cost : ($stock->product->unit_cost ?? 0));
                    $val      = (float)$stock->quantity * $unitCost;

                    $totalQty       += (float)$stock->quantity;
                    $totalValuation += $val;

                    fputcsv($handle, [
                        $index++,
                        $stock->product->name ?? 'N/A',
                        $stock->product->sku ?? '-',
                        $stock->warehouse->name ?? 'N/A',
                        number_format($stock->quantity, 2, '.', ''),
                        number_format($unitCost, 2, '.', ''),
                        number_format($val, 2, '.', ''),
                    ]);
                }
            });

            fputcsv($handle, []);
            fputcsv($handle, [
                '',
                'TOTAL VALUATION SUMMARY',
                '',
                '',
                number_format($totalQty, 2, '.', ''),
                '',
                number_format($totalValuation, 2, '.', ''),
            ]);

            fclose($handle);
        }, $fileName, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * GET /api/inventory/reports/expiry
     * Batches expiring soon / expired with risk asset valuation.
     */
    public function expiryReport(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $days = (int)$request->input('days', 30);
        $thresholdDate = now()->addDays($days)->toDateString();

        $baseQuery = \App\Domains\Inventory\Models\Batch::query()
            ->where('tenant_id', $tenantId)
            ->with(['product:id,name,sku,type,cost_price,selling_price', 'warehouse:id,name,code'])
            ->whereNotNull('expiry_date')
            ->where('available_qty', '>', 0);

        if ($warehouseId = $request->input('warehouse_id')) {
            $baseQuery->where('warehouse_id', $warehouseId);
        }

        if ($productId = $request->input('product_id')) {
            $baseQuery->where('product_id', $productId);
        }

        if ($search = $request->input('search')) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                         ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        $allBatches = (clone $baseQuery)->get();

        $totalBatches = $allBatches->count();
        $expiredCount = 0;
        $expiringSoonCount = 0;
        $totalRiskValue = 0.0;
        $todayStr = now()->toDateString();

        foreach ($allBatches as $batch) {
            $unitCost = (float)($batch->product?->cost_price ?? 0);
            $val = (float)$batch->available_qty * $unitCost;
            $expDate = $batch->expiry_date ? \Illuminate\Support\Carbon::parse($batch->expiry_date)->toDateString() : null;

            if ($expDate && $expDate < $todayStr) {
                $expiredCount++;
                $totalRiskValue += $val;
            } elseif ($expDate && $expDate <= $thresholdDate) {
                $expiringSoonCount++;
                $totalRiskValue += $val;
            }
        }

        $filterStatus = $request->input('status', 'all'); // 'expired', 'expiring_soon', 'all'
        $filteredQuery = clone $baseQuery;

        if ($filterStatus === 'expired') {
            $filteredQuery->where('expiry_date', '<', $todayStr);
        } elseif ($filterStatus === 'expiring_soon') {
            $filteredQuery->where('expiry_date', '>=', $todayStr)
                          ->where('expiry_date', '<=', $thresholdDate);
        } else {
            $filteredQuery->where('expiry_date', '<=', $thresholdDate);
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $batches = $filteredQuery->orderBy('expiry_date', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'summary' => [
                'days_threshold'       => $days,
                'total_tracked'        => $totalBatches,
                'expired_batches'      => $expiredCount,
                'expiring_soon_count'  => $expiringSoonCount,
                'total_risk_valuation' => round($totalRiskValue, 2),
            ],
            'data'    => $batches->items(),
            'meta'    => [
                'current_page' => $batches->currentPage(),
                'last_page'    => $batches->lastPage(),
                'per_page'     => $batches->perPage(),
                'total'        => $batches->total(),
            ],
        ]);
    }

    /**
     * GET /api/inventory/reports/expiry/export
     */
    public function exportExpiryReport(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        $days = (int)$request->input('days', 30);
        $thresholdDate = now()->addDays($days)->toDateString();
        $todayStr = now()->toDateString();

        $query = \App\Domains\Inventory\Models\Batch::query()
            ->where('tenant_id', $tenantId)
            ->with(['product', 'warehouse'])
            ->whereNotNull('expiry_date')
            ->where('available_qty', '>', 0)
            ->where('expiry_date', '<=', $thresholdDate)
            ->orderBy('expiry_date', 'asc');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        $fileName = 'Batch_Expiry_Report_' . date('Y-m-d_Hi') . '.csv';

        return response()->streamDownload(function () use ($query, $todayStr) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['BATCH EXPIRY RISK REPORT']);
            fputcsv($handle, ['Generated Date', date('d M Y, h:i A')]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'S.No',
                'Batch Number',
                'Product Name',
                'Product SKU',
                'Warehouse',
                'Available Qty',
                'Expiry Date',
                'Days Remaining',
                'Status',
                'Unit Cost',
                'Total Risk Value',
            ]);

            $index = 1;
            $totalQty = 0.0;
            $totalVal = 0.0;

            $query->chunk(500, function ($batches) use ($handle, $todayStr, &$index, &$totalQty, &$totalVal) {
                foreach ($batches as $b) {
                    $expDate = $b->expiry_date ? \Illuminate\Support\Carbon::parse($b->expiry_date) : null;
                    $daysRemaining = $expDate ? (int)now()->diffInDays($expDate, false) : 0;
                    $status = $daysRemaining < 0 ? 'EXPIRED' : ($daysRemaining <= 30 ? 'EXPIRING CRITICAL' : 'EXPIRING SOON');

                    $unitCost = (float)($b->product?->cost_price ?? 0);
                    $val = (float)$b->available_qty * $unitCost;

                    $totalQty += (float)$b->available_qty;
                    $totalVal += $val;

                    fputcsv($handle, [
                        $index++,
                        $b->batch_number,
                        $b->product?->name ?? 'N/A',
                        $b->product?->sku ?? '-',
                        $b->warehouse?->name ?? 'N/A',
                        number_format($b->available_qty, 2, '.', ''),
                        $expDate ? $expDate->format('d M Y') : 'N/A',
                        $daysRemaining,
                        $status,
                        number_format($unitCost, 2, '.', ''),
                        number_format($val, 2, '.', ''),
                    ]);
                }
            });

            fputcsv($handle, []);
            fputcsv($handle, [
                '',
                'TOTAL RISK SUMMARY',
                '',
                '',
                '',
                number_format($totalQty, 2, '.', ''),
                '',
                '',
                '',
                '',
                number_format($totalVal, 2, '.', ''),
            ]);

            fclose($handle);
        }, $fileName, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}

