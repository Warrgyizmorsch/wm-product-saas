<?php

namespace App\Domains\Purchase\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Repositories\PurchaseAdvancePaymentRepository;
use App\Domains\Purchase\Repositories\PurchaseOrderRepository;
use App\Domains\Purchase\Services\PurchaseAdvancePaymentService;
use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PurchaseAdvancePaymentApiController extends Controller
{
    public function __construct(
        protected PurchaseAdvancePaymentRepository $advanceRepo,
        protected PurchaseOrderRepository $orderRepo,
        protected PurchaseAdvancePaymentService $advanceService
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
     * GET /api/purchase/advances
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = PurchaseAdvancePayment::query()
            ->where('tenant_id', $tenantId)
            ->with(['vendor:id,name,company_name', 'purchaseOrder:id,purchase_order_number']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('advance_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%")
                         ->orWhere('company_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($vendorId = $request->input('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }
        if ($poId = $request->input('purchase_order_id')) {
            $query->where('purchase_order_id', $poId);
        }

        $perPage  = min((int)$request->input('per_page', 15), 100);
        $advances = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $advances->items(),
            'meta'    => [
                'current_page' => $advances->currentPage(),
                'last_page'    => $advances->lastPage(),
                'per_page'     => $advances->perPage(),
                'total'        => $advances->total(),
            ],
        ]);
    }

    /**
     * POST /api/purchase/advances
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_date'      => ['required', 'date'],
            'vendor_id'         => ['required', 'integer'],
            'purchase_order_id' => ['nullable', 'integer'],
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'payment_method'    => ['required', 'string', 'in:Bank Transfer,Cash,Cheque,UPI,Credit Card,RTGS / NEFT'],
            'reference_number'  => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        [$tenantId] = $this->resolveTenantContext();

        try {
            $advance = $this->advanceService->recordAdvancePayment($validator->validated(), $tenantId);
            return response()->json([
                'success' => true,
                'message' => "Advance Payment {$advance->advance_number} recorded successfully.",
                'data'    => $advance->load(['vendor', 'purchaseOrder']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to record advance payment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/purchase/advances/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $advance    = PurchaseAdvancePayment::where('tenant_id', $tenantId)
            ->with(['vendor', 'purchaseOrder'])
            ->find($id);

        if (!$advance) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Advance Payment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $advance,
        ]);
    }
}
