<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Repositories\DispatchOrderRepository;
use App\Domains\Sales\Services\DispatchOrderService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DispatchOrderController extends Controller
{
    public function __construct(
        private readonly DispatchOrderService $dispatchService,
        private readonly DispatchOrderRepository $dispatchRepo
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DispatchOrder::class);

        $dispatches = $this->dispatchRepo->getPaginated($request->all(), 15);

        return view('modules.sales.dispatches.index', compact('dispatches'));
    }

    public function create(): View
    {
        $this->authorize('create', DispatchOrder::class);

        $tenantId = require_tenant_id();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get();
        $pendingDOs = $this->dispatchRepo->getAllPendingMaterialRequirements();
        $customers  = \App\Domains\CRM\Models\Customer::where('tenant_id', $tenantId)->orderBy('name')->get();
        $products   = Product::where('tenant_id', $tenantId)->with(['uom'])->orderBy('name')->get();

        $formattedWarehouses = $warehouses->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])->values()->all();
        $formattedProducts   = $products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?: '',
            'track_serial_number' => (bool) $p->track_serial_number,
            'track_batch' => (bool) $p->track_batch,
        ])->values()->all();

        return view('modules.sales.dispatches.create', compact('warehouses', 'pendingDOs', 'customers', 'products', 'formattedWarehouses', 'formattedProducts'));
    }

    public function pendingMaterialRequirements(Request $request): JsonResponse
    {
        $this->authorize('create', DispatchOrder::class);

        $result = $this->dispatchService->getPendingMaterialRequirementsFormatted();

        return response()->json(['data' => $result]);
    }

    public function getAvailableSerials(Request $request): JsonResponse
    {
        $productId = $request->input('product_id');
        $warehouseId = $request->input('warehouse_id');
        $status = $request->input('status', 'Available');
        $tenantId = require_tenant_id();

        $serials = \App\Domains\Inventory\Models\SerialNumber::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->when($warehouseId, function ($q) use ($warehouseId) {
                return $q->where('warehouse_id', $warehouseId);
            })
            ->where('status', $status)
            ->pluck('serial_number');

        return response()->json(['success' => true, 'serials' => $serials]);
    }

    public function getAvailableBatches(Request $request): JsonResponse
    {
        $tenantId = require_tenant_id();
        $productId = $request->input('product_id');
        $warehouseId = $request->input('warehouse_id');

        if (!$productId) {
            return response()->json(['batches' => []]);
        }

        $query = \App\Domains\Inventory\Models\Batch::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('available_qty', '>', 0)
            ->with('warehouse');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $batches = $query->orderBy('expiry_date', 'asc')->get();

        // If no batches in specific warehouse, fallback to all available batches for product
        if ($batches->isEmpty() && $warehouseId) {
            $batches = \App\Domains\Inventory\Models\Batch::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('available_qty', '>', 0)
                ->with('warehouse')
                ->orderBy('expiry_date', 'asc')
                ->get();
        }

        $formatted = $batches->map(function ($b) {
            $daysLeft = (int) now()->diffInDays($b->expiry_date, false);
            $whName = $b->warehouse ? $b->warehouse->name : 'Main Warehouse';
            return [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'available_qty' => (float) $b->available_qty,
                'expiry_date' => $b->expiry_date ? $b->expiry_date->format('d-M-Y') : 'N/A',
                'is_expired' => $daysLeft < 0,
                'is_expiring_soon' => $daysLeft >= 0 && $daysLeft <= 30,
                'warehouse_name' => $whName,
            ];
        });

        return response()->json(['batches' => $formatted]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DispatchOrder::class);

        $validated = $request->validate([
            'material_requirement_id' => ['nullable', 'exists:material_requirements,id'],
            'customer_id'             => ['nullable', 'exists:customers,id'],
            'vehicle_number'          => ['nullable', 'string', 'max:100'],
            'driver_name'             => ['nullable', 'string', 'max:100'],
            'driver_phone'            => ['nullable', 'string', 'max:50'],
            'shipping_agent'          => ['nullable', 'string', 'max:100'],
            'dispatch_date'           => ['required', 'date'],
            'notes'                   => ['nullable', 'string'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.material_requirement_item_id' => ['nullable', 'exists:material_requirement_items,id'],
            'items.*.product_id'      => ['required', 'exists:products,id'],
            'items.*.warehouse_id'    => ['required', 'exists:warehouses,id'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.0001'],
            'items.*.serial_numbers'  => ['nullable', 'string'],
            'items.*.batch_number'    => ['nullable', 'string'],
        ], [
            'items.*.quantity.min'      => 'Dispatch quantity must be greater than 0.',
            'items.*.quantity.required' => 'Dispatch quantity is required.',
            'items.*.quantity.numeric'  => 'Dispatch quantity must be a valid number.',
        ]);

        try {
            $dispatch = $this->dispatchService->createDispatchOrder($validated, Auth::id() ?? 1);
            return redirect()->route('sales.dispatches.index')->with('success', "Dispatch Order {$dispatch->dispatch_number} created successfully.");
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View
    {
        $dispatch = $this->dispatchRepo->find($id);
        if (!$dispatch) abort(404);
        $this->authorize('view', $dispatch);

        return view('modules.sales.dispatches.show', compact('dispatch'));
    }

    public function confirm(int $id): RedirectResponse
    {
        $dispatch = $this->dispatchRepo->find($id);
        if (!$dispatch) abort(404);
        $this->authorize('update', $dispatch);

        try {
            $dispatch = $this->dispatchService->confirmDispatchOrder($dispatch);
            return redirect()->route('sales.dispatches.show', $dispatch->id)->with('success', "Dispatch Order {$dispatch->dispatch_number} confirmed and stock deducted successfully.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
