<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\Warehouse;
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

        return view('modules.inventory.reports.low-stock', compact('products'));
    }

    public function valuationReport(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $query = ProductWarehouseStock::query()
            ->with(['product', 'warehouse'])
            ->where('tenant_id', $tenantId)
            ->where('quantity', '>', 0);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        $totalValuation = (float)(clone $query)->get()->sum(fn($s) => (float)$s->quantity * (float)$s->unit_cost);
        $stocks = $query->paginate(15)->withQueryString();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.inventory.reports.valuation', compact('stocks', 'totalValuation', 'warehouses'));
    }
}
