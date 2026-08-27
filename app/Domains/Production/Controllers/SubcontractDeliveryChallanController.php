<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\DeliveryChallanItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubcontractDeliveryChallanController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $query = DeliveryChallan::where('tenant_id', $tenantId)
            ->with(['productionOrder', 'operation', 'vendor', 'warehouse', 'creator', 'items.warehouse', 'items.product']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('challan_number', 'like', "%{$search}%")
                  ->orWhere('vehicle_number', 'like', "%{$search}%")
                  ->orWhere('lr_number', 'like', "%{$search}%");
            });
        }

        $challans = $query->latest()->paginate(15);
        $statusCounts = [
            'total' => DeliveryChallan::where('tenant_id', $tenantId)->count(),
            'draft' => DeliveryChallan::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'dispatched' => DeliveryChallan::where('tenant_id', $tenantId)->where('status', 'dispatched')->count(),
            'completed' => DeliveryChallan::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
        ];

        return view('modules.production.subcontract.challans.index', compact('challans', 'statusCounts'));
    }

    public function create(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $order = null;
        $operation = null;
        $vendor = null;
        $prefilledItems = collect();

        if ($request->filled('production_order_id')) {
            $order = ProductionOrder::where('tenant_id', $tenantId)
                ->with(['product', 'bom.items.material', 'operations.vendor'])
                ->find($request->production_order_id);
        }

        if ($request->filled('operation_id')) {
            $operation = ProductionOrderOperation::where('tenant_id', $tenantId)
                ->with('vendor')
                ->find($request->operation_id);

            if ($operation) {
                if ($operation->vendor_id) {
                    $vendor = $operation->vendor;
                }

                // Prevent opening duplicate draft gate passes if one already exists
                $existingDraft = DeliveryChallan::where('tenant_id', $tenantId)
                    ->where('production_order_operation_id', $operation->id)
                    ->where('status', 'draft')
                    ->first();

                if ($existingDraft) {
                    return redirect()->route('production.subcontract.delivery-challans.show', $existingDraft->id)
                        ->with('warning', "An active Draft Delivery Challan ({$existingDraft->challan_number}) already exists for this operation. You can edit or dispatch it.");
                }
            }
        }

        if ($order && !$vendor && $order->operations) {
            $extOp = $order->operations->firstWhere('is_external', true);
            $vendor = $extOp?->vendor;
        }

        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $defaultWarehouseId = $warehouses->firstWhere('is_default', true)?->id ?? $warehouses->first()?->id;

        // Pre-fill raw material items for this operation from BOM
        if ($order && $order->bom && $order->bom->items) {
            $targetQty = (float) $order->quantity_ordered;
            $bomBase = (float) ($order->bom->base_quantity > 0 ? $order->bom->base_quantity : 1.0);
            $multiplier = $targetQty / $bomBase;

            foreach ($order->bom->items as $bomItem) {
                if ($bomItem->material) {
                    $prefilledItems->push([
                        'product_id' => $bomItem->material_id,
                        'warehouse_id' => $defaultWarehouseId,
                        'product_name' => $bomItem->material->name,
                        'sku' => $bomItem->material->sku,
                        'uom' => $bomItem->material->unit_of_measure ?? 'PCS',
                        'quantity' => round($bomItem->quantity * $multiplier, 2),
                    ]);
                }
            }
        }

        // Fallback: If no BOM items, add finished product or dummy line
        if ($prefilledItems->isEmpty() && $order && $order->product) {
            $prefilledItems->push([
                'product_id' => $order->product_id,
                'warehouse_id' => $defaultWarehouseId,
                'product_name' => $order->product->name . ' (Raw/Semi-Finished)',
                'sku' => $order->product->sku,
                'uom' => $order->product->unit_of_measure ?? 'PCS',
                'quantity' => $order->quantity_ordered,
            ]);
        }

        $nextNumber = $this->getNextChallanNumber($tenantId);
        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $products = Product::where('tenant_id', $tenantId)->get();

        return view('modules.production.subcontract.challans.create', compact(
            'order',
            'operation',
            'vendor',
            'prefilledItems',
            'nextNumber',
            'warehouses',
            'vendors',
            'products',
            'defaultWarehouseId'
        ));
    }

    public function checkStock(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $warehouseId = $request->query('warehouse_id');
        $productId = $request->query('product_id');

        if (!$warehouseId) {
            return response()->json(['success' => false, 'message' => 'Warehouse ID is required']);
        }

        $warehouse = Warehouse::where('tenant_id', $tenantId)->find($warehouseId);
        if (!$warehouse) {
            return response()->json(['success' => false, 'message' => 'Warehouse not found']);
        }

        $query = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
            ->where('warehouse_id', $warehouseId);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $stocks = $query->get(['product_id', 'quantity', 'available_qty', 'reserved_qty'])
            ->keyBy('product_id');

        return response()->json([
            'success' => true,
            'warehouse' => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
            ],
            'stocks' => $stocks,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $validated = $request->validate([
            'production_order_id' => 'nullable|exists:production_orders,id',
            'production_order_operation_id' => 'nullable|exists:production_order_operations,id',
            'vendor_id' => 'required|exists:vendors,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'challan_date' => 'required|date',
            'expected_return_date' => 'nullable|date',
            'vehicle_number' => 'nullable|string|max:50',
            'transporter_name' => 'nullable|string|max:100',
            'lr_number' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'status' => 'required|in:draft,dispatched',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_of_measure' => 'nullable|string',
            'items.*.batch_number' => 'nullable|string',
            'items.*.serial_number' => 'nullable|string',
        ]);

        // Stock availability verification when dispatching
        if ($validated['status'] === 'dispatched') {
            $stockErrors = $this->validateStockAvailability($tenantId, $validated['items']);
            if (!empty($stockErrors)) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['items' => implode(' ', $stockErrors)]);
            }
        }

        $challan = DB::transaction(function () use ($validated, $tenantId) {
            $challanNumber = $this->getNextChallanNumber($tenantId);
            $firstWarehouseId = $validated['warehouse_id'] ?? ($validated['items'][0]['warehouse_id'] ?? null);

            $challan = DeliveryChallan::create([
                'tenant_id' => $tenantId,
                'challan_number' => $challanNumber,
                'type' => 'subcontract_dispatch',
                'production_order_id' => $validated['production_order_id'] ?? null,
                'production_order_operation_id' => $validated['production_order_operation_id'] ?? null,
                'vendor_id' => $validated['vendor_id'],
                'warehouse_id' => $firstWarehouseId,
                'challan_date' => $validated['challan_date'],
                'expected_return_date' => $validated['expected_return_date'] ?? null,
                'status' => $validated['status'],
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'transporter_name' => $validated['transporter_name'] ?? null,
                'lr_number' => $validated['lr_number'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id() ?: 1,
            ]);

            foreach ($validated['items'] as $item) {
                DeliveryChallanItem::create([
                    'tenant_id' => $tenantId,
                    'delivery_challan_id' => $challan->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'quantity' => $item['quantity'],
                    'unit_of_measure' => $item['unit_of_measure'] ?? 'PCS',
                    'batch_number' => $item['batch_number'] ?? null,
                    'serial_number' => $item['serial_number'] ?? null,
                ]);
            }

            if ($validated['status'] === 'dispatched') {
                $this->processDispatchActions($challan, $tenantId);
            }

            return $challan;
        });

        return redirect()->route('production.subcontract.delivery-challans.show', $challan->id)
            ->with('success', "Delivery Challan {$challan->challan_number} created successfully.");
    }

    public function show($id)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $challan = DeliveryChallan::where('tenant_id', $tenantId)
            ->with(['productionOrder.product', 'operation', 'vendor', 'warehouse', 'items.product', 'items.warehouse', 'creator'])
            ->findOrFail($id);

        $warehouses = \App\Domains\Inventory\Models\Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.production.subcontract.challans.show', compact('challan', 'warehouses'));
    }

    public function print($id)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $challan = DeliveryChallan::where('tenant_id', $tenantId)
            ->with(['productionOrder.product', 'operation', 'vendor', 'warehouse', 'items.product', 'items.warehouse', 'creator'])
            ->findOrFail($id);

        return view('modules.production.subcontract.challans.print', compact('challan'));
    }

    public function dispatch($id)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $challan = DeliveryChallan::where('tenant_id', $tenantId)->with('items')->findOrFail($id);

        if ($challan->status === 'dispatched') {
            return redirect()->back()->with('warning', 'Challan is already dispatched.');
        }

        $itemsArray = $challan->items->map(fn($i) => [
            'product_id' => $i->product_id,
            'warehouse_id' => $i->warehouse_id ?: $challan->warehouse_id,
            'quantity' => $i->quantity
        ])->toArray();

        $stockErrors = $this->validateStockAvailability($tenantId, $itemsArray);

        if (!empty($stockErrors)) {
            return redirect()->back()->with('error', implode(' ', $stockErrors));
        }

        DB::transaction(function () use ($challan, $tenantId) {
            $challan->status = 'dispatched';
            $challan->save();

            $this->processDispatchActions($challan, $tenantId);
        });

        return redirect()->back()->with('success', "Delivery Challan {$challan->challan_number} has been dispatched to vendor.");
    }

    public function receive(Request $request, $id)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $challan = DeliveryChallan::where('tenant_id', $tenantId)
            ->with(['productionOrder.product', 'operation', 'vendor'])
            ->findOrFail($id);

        if ($challan->status === 'completed') {
            return redirect()->back()->with('warning', 'This delivery challan is already completed.');
        }

        $request->validate([
            'received_qty' => 'required|numeric|min:0.01',
            'accepted_qty' => 'nullable|numeric|min:0',
            'rejected_qty' => 'nullable|numeric|min:0',
            'warehouse_id' => 'required|exists:warehouses,id',
            'remarks'      => 'nullable|string',
        ]);

        $receivedQty = (float) $request->input('received_qty');
        $acceptedQty = (float) ($request->input('accepted_qty') ?? $receivedQty);
        $rejectedQty = (float) ($request->input('rejected_qty') ?? 0);
        $warehouseId = (int) $request->input('warehouse_id');
        $remarks     = $request->input('remarks');

        DB::transaction(function () use ($challan, $tenantId, $receivedQty, $acceptedQty, $rejectedQty, $warehouseId, $remarks) {
            $op = $challan->operation;
            $order = $challan->productionOrder;

            // 1. Add processed product stock back into warehouse (IN)
            $targetProductId = $order?->product_id;
            if ($targetProductId && $acceptedQty > 0) {
                \App\Domains\Inventory\Services\StockService::recordInflow(
                    tenantId: $tenantId,
                    productId: $targetProductId,
                    warehouseId: $warehouseId,
                    quantity: $acceptedQty,
                    unitCost: 0,
                    referenceType: 'SubcontractReceipt',
                    referenceId: $challan->id
                );
            }

            // 2. Backflush company material components used for this subcontract batch
            if ($op) {
                app(\App\Domains\Production\Services\SubcontractMaterialBalanceService::class)
                    ->backflushCompanyMaterial($tenantId, $op, $acceptedQty);

                // 3. Update operation progress & status
                $op->quantity_produced = (float) $op->quantity_produced + $acceptedQty;
                if ($rejectedQty > 0) {
                    $op->quantity_rejected = (float) $op->quantity_rejected + $rejectedQty;
                }

                $targetQty = (float) ($op->target_produced_qty ?: ($order?->quantity_ordered ?: 0.0));
                if ($targetQty > 0 && $op->quantity_produced < ($targetQty - 0.0001)) {
                    $op->status = 'running';
                } else {
                    $op->status = 'completed';
                    $op->actual_end_time = now();
                }
                $op->save();

                // 4. Log progress
                ProductionOrderProgressLog::create([
                    'tenant_id'           => $tenantId,
                    'production_order_id' => $op->production_order_id,
                    'operation_id'        => $op->id,
                    'quantity_completed'  => $acceptedQty,
                    'recorded_at'          => now(),
                    'status'              => $op->status,
                    'log_type'            => 'subcontract_receipt',
                    'logged_by'           => auth()->id() ?: 1,
                    'remarks'             => "Received {$acceptedQty} processed items from vendor [{$challan->vendor?->name}] via Challan #{$challan->challan_number}. " . ($remarks ?? ''),
                ]);

                // 5. Unlock next operation on Shopfloor
                app(\App\Domains\Production\Services\ProductionWipService::class)->evaluateAndExecuteWipTransfers($op->id);
            }

            // Mark Challan as Completed
            $challan->status = 'completed';
            $challan->save();

            // Auto-approve Subcontract PO if draft
            if ($challan->production_order_operation_id) {
                $poItem = \App\Domains\Purchase\Models\PurchaseOrderItem::where('production_order_operation_id', $challan->production_order_operation_id)->first();
                if ($poItem && $poItem->order && $poItem->order->status === 'Draft') {
                    $poItem->order->status = 'Approved';
                    $poItem->order->save();
                }
            }

            // Auto-complete Production Order if all operations completed
            if ($challan->production_order_id) {
                app(\App\Domains\Production\Services\ProductionOrderService::class)->evaluateAndAutoCompleteOrder($challan->production_order_id);
            }
        });

        return redirect()->route('production.subcontract.delivery-challans.show', $challan->id)
            ->with('success', "Received {$acceptedQty} items from vendor. Stock updated and next operation unlocked on shopfloor.");
    }

    protected function validateStockAvailability(int $tenantId, array $items): array
    {
        $errors = [];

        foreach ($items as $item) {
            $whId = $item['warehouse_id'] ?? null;
            $productId = $item['product_id'];
            $reqQty = (float) $item['quantity'];

            if (!$whId) continue;

            $wh = Warehouse::find($whId);
            $stock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                ->where('warehouse_id', $whId)
                ->where('product_id', $productId)
                ->first();

            $available = $stock ? (float) ($stock->available_qty ?? $stock->quantity) : 0.0;

            if ($available < $reqQty) {
                $product = Product::find($productId);
                $errors[] = "Insufficient stock for [{$product?->name}] in warehouse [{$wh?->name}]. Required: " . number_format($reqQty, 2) . ", Available: " . number_format($available, 2) . ".";
            }
        }

        return $errors;
    }

    protected function processDispatchActions(DeliveryChallan $challan, int $tenantId): void
    {
        // 1. Physical Stock Deduction & Stock Ledger Transaction per item warehouse
        foreach ($challan->items as $item) {
            $whId = $item->warehouse_id ?: $challan->warehouse_id;

            if ($whId) {
                StockTransaction::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $whId,
                    'type' => 'OUT',
                    'reference_type' => 'DeliveryChallan',
                    'reference_id' => $challan->production_order_id ?: $challan->id,
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => 0,
                    'total_value' => 0,
                    'balance_qty' => 0,
                ]);

                $stock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                    ->where('warehouse_id', $whId)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($stock) {
                    $stock->quantity = max(0.0, (float) $stock->quantity - (float) $item->quantity);
                    $stock->available_qty = max(0.0, (float) $stock->available_qty - (float) $item->quantity);
                    $stock->save();
                }
            }
        }

        // 2. Production Operation & Progress Log Update
        if ($challan->production_order_operation_id) {
            $op = ProductionOrderOperation::where('tenant_id', $tenantId)->find($challan->production_order_operation_id);

            if ($op) {
                $op->status = 'vendor_dispatched';
                $op->actual_start_time = $op->actual_start_time ?: now();
                $op->save();

                ProductionOrderProgressLog::create([
                    'tenant_id' => $tenantId,
                    'production_order_id' => $op->production_order_id,
                    'operation_id' => $op->id,
                    'quantity_completed' => 0,
                    'recorded_at' => now(),
                    'status' => 'vendor_dispatched',
                    'log_type' => 'subcontract_dispatch',
                    'logged_by' => auth()->id() ?: 1,
                    'remarks' => "Company material dispatched to vendor via Delivery Challan #{$challan->challan_number} (Vehicle: {$challan->vehicle_number}). Stock deducted from respective item warehouses.",
                ]);
            }
        }
    }

    protected function getNextChallanNumber(int $tenantId): string
    {
        $lastChallan = DeliveryChallan::where('tenant_id', $tenantId)->latest('id')->first();
        $nextNum = $lastChallan ? ((int) preg_replace('/[^0-9]/', '', $lastChallan->challan_number)) + 1 : 1;

        return 'DC-' . date('Y') . '-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }
}
