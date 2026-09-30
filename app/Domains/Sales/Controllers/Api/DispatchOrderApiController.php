<?php

namespace App\Domains\Sales\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Platform\Models\Transporter;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Repositories\DispatchOrderRepository;
use App\Domains\Sales\Services\DispatchOrderService;
use App\Exports\DispatchOrderExport;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class DispatchOrderApiController extends Controller
{
    public function __construct(
        private readonly DispatchOrderService $dispatchService,
        private readonly DispatchOrderRepository $dispatchRepo
    ) {}

    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/sales/dispatches/meta
     */
    public function meta(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        $transporters = Transporter::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']);
        $pendingDOs = $this->dispatchService->getPendingMaterialRequirementsFormatted();

        $soId = $request->input('sales_order_id');
        $invoices = [];
        if ($soId) {
            $invoices = $this->dispatchService->getFormattedInvoicesForSalesOrder((int)$soId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'warehouses' => $warehouses,
                'transporters' => $transporters,
                'pending_material_requirements' => $pendingDOs,
                'invoices' => $invoices,
            ],
        ]);
    }

    /**
     * GET /api/sales/dispatches
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = DispatchOrder::query()
            ->where('tenant_id', $tenantId)
            ->with(['salesOrder.customer:id,name,company_name', 'transporter:id,name', 'items.product:id,name,sku', 'items.warehouse:id,name']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('dispatch_number', 'like', "%{$search}%")
                  ->orWhere('carrier', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('eway_bill_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage    = min((int)$request->input('per_page', 15), 100);
        $dispatches = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $dispatches->items(),
            'meta'    => [
                'current_page' => $dispatches->currentPage(),
                'last_page'    => $dispatches->lastPage(),
                'per_page'     => $dispatches->perPage(),
                'total'        => $dispatches->total(),
            ],
        ]);
    }

    /**
     * POST /api/sales/dispatches
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $dispatch = $this->dispatchService->createDispatchOrder($validator->validated(), Auth::id() ?? 1);
            return response()->json([
                'success' => true,
                'message' => "Dispatch Order {$dispatch->dispatch_number} created successfully.",
                'data'    => $dispatch->load(['items', 'customer', 'salesOrder']),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/sales/dispatches/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $dispatch = DispatchOrder::where('tenant_id', $tenantId)
            ->with(['salesOrder.customer', 'customer', 'transporter', 'items.product', 'items.warehouse'])
            ->find($id);

        if (!$dispatch) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $dispatch,
        ]);
    }

    /**
     * POST /api/sales/dispatches/{id}/confirm
     */
    public function confirm(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $dispatch   = DispatchOrder::where('tenant_id', $tenantId)->find($id);

        if (!$dispatch) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch Order not found',
            ], 404);
        }

        try {
            $dispatch = $this->dispatchService->confirmDispatchOrder($dispatch);
            return response()->json([
                'success' => true,
                'message' => "Dispatch Order {$dispatch->dispatch_number} confirmed successfully.",
                'data'    => $dispatch->fresh(['items']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/sales/dispatches/{id}/ship
     */
    public function ship(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $dispatch   = DispatchOrder::where('tenant_id', $tenantId)->find($id);

        if (!$dispatch) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch Order not found',
            ], 404);
        }

        try {
            $dispatch = $this->dispatchService->shipDispatchOrder($dispatch);

            \App\Domains\Platform\Services\NotificationRuleService::trigger('sales.dispatch.shipped', [
                'doc_no' => $dispatch->dispatch_number,
                'customer_name' => $dispatch->customer?->name ?? 'Customer',
                'transporter' => $dispatch->transporter?->name ?? $dispatch->carrier ?? 'Carrier',
                'tracking_no' => $dispatch->tracking_number ?? $dispatch->lr_number ?? 'N/A',
                'date' => now()->format('Y-m-d'),
            ], route('sales.dispatches.show', $dispatch->id, false) ?: '', $dispatch);

            return response()->json([
                'success' => true,
                'message' => "Dispatch Order {$dispatch->dispatch_number} marked as Shipped successfully.",
                'data'    => $dispatch->fresh(['items']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/sales/dispatches/{id}/update-tracking
     */
    public function updateTracking(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $dispatch   = DispatchOrder::where('tenant_id', $tenantId)->find($id);

        if (!$dispatch) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch Order not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $dispatch->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tracking & logistics details updated successfully.',
            'data'    => $dispatch->fresh(),
        ]);
    }

    /**
     * POST /api/sales/dispatches/{id}/pod
     */
    public function uploadPod(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $dispatch   = DispatchOrder::where('tenant_id', $tenantId)->find($id);

        if (!$dispatch) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch Order not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pod_file'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'delivered_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $updateData = [
            'delivered_at' => $request->input('delivered_at') ?: now(),
            'status'       => 'Delivered',
        ];

        if ($request->hasFile('pod_file')) {
            $updateData['pod_attachment_path'] = $request->file('pod_file')->store('pods', 'public');
        }

        $dispatch->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Dispatch Order marked as Delivered successfully.',
            'data'    => $dispatch->fresh(),
        ]);
    }

    /**
     * GET /api/sales/dispatches/available-serials
     */
    public function getAvailableSerials(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $productId   = $request->input('product_id');
        $warehouseId = $request->input('warehouse_id');
        $status      = $request->input('status', 'Available');

        $serials = \App\Domains\Inventory\Models\SerialNumber::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->when($warehouseId, function ($q) use ($warehouseId) {
                return $q->where('warehouse_id', $warehouseId);
            })
            ->where('status', $status)
            ->pluck('serial_number');

        return response()->json(['success' => true, 'serials' => $serials]);
    }

    /**
     * GET /api/sales/dispatches/available-batches
     */
    public function getAvailableBatches(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $productId   = $request->input('product_id');
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

    /**
     * GET /api/sales/dispatches/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return Excel::download(
            new DispatchOrderExport($tenantId, $request->all()),
            'dispatch_orders_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
