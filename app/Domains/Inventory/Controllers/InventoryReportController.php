<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class InventoryReportController extends Controller
{
    public function lowStockReport(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $query = Product::query()
            ->with('warehouseStocks.warehouse')
            ->where('tenant_id', $tenantId);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $filteredProducts = $query->sellable()->get()
            ->filter(function ($product) {
                $reorderPoint = (float)($product->reorder_point > 0 ? $product->reorder_point : 10);
                return (float)$product->total_stock <= $reorderPoint;
            })->values();

        $page = (int)$request->input('page', 1);
        $perPage = 15;
        $total = $filteredProducts->count();
        $items = $filteredProducts->slice(($page - 1) * $perPage, $perPage)->values();

        $products = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

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

        return view('modules.inventory.reports.low-stock', compact('products', 'existingPrItems'));
    }

    public function valuationReport(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $baseQuery = ProductWarehouseStock::query()
            ->with(['product', 'warehouse'])
            ->where('tenant_id', $tenantId)
            ->where('quantity', '>', 0);

        // Apply filters that define the scope (Warehouse & Search)
        if ($request->filled('warehouse_id')) {
            $baseQuery->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $baseQuery->whereHas('product', function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        // Compute aggregates dynamically based on the selected scope/filters
        $allStocks = (clone $baseQuery)->get();

        $totalValuation = 0.0;
        $fgValuation = 0.0;
        $rmValuation = 0.0;

        $totalSkus = $allStocks->count();
        $fgSkus = 0;
        $rmSkus = 0;

        $totalQty = 0.0;
        $fgQty = 0.0;
        $rmQty = 0.0;

        foreach ($allStocks as $stock) {
            $unitCost = (float)($stock->unit_cost > 0 ? $stock->unit_cost : ($stock->product->unit_cost ?? 0));
            $val = (float)$stock->quantity * $unitCost;
            $qty = (float)$stock->quantity;

            $prodType = strtolower($stock->product->type ?? '');
            $whType = strtolower($stock->warehouse->type ?? '');
            $whName = strtolower($stock->warehouse->name ?? '');

            $isFg = in_array($prodType, ['finished_good', 'finished_goods', 'fg']) 
                || str_contains($whName, 'finished goods') 
                || in_array($whType, ['finished_goods', 'fg']);

            $totalValuation += $val;
            $totalQty += $qty;

            if ($isFg) {
                $fgValuation += $val;
                $fgQty += $qty;
                $fgSkus++;
            } else {
                $rmValuation += $val;
                $rmQty += $qty;
                $rmSkus++;
            }
        }

        // Apply item_category filter for table list
        $query = clone $baseQuery;
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

        $stocks = $query->paginate(10)->withQueryString();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.inventory.reports.valuation', compact(
            'stocks', 
            'totalValuation', 
            'fgValuation', 
            'rmValuation', 
            'totalSkus', 
            'fgSkus', 
            'rmSkus', 
            'totalQty', 
            'fgQty', 
            'rmQty', 
            'warehouses'
        ));
    }

    public function exportValuationReport(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $query = ProductWarehouseStock::query()
            ->with(['product', 'warehouse'])
            ->where('tenant_id', $tenantId)
            ->where('quantity', '>', 0);

        // Apply filters (Warehouse & Search)
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        // Apply Category filter
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

        $categoryLabel = match($itemCategory) {
            'fg' => 'Finished Goods (FG)',
            'rm' => 'Raw Materials & Components',
            default => 'All Items',
        };

        $fileName = 'Stock_Valuation_Report_' . date('Y-m-d_H-i') . '.csv';

        return response()->streamDownload(function () use ($query, $warehouseName, $categoryLabel) {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM for Microsoft Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // Enterprise Report Header Block
            fputcsv($handle, ['STOCK ASSET VALUATION REPORT']);
            fputcsv($handle, ['Generated Date', date('d M Y, h:i A')]);
            fputcsv($handle, ['Warehouse Scope', $warehouseName]);
            fputcsv($handle, ['Category Scope', $categoryLabel]);
            fputcsv($handle, []); // Blank line

            // Column Headers
            fputcsv($handle, [
                'S.No',
                'Product Name',
                'SKU Code',
                'Warehouse Location',
                'On Hand Quantity',
                'Unit Cost Rate (INR)',
                'Total Asset Value (INR)',
            ]);

            $index = 1;
            $totalQty = 0.0;
            $totalValuation = 0.0;

            // Fetch ALL matching records without pagination
            $query->chunk(500, function ($stocks) use ($handle, &$index, &$totalQty, &$totalValuation) {
                foreach ($stocks as $stock) {
                    $unitCost = (float)($stock->unit_cost > 0 ? $stock->unit_cost : ($stock->product->unit_cost ?? 0));
                    $val = (float)$stock->quantity * $unitCost;

                    $totalQty += (float)$stock->quantity;
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

            // Summary Totals Footer Row
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
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function createPrFromLowStock(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $itemsData = null;
        if ($request->has('items') && is_array($request->input('items'))) {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:0.0001',
            ]);
            $itemsData = collect($validated['items'])->keyBy('product_id');
            $productIds = $itemsData->keys()->toArray();
        } else {
            $validated = $request->validate([
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'required|integer|exists:products,id',
            ]);
            $productIds = array_unique($validated['product_ids']);
        }

        $products = Product::where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->get();

        if ($products->isEmpty()) {
            return redirect()->back()->with('error', 'No valid low stock products selected.');
        }

        $pr = DB::transaction(function () use ($tenantId, $products, $itemsData) {
            $year = now()->format('Y');
            $prefix = "PR-{$year}-";
            $lastPr = PurchaseRequisition::where('tenant_id', $tenantId)
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();

            $nextNum = 1;
            if ($lastPr) {
                $lastNumStr = str_replace($prefix, '', $lastPr->requisition_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $requisitionNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $pr = PurchaseRequisition::create([
                'tenant_id'          => $tenantId,
                'requisition_number' => $requisitionNumber,
                'requisition_date'   => now()->toDateString(),
                'status'             => 'Draft',
                'source_type'        => 'direct',
                'notes'              => 'Auto-generated Draft PR from Low Stock Alert Report (' . $products->count() . ' items)',
                'requested_by'       => auth()->id() ?: 1,
            ]);

            foreach ($products as $product) {
                if ($itemsData && isset($itemsData[$product->id])) {
                    $qty = (float) $itemsData[$product->id]['quantity'];
                } else {
                    $reorderPoint = (float)($product->reorder_point > 0 ? $product->reorder_point : 10);
                    $qty = max(1.0, $reorderPoint - (float)$product->total_stock);
                }

                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $pr->id,
                    'tenant_id'               => $tenantId,
                    'product_id'              => $product->id,
                    'quantity'                => $qty,
                    'estimated_cost'          => (float)($product->cost_price ?? $product->purchase_price ?? 0),
                ]);
            }

            return $pr;
        });

        return redirect()->back()
            ->with('success', "Draft Purchase Requisition {$pr->requisition_number} created successfully for " . $products->count() . " item(s).");
    }
}
