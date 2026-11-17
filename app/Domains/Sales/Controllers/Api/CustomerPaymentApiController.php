<?php

namespace App\Domains\Sales\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\PaymentAllocation;
use App\Domains\Sales\Models\Invoice;
use App\Domains\CRM\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CustomerPaymentApiController extends Controller
{
    private function resolveTenantContext(): array
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id ?? (tenant_id() ?? 1);
        $companyId = company_id() ?? $user?->company_id;
        $branchId = branch_id() ?? $user?->branch_id;

        return [(int) $tenantId, (int) $companyId, $branchId ? (int) $branchId : null];
    }

    /**
     * GET /api/sales/payments/meta
     */
    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', CustomerPayment::class);
        [$tenantId] = $this->resolveTenantContext();
        $customers = Customer::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'company_name', 'email', 'phone']);

        return response()->json([
            'success' => true,
            'data' => [
                'payment_methods' => ['Cash', 'Bank Transfer', 'Cheque', 'UPI / QR', 'Credit Card', 'Debit Card', 'Online Gateway'],
                'statuses' => ['Received', 'Pending', 'Cleared', 'Failed', 'Refunded'],
                'customers' => $customers,
            ],
        ]);
    }

    /**
     * GET /api/sales/payments
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerPayment::class);
        [$tenantId] = $this->resolveTenantContext();

        $query = CustomerPayment::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer:id,name,company_name,email,phone', 'allocations.invoice']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($method = $request->input('payment_method')) {
            $query->where('payment_method', $method);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $payments = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * POST /api/sales/payments
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CustomerPayment::class);

        $validator = Validator::make($request->all(), [
            'customer_id' => ['required', 'integer'],
            'invoice_id' => ['nullable', 'integer'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'bank_account_id' => ['nullable', 'integer'],
            'tds_amount' => ['nullable', 'numeric', 'min:0'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required_with:allocations', 'integer'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'allocations.*.allocated_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $validated = $validator->validated();

        // Normalize allocation keys
        if (!empty($validated['allocations'])) {
            foreach ($validated['allocations'] as &$alloc) {
                if (!isset($alloc['amount']) && isset($alloc['allocated_amount'])) {
                    $alloc['amount'] = $alloc['allocated_amount'];
                }
            }
            unset($alloc);
        }

        // If invoice_id is passed without allocations array, map it to allocations
        if (empty($validated['allocations']) && !empty($validated['invoice_id'])) {
            $validated['allocations'] = [
                [
                    'invoice_id' => (int) $validated['invoice_id'],
                    'amount'     => (float) $validated['amount'],
                ]
            ];
        }

        // Balance due validation for linked invoice
        $targetInvoiceId = $validated['invoice_id'] ?? ($validated['allocations'][0]['invoice_id'] ?? null);
        if ($targetInvoiceId) {
            $targetInvoice = Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($targetInvoiceId)
                ?? Invoice::withoutGlobalScopes()->find($targetInvoiceId);
            if ($targetInvoice) {
                $maxPayable = (float) $targetInvoice->balance_due;
                if ($maxPayable <= 0 && (float) $targetInvoice->total_amount > (float) $targetInvoice->amount_paid) {
                    $maxPayable = max(0, (float) $targetInvoice->total_amount - (float) $targetInvoice->amount_paid);
                }
                $attemptedAmount = (float) ($validated['allocations'][0]['amount'] ?? $validated['amount']);
                if (!empty($validated['tds_amount'])) {
                    $attemptedAmount += (float) $validated['tds_amount'];
                }
                if ($attemptedAmount > ($maxPayable + 0.01)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Payment amount (" . format_currency($attemptedAmount) . ") cannot exceed the invoice's net balance due of " . format_currency($maxPayable) . ".",
                        'errors'  => [
                            'amount' => ["Payment amount cannot exceed the invoice's net balance due of " . format_currency($maxPayable) . "."]
                        ]
                    ], 422);
                }
            }
        }

        // Also check if any individual allocation exceeds its invoice's balance_due
        if (!empty($validated['allocations'])) {
            foreach ($validated['allocations'] as $alloc) {
                $inv = Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($alloc['invoice_id'])
                    ?? Invoice::withoutGlobalScopes()->find($alloc['invoice_id']);
                if ($inv) {
                    $invMax = (float) $inv->balance_due;
                    if ($invMax <= 0 && (float) $inv->total_amount > (float) $inv->amount_paid) {
                        $invMax = max(0, (float) $inv->total_amount - (float) $inv->amount_paid);
                    }
                    $allocAmount = (float) $alloc['amount'];
                    if ($allocAmount > ($invMax + 0.01)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Allocated amount (" . format_currency($allocAmount) . ") cannot exceed invoice {$inv->invoice_number}'s net balance due of " . format_currency($invMax) . ".",
                            'errors'  => [
                                'amount' => ["Allocated amount cannot exceed invoice {$inv->invoice_number}'s net balance due of " . format_currency($invMax) . "."]
                            ]
                        ], 422);
                    }
                }
            }
        }

        $payment = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId) {
            $lastId = (CustomerPayment::withoutGlobalScopes()->where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
            $payNumber = 'PAY-' . str_pad((string) $lastId, 4, '0', STR_PAD_LEFT);
            while (CustomerPayment::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('payment_number', $payNumber)->exists()) {
                $lastId++;
                $payNumber = 'PAY-' . str_pad((string) $lastId, 4, '0', STR_PAD_LEFT);
            }

            $pay = CustomerPayment::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $validated['customer_id'],
                'payment_number' => $payNumber,
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'tds_amount' => $validated['tds_amount'] ?? 0,
                'reference_no' => $validated['reference_no'] ?? null,
                'status' => 'Received',
                'notes' => $validated['notes'] ?? null,
            ]);

            if (!empty($validated['allocations'])) {
                foreach ($validated['allocations'] as $alloc) {
                    $invoice = Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($alloc['invoice_id'])
                        ?? Invoice::withoutGlobalScopes()->find($alloc['invoice_id']);
                    if ($invoice) {
                        $allocatedAmt = min((float) $alloc['amount'], (float) $invoice->balance_due);
                        PaymentAllocation::create([
                            'tenant_id' => $tenantId,
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'customer_payment_id' => $pay->id,
                            'sales_order_id' => $invoice->sales_order_id,
                            'invoice_id' => $invoice->id,
                            'allocated_amount' => $allocatedAmt,
                        ]);

                        $invoice->amount_paid += $allocatedAmt;
                        $invoice->balance_due = max(0, (float) $invoice->total_amount - (float) $invoice->amount_paid);
                        $invoice->status = $invoice->balance_due <= 0 ? 'Paid' : 'Partial';
                        $invoice->save();
                    }
                }
            }

            return $pay->load(['customer', 'allocations.invoice']);
        });

        event(new \App\Domains\Sales\Events\CustomerPaymentReceived($payment));

        return response()->json([
            'success' => true,
            'message' => 'Customer payment recorded successfully',
            'data' => $payment->fresh(['customer', 'allocations.invoice']),
        ], 201);
    }

    /**
     * GET /api/sales/payments/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $payment = CustomerPayment::where('tenant_id', $tenantId)->with(['customer', 'allocations.invoice'])->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Customer Payment not found',
            ], 404);
        }

        $authUser = auth()->user();
        $ownerId = $payment->customer?->crmAccount?->owner_id;

        if ($authUser && !app(\App\Services\Access\AccessService::class)->allows($authUser, 'sales.payments.view', [
            'tenant_id' => $tenantId,
            'owner_id'  => $ownerId,
        ])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to view this payment.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }

    /**
     * POST /api/sales/payments/{id}/confirm
     */
    public function confirm(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $payment = CustomerPayment::where('tenant_id', $tenantId)->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Customer Payment not found',
            ], 404);
        }

        $authUser = auth()->user();
        $ownerId = $payment->customer?->crmAccount?->owner_id;

        if ($authUser && !app(\App\Services\Access\AccessService::class)->allows($authUser, 'sales.payments.view', [
            'tenant_id' => $tenantId,
            'owner_id'  => $ownerId,
        ])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to view this payment.'], 403);
        }

        $payment->update(['status' => 'Posted']);

        event(new \App\Domains\Sales\Events\CustomerPaymentReceived($payment));

        return response()->json([
            'success' => true,
            'message' => 'Payment confirmed and posted successfully',
            'data' => $payment->fresh(['customer', 'allocations.invoice']),
        ]);
    }

    /**
     * DELETE /api/sales/payments/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $payment = CustomerPayment::where('tenant_id', $tenantId)->with('allocations')->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Customer Payment not found',
            ], 404);
        }

        $authUser = auth()->user();
        $ownerId = $payment->customer?->crmAccount?->owner_id;

        if ($authUser && !app(\App\Services\Access\AccessService::class)->allows($authUser, 'sales.payments.view', [
            'tenant_id' => $tenantId,
            'owner_id'  => $ownerId,
        ])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to view this payment.'], 403);
        }

        DB::transaction(function () use ($payment) {
            foreach ($payment->allocations as $alloc) {
                $inv = Invoice::find($alloc->invoice_id);
                if ($inv) {
                    $inv->amount_paid = max(0, (float) $inv->amount_paid - (float) $alloc->amount);
                    $inv->balance_due = max(0, (float) $inv->total_amount - (float) $inv->amount_paid);
                    $inv->status = $inv->amount_paid <= 0 ? 'Sent' : 'Partial';
                    $inv->save();
                }
                $alloc->delete();
            }
            $payment->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment deleted and invoice balances rolled back successfully',
        ]);
    }

    /**
     * GET /api/sales/payments/export
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', CustomerPayment::class);
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\CustomerPaymentExport($tenantId, $request->all()),
            'customer_payments_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
