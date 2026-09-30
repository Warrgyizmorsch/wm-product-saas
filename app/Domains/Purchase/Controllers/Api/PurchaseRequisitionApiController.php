<?php

namespace App\Domains\Purchase\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Purchase\Models\ApprovalReminder;
use App\Domains\Purchase\Repositories\PurchaseRequisitionRepository;
use App\Domains\Purchase\Services\PurchaseRequisitionService;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Exports\PurchaseRequisitionExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PurchaseRequisitionApiController extends Controller
{
    public function __construct(
        protected PurchaseRequisitionRepository $requisitionRepo,
        protected PurchaseRequisitionService $requisitionService
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
     * GET /api/purchase/requisitions/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $products   = Product::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'sku', 'cost_price', 'unit_cost']);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data'    => [
                'statuses'     => ['Draft', 'Approved', 'Partially Ordered', 'Ordered', 'Cancelled'],
                'source_types' => ['direct', 'so', 'mo', 'material_request', 'material_requirement', 'requisition_slip'],
                'products'     => $products,
                'warehouses'   => $warehouses,
            ],
        ]);
    }

    /**
     * GET /api/purchase/requisitions
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = PurchaseRequisition::query()
            ->where('tenant_id', $tenantId)
            ->with(['requester:id,name', 'items.product:id,name,sku']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('requisition_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($sourceType = $request->input('source_type')) {
            $query->where('source_type', $sourceType);
        }
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('requisition_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('requisition_date', '<=', $toDate);
        }

        $perPage      = min((int)$request->input('per_page', 15), 100);
        $requisitions = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $requisitions->items(),
            'meta'    => [
                'current_page' => $requisitions->currentPage(),
                'last_page'    => $requisitions->lastPage(),
                'per_page'     => $requisitions->perPage(),
                'total'        => $requisitions->total(),
            ],
        ]);
    }

    /**
     * POST /api/purchase/requisitions
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'requisition_date'               => ['required', 'date'],
            'expected_date'                  => ['nullable', 'date'],
            'source_type'                    => ['required', 'string', 'in:direct,so,mo,material_request,material_requirement,requisition_slip'],
            'sales_order_id'                 => ['nullable', 'integer'],
            'production_order_id'            => ['nullable', 'integer'],
            'production_requisition_slip_id' => ['nullable', 'integer'],
            'material_requirement_id'        => ['nullable', 'integer'],
            'requisition_slip_number'        => ['nullable', 'string', 'max:255'],
            'notes'                          => ['nullable', 'string'],
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.product_id'             => ['required', 'integer'],
            'items.*.warehouse_id'           => ['nullable', 'integer'],
            'items.*.quantity'               => ['required', 'numeric', 'min:0.0001'],
            'items.*.estimated_cost'         => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        [$tenantId] = $this->resolveTenantContext();
        $validated  = $validator->validated();

        try {
            $pr = $this->requisitionService->storeRequisition($validated, $tenantId);
            return response()->json([
                'success' => true,
                'message' => "Purchase Requisition {$pr->requisition_number} created successfully.",
                'data'    => $pr->load(['items.product', 'items.warehouse', 'requester']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create requisition: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/purchase/requisitions/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with(['requester', 'items.product', 'items.warehouse', 'reminders.user'])
            ->find($id);

        if (!$requisition) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Requisition not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $requisition,
        ]);
    }

    /**
     * PUT/PATCH /api/purchase/requisitions/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId]  = $this->resolveTenantContext();
        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)->find($id);

        if (!$requisition) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Requisition not found',
            ], 404);
        }

        if ($requisition->status !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft Purchase Requisitions can be updated.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'requisition_date'               => ['sometimes', 'required', 'date'],
            'expected_date'                  => ['nullable', 'date'],
            'source_type'                    => ['sometimes', 'required', 'string', 'in:direct,so,mo,material_request,material_requirement,requisition_slip'],
            'sales_order_id'                 => ['nullable', 'integer'],
            'production_order_id'            => ['nullable', 'integer'],
            'production_requisition_slip_id' => ['nullable', 'integer'],
            'material_requirement_id'        => ['nullable', 'integer'],
            'requisition_slip_number'        => ['nullable', 'string', 'max:255'],
            'notes'                          => ['nullable', 'string'],
            'items'                          => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_id'             => ['required', 'integer'],
            'items.*.warehouse_id'           => ['nullable', 'integer'],
            'items.*.quantity'               => ['required', 'numeric', 'min:0.0001'],
            'items.*.estimated_cost'         => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $this->requisitionService->updateRequisition($requisition, $validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Purchase Requisition updated successfully.',
            'data'    => $requisition->fresh(['items.product', 'items.warehouse', 'requester']),
        ]);
    }

    /**
     * DELETE /api/purchase/requisitions/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId]  = $this->resolveTenantContext();
        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)->find($id);

        if (!$requisition) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Requisition not found',
            ], 404);
        }

        if ($requisition->status !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft Purchase Requisitions can be deleted.',
            ], 422);
        }

        $this->requisitionRepo->delete($requisition);

        return response()->json([
            'success' => true,
            'message' => 'Purchase Requisition deleted successfully.',
        ]);
    }

    /**
     * POST /api/purchase/requisitions/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        [$tenantId]  = $this->resolveTenantContext();
        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)->find($id);

        if (!$requisition) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Requisition not found',
            ], 404);
        }

        if ($requisition->status !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft Purchase Requisitions can be approved.',
            ], 422);
        }

        $requisition->update([
            'status'      => 'Approved',
            'approved_by' => auth()->id() ?? 1,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Purchase Requisition has been successfully approved.',
            'data'    => $requisition->fresh(['items.product', 'requester']),
        ]);
    }

    /**
     * POST /api/purchase/requisitions/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        [$tenantId]  = $this->resolveTenantContext();
        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)->find($id);

        if (!$requisition) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Requisition not found',
            ], 404);
        }

        if ($requisition->status !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft Purchase Requisitions can be rejected.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $requisition->update([
            'status'           => 'Cancelled',
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Purchase Requisition has been rejected.',
            'data'    => $requisition,
        ]);
    }

    /**
     * POST /api/purchase/requisitions/{id}/remind
     */
    public function remind(Request $request, int $id): JsonResponse
    {
        [$tenantId]  = $this->resolveTenantContext();
        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)->find($id);

        if (!$requisition) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Requisition not found',
            ], 404);
        }

        if ($requisition->status !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Reminders can only be sent for pending Draft Purchase Requisitions.',
            ], 422);
        }

        ApprovalReminder::create([
            'tenant_id'       => $tenantId,
            'remindable_type' => get_class($requisition),
            'remindable_id'   => $requisition->id,
            'user_id'         => auth()->id() ?? 1,
            'note'            => $request->input('note'),
        ]);

        $requisition->increment('reminder_count');
        $requisition->update(['last_reminded_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Reminder successfully recorded for PR #{$requisition->requisition_number}.",
            'data'    => $requisition->fresh('reminders.user'),
        ]);
    }

    /**
     * GET /api/purchase/requisitions/pending-items
     */
    public function pendingItems(Request $request): JsonResponse
    {
        $data = $this->requisitionRepo->getPendingItemsData($request->all());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * POST /api/purchase/requisitions/pending-items/create-po
     */
    public function createPosFromPendingItems(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'item_ids'    => ['required', 'array', 'min:1'],
            'bulk_action' => ['nullable', 'string', 'in:po,rfq'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $selectedItemIds = $request->input('item_ids', []);
        $actionType      = $request->input('bulk_action', 'po');

        try {
            $res = $this->requisitionService->createPosFromPendingItems($selectedItemIds, $actionType, $tenantId);
            return response()->json([
                'success' => true,
                'message' => $res['message'],
                'data'    => $res,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/purchase/requisitions/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return Excel::download(
            new PurchaseRequisitionExport($tenantId, $request->all()),
            'purchase_requisitions_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
