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
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/sales/payments/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $customers  = Customer::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'company_name', 'email', 'phone']);

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_methods' => ['Cash', 'Bank Transfer', 'Cheque', 'UPI / QR', 'Credit Card', 'Debit Card', 'Online Gateway'],
                'statuses'        => ['Received', 'Pending', 'Cleared', 'Failed', 'Refunded'],
                'customers'       => $customers,
            ],
        ]);
    }

    /**
     * GET /api/sales/payments
     */
    public function index(Request $request): JsonResponse
    {
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
     * POST /api/sales/payments
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id'    => ['required', 'integer'],
            'payment_date'   => ['required', 'date'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string'],
            'reference_no'   => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string'],
            'allocations'    => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.amount'     => ['required', 'numeric', 'min:0.01'],
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
            $lastId    = (CustomerPayment::where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
            $payNumber = 'PAY-' . str_pad((string)$lastId, 5, '0', STR_PAD_LEFT);

            $pay = CustomerPayment::create([
                'tenant_id'      => $tenantId,
                'company_id'     => $companyId,
                'branch_id'      => $branchId,
                'customer_id'    => $validated['customer_id'],
                'payment_number' => $payNumber,
                'payment_date'   => $validated['payment_date'],
                'amount'         => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_no'   => $validated['reference_no'] ?? null,
                'status'         => 'Received',
                'notes'          => $validated['notes'] ?? null,
            ]);

            if (!empty($validated['allocations'])) {
                foreach ($validated['allocations'] as $alloc) {
                    $invoice = Invoice::where('tenant_id', $tenantId)->find($alloc['invoice_id']);
                    if ($invoice) {
                        $allocatedAmt = min((float)$alloc['amount'], (float)$invoice->balance_due);
                        PaymentAllocation::create([
                            'tenant_id'           => $tenantId,
                            'company_id'          => $companyId,
                            'branch_id'           => $branchId,
                            'customer_payment_id' => $pay->id,
                            'sales_order_id'      => $invoice->sales_order_id,
                            'invoice_id'          => $invoice->id,
                            'allocated_amount'    => $allocatedAmt,
                        ]);

                        $invoice->amount_paid += $allocatedAmt;
                        $invoice->balance_due = max(0, (float)$invoice->total_amount - (float)$invoice->amount_paid);
                        $invoice->status      = $invoice->balance_due <= 0 ? 'Paid' : 'Partial';
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
            'data'    => $payment->fresh(['customer', 'allocations.invoice']),
        ], 201);
    }

    /**
     * GET /api/sales/payments/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $payment    = CustomerPayment::where('tenant_id', $tenantId)->with(['customer', 'allocations.invoice'])->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Customer Payment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $payment,
        ]);
    }

    /**
     * POST /api/sales/payments/{id}/confirm
     */
    public function confirm(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $payment    = CustomerPayment::where('tenant_id', $tenantId)->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Customer Payment not found',
            ], 404);
        }

        $payment->update(['status' => 'Posted']);

        event(new \App\Domains\Sales\Events\CustomerPaymentReceived($payment));

        return response()->json([
            'success' => true,
            'message' => 'Payment confirmed and posted successfully',
            'data'    => $payment->fresh(['customer', 'allocations.invoice']),
        ]);
    }

    /**
     * DELETE /api/sales/payments/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $payment    = CustomerPayment::where('tenant_id', $tenantId)->with('allocations')->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Customer Payment not found',
            ], 404);
        }

        DB::transaction(function () use ($payment) {
            foreach ($payment->allocations as $alloc) {
                $inv = Invoice::find($alloc->invoice_id);
                if ($inv) {
                    $inv->amount_paid = max(0, (float)$inv->amount_paid - (float)$alloc->amount);
                    $inv->balance_due = max(0, (float)$inv->total_amount - (float)$inv->amount_paid);
                    $inv->status      = $inv->amount_paid <= 0 ? 'Sent' : 'Partial';
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
