<?php

namespace App\Domains\Purchase\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Repositories\PurchaseRfqRepository;
use App\Domains\Purchase\Services\PurchaseRfqService;
use App\Domains\Purchase\Models\PurchaseRfq;
use App\Domains\Purchase\Models\PurchaseRfqVendor;
use App\Domains\Purchase\Models\PurchaseRfqVendorRate;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Platform\Models\PaymentTerm;
use App\Exports\PurchaseRfqExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PurchaseRfqApiController extends Controller
{
    public function __construct(
        protected PurchaseRfqRepository $rfqRepo,
        protected PurchaseRfqService $rfqService
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
     * GET /api/purchase/rfqs/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $requisitions = PurchaseRequisition::where('tenant_id', $tenantId)->where('status', 'Approved')->orderBy('id', 'desc')->get(['id', 'requisition_number']);
        $products     = Product::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'sku', 'cost_price', 'unit_cost', 'preferred_vendor_id']);
        $vendors      = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'company_name', 'phone', 'email']);
        $warehouses   = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);
        $paymentTerms = PaymentTerm::where('is_active', true)->orderBy('due_days')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'statuses'      => ['Draft', 'Sent', 'Received', 'Confirmed', 'Cancelled'],
                'requisitions'  => $requisitions,
                'products'      => $products,
                'vendors'       => $vendors,
                'warehouses'    => $warehouses,
                'payment_terms' => $paymentTerms,
            ],
        ]);
    }

    /**
     * GET /api/purchase/rfqs
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = PurchaseRfq::query()
            ->where('tenant_id', $tenantId)
            ->with(['creator:id,name', 'requisition:id,requisition_number', 'rfqVendors.vendor:id,name,company_name', 'items.product:id,name,sku']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('rfq_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($vendorId = $request->input('vendor_id')) {
            $query->whereHas('rfqVendors', fn($vq) => $vq->where('vendor_id', $vendorId));
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $rfqs    = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $rfqs->items(),
            'meta'    => [
                'current_page' => $rfqs->currentPage(),
                'last_page'    => $rfqs->lastPage(),
                'per_page'     => $rfqs->perPage(),
                'total'        => $rfqs->total(),
            ],
        ]);
    }

    /**
     * POST /api/purchase/rfqs
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rfq_date'                => ['required', 'date'],
            'purchase_requisition_id' => ['nullable', 'integer'],
            'notes'                   => ['nullable', 'string'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.product_id'      => ['required', 'integer'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.0001'],
            'items.*.estimated_cost'  => ['nullable', 'numeric', 'min:0'],
            'items.*.vendor_ids'      => ['required', 'array', 'min:1'],
            'items.*.vendor_ids.*'    => ['integer'],
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
            $rfq = $this->rfqService->storeRfq($validated, $tenantId);
            return response()->json([
                'success' => true,
                'message' => "RFQ {$rfq->rfq_number} created successfully.",
                'data'    => $rfq->load(['items.product', 'rfqVendors.vendor', 'creator']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create RFQ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/purchase/rfqs/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $rfq = PurchaseRfq::where('tenant_id', $tenantId)
            ->with(['creator', 'requisition', 'items.product', 'rfqVendors.vendor', 'rfqVendors.rates.product'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $rfq,
        ]);
    }

    /**
     * PUT/PATCH /api/purchase/rfqs/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $rfq        = PurchaseRfq::where('tenant_id', $tenantId)->findOrFail($id);

        if ($rfq->status !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft RFQs can be updated.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'rfq_date'                => ['sometimes', 'required', 'date'],
            'purchase_requisition_id' => ['nullable', 'integer'],
            'notes'                   => ['nullable', 'string'],
            'items'                   => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_id'      => ['required', 'integer'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.0001'],
            'items.*.estimated_cost'  => ['nullable', 'numeric', 'min:0'],
            'items.*.vendor_ids'      => ['required', 'array', 'min:1'],
            'items.*.vendor_ids.*'    => ['integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $this->rfqService->updateRfq($rfq, $validator->validated(), $tenantId);

        return response()->json([
            'success' => true,
            'message' => 'RFQ updated successfully.',
            'data'    => $rfq->fresh(['items.product', 'rfqVendors.vendor']),
        ]);
    }

    /**
     * DELETE /api/purchase/rfqs/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $rfq        = PurchaseRfq::where('tenant_id', $tenantId)->findOrFail($id);

        if ($rfq->status !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft RFQs can be deleted.',
            ], 422);
        }

        $this->rfqRepo->delete($rfq);

        return response()->json([
            'success' => true,
            'message' => 'RFQ deleted successfully.',
        ]);
    }

    /**
     * POST /api/purchase/rfqs/{id}/store-quotes
     */
    public function storeQuotes(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $rfq        = PurchaseRfq::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'quotes'                     => ['required', 'array'],
            'quotes.*.quotation_number'  => ['nullable', 'string', 'max:255'],
            'quotes.*.rates'             => ['required', 'array'],
            'quotes.*.rates.*.rate'      => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($validated, $rfq) {
            foreach ($validated['quotes'] as $vendorId => $qData) {
                $rfqVendor = PurchaseRfqVendor::where('purchase_rfq_id', $rfq->id)
                    ->where('vendor_id', $vendorId)
                    ->first();

                if ($rfqVendor) {
                    $rfqVendor->update([
                        'quotation_number' => $qData['quotation_number'] ?? null,
                        'status'           => 'Submitted',
                        'submitted_at'     => now(),
                    ]);

                    foreach ($qData['rates'] as $productId => $rData) {
                        PurchaseRfqVendorRate::withoutGlobalScopes()->updateOrCreate(
                            [
                                'purchase_rfq_vendor_id' => $rfqVendor->id,
                                'product_id'             => $productId,
                            ],
                            [
                                'tenant_id'  => $rfq->tenant_id,
                                'company_id' => $rfq->company_id,
                                'branch_id'  => $rfq->branch_id,
                                'rate'       => $rData['rate'],
                            ]
                        );
                    }
                }
            }

            if ($rfq->status === 'Draft') {
                $rfq->update(['status' => 'Received']);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Supplier quotes recorded successfully.',
            'data'    => $rfq->fresh(['rfqVendors.vendor', 'rfqVendors.rates']),
        ]);
    }

    /**
     * POST /api/purchase/rfqs/{id}/confirm
     */
    public function confirmRfq(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $rfq        = PurchaseRfq::where('tenant_id', $tenantId)->findOrFail($id);

        $rfq->update(['status' => 'Confirmed']);

        return response()->json([
            'success' => true,
            'message' => "RFQ {$rfq->rfq_number} confirmed.",
            'data'    => $rfq,
        ]);
    }

    /**
     * POST /api/purchase/rfqs/{id}/create-po
     */
    public function createPo(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $rfq        = PurchaseRfq::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'vendor_id'                 => ['required', 'integer'],
            'location'                  => ['nullable', 'string', 'max:255'],
            'reference'                 => ['nullable', 'string', 'max:255'],
            'supplier_quotation_number' => ['nullable', 'string', 'max:255'],
            'date'                      => ['required', 'date'],
            'delivery_date'             => ['nullable', 'date'],
            'discount_type'             => ['nullable', 'string'],
            'tax_type'                  => ['nullable', 'string'],
            'gst_type'                  => ['nullable', 'string'],
            'subtotal'                  => ['required', 'numeric'],
            'discount_amount'           => ['nullable', 'numeric'],
            'tax_amount'                => ['nullable', 'numeric'],
            'grand_total'               => ['required', 'numeric'],
            'notes'                     => ['nullable', 'string'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.product_id'        => ['required', 'integer'],
            'items.*.quantity'          => ['required', 'numeric'],
            'items.*.rate'              => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        try {
            $po = $this->rfqService->createPoFromRfq($rfq, $validator->validated(), $tenantId);
            return response()->json([
                'success' => true,
                'message' => "Draft PO {$po->purchase_order_number} created successfully from RFQ {$rfq->rfq_number}.",
                'data'    => $po->load(['vendor', 'items.product']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/purchase/rfqs/savings-dashboard
     */
    public function savingsDashboard(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $user       = auth()->user();
        $data       = $this->rfqService->getSavingsDashboardData($request, $tenantId, $user);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/purchase/rfqs/savings-details/{orderId}
     */
    public function poSavingsDetails(int $orderId): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order      = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['vendor', 'creator', 'items.product', 'requisition'])
            ->findOrFail($orderId);

        $rfqNumber = $order->reference ? str_replace('RFQ: ', '', $order->reference) : null;
        $rfq       = null;
        if ($rfqNumber) {
            $rfq = PurchaseRfq::where('tenant_id', $tenantId)
                ->where('rfq_number', $rfqNumber)
                ->with(['rfqVendors.vendor', 'rfqVendors.rates.product', 'items.product'])
                ->first();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'order' => $order,
                'rfq'   => $rfq,
            ],
        ]);
    }

    /**
     * GET /api/purchase/rfqs/get-requisition-items
     */
    public function getRequisitionItems(Request $request): JsonResponse
    {
        [$tenantId]    = $this->resolveTenantContext();
        $requisitionId = (int)$request->query('requisition_id');
        $requisition   = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with(['items.product'])
            ->find($requisitionId);

        if (!$requisition) {
            return response()->json(['success' => false, 'message' => 'Requisition not found.'], 404);
        }

        $items = [];
        foreach ($requisition->items as $item) {
            $items[] = [
                'product_id'     => $item->product_id,
                'product_name'   => $item->product ? ($item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : '')) : ($item->description ?? 'Unknown Item'),
                'quantity'       => (float)$item->quantity,
                'estimated_cost' => (float)$item->estimated_cost,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }

    /**
     * GET /api/purchase/rfqs/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return Excel::download(
            new PurchaseRfqExport($tenantId, $request->all()),
            'rfqs_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
