<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Product;
use App\Exports\StockTransactionExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class StockTransactionController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $query = StockTransaction::query()
            ->with(['product', 'warehouse', 'batch', 'incomingSerials', 'outgoingSerials'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
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

        // Summary calculations
        $totalInQty = (float)(clone $query)->where('type', 'IN')->sum('quantity');
        $totalInValue = (float)(clone $query)->where('type', 'IN')->sum('total_value');
        $totalOutQty = (float)(clone $query)->where('type', 'OUT')->sum('quantity');
        $totalOutValue = (float)(clone $query)->where('type', 'OUT')->sum('total_value');
        $netQty = $totalInQty - $totalOutQty;
        $totalTransactionsCount = (clone $query)->count();

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'quantity', 'total_value'])) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $transactions = $query->paginate(10)->withQueryString();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $products = Product::where('tenant_id', $tenantId)->sellable()->get();

        return view('modules.inventory.transactions.index', compact(
            'transactions',
            'warehouses',
            'products',
            'totalInQty',
            'totalInValue',
            'totalOutQty',
            'totalOutValue',
            'netQty',
            'totalTransactionsCount',
            'itemCategory'
        ));
    }

    /**
     * Export Stock Movement Ledger to Excel with custom columns and active query filters
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', StockTransaction::class);
        $tenantId = current_tenant_id() ?? tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        return Excel::download(
            new StockTransactionExport($tenantId, $request->all()),
            'stock_ledger_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
