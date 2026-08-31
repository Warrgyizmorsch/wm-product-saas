<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Repositories\DeliveryChallanRepositoryInterface;
use App\Domains\Production\Services\SubcontractMaterialBalanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SubcontractDeliveryChallanController extends Controller
{
    public function __construct(
        private readonly DeliveryChallanRepositoryInterface $repository,
        private readonly SubcontractMaterialBalanceService $materialBalanceService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', DeliveryChallan::class);
        $tenantId = require_tenant_id();

        $filters = $request->only(['status', 'search']);
        $challans = $this->repository->paginate($tenantId, $filters, 15);
        $statusCounts = $this->repository->getStatusCounts($tenantId);

        return view('modules.production.subcontract.challans.index', compact('challans', 'statusCounts'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', DeliveryChallan::class);
        $tenantId = require_tenant_id();

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

        $nextNumber = $this->repository->getNextChallanNumber($tenantId);
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
        $tenantId = require_tenant_id();

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
        $this->authorize('create', DeliveryChallan::class);
        $tenantId = require_tenant_id();

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

        if ($validated['status'] === 'dispatched') {
            $stockErrors = $this->materialBalanceService->validateStockAvailability($tenantId, $validated['items']);
            if (!empty($stockErrors)) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['items' => implode(' ', $stockErrors)]);
            }
        }

        $challan = $this->repository->create($tenantId, $validated, $validated['items']);

        if ($validated['status'] === 'dispatched') {
            $this->materialBalanceService->processDispatchActions($challan, $tenantId);
        }

        return redirect()->route('production.subcontract.delivery-challans.show', $challan->id)
            ->with('success', "Delivery Challan {$challan->challan_number} created successfully.");
    }

    public function show($id)
    {
        $tenantId = require_tenant_id();
        $challan = $this->repository->findOrFail((int) $id, $tenantId);

        $this->authorize('view', $challan);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.production.subcontract.challans.show', compact('challan', 'warehouses'));
    }

    public function print($id)
    {
        $tenantId = require_tenant_id();
        $challan = $this->repository->findOrFail((int) $id, $tenantId);

        $this->authorize('view', $challan);

        return view('modules.production.subcontract.challans.print', compact('challan'));
    }

    public function dispatch($id)
    {
        $tenantId = require_tenant_id();
        $challan = $this->repository->findOrFail((int) $id, $tenantId);

        $this->authorize('dispatch', $challan);

        if ($challan->status === 'dispatched') {
            return redirect()->back()->with('warning', 'Challan is already dispatched.');
        }

        $itemsArray = $challan->items->map(fn($i) => [
            'product_id' => $i->product_id,
            'warehouse_id' => $i->warehouse_id ?: $challan->warehouse_id,
            'quantity' => $i->quantity
        ])->toArray();

        $stockErrors = $this->materialBalanceService->validateStockAvailability($tenantId, $itemsArray);

        if (!empty($stockErrors)) {
            return redirect()->back()->with('error', implode(' ', $stockErrors));
        }

        $this->repository->updateStatus($challan, 'dispatched');
        $this->materialBalanceService->processDispatchActions($challan, $tenantId);

        return redirect()->back()->with('success', "Delivery Challan {$challan->challan_number} has been dispatched to vendor.");
    }

    public function receive(Request $request, $id)
    {
        $tenantId = require_tenant_id();
        $challan = $this->repository->findOrFail((int) $id, $tenantId);

        $this->authorize('receive', $challan);

        if ($challan->status === 'completed') {
            return redirect()->back()->with('warning', 'This delivery challan is already completed.');
        }

        $validated = $request->validate([
            'received_qty' => 'required|numeric|min:0.01',
            'accepted_qty' => 'nullable|numeric|min:0',
            'rejected_qty' => 'nullable|numeric|min:0',
            'warehouse_id' => 'required|exists:warehouses,id',
            'remarks'      => 'nullable|string',
        ]);

        $receivedQty = (float) $validated['received_qty'];
        $acceptedQty = (float) ($validated['accepted_qty'] ?? $receivedQty);
        $rejectedQty = (float) ($validated['rejected_qty'] ?? 0);
        $warehouseId = (int) $validated['warehouse_id'];
        $remarks     = $validated['remarks'] ?? null;

        $this->materialBalanceService->processReceiveActions(
            $challan,
            $tenantId,
            $receivedQty,
            $acceptedQty,
            $rejectedQty,
            $warehouseId,
            $remarks
        );

        return redirect()->route('production.subcontract.delivery-challans.show', $challan->id)
            ->with('success', "Received {$acceptedQty} items from vendor. Stock updated and next operation unlocked on shopfloor.");
    }
}
