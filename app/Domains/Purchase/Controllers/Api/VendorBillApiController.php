<?php

namespace App\Domains\Purchase\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorBillItem;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class VendorBillApiController extends Controller
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
     * GET /api/purchase/bills/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $vendors    = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'company_name', 'email', 'phone', 'gstin']);

        return response()->json([
            'success' => true,
            'data'    => [
                'statuses' => ['Draft', 'Approved', 'Paid', 'Partial', 'Cancelled'],
                'vendors'  => $vendors,
            ],
        ]);
    }

    /**
     * GET /api/purchase/bills
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = VendorBill::query()
            ->where('tenant_id', $tenantId)
            ->with(['vendor:id,name,company_name,email,phone', 'purchaseOrder:id,purchase_order_number']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'like', "%{$search}%")
                  ->orWhere('vendor_invoice_no', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%")
                         ->orWhere('company_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($vendorId = $request->input('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }
        if ($request->boolean('unpaid_only')) {
            $query->where('balance_due', '>', 0);
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $bills   = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $bills->items(),
            'meta'    => [
                'current_page' => $bills->currentPage(),
                'last_page'    => $bills->lastPage(),
                'per_page'     => $bills->perPage(),
                'total'        => $bills->total(),
            ],
        ]);
    }

    /**
     * POST /api/purchase/bills
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vendor_id'             => ['required', 'integer'],
            'purchase_order_id'     => ['nullable', 'integer'],
            'goods_receipt_note_id' => ['nullable', 'integer'],
            'vendor_invoice_no'     => ['nullable', 'string', 'max:100'],
            'bill_date'             => ['required', 'date'],
            'due_date'              => ['nullable', 'date'],
            'notes'                 => ['nullable', 'string'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'integer'],
            'items.*.quantity'      => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'    => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'      => ['nullable', 'numeric', 'min:0'],
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

        $bill = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId) {
            $lastId = (VendorBill::where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
            $billNo = 'BILL-' . str_pad((string)$lastId, 5, '0', STR_PAD_LEFT);

            $subtotal    = 0;
            $totalTax    = 0;
            $itemRecords = [];

            foreach ($validated['items'] as $row) {
                $qty     = (float)$row['quantity'];
                $price   = (float)$row['unit_price'];
                $taxRate = (float)($row['tax_rate'] ?? 0);

                $lineSubtotal = $qty * $price;
                $lineTax      = $lineSubtotal * ($taxRate / 100);
                $lineTotal    = $lineSubtotal + $lineTax;

                $subtotal += $lineSubtotal;
                $totalTax += $lineTax;

                $itemRecords[] = [
                    'tenant_id'      => $tenantId,
                    'company_id'     => $companyId,
                    'branch_id'      => $branchId,
                    'product_id'     => $row['product_id'],
                    'quantity'       => $qty,
                    'unit_rate'      => $price,
                    'tax_percentage' => $taxRate,
                    'total_amount'   => $lineTotal,
                ];
            }

            $grandTotal = $subtotal + $totalTax;

            $vb = VendorBill::create([
                'tenant_id'             => $tenantId,
                'company_id'            => $companyId,
                'branch_id'             => $branchId,
                'vendor_id'             => $validated['vendor_id'],
                'purchase_order_id'     => $validated['purchase_order_id'] ?? null,
                'goods_receipt_note_id' => $validated['goods_receipt_note_id'] ?? null,
                'bill_number'           => $billNo,
                'vendor_invoice_number' => $validated['vendor_invoice_number'] ?? $validated['vendor_invoice_no'] ?? null,
                'bill_date'             => $validated['bill_date'],
                'due_date'              => $validated['due_date'] ?? now()->addDays(30)->toDateString(),
                'status'                => 'Draft',
                'subtotal'              => $subtotal,
                'tax_amount'            => $totalTax,
                'grand_total'           => $grandTotal,
                'paid_amount'           => 0.00,
                'due_amount'            => $grandTotal,
                'notes'                 => $validated['notes'] ?? null,
                'created_by'            => auth()->id() ?? 1,
            ]);

            foreach ($itemRecords as $itemData) {
                $itemData['vendor_bill_id'] = $vb->id;
                VendorBillItem::create($itemData);
            }

            return $vb->load(['items.product', 'vendor']);
        });

        event(new \App\Domains\Purchase\Events\BillPosted($bill));

        return response()->json([
            'success' => true,
            'message' => 'Vendor Bill created successfully',
            'data'    => $bill->fresh(['items.product', 'vendor']),
        ], 201);
    }

    /**
     * GET /api/purchase/bills/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $bill = VendorBill::where('tenant_id', $tenantId)
            ->with(['vendor', 'purchaseOrder', 'items.product'])
            ->find($id);

        if (!$bill) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor Bill not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $bill,
        ]);
    }

    /**
     * PATCH /api/purchase/bills/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $bill       = VendorBill::where('tenant_id', $tenantId)->find($id);

        if (!$bill) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor Bill not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:Draft,Approved,Paid,Partial,Cancelled'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $bill->status = $request->input('status');
        if ($bill->status === 'Paid') {
            $bill->paid_amount = $bill->grand_total;
            $bill->due_amount  = 0;
        }
        $bill->save();

        if (in_array($bill->status, ['Approved', 'Paid'])) {
            event(new \App\Domains\Purchase\Events\BillPosted($bill));
        }

        return response()->json([
            'success' => true,
            'message' => "Vendor Bill status updated to {$bill->status}",
            'data'    => $bill->fresh(['vendor', 'items.product']),
        ]);
    }

    /**
     * DELETE /api/purchase/bills/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $bill       = VendorBill::where('tenant_id', $tenantId)->find($id);

        if (!$bill) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor Bill not found',
            ], 404);
        }

        if ($bill->amount_paid > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete bill with recorded payments.',
            ], 422);
        }

        VendorBillItem::where('vendor_bill_id', $bill->id)->delete();
        $bill->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vendor Bill deleted successfully',
        ]);
    }

    /**
     * GET /api/purchase/bills/pending
     */
    public function pendingGrns(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = \App\Domains\Purchase\Models\GoodsReceiptNote::where('tenant_id', $tenantId)
            ->where('status', 'Approved')
            ->whereDoesntHave('vendorBill')
            ->with(['vendor:id,name,company_name', 'purchaseOrder:id,purchase_order_number', 'warehouse:id,name', 'items.product:id,name,sku']);

        $perPage = min((int)$request->input('per_page', 15), 100);
        $grns    = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $grns->items(),
            'meta'    => [
                'current_page' => $grns->currentPage(),
                'last_page'    => $grns->lastPage(),
                'per_page'     => $grns->perPage(),
                'total'        => $grns->total(),
            ],
        ]);
    }

    /**
     * GET /api/purchase/bills/pending-freight
     */
    public function pendingFreight(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = \App\Domains\Purchase\Models\GoodsReceiptNote::where('tenant_id', $tenantId)
            ->whereNotNull('transporter_id')
            ->where('status', 'Approved')
            ->with(['transporter:id,name', 'vendor:id,name', 'warehouse:id,name']);

        $perPage = min((int)$request->input('per_page', 15), 100);
        $grns    = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $grns->items(),
            'meta'    => [
                'current_page' => $grns->currentPage(),
                'last_page'    => $grns->lastPage(),
                'per_page'     => $grns->perPage(),
                'total'        => $grns->total(),
            ],
        ]);
    }

    /**
     * POST /api/purchase/bills/store-service
     */
    public function storeService(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vendor_id'         => ['required', 'integer'],
            'bill_date'         => ['required', 'date'],
            'due_date'          => ['nullable', 'date'],
            'vendor_invoice_no' => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.service_description' => ['required', 'string', 'max:255'],
            'items.*.amount'              => ['required', 'numeric', 'min:0.01'],
            'items.*.tax_rate'            => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $validated = $validator->validated();

        $bill = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId) {
            $lastId = (VendorBill::where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
            $billNo = 'BILL-SRV-' . str_pad((string)$lastId, 5, '0', STR_PAD_LEFT);

            $subtotal = 0;
            $totalTax = 0;
            $itemRecords = [];

            foreach ($validated['items'] as $item) {
                $amt     = (float)$item['amount'];
                $taxRate = (float)($item['tax_rate'] ?? 0);
                $taxAmt  = $amt * ($taxRate / 100);

                $subtotal += $amt;
                $totalTax += $taxAmt;

                $itemRecords[] = [
                    'tenant_id'      => $tenantId,
                    'company_id'     => $companyId,
                    'branch_id'      => $branchId,
                    'product_id'     => null,
                    'quantity'       => 1,
                    'unit_rate'      => $amt,
                    'tax_percentage' => $taxRate,
                    'total_amount'   => $amt + $taxAmt,
                ];
            }

            $grandTotal = $subtotal + $totalTax;

            $vb = VendorBill::create([
                'tenant_id'             => $tenantId,
                'company_id'            => $companyId,
                'branch_id'             => $branchId,
                'vendor_id'             => $validated['vendor_id'],
                'bill_number'           => $billNo,
                'vendor_invoice_number' => $validated['vendor_invoice_number'] ?? $validated['vendor_invoice_no'] ?? null,
                'bill_date'             => $validated['bill_date'],
                'due_date'              => $validated['due_date'] ?? now()->addDays(30)->toDateString(),
                'status'                => 'Posted',
                'subtotal'              => $subtotal,
                'tax_amount'            => $totalTax,
                'grand_total'           => $grandTotal,
                'paid_amount'           => 0.00,
                'due_amount'            => $grandTotal,
                'notes'                 => $validated['notes'] ?? null,
                'created_by'            => auth()->id() ?? 1,
            ]);

            foreach ($itemRecords as $iData) {
                $iData['vendor_bill_id'] = $vb->id;
                VendorBillItem::create($iData);
            }

            return $vb->load(['vendor']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Service Vendor Bill created successfully',
            'data'    => $bill,
        ], 201);
    }

    /**
     * POST /api/purchase/bills/{id}/apply-advance
     */
    public function applyAdvance(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $bill       = VendorBill::where('tenant_id', $tenantId)->find($id);

        if (!$bill) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor Bill not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'advance_payment_id' => ['required', 'integer'],
            'amount'             => ['required', 'numeric', 'min:0.01'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $advance = \App\Domains\Purchase\Models\PurchaseAdvancePayment::where('tenant_id', $tenantId)->find($request->input('advance_payment_id'));

        if (!$advance) {
            return response()->json([
                'success' => false,
                'message' => 'Advance Payment record not found',
            ], 404);
        }

        $dueAmt       = (float)($bill->due_amount ?? $bill->balance_due);
        $allocatedAmt = min((float)$request->input('amount'), $dueAmt);

        $bill->paid_amount = (float)($bill->paid_amount ?? 0) + $allocatedAmt;
        $bill->due_amount  = max(0, (float)($bill->grand_total ?? $bill->total_amount) - (float)$bill->paid_amount);
        $bill->status      = $bill->due_amount <= 0.001 ? 'Paid' : 'Partially Paid';
        $bill->save();

        return response()->json([
            'success' => true,
            'message' => 'Advance applied to bill successfully',
            'data'    => $bill,
        ]);
    }

    /**
     * GET /api/purchase/bills/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\VendorBillExport($tenantId, $request->all()),
            'vendor_bills_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
