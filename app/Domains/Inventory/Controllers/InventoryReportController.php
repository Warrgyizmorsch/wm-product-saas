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

        $filteredProducts = Product::query()
            ->with('warehouseStocks.warehouse')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('reorder_point')
            ->where('reorder_point', '>', 0)
            ->sellable()
            ->get()
            ->filter(function ($product) {
                return $product->total_stock <= $product->reorder_point;
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

        $totalValuation = (float)(clone $query)->get()->sum(fn($s) => (float)$s->quantity * (float)$s->unit_cost);
        $stocks = $query->paginate(15);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.inventory.reports.valuation', compact('stocks', 'totalValuation', 'warehouses'));
    }
}
