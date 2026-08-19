<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use App\Domains\Sales\Repositories\MaterialRequirementRepository;
use App\Domains\Sales\Services\MaterialRequirementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialRequirementController extends Controller
{
    public function __construct(
        private readonly MaterialRequirementRepository $requirementRepo,
        private readonly MaterialRequirementService $deliveryService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MaterialRequirement::class);

        $filters = $request->only([
            'search', 'status', 'date_from', 'date_to', 'sort_by', 'sort_order', 'per_page'
        ]);

        $perPage = (int) $request->input('per_page', 15);
        if ($perPage < 5 || $perPage > 100) {
            $perPage = 15;
        }

        $deliveries = $this->requirementRepo->getPaginatedRequirements($filters, $perPage);
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return view('modules.sales.material-requirements.index', compact('deliveries', 'sortBy', 'sortOrder'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', MaterialRequirement::class);
        $data = $this->requirementRepo->getCreateData((int) $request->input('sales_order_id'));

        return view('modules.sales.material-requirements.create', array_merge($data, [
            'nextDeliveryNumber' => $this->deliveryService->getNextRequirementNumber(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MaterialRequirement::class);

        $validated = $request->validate([
            'sales_order_id' => ['required', 'exists:sales_orders,id'],
            'requirement_number' => ['required', 'string', 'max:255'],
            'requirement_date' => ['required', 'date'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
        ]);

        try {
            $delivery = $this->deliveryService->create($validated, $request->input('items', []));
            return redirect()->route('sales.material-requirements.show', $delivery->id)->with('success', 'Material Requirement successfully created!');
        } catch (\Exception $e) {
            return back()->withErrors([$e->getMessage()])->withInput();
        }
    }

    public function show(int $id): View
    {
        $delivery = $this->requirementRepo->find($id);
        if (!$delivery) abort(404);
        $this->authorize('view', $delivery);

        $warehouses = Warehouse::query()->where('status', 'active')->orderBy('name')->get();
        $defaultWarehouseId = Warehouse::where('tenant_id', $delivery->tenant_id)->orderBy('is_default', 'desc')->first()?->id ?? 1;

        foreach ($delivery->items as $item) {
            $warehouseId = $item->warehouse_id ?: $defaultWarehouseId;
            $item->available_qty = $warehouseId ? StockService::getAvailableStock($item->product_id, $warehouseId) : 0;
        }

        $itemAllocations = [];
        return view('modules.sales.material-requirements.show', compact('delivery', 'warehouses', 'defaultWarehouseId', 'itemAllocations'));
    }

    public function updateWarehouse(Request $request, int $itemId)
    {
        $item = MaterialRequirementItem::findOrFail($itemId);
        $warehouseId = (int) $request->input('warehouse_id');
        $item->update(['warehouse_id' => $warehouseId]);

        return response()->json([
            'success' => true,
            'available_qty' => StockService::getAvailableStock($item->product_id, $warehouseId),
        ]);
    }

    public function reserveQty(Request $request, int $itemId)
    {
        $item = MaterialRequirementItem::with('materialRequirement')->findOrFail($itemId);
        $qtyToReserve = (float) $request->input('quantity_reserve');

        if ($qtyToReserve <= 0) {
            return back()->with('error', 'Reserve quantity must be greater than 0.');
        }

        try {
            $this->deliveryService->reserveQty($item, $qtyToReserve);
            return back()->with('success', "Successfully reserved {$qtyToReserve} unit(s).");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function mockIndent(Request $request, int $itemId)
    {
        $item = MaterialRequirementItem::with('materialRequirement')->findOrFail($itemId);
        $qtyToRequest = (float) $request->input('quantity_request');
        if ($qtyToRequest <= 0) return back()->with('error', 'Quantity to request must be greater than 0.');

        $this->deliveryService->createPurchaseRequisition(
            $item,
            $qtyToRequest,
            $request->input('warehouse_id'),
            $request->input('notes'),
            $request->input('expected_date')
        );
        return back()->with('success', "Purchase Requisition successfully generated for {$qtyToRequest} unit(s).");
    }

    public function mockMo(Request $request, int $itemId)
    {
        $tenantId = require_tenant_id();
        $item = MaterialRequirementItem::with('materialRequirement')
            ->whereHas('materialRequirement', fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($itemId);

        $orderedQty = (float) ($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
        $shortageQty = max(0, $orderedQty - (float) $item->quantity_reserved);
        $qtyToMfg = (float) $request->input('quantity_mfg', $shortageQty > 0 ? $shortageQty : $orderedQty);

        if ($qtyToMfg <= 0) {
            return back()->withErrors(['quantity_mfg' => 'Quantity to manufacture must be greater than 0.']);
        }

        try {
            $this->deliveryService->createProductionRequest($item, $qtyToMfg, $request->input('notes'));
            return back()->with('success', 'Manufacturing Request submitted successfully. Line status set to Waiting Production.');
        } catch (\Exception $e) {
            return back()->withErrors(['quantity_mfg' => $e->getMessage()]);
        }
    }

    public function startPicking(int $id)
    {
        $delivery = MaterialRequirement::findOrFail($id);
        $this->deliveryService->startPicking($delivery);
        return back()->with('success', 'Picking process started. Status set to Picked.');
    }

    public function pack(int $id)
    {
        $delivery = MaterialRequirement::findOrFail($id);
        $this->deliveryService->pack($delivery);
        return back()->with('success', 'Package packed successfully. Status set to Packed.');
    }

    public function dispatch(int $id, Request $request)
    {
        $delivery = MaterialRequirement::with('items.product', 'salesOrder')->findOrFail($id);
        $validated = $request->validate(['carrier' => 'nullable|string|max:255', 'tracking_number' => 'nullable|string|max:255', 'notes' => 'nullable|string']);

        $this->deliveryService->dispatch($delivery, $validated);
        return back()->with('success', 'Order dispatched successfully. Stock ledger entries recorded.');
    }

    public function deliver(int $id)
    {
        $delivery = MaterialRequirement::with('items', 'salesOrder')->findOrFail($id);
        $this->deliveryService->deliver($delivery);
        return back()->with('success', 'Order delivered successfully! Ready for invoicing.');
    }

    public function ship(int $id, Request $request): RedirectResponse
    {
        $delivery = MaterialRequirement::findOrFail($id);
        $this->authorize('ship', $delivery);

        $allocations = $request->input('allocations', []);
        foreach ($delivery->items as $item) {
            if (!$item->product_id || $item->product?->type === 'Service') continue;

            if ($item->product?->track_serial_number) {
                $serials = array_filter(array_map('trim', $allocations[$item->id]['serials'] ?? []));
                if (count($serials) != (int) $item->quantity) {
                    return back()->withErrors(['Please select exactly '.(int) $item->quantity.' serial number(s) for item: '.$item->product->name]);
                }

                $validCount = SerialNumber::query()->where('product_id', $item->product_id)->where('warehouse_id', $item->warehouse_id)->whereIn('status', ['Available', 'Reserved'])->whereIn('serial_number', $serials)->count();
                if ($validCount != count($serials)) {
                    return back()->withErrors(["One or more selected serial numbers for product '".$item->product->name."' are invalid or already sold."]);
                }
            }
        }

        try {
            $this->deliveryService->ship($delivery, $allocations);
            return redirect()->route('sales.material-requirements.show', $delivery->id)->with('success', 'Material Requirement shipped successfully! Inventory updated.');
        } catch (\Exception $e) {
            return back()->withErrors([$e->getMessage()]);
        }
    }

    public function cancel(int $id): RedirectResponse
    {
        $delivery = MaterialRequirement::findOrFail($id);
        $this->authorize('cancel', $delivery);

        try {
            $this->deliveryService->cancel($delivery);
            return back()->with('success', 'Material Requirement cancelled successfully.');
        } catch (\Exception $e) {
            return back()->withErrors([$e->getMessage()]);
        }
    }

    public function storeDispatchOrder(int $deliveryId, Request $request): RedirectResponse
    {
        $delivery = MaterialRequirement::with('items.product', 'items.warehouse', 'salesOrder')->findOrFail($deliveryId);
        $validated = $request->validate([
            'carrier' => 'nullable|string|max:255', 'tracking_number' => 'nullable|string|max:255',
            'vehicle_number' => 'nullable|string|max:100', 'driver_name' => 'nullable|string|max:150',
            'driver_phone' => 'nullable|string|max:20', 'notes' => 'nullable|string',
        ]);

        $dispatchOrder = $this->deliveryService->storeDispatchOrder($delivery, $validated);

        return redirect()->route('sales.dispatches.show', $dispatchOrder->id)->with('success', "Dispatch Order {$dispatchOrder->dispatch_number} created successfully!");
    }
}
