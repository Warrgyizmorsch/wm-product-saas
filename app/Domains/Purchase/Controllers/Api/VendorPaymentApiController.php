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
    public function __construct(
        protected \App\Domains\Purchase\Services\VendorPaymentService $paymentService
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
     * GET /api/purchase/payments/meta
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

        $unpaidBills = [];
        $availableAdvance = 0.0;
        if ($vendorId = $request->input('vendor_id')) {
            $unpaidBills = VendorBill::where('tenant_id', $tenantId)
                ->where('vendor_id', $vendorId)
                ->where('due_amount', '>', 0)
                ->where('status', '!=', 'Cancelled')
                ->orderBy('bill_date')
                ->get(['id', 'bill_number', 'bill_date', 'due_date', 'grand_total', 'paid_amount', 'due_amount']);

            $availableAdvance = app(\App\Domains\Purchase\Services\VendorBillService::class)->getAvailableVendorAdvance((int)$vendorId, $tenantId);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_methods'   => ['Bank Transfer', 'Cheque', 'Cash', 'UPI / QR', 'RTGS / NEFT', 'Online Gateway'],
                'statuses'          => ['Posted', 'Paid', 'Pending', 'Cleared', 'Failed', 'Cancelled'],
                'bank_accounts'     => $bankAccounts,
                'vendors'           => $vendors,
                'unpaid_bills'      => $unpaidBills,
                'available_advance' => $availableAdvance,
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
            ->with(['vendor:id,name,company_name', 'bankAccount:id,code,name', 'allocations.vendorBill']);

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
            'vendor_id'           => ['required', 'integer'],
            'vendor_bill_id'      => ['nullable', 'integer'],
            'payment_date'        => ['required', 'date'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'payment_method'      => ['required', 'string'],
            'bank_account_id'     => ['nullable', 'integer'],
            'reference_number'    => ['nullable', 'string', 'max:100'],
            'auto_apply_advance'  => ['nullable', 'boolean'],
            'notes'               => ['nullable', 'string'],
            'allocations'         => ['nullable', 'array'],
            'allocations.*.vendor_bill_id'   => ['required_with:allocations', 'integer'],
            'allocations.*.amount'           => ['nullable', 'numeric', 'min:0.01'],
            'allocations.*.allocated_amount' => ['nullable', 'numeric', 'min:0.01'],
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

        // Normalize allocation keys for VendorPaymentService
        if (!empty($validated['allocations'])) {
            foreach ($validated['allocations'] as &$alloc) {
                if (!isset($alloc['allocated_amount']) && isset($alloc['amount'])) {
                    $alloc['allocated_amount'] = $alloc['amount'];
                }
            }
            unset($alloc);
        }

        // Check for 3-way match holds on linked bills
        $linkedBillIds = [];
        if (!empty($validated['vendor_bill_id'])) {
            $linkedBillIds[] = $validated['vendor_bill_id'];
        }
        if (!empty($validated['allocations'])) {
            $linkedBillIds = array_merge($linkedBillIds, array_column($validated['allocations'], 'vendor_bill_id'));
        }

        if (!empty($linkedBillIds)) {
            $heldBill = VendorBill::where('tenant_id', $tenantId)
                ->whereIn('id', $linkedBillIds)
                ->where('status', VendorBill::STATUS_ON_HOLD)
                ->value('bill_number');
            if ($heldBill) {
                return response()->json([
                    'success' => false,
                    'message' => "Bill {$heldBill} is on hold for a PO/GRN mismatch and cannot be paid until it is released.",
                ], 422);
            }
        }

        // Balance due validation for linked bill
        $targetBillId = $validated['vendor_bill_id'] ?? ($validated['allocations'][0]['vendor_bill_id'] ?? null);
        if ($targetBillId) {
            $targetBill = VendorBill::where('tenant_id', $tenantId)->find($targetBillId);
            if ($targetBill) {
                $maxPayable = (float) $targetBill->due_amount;
                $attemptedAmount = (float) ($validated['allocations'][0]['allocated_amount'] ?? $validated['amount']);
                if ($attemptedAmount > ($maxPayable + 0.01)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Payment amount (" . format_currency($attemptedAmount) . ") cannot exceed the bill's net balance due of " . format_currency($maxPayable) . ".",
                        'errors'  => [
                            'amount' => ["Payment amount cannot exceed the bill's net balance due of " . format_currency($maxPayable) . "."]
                        ]
                    ], 422);
                }
            }
        }

        try {
            $payment = $this->paymentService->recordPayment($validated, $tenantId);

            return response()->json([
                'success' => true,
                'message' => "Vendor payment {$payment->payment_number} recorded successfully",
                'data'    => $payment->fresh(['vendor', 'bankAccount', 'allocations.vendorBill']),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record vendor payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/purchase/payments/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $payment    = VendorPayment::where('tenant_id', $tenantId)
            ->with(['vendor', 'bankAccount', 'creator', 'purchaseOrder', 'allocations.vendorBill.allocations.vendorPayment', 'allocations.vendorBill.allocations.payment'])
            ->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor Payment not found',
            ], 404);
        }

        // Calculate advance adjusted across linked bills matching the web voucher view
        $allLinkedAdvanceSettled = 0.0;
        $advanceVouchers = [];

        foreach ($payment->allocations as $alloc) {
            $bill = $alloc->vendorBill ?? $alloc->bill;
            if ($bill) {
                $otherAllocs = $bill->allocations->filter(function($a) use ($payment) {
                    return $a->vendor_payment_id != $payment->id && ($a->vendorPayment?->payment_type === 'Advance' || $a->payment?->payment_type === 'Advance');
                });
                foreach ($otherAllocs as $oa) {
                    $allLinkedAdvanceSettled += (float)$oa->allocated_amount;
                    $vNum = $oa->vendorPayment?->payment_number ?? $oa->payment?->payment_number;
                    if ($vNum && !in_array($vNum, $advanceVouchers)) {
                        $advanceVouchers[] = $vNum;
                    }
                }
            }
        }

        $paymentData = $payment->toArray();
        $paymentData['advance_adjusted_total'] = round($allLinkedAdvanceSettled, 4);
        $paymentData['advance_adjusted_vouchers'] = $advanceVouchers;
        $paymentData['total_settled_with_advance'] = round((float)$payment->amount + $allLinkedAdvanceSettled, 4);

        return response()->json([
            'success' => true,
            'data'    => $paymentData,
        ]);
    }

    /**
     * DELETE /api/purchase/payments/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $payment    = VendorPayment::where('tenant_id', $tenantId)->with('allocations')->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor Payment not found',
            ], 404);
        }

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
