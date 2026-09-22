<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Inventory\Models\StockTransferItem;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Exports\StockTransferExport;
use Maatwebsite\Excel\Facades\Excel;

class StockTransferController extends Controller
{
    /**
     * Export Stock Transfers to Excel with custom columns and active query filters
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', StockTransfer::class);
        $tenantId = current_tenant_id() ?? tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        return Excel::download(
            new StockTransferExport($tenantId, $request->all()),
            'stock_transfers_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', StockTransfer::class);

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $query = StockTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'creator', 'items.product'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('warehouse_id')) {
            $whId = $request->warehouse_id;
            $query->where(function($q) use ($whId) {
                $q->where('from_warehouse_id', $whId)
                  ->orWhere('to_warehouse_id', $whId);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('transfer_number', 'LIKE', "%{$search}%");
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        if (in_array($sortBy, ['transfer_number', 'transfer_date', 'status', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $transfers = $query->paginate(15);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.inventory.transfers.index', compact('transfers', 'warehouses'));
    }

    public function create()
    {
        $this->authorize('create', StockTransfer::class);

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $products = Product::where('tenant_id', $tenantId)->sellable()->get();

        return view('modules.inventory.transfers.create', compact('warehouses', 'products'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', StockTransfer::class);

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $validated = $request->validate([
            'from_warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId),
                'different:to_warehouse_id',
            ],
            'to_warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId),
            ],
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.quantity' => 'required|numeric|gt:0',
        ]);

        try {
            DB::transaction(function () use ($validated, $tenantId) {
                // Stock Availability Verification against From Warehouse
                foreach ($validated['items'] as $item) {
                    $stock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                        ->where('product_id', $item['product_id'])
                        ->where('warehouse_id', $validated['from_warehouse_id'])
                        ->first();

                    $reserved = \App\Domains\Inventory\Models\StockReservation::where('tenant_id', $tenantId)
                        ->where('product_id', $item['product_id'])
                        ->where('warehouse_id', $validated['from_warehouse_id'])
                        ->where('status', 'Active')
                        ->sum('reserved_qty');

                    $physicalQty = (float)($stock?->quantity ?? 0);
                    $availableQty = max(0, $physicalQty - (float)$reserved);

                    if ((float)$item['quantity'] > $availableQty) {
                        $prod = Product::where('tenant_id', $tenantId)->find($item['product_id']);
                        $name = $prod?->name ?? 'Item';
                        throw new \Exception("Insufficient available stock for '{$name}'. Net Available: {$availableQty}, Requested: {$item['quantity']}.");
                    }
                }

                $transferNumber = 'TRF-' . strtoupper(uniqid());

                $transfer = StockTransfer::create([
                    'tenant_id' => $tenantId,
                    'transfer_number' => $transferNumber,
                    'from_warehouse_id' => $validated['from_warehouse_id'],
                    'to_warehouse_id' => $validated['to_warehouse_id'],
                    'transfer_date' => $validated['transfer_date'],
                    'status' => 'Draft',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                foreach ($validated['items'] as $item) {
                    StockTransferItem::create([
                        'stock_transfer_id' => $transfer->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'received_quantity' => 0,
                        'batch_id' => $item['batch_id'] ?? null,
                        'serial_numbers' => !empty($item['serial_numbers']) ? explode(',', $item['serial_numbers']) : null,
                    ]);
                }
            });
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('inventory.transfers.index')->with('success', 'Stock Transfer Created successfully.');
    }

    public function show(StockTransfer $transfer)
    {
        $this->authorize('view', $transfer);

        $transfer->load(['fromWarehouse', 'toWarehouse', 'creator', 'approver', 'receiver', 'items.product', 'items.batch']);
        return view('modules.inventory.transfers.show', compact('transfer'));
    }

    public function dispatch(StockTransfer $transfer)
    {
        $this->authorize('dispatch', $transfer);

        if ($transfer->status !== 'Draft' && $transfer->status !== 'Pending') {
            return back()->with('error', 'Only Draft or Pending transfers can be dispatched.');
        }

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        DB::transaction(function () use ($transfer, $tenantId) {
            foreach ($transfer->items as $item) {
                // Record stock outflow from source warehouse
                StockService::recordOutflow(
                    tenantId: $tenantId,
                    productId: $item->product_id,
                    warehouseId: $transfer->from_warehouse_id,
                    quantity: (float)$item->quantity,
                    referenceType: 'StockTransfer',
                    referenceId: $transfer->id,
                    serialNumbers: $item->serial_numbers ?? []
                );
            }

            $transfer->update([
                'status' => 'In-Transit',
                'approved_by' => Auth::id(),
            ]);
        });

        \App\Domains\Platform\Services\NotificationRuleService::trigger('inventory.transfer.dispatched', [
            'transfer_no' => $transfer->transfer_number,
            'from_warehouse' => $transfer->fromWarehouse?->name ?? 'Origin Warehouse',
            'to_warehouse' => $transfer->toWarehouse?->name ?? 'Destination Warehouse',
            'items_count' => (string) $transfer->items()->count(),
        ], $transfer->tenant_id ?? current_tenant_id() ?? 1, Auth::id());

        return back()->with('success', 'Stock Transfer dispatched and items are now In-Transit.');
    }

    public function receive(StockTransfer $transfer)
    {
        $this->authorize('receive', $transfer);

        if ($transfer->status !== 'In-Transit') {
            return back()->with('error', 'Only In-Transit transfers can be received.');
        }

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        DB::transaction(function () use ($transfer, $tenantId) {
            foreach ($transfer->items as $item) {
                $outTx = StockTransaction::where('tenant_id', $tenantId)
                    ->where('reference_type', 'StockTransfer')
                    ->where('reference_id', $transfer->id)
                    ->where('product_id', $item->product_id)
                    ->where('type', 'OUT')
                    ->first();

                $product = Product::where('tenant_id', $tenantId)->find($item->product_id);
                $unitCost = $outTx ? (float)$outTx->unit_cost : (float)($product ? $product->cost_price : 0);

                // Record stock inflow into destination warehouse with cost from dispatch
                StockService::recordInflow(
                    tenantId: $tenantId,
                    productId: $item->product_id,
                    warehouseId: $transfer->to_warehouse_id,
                    quantity: (float)$item->quantity,
                    unitCost: $unitCost,
                    referenceType: 'StockTransfer',
                    referenceId: $transfer->id,
                    serialNumbers: $item->serial_numbers ?? []
                );

                $item->update(['received_quantity' => $item->quantity]);
            }

            $transfer->update([
                'status' => 'Completed',
                'received_by' => Auth::id(),
            ]);
        });

        \App\Domains\Platform\Services\NotificationRuleService::trigger('inventory.transfer.received', [
            'transfer_no' => $transfer->transfer_number,
            'from_warehouse' => $transfer->fromWarehouse?->name ?? 'Origin Warehouse',
            'to_warehouse' => $transfer->toWarehouse?->name ?? 'Destination Warehouse',
            'received_by' => Auth::user()?->name ?? 'Warehouse Staff',
        ], $transfer->tenant_id ?? $tenantId, Auth::id());

        return back()->with('success', 'Stock Transfer successfully received at destination warehouse.');
    }

    public function cancel(StockTransfer $transfer)
    {
        $this->authorize('cancel', $transfer);

        if ($transfer->status === 'Completed' || $transfer->status === 'In-Transit') {
            return back()->with('error', 'Cannot cancel an In-Transit or Completed transfer.');
        }

        $transfer->update(['status' => 'Cancelled']);
        return back()->with('success', 'Stock Transfer cancelled.');
    }
}
