<?php

namespace App\Domains\Purchase\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Purchase\Models\VendorPaymentAllocation;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class VendorPaymentApiController extends Controller
{
    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/purchase/payments/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $vendors    = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'company_name', 'email', 'phone']);

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_methods' => ['Bank Transfer', 'Cheque', 'Cash', 'UPI / QR', 'RTGS / NEFT', 'Online Gateway'],
                'statuses'        => ['Paid', 'Pending', 'Cleared', 'Failed', 'Cancelled'],
                'vendors'         => $vendors,
            ],
        ]);
    }

    /**
     * GET /api/purchase/payments
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = VendorPayment::query()
            ->where('tenant_id', $tenantId)
            ->with(['vendor:id,name,company_name', 'allocations.vendorBill']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
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

        $perPage  = min((int)$request->input('per_page', 15), 100);
        $payments = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $payments->items(),
            'meta'    => [
                'current_page' => $payments->currentPage(),
                'last_page'    => $payments->lastPage(),
                'per_page'     => $payments->perPage(),
                'total'        => $payments->total(),
            ],
        ]);
    }

    /**
     * POST /api/purchase/payments
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vendor_id'         => ['required', 'integer'],
            'payment_date'      => ['required', 'date'],
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'payment_method'    => ['required', 'string'],
            'reference_number'  => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string'],
            'allocations'       => ['nullable', 'array'],
            'allocations.*.vendor_bill_id' => ['required', 'integer'],
            'allocations.*.amount'         => ['required', 'numeric', 'min:0.01'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $validated = $validator->validated();

        $payment = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId) {
            $lastId    = (VendorPayment::where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
            $payNumber = 'VPAY-' . str_pad((string)$lastId, 5, '0', STR_PAD_LEFT);

            $pay = VendorPayment::create([
                'tenant_id'        => $tenantId,
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'vendor_id'        => $validated['vendor_id'],
                'payment_number'   => $payNumber,
                'payment_date'     => $validated['payment_date'],
                'amount'           => $validated['amount'],
                'payment_method'   => $validated['payment_method'],
                'reference_number' => $validated['reference_number'] ?? null,
                'status'           => 'Paid',
                'notes'            => $validated['notes'] ?? null,
            ]);

            if (!empty($validated['allocations'])) {
                foreach ($validated['allocations'] as $alloc) {
                    $bill = VendorBill::where('tenant_id', $tenantId)->find($alloc['vendor_bill_id']);
                    if ($bill) {
                        $dueAmt       = (float)($bill->due_amount ?? $bill->balance_due);
                        $allocatedAmt = min((float)$alloc['amount'], $dueAmt);
                        VendorPaymentAllocation::create([
                            'tenant_id'         => $tenantId,
                            'company_id'        => $companyId,
                            'branch_id'         => $branchId,
                            'vendor_payment_id' => $pay->id,
                            'vendor_bill_id'    => $bill->id,
                            'allocated_amount'  => $allocatedAmt,
                        ]);

                        $bill->paid_amount = (float)($bill->paid_amount ?? 0) + $allocatedAmt;
                        $bill->due_amount  = max(0, (float)($bill->grand_total ?? $bill->total_amount) - (float)$bill->paid_amount);
                        $bill->status      = $bill->due_amount <= 0.001 ? 'Paid' : 'Partially Paid';
                        $bill->save();
                    }
                }
            }

            return $pay->load(['vendor', 'allocations.vendorBill']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Vendor payment recorded successfully',
            'data'    => $payment,
        ], 201);
    }

    /**
     * GET /api/purchase/payments/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $payment    = VendorPayment::where('tenant_id', $tenantId)->with(['vendor', 'allocations.vendorBill'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $payment,
        ]);
    }

    /**
     * DELETE /api/purchase/payments/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $payment    = VendorPayment::where('tenant_id', $tenantId)->with('allocations')->findOrFail($id);

        DB::transaction(function () use ($payment) {
            foreach ($payment->allocations as $alloc) {
                $bill = VendorBill::find($alloc->vendor_bill_id);
                if ($bill) {
                    $bill->amount_paid = max(0, (float)$bill->amount_paid - (float)$alloc->allocated_amount);
                    $bill->balance_due = max(0, (float)$bill->total_amount - (float)$bill->amount_paid);
                    $bill->status      = $bill->amount_paid <= 0 ? 'Approved' : 'Partial';
                    $bill->save();
                }
                $alloc->delete();
            }
            $payment->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Vendor payment deleted and bill balances rolled back successfully',
        ]);
    }

    /**
     * GET /api/purchase/payments/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\VendorPaymentExport($tenantId, $request->all()),
            'vendor_payments_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
