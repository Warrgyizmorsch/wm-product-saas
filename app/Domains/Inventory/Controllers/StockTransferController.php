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
            $query->where(function ($q) use ($whId) {
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
            'items.*.serial_numbers' => 'nullable|string',
            'items.*.batch_id' => 'nullable|integer',
        ]);

        try {
            $transfer = DB::transaction(function () use ($validated, $tenantId) {
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

                    $physicalQty = (float) ($stock?->quantity ?? 0);
                    $availableQty = max(0, $physicalQty - (float) $reserved);

                    if ((float) $item['quantity'] > $availableQty) {
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
                    $rawSerials = $item['serial_numbers'] ?? null;
                    $serialArray = null;
                    if (!empty($rawSerials)) {
                        $parts = is_array($rawSerials) ? $rawSerials : explode(',', (string) $rawSerials);
                        $resolved = [];
                        foreach ($parts as $p) {
                            $cleanSn = trim((string) $p);
                            if ($cleanSn === '')
                                continue;

                            // Resolve partial or full serial number matching warehouse
                            $matchedSn = \App\Domains\Inventory\Models\SerialNumber::where('tenant_id', $tenantId)
                                ->where('product_id', $item['product_id'])
                                ->where('warehouse_id', $validated['from_warehouse_id'])
                                ->where(function ($q) use ($cleanSn) {
                                    $q->where('serial_number', $cleanSn)
                                        ->orWhere('serial_number', 'like', "%-{$cleanSn}")
                                        ->orWhere('serial_number', 'like', "%{$cleanSn}");
                                })
                                ->value('serial_number');

                            $resolved[] = $matchedSn ?: $cleanSn;
                        }
                        $serialArray = !empty($resolved) ? $resolved : null;
                    }

                    $transferItem = StockTransferItem::create([
                        'stock_transfer_id' => $transfer->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'received_quantity' => 0,
                        'batch_id' => $item['batch_id'] ?? null,
                        'serial_numbers' => $serialArray,
                    ]);

                    // Reserve stock in source warehouse for this transfer
                    \App\Domains\Inventory\Services\StockService::reserveStock(
                        tenantId: $tenantId,
                        productId: (int) $item['product_id'],
                        warehouseId: (int) $validated['from_warehouse_id'],
                        qty: (float) $item['quantity'],
                        referenceType: 'StockTransfer',
                        referenceId: $transfer->id,
                        referenceItemId: $transferItem->id,
                        selectedSerials: $serialArray ?? []
                    );
                }

                return $transfer;
            });

            \App\Domains\Inventory\Services\InventoryNotificationService::notifyTransferCreated($transfer);
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

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        return DB::transaction(function () use ($transfer, $tenantId) {
            $lockedTransfer = StockTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status !== 'Draft' && $lockedTransfer->status !== 'Pending') {
                return back()->with('error', 'Only Draft or Pending transfers can be dispatched.');
            }

            foreach ($lockedTransfer->items as $item) {
                // Record stock outflow from source warehouse
                StockService::recordOutflow(
                    tenantId: $tenantId,
                    productId: $item->product_id,
                    warehouseId: $lockedTransfer->from_warehouse_id,
                    quantity: (float) $item->quantity,
                    referenceType: 'StockTransfer',
                    referenceId: $lockedTransfer->id,
                    serialNumbers: $item->serial_numbers ?? []
                );
            }

            $lockedTransfer->update([
                'status' => 'In-Transit',
                'approved_by' => Auth::id(),
            ]);

            \App\Domains\Inventory\Services\InventoryNotificationService::notifyTransferDispatched($lockedTransfer);

            return back()->with('success', 'Stock Transfer dispatched and items are now In-Transit.');
        }, 3);
    }

    public function receive(StockTransfer $transfer)
    {
        $this->authorize('receive', $transfer);

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        return DB::transaction(function () use ($transfer, $tenantId) {
            $lockedTransfer = StockTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status !== 'In-Transit') {
                return back()->with('error', 'Only In-Transit transfers can be received.');
            }

            foreach ($lockedTransfer->items as $item) {
                $outTx = StockTransaction::where('tenant_id', $tenantId)
                    ->where('reference_type', 'StockTransfer')
                    ->where('reference_id', $lockedTransfer->id)
                    ->where('product_id', $item->product_id)
                    ->where('type', 'OUT')
                    ->first();

                $product = Product::where('tenant_id', $tenantId)->find($item->product_id);
                $unitCost = $outTx ? (float) $outTx->unit_cost : (float) ($product ? $product->cost_price : 0);

                $batch = $item->batch;
                $batchNumber = $batch ? $batch->batch_number : null;
                $mfgDate = $batch && $batch->manufacturing_date ? $batch->manufacturing_date->format('Y-m-d') : null;
                $expiryDate = $batch && $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : null;

                // Record stock inflow into destination warehouse with cost from dispatch
                StockService::recordInflow(
                    tenantId: $tenantId,
                    productId: $item->product_id,
                    warehouseId: $lockedTransfer->to_warehouse_id,
                    quantity: (float) $item->quantity,
                    unitCost: $unitCost,
                    referenceType: 'StockTransfer',
                    referenceId: $lockedTransfer->id,
                    batchNumber: $batchNumber,
                    serialNumbers: $item->serial_numbers ?? [],
                    mfgDate: $mfgDate,
                    expiryDate: $expiryDate
                );

                $item->update(['received_quantity' => $item->quantity]);
            }

            $lockedTransfer->update([
                'status' => 'Completed',
                'received_by' => Auth::id(),
            ]);

            \App\Domains\Inventory\Services\InventoryNotificationService::notifyTransferReceived($lockedTransfer);

            return back()->with('success', 'Stock Transfer successfully received at destination warehouse.');
        }, 3);
    }

    public function cancel(StockTransfer $transfer)
    {
        $this->authorize('cancel', $transfer);

        if ($transfer->status === 'Completed') {
            return back()->with('error', 'Cannot cancel a Completed transfer.');
        }

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        DB::transaction(function () use ($transfer, $tenantId) {
            if ($transfer->status === 'In-Transit') {
                // Return in-transit stock back to origin warehouse
                foreach ($transfer->items as $item) {
                    $outTx = StockTransaction::where('tenant_id', $tenantId)
                        ->where('reference_type', 'StockTransfer')
                        ->where('reference_id', $transfer->id)
                        ->where('product_id', $item->product_id)
                        ->where('type', 'OUT')
                        ->first();

                    $product = Product::where('tenant_id', $tenantId)->find($item->product_id);
                    $unitCost = $outTx ? (float) $outTx->unit_cost : (float) ($product ? $product->cost_price : 0);
                    $batch = $item->batch;
                    $batchNumber = $batch ? $batch->batch_number : null;
                    $mfgDate = $batch && $batch->manufacturing_date ? $batch->manufacturing_date->format('Y-m-d') : null;
                    $expiryDate = $batch && $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : null;

                    \App\Domains\Inventory\Services\StockService::recordInflow(
                        tenantId: $tenantId,
                        productId: (int) $item->product_id,
                        warehouseId: (int) $transfer->from_warehouse_id,
                        quantity: (float) $item->quantity,
                        unitCost: $unitCost,
                        referenceType: 'StockTransferReturn',
                        referenceId: $transfer->id,
                        batchNumber: $batchNumber,
                        serialNumbers: $item->serial_numbers ?? [],
                        mfgDate: $mfgDate,
                        expiryDate: $expiryDate
                    );
                }
            } else {
                foreach ($transfer->items as $item) {
                    \App\Domains\Inventory\Services\StockService::releaseStock(
                        tenantId: $tenantId,
                        productId: (int) $item->product_id,
                        warehouseId: (int) $transfer->from_warehouse_id,
                        qty: (float) $item->quantity,
                        referenceType: 'StockTransfer',
                        referenceId: $transfer->id,
                        referenceItemId: $item->id
                    );
                }
            }

            $transfer->update(['status' => 'Cancelled']);
        });

        \App\Domains\Inventory\Services\InventoryNotificationService::notifyTransferCancelled($transfer);

        return back()->with('success', 'Stock Transfer cancelled and stock returned/released.');
    }

    public function destroy(StockTransfer $transfer)
    {
        $this->authorize('delete', $transfer);

        if ($transfer->status !== 'Draft') {
            return back()->with('error', 'Cannot delete a transfer that has already been dispatched or completed.');
        }

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        DB::transaction(function () use ($transfer, $tenantId) {
            foreach ($transfer->items as $item) {
                // Release any active reservation
                \App\Domains\Inventory\Services\StockService::releaseStock(
                    tenantId: $tenantId,
                    productId: (int)$item->product_id,
                    warehouseId: (int)$transfer->from_warehouse_id,
                    qty: (float)$item->quantity,
                    referenceType: 'StockTransfer',
                    referenceId: $transfer->id,
                    referenceItemId: $item->id
                );
            }

            $transfer->items()->delete();
            $transfer->delete();
        });

        return redirect()->route('inventory.transfers.index')->with('success', 'Stock Transfer deleted successfully.');
    }
}
