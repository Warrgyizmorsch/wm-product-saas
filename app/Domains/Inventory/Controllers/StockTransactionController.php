<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Product;
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

    public function export(Request $request)
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

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'quantity', 'total_value'])) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $fileName = 'Stock_Ledger_Export_' . date('Y-m-d_H-i') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM for Microsoft Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // Enterprise Header
            fputcsv($handle, ['STOCK MOVEMENT LEDGER REPORT']);
            fputcsv($handle, ['Export Date', date('d M Y, h:i A')]);
            fputcsv($handle, []);

            // Column Headers
            fputcsv($handle, [
                'S.No',
                'Date & Time',
                'Product Name',
                'SKU Code',
                'Batch Number',
                'Expiry Date',
                'Serial Numbers',
                'Warehouse Location',
                'Movement Type',
                'Movement Qty',
                'Running Balance Qty',
                'Unit Cost (INR)',
                'Total Value (INR)',
                'Reference Document',
            ]);

            $index = 1;
            $totalInQty = 0.0;
            $totalOutQty = 0.0;
            $totalVal = 0.0;

            $query->chunk(500, function ($transactions) use ($handle, &$index, &$totalInQty, &$totalOutQty, &$totalVal) {
                foreach ($transactions as $trx) {
                    if ($trx->type === 'IN') {
                        $totalInQty += (float)$trx->quantity;
                    } else {
                        $totalOutQty += (float)$trx->quantity;
                    }
                    $totalVal += (float)$trx->total_value;

                    $batchNo = $trx->batch->batch_number ?? '-';
                    $expDate = $trx->batch->expiry_date ? $trx->batch->expiry_date->format('Y-m-d') : '-';
                    $serialsList = ($trx->type === 'IN' ? $trx->incomingSerials : $trx->outgoingSerials)->pluck('serial_number')->join(', ') ?: '-';

                    fputcsv($handle, [
                        $index++,
                        \Carbon\Carbon::parse($trx->created_at)->format('d M Y, h:i A'),
                        $trx->product->name ?? 'N/A',
                        $trx->product->sku ?? '-',
                        $batchNo,
                        $expDate,
                        $serialsList,
                        $trx->warehouse->name ?? 'N/A',
                        $trx->type,
                        number_format($trx->quantity, 2, '.', ''),
                        number_format($trx->balance_qty ?? 0, 2, '.', ''),
                        number_format($trx->unit_cost, 2, '.', ''),
                        number_format($trx->total_value, 2, '.', ''),
                        $trx->document_number,
                    ]);
                }
            });

            fputcsv($handle, []);
            fputcsv($handle, [
                '',
                'SUMMARY TOTALS',
                '',
                '',
                '',
                'IN: ' . number_format($totalInQty, 2) . ' | OUT: ' . number_format($totalOutQty, 2),
                'Net: ' . number_format($totalInQty - $totalOutQty, 2),
                '',
                '',
                number_format($totalVal, 2, '.', ''),
                '',
            ]);

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
