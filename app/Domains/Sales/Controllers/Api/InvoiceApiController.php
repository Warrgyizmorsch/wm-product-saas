<?php

namespace App\Domains\Sales\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InvoiceApiController extends Controller
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
     * GET /api/sales/invoices/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $customers = Customer::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'company_name', 'email', 'phone', 'gstin']);
        $products  = Product::where('tenant_id', $tenantId)->sellable()->orderBy('name')->get(['id', 'name', 'sku', 'selling_price', 'gst_rate']);

        return response()->json([
            'success' => true,
            'data'    => [
                'statuses'       => ['Draft', 'Sent', 'Partial', 'Paid', 'Overdue', 'Void'],
                'discount_types' => ['fixed', 'percentage'],
                'tax_types'      => ['exclusive', 'inclusive'],
                'gst_types'      => ['cgst_sgst', 'igst', 'none'],
                'payment_terms'  => ['Immediate', 'Net 15', 'Net 30', 'Net 45', 'Net 60', 'Due on Receipt'],
                'customers'      => $customers,
                'products'       => $products,
            ],
        ]);
    }

    /**
     * GET /api/sales/invoices
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer:id,name,company_name,email,phone']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('company_name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('invoice_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('invoice_date', '<=', $toDate);
        }
        if ($request->boolean('unpaid_only')) {
            $query->where('balance_due', '>', 0);
        }

        // Sorting
        $sortBy  = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'invoice_number', 'invoice_date', 'due_date', 'status', 'total_amount', 'amount_paid', 'balance_due', 'created_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage  = min((int)$request->input('per_page', 15), 100);
        $invoices = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $invoices->items(),
            'meta'    => [
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
                'per_page'     => $invoices->perPage(),
                'total'        => $invoices->total(),
            ],
        ]);
    }

    /**
     * POST /api/sales/invoices
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id'       => ['required', 'integer'],
            'sales_order_id'    => ['nullable', 'integer'],
            'invoice_date'      => ['required', 'date'],
            'due_date'          => ['nullable', 'date'],
            'payment_terms'     => ['nullable', 'string'],
            'discount_type'     => ['nullable', 'in:fixed,percentage'],
            'tax_type'          => ['nullable', 'in:exclusive,inclusive'],
            'gst_type'          => ['nullable', 'in:cgst_sgst,igst,none'],
            'freight_terms'     => ['nullable', 'string'],
            'freight_amount'    => ['nullable', 'numeric'],
            'adjustment'        => ['nullable', 'numeric'],
            'notes'             => ['nullable', 'string'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['nullable', 'integer'],
            'items.*.item_name'   => ['required', 'string', 'max:255'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0'],
            'items.*.discount'    => ['nullable', 'numeric', 'min:0'],
            'items.*.description' => ['nullable', 'string'],
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

        $invoice = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId) {
            $lastId    = (Invoice::where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
            $invNumber = 'INV-' . str_pad((string)$lastId, 5, '0', STR_PAD_LEFT);

            $subtotal    = 0;
            $totalTax    = 0;
            $itemRecords = [];

            foreach ($validated['items'] as $row) {
                $qty       = (float)$row['quantity'];
                $price     = (float)$row['unit_price'];
                $discount  = (float)($row['discount'] ?? 0);
                $taxRate   = (float)($row['tax_rate'] ?? 0);

                $lineSubtotal = max(0, ($qty * $price) - $discount);
                $lineTax      = $lineSubtotal * ($taxRate / 100);
                $lineTotal    = $lineSubtotal + $lineTax;

                $subtotal += $lineSubtotal;
                $totalTax += $lineTax;

                $itemRecords[] = [
                    'tenant_id'    => $tenantId,
                    'company_id'   => $companyId,
                    'branch_id'    => $branchId,
                    'product_id'   => $row['product_id'] ?? null,
                    'item_name'    => $row['item_name'],
                    'description'  => $row['description'] ?? null,
                    'quantity'     => $qty,
                    'unit_price'   => $price,
                    'tax_rate'     => $taxRate,
                    'discount'     => $discount,
                    'amount'       => $lineTotal,
                ];
            }

            $gstType = $validated['gst_type'] ?? 'cgst_sgst';
            $cgstAmt = 0.0;
            $sgstAmt = 0.0;
            $igstAmt = 0.0;
            if ($gstType === 'cgst_sgst') {
                $cgstAmt = round($totalTax / 2, 2);
                $sgstAmt = round($totalTax - $cgstAmt, 2);
            } elseif ($gstType === 'igst') {
                $igstAmt = $totalTax;
            }

            $freight    = (float)($validated['freight_amount'] ?? 0);
            $adjustment = (float)($validated['adjustment'] ?? 0);
            $grandTotal = $subtotal + $totalTax + $freight + $adjustment;

            $inv = Invoice::create([
                'tenant_id'        => $tenantId,
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'customer_id'      => $validated['customer_id'],
                'sales_order_id'   => $validated['sales_order_id'] ?? null,
                'invoice_number'   => $invNumber,
                'invoice_date'     => $validated['invoice_date'],
                'due_date'         => $validated['due_date'] ?? now()->addDays(30)->toDateString(),
                'payment_terms'    => $validated['payment_terms'] ?? null,
                'status'           => 'Draft',
                'discount_type'    => $validated['discount_type'] ?? 'fixed',
                'discount_amount'  => 0.00,
                'tax_type'         => $validated['tax_type'] ?? 'exclusive',
                'gst_type'         => $gstType,
                'cgst_amount'      => $cgstAmt,
                'sgst_amount'      => $sgstAmt,
                'igst_amount'      => $igstAmt,
                'subtotal'         => $subtotal,
                'tax_amount'       => $totalTax,
                'freight_terms'    => $validated['freight_terms'] ?? 'To Pay',
                'freight_amount'   => $freight,
                'adjustment'       => $adjustment,
                'total_amount'     => $grandTotal,
                'amount_paid'      => 0.00,
                'balance_due'      => $grandTotal,
                'notes'            => $validated['notes'] ?? null,
            ]);

            foreach ($itemRecords as $itemData) {
                $itemData['invoice_id'] = $inv->id;
                InvoiceItem::create($itemData);
            }

            return $inv->load(['items.product', 'customer']);
        });

        event(new \App\Domains\Sales\Events\InvoicePosted($invoice));

        return response()->json([
            'success' => true,
            'message' => 'Invoice created successfully',
            'data'    => $invoice->fresh(['items.product', 'customer']),
        ], 201);
    }

    /**
     * GET /api/sales/invoices/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $invoice = Invoice::where('tenant_id', $tenantId)
            ->with(['customer', 'salesOrder', 'items.product'])
            ->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $invoice,
        ]);
    }

    /**
     * PATCH /api/sales/invoices/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $invoice    = Invoice::where('tenant_id', $tenantId)->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:Draft,Sent,Partial,Paid,Overdue,Void'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $invoice->status = $request->input('status');
        if ($invoice->status === 'Paid') {
            $invoice->amount_paid = $invoice->total_amount;
            $invoice->balance_due = 0;
        }
        $invoice->save();

        return response()->json([
            'success' => true,
            'message' => "Invoice status updated to {$invoice->status}",
            'data'    => $invoice,
        ]);
    }

    /**
     * DELETE /api/sales/invoices/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $invoice    = Invoice::where('tenant_id', $tenantId)->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        if ($invoice->amount_paid > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an invoice that has payments recorded.',
            ], 422);
        }

        InvoiceItem::where('invoice_id', $invoice->id)->delete();
        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invoice deleted successfully',
        ]);
    }

    /**
     * POST /api/sales/invoices/{id}/post
     * Post/Lock invoice and sync to accounting ledger.
     */
    public function post(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $invoice = Invoice::where('tenant_id', $tenantId)->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        $this->authorize('update', $invoice);

        $invoice->status = 'Posted';
        $invoice->save();

        event(new \App\Domains\Sales\Events\InvoicePosted($invoice));

        return response()->json([
            'success' => true,
            'message' => "Invoice {$invoice->invoice_number} posted successfully.",
            'data'    => $invoice->fresh(['customer', 'items.product']),
        ]);
    }

    /**
     * POST /api/sales/invoices/{id}/pay
     * Record payment directly against an invoice.
     */
    public function pay(Request $request, int $id): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $invoice = Invoice::where('tenant_id', $tenantId)->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        $this->authorize('update', $invoice);

        $validator = Validator::make($request->all(), [
            'amount'         => ['required', 'numeric', 'min:0.01', "max:{$invoice->balance_due}"],
            'payment_date'   => ['required', 'date'],
            'payment_method' => ['required', 'string'],
            'reference_no'   => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $payment = DB::transaction(function () use ($invoice, $validated, $tenantId, $companyId, $branchId) {
            $paymentNo = 'PAY-' . strtoupper(bin2hex(random_bytes(4)));

            $payment = \App\Domains\Sales\Models\CustomerPayment::create([
                'tenant_id'      => $tenantId,
                'company_id'     => $companyId,
                'branch_id'      => $branchId,
                'customer_id'    => $invoice->customer_id,
                'payment_number' => $paymentNo,
                'payment_date'   => $validated['payment_date'],
                'amount'         => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_no'   => $validated['reference_no'] ?? null,
                'status'         => 'Completed',
                'notes'          => $validated['notes'] ?? null,
            ]);

            \App\Domains\Sales\Models\PaymentAllocation::create([
                'tenant_id'           => $tenantId,
                'company_id'          => $companyId,
                'branch_id'           => $branchId,
                'customer_payment_id' => $payment->id,
                'sales_order_id'      => $invoice->sales_order_id,
                'invoice_id'          => $invoice->id,
                'allocated_amount'    => $validated['amount'],
            ]);

            $invoice->amount_paid += $validated['amount'];
            $invoice->balance_due  = max(0, $invoice->total_amount - $invoice->amount_paid);
            $invoice->status       = $invoice->balance_due <= 0 ? 'Paid' : 'Partial';
            $invoice->save();

            return $payment;
        });

        event(new \App\Domains\Sales\Events\CustomerPaymentReceived($payment));

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully.',
            'data'    => [
                'payment' => $payment,
                'invoice' => $invoice->fresh(),
            ],
        ], 201);
    }

    /**
     * GET /api/sales/invoices/export
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\InvoiceExport($tenantId, $request->all()),
            'invoices_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    /**
     * POST /api/sales/invoices/{id}/send-email
     */
    public function sendEmail(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $invoice = Invoice::where('tenant_id', $tenantId)->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        $this->authorize('view', $invoice);

        $validator = Validator::make($request->all(), [
            'to_email'   => 'required|email',
            'subject'    => 'required|string|max:255',
            'body_html'  => 'required|string',
            'account_id' => 'nullable|exists:email_configurations,id',
            'custom_pdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            /** @var \App\Services\EmailService $emailService */
            $emailService = app(\App\Services\EmailService::class);
            $emailService->sendInvoiceEmail($invoice, $request->all(), $request->file('custom_pdf'));

            return response()->json([
                'success' => true,
                'message' => "Invoice {$invoice->invoice_number} sent successfully to {$request->input('to_email')}",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send Invoice Email: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/sales/invoices/{id}/send-whatsapp
     */
    public function sendWhatsApp(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $invoice = Invoice::where('tenant_id', $tenantId)->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        $this->authorize('view', $invoice);

        $validator = Validator::make($request->all(), [
            'phone'      => 'required|string',
            'caption'    => 'nullable|string',
            'custom_pdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            /** @var \App\Services\WhatsAppService $waService */
            $waService = app(\App\Services\WhatsAppService::class);
            $result = $waService->sendInvoice(
                invoice: $invoice,
                mobile: $request->input('phone'),
                customCaption: $request->input('caption'),
                customPdfFile: $request->file('custom_pdf')
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to send WhatsApp message.',
                ], 422);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send WhatsApp: ' . $e->getMessage(),
            ], 422);
        }
    }
}
