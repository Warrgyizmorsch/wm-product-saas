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
        $companyId = company_id() ?? $user?->company_id;
        $branchId  = branch_id() ?? $user?->branch_id;

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/purchase/advances/meta
     */
    public function meta(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $vendors    = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'company_name', 'email', 'phone']);

        $bankAccounts = \App\Domains\Accounting\Models\ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('is_cash_or_bank', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $vendorId = $request->input('vendor_id');
        $purchaseOrders = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)
            ->when($vendorId, fn($q) => $q->where('vendor_id', $vendorId))
            ->whereNotIn('status', ['Completed', 'Cancelled', 'Draft'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($po) {
                return [
                    'id'                      => $po->id,
                    'purchase_order_number'   => $po->purchase_order_number,
                    'date'                    => $po->date,
                    'grand_total'             => (float)$po->grand_total,
                    'remaining_advance_limit' => $po->getRemainingAdvanceLimit(),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_methods' => ['Bank Transfer', 'Cash', 'Cheque', 'UPI', 'Credit Card', 'RTGS / NEFT'],
                'bank_accounts'   => $bankAccounts,
                'vendors'         => $vendors,
                'purchase_orders' => $purchaseOrders,
            ],
        ]);
    }

    /**
     * GET /api/purchase/advances
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = PurchaseAdvancePayment::query()
            ->where('tenant_id', $tenantId)
            ->with(['vendor:id,name,company_name', 'bankAccount:id,code,name', 'purchaseOrder:id,purchase_order_number']);

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
            'bank_account_id'   => ['nullable', 'integer'],
            'reference_number'  => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        [$tenantId] = $this->resolveTenantContext();
        $validated  = $validator->validated();

        if (!empty($validated['bank_account_id'])) {
            $isBankLedger = \App\Domains\Accounting\Models\ChartOfAccount::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($validated['bank_account_id'])
                ->where('is_cash_or_bank', true)
                ->exists();
            if (!$isBankLedger) {
                return response()->json(['success' => false, 'message' => 'Please select a valid cash or bank account.'], 422);
            }
        }

        if (!empty($validated['purchase_order_id'])) {
            $po = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)->find($validated['purchase_order_id']);
            if (!$po) {
                return response()->json(['success' => false, 'message' => 'Purchase Order not found.'], 404);
            }
            if (in_array($po->status, ['Completed', 'Cancelled'])) {
                return response()->json(['success' => false, 'message' => "Cannot record advance payment for a {$po->status} Purchase Order."], 422);
            }
            $remainingAdvance = $po->getRemainingAdvanceLimit();
            $enteredBase = convert_to_base((float) $validated['amount']);

            if ($enteredBase > ($remainingAdvance + 0.01)) {
                return response()->json([
                    'success' => false,
                    'message' => "Advance amount cannot exceed remaining PO unbilled advance limit of " . format_currency($remainingAdvance) . "."
                ], 422);
            }
        }

        try {
            $advance = $this->advanceService->recordAdvancePayment($validated, $tenantId);
            return response()->json([
                'success' => true,
                'message' => "Advance Payment {$advance->advance_number} recorded successfully.",
                'data'    => $advance->load(['vendor', 'purchaseOrder', 'bankAccount']),
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
            ->with(['vendor', 'purchaseOrder', 'bankAccount', 'creator'])
            ->find($id);

        if (!$advance) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Advance Payment not found',
            ], 404);
        }

        // Mirror vendor payment allocations
        $vendorPayment = \App\Domains\Purchase\Models\VendorPayment::where('tenant_id', $tenantId)
            ->where('payment_number', $advance->advance_number)
            ->with('allocations.vendorBill')
            ->first();

        $allocatedAmount = (float)($vendorPayment?->allocations?->sum('allocated_amount') ?? 0);
        $unallocatedAmount = max(0.0, (float)$advance->amount - $allocatedAmount);

        $advanceData = $advance->toArray();
        $advanceData['allocated_amount'] = round($allocatedAmount, 4);
        $advanceData['unallocated_amount'] = round($unallocatedAmount, 4);
        $advanceData['allocations'] = $vendorPayment?->allocations ?? [];

        return response()->json([
            'success' => true,
            'data'    => $advanceData,
        ]);
    }
}
