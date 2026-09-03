<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Platform\Models\Transporter;
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

    public function create(Request $request): View
    {
        $this->authorize('create', DispatchOrder::class);

        $tenantId = require_tenant_id();
        $warehouses   = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get();
        $transporters = Transporter::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get();
        $pendingDOsFormatted = $this->dispatchService->getPendingMaterialRequirementsFormatted();
        $pendingDOIds = array_column($pendingDOsFormatted, 'id');
        $mrIdTemp = $request->input('material_requirement_id') ?: $request->input('mr_id');
        if ($mrIdTemp && !in_array((int)$mrIdTemp, $pendingDOIds)) {
            $pendingDOIds[] = (int)$mrIdTemp;
        }

        $pendingDOs = MaterialRequirement::with([
            'salesOrder.customer',
            'items.product',
            'items.warehouse',
        ])
        ->whereIn('id', $pendingDOIds)
        ->latest()
        ->get();

        $customers    = \App\Domains\CRM\Models\Customer::where('tenant_id', $tenantId)->orderBy('name')->get();
        $products     = Product::where('tenant_id', $tenantId)->with(['uom'])->orderBy('name')->get();

        $mrId = $request->input('material_requirement_id') ?: $request->input('mr_id');
        $soId = $request->input('sales_order_id') ?: $request->input('so_id');

        $prefillSalesOrder = null;
        if ($soId) {
            $prefillSalesOrder = \App\Domains\Sales\Models\SalesOrder::find($soId);
            if (!$mrId && $prefillSalesOrder) {
                $mr = \App\Domains\Sales\Models\MaterialRequirement::where('sales_order_id', $prefillSalesOrder->id)->first();
                $mrId = $mr?->id;
            }
        } elseif ($mrId) {
            $mr = \App\Domains\Sales\Models\MaterialRequirement::with('salesOrder')->find($mrId);
            $prefillSalesOrder = $mr?->salesOrder;
            $soId = $prefillSalesOrder?->id;
        }

        $formattedWarehouses = $warehouses->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])->values()->all();
        $formattedProducts   = $products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?: '',
            'track_serial_number' => (bool) $p->track_serial_number,
            'track_batch' => (bool) $p->track_batch,
        ])->values()->all();

        $trpCount = Transporter::where('tenant_id', $tenantId)->count() + 1;
        $autoCode = 'TRP-' . str_pad($trpCount, 4, '0', STR_PAD_LEFT);

        return view('modules.sales.dispatches.create', compact('warehouses', 'transporters', 'autoCode', 'pendingDOs', 'customers', 'products', 'formattedWarehouses', 'formattedProducts', 'mrId', 'prefillSalesOrder', 'soId'));
    }

    public function pendingMaterialRequirements(Request $request): JsonResponse
    {
        $this->authorize('create', DispatchOrder::class);

        $result = $this->dispatchService->getPendingMaterialRequirementsFormatted();

        return response()->json(['data' => $result]);
    }

    public function getSalesOrderInvoices(Request $request): JsonResponse
    {
        $this->authorize('create', DispatchOrder::class);
        $salesOrderId = (int) $request->input('sales_order_id');

        if (!$salesOrderId) {
            return response()->json(['invoices' => []]);
        }

        $invoices = $this->dispatchService->getFormattedInvoicesForSalesOrder($salesOrderId);

        return response()->json(['invoices' => $invoices]);
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
            'sales_order_id'          => ['nullable', 'exists:sales_orders,id'],
            'material_requirement_id' => ['nullable', 'exists:material_requirements,id'],
            'invoice_id'              => ['nullable', 'exists:invoices,id'],
            'customer_id'             => ['nullable', 'exists:customers,id'],
            'transporter_id'          => ['nullable', 'exists:transporters,id'],
            'carrier'                 => ['nullable', 'string', 'max:255'],
            'tracking_number'         => ['nullable', 'string', 'max:255'],
            'eway_bill_number'        => ['nullable', 'string', 'max:50'],
            'eway_bill_date'          => ['nullable', 'date'],
            'lr_number'               => ['nullable', 'string', 'max:50'],
            'lr_date'                 => ['nullable', 'date'],
            'freight_terms'           => ['nullable', 'string', 'max:50'],
            'freight_amount'          => ['nullable', 'numeric', 'min:0'],
            'shipping_address'        => ['nullable', 'string'],
            'total_packages'          => ['nullable', 'integer', 'min:0'],
            'gross_weight'            => ['nullable', 'numeric', 'min:0'],
            'net_weight'              => ['nullable', 'numeric', 'min:0'],
            'volume_cbm'              => ['nullable', 'numeric', 'min:0'],
            'vehicle_number'          => ['nullable', 'string', 'max:100'],
            'driver_name'             => ['nullable', 'string', 'max:100'],
            'driver_phone'            => ['nullable', 'string', 'max:50'],
            'dispatch_date'           => ['required', 'date'],
            'notes'                   => ['nullable', 'string'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.material_requirement_item_id' => ['nullable', 'exists:material_requirement_items,id'],
            'items.*.invoice_item_id' => ['nullable', 'exists:invoice_items,id'],
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
            return redirect()->back()
                ->with('success', "Dispatch Order {$dispatch->dispatch_number} confirmed and warehouse stock reserved successfully. Invoice creation is now enabled.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function ship(int $id): RedirectResponse
    {
        $dispatch = $this->dispatchRepo->find($id);
        if (!$dispatch) abort(404);
        $this->authorize('update', $dispatch);

        try {
            $dispatch = $this->dispatchService->shipDispatchOrder($dispatch);
            return redirect()->back()
                ->with('success', "Dispatch Order {$dispatch->dispatch_number} marked as Shipped (Gate Outward) and physical stock deducted from warehouse successfully.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function downloadChallan(int $id)
    {
        $dispatch = $this->dispatchRepo->find($id);
        if (!$dispatch) abort(404);
        $this->authorize('view', $dispatch);

        return view('modules.sales.dispatches.pdf', compact('dispatch'));
    }

    public function uploadPod(Request $request, int $id): RedirectResponse
    {
        $dispatch = $this->dispatchRepo->find($id);
        if (!$dispatch) abort(404);
        $this->authorize('update', $dispatch);

        $request->validate([
            'pod_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'delivered_at' => 'nullable|date',
        ]);

        $updateData = [
            'delivered_at' => $request->input('delivered_at') ?: now(),
            'status' => 'Delivered',
        ];

        if ($request->hasFile('pod_file')) {
            $updateData['pod_attachment_path'] = $request->file('pod_file')->store('pods', 'public');
        }

        $dispatch->update($updateData);

        return redirect()->route('sales.dispatches.show', $dispatch->id)
            ->with('success', 'Dispatch Order status updated to Delivered successfully.');
    }

    public function updateTracking(Request $request, int $id): RedirectResponse
    {
        $dispatch = $this->dispatchRepo->find($id);
        if (!$dispatch) abort(404);
        $this->authorize('update', $dispatch);

        $validated = $request->validate([
            'carrier'          => 'nullable|string|max:255',
            'tracking_number'  => 'nullable|string|max:255',
            'vehicle_number'   => 'nullable|string|max:100',
            'driver_name'      => 'nullable|string|max:100',
            'driver_phone'     => 'nullable|string|max:50',
            'eway_bill_number' => 'nullable|string|max:50',
            'lr_number'        => 'nullable|string|max:50',
            'lr_date'          => 'nullable|date',
            'freight_terms'    => 'nullable|string|in:To Pay,To Be Billed,Prepaid,Customer Pickup',
            'freight_amount'   => 'nullable|numeric|min:0',
        ]);

        $dispatch->update($validated);

        return redirect()->route('sales.dispatches.show', $dispatch->id)
            ->with('success', 'Tracking & logistics details updated successfully.');
    }
}
