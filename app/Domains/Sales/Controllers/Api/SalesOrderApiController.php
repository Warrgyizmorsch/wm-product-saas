<?php

namespace App\Domains\Sales\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderItem;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\DispatchOrderItem;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SalesOrderApiController extends Controller
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
     * GET /api/sales/orders/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $customers  = Customer::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'company_name', 'email', 'phone', 'gstin']);
        $products   = Product::where('tenant_id', $tenantId)->sellable()->orderBy('name')->get(['id', 'name', 'sku', 'selling_price', 'unit_cost', 'gst_rate']);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);
        $users      = User::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email']);

        return response()->json([
            'success' => true,
            'data'    => [
                'statuses'       => ['Draft', 'Confirmed', 'Processing', 'Closed', 'Cancelled'],
                'discount_types' => ['fixed', 'percentage'],
                'tax_types'      => ['exclusive', 'inclusive'],
                'gst_types'      => ['cgst_sgst', 'igst', 'none'],
                'freight_terms'  => ['Prepaid', 'To Pay', 'Free on Board (FOB)', 'Cost & Freight (CFR)', 'Ex-Works'],
                'payment_terms'  => ['Immediate', 'Net 15', 'Net 30', 'Net 45', 'Net 60', 'Due on Receipt'],
                'customers'      => $customers,
                'products'       => $products,
                'warehouses'     => $warehouses,
                'sales_persons'  => $users,
            ],
        ]);
    }

    /**
     * GET /api/sales/orders
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer:id,name,company_name,email,phone', 'salesPerson:id,name']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('sales_order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('company_name', 'like', "%{$search}%");
                  });
            });
        }

        // Filters
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($salesPersonId = $request->input('sales_person_id')) {
            $query->where('sales_person_id', $salesPersonId);
        }
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('order_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('order_date', '<=', $toDate);
        }

        // Trashed filter
        if ($request->boolean('trashed_only')) {
            $query->onlyTrashed();
        }

        // Sorting
        $sortBy  = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'sales_order_number', 'order_date', 'shipment_date', 'status', 'total_amount', 'created_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $orders  = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $orders->items(),
            'meta'    => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * POST /api/sales/orders
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id'       => ['required', 'integer'],
            'quotation_id'      => ['nullable', 'integer'],
            'order_date'        => ['required', 'date'],
            'shipment_date'     => ['nullable', 'date'],
            'status'            => ['nullable', 'string', 'in:Draft,Confirmed,Processing,Closed,Cancelled'],
            'billing_address'   => ['nullable', 'string'],
            'shipping_address'  => ['nullable', 'string'],
            'payment_terms'     => ['nullable', 'string'],
            'sales_person_id'   => ['nullable', 'integer'],
            'discount_type'     => ['nullable', 'in:fixed,percentage'],
            'tax_type'          => ['nullable', 'in:exclusive,inclusive'],
            'gst_type'          => ['nullable', 'in:cgst_sgst,igst,none'],
            'freight_terms'     => ['nullable', 'string'],
            'freight_amount'    => ['nullable', 'numeric'],
            'shipping_charges'  => ['nullable', 'numeric'],
            'adjustment'        => ['nullable', 'numeric'],
            'terms_conditions'  => ['nullable', 'string'],
            'notes'             => ['nullable', 'string'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['nullable', 'integer'],
            'items.*.warehouse_id' => ['nullable', 'integer'],
            'items.*.item_name'    => ['required', 'string', 'max:255'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'     => ['nullable', 'numeric', 'min:0'],
            'items.*.discount'     => ['nullable', 'numeric', 'min:0'],
            'items.*.description'  => ['nullable', 'string'],
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

        $order = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId) {
            // Auto generate SO number
            $lastId   = (SalesOrder::where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
            $soNumber = 'SO-' . str_pad((string)$lastId, 5, '0', STR_PAD_LEFT);

            // Calculate totals
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
                    'warehouse_id' => $row['warehouse_id'] ?? null,
                    'item_name'    => $row['item_name'],
                    'description'  => $row['description'] ?? null,
                    'quantity'     => $qty,
                    'unit_price'   => $price,
                    'tax_rate'     => $taxRate,
                    'discount'     => $discount,
                    'amount'       => $lineTotal,
                ];
            }

            $freight    = (float)($validated['freight_amount'] ?? 0);
            $shipping   = (float)($validated['shipping_charges'] ?? 0);
            $adjustment = (float)($validated['adjustment'] ?? 0);
            $grandTotal = $subtotal + $totalTax + $freight + $shipping + $adjustment;

            $so = SalesOrder::create([
                'tenant_id'          => $tenantId,
                'company_id'         => $companyId,
                'branch_id'          => $branchId,
                'customer_id'        => $validated['customer_id'],
                'quotation_id'       => $validated['quotation_id'] ?? null,
                'sales_order_number' => $soNumber,
                'order_date'         => $validated['order_date'],
                'shipment_date'      => $validated['shipment_date'] ?? null,
                'status'             => $validated['status'] ?? 'Draft',
                'billing_address'    => $validated['billing_address'] ?? null,
                'shipping_address'   => $validated['shipping_address'] ?? null,
                'payment_terms'      => $validated['payment_terms'] ?? null,
                'sales_person_id'    => $validated['sales_person_id'] ?? null,
                'discount_type'      => $validated['discount_type'] ?? 'fixed',
                'tax_type'           => $validated['tax_type'] ?? 'exclusive',
                'gst_type'           => $validated['gst_type'] ?? 'cgst_sgst',
                'subtotal'           => $subtotal,
                'tax'                => $totalTax,
                'discount'           => 0,
                'freight_terms'      => $validated['freight_terms'] ?? null,
                'freight_amount'     => $freight,
                'shipping_charges'   => $shipping,
                'adjustment'         => $adjustment,
                'total_amount'       => $grandTotal,
                'terms_conditions'   => $validated['terms_conditions'] ?? null,
                'notes'              => $validated['notes'] ?? null,
            ]);

            foreach ($itemRecords as $itemData) {
                $itemData['sales_order_id'] = $so->id;
                SalesOrderItem::create($itemData);
            }

            return $so->load(['items.product', 'customer', 'salesPerson']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Sales Order created successfully',
            'data'    => $order,
        ], 201);
    }

    /**
     * GET /api/sales/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $order = SalesOrder::where('tenant_id', $tenantId)
            ->with([
                'customer',
                'salesPerson',
                'items.product',
                'items.warehouse',
                'quotation',
            ])
            ->findOrFail($id);

        $invoices   = Invoice::where('tenant_id', $tenantId)->where('sales_order_id', $id)->get();
        $dispatches = DispatchOrder::where('tenant_id', $tenantId)->where('sales_order_id', $id)->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'order'      => $order,
                'invoices'   => $invoices,
                'dispatches' => $dispatches,
            ],
        ]);
    }

    /**
     * PUT/PATCH /api/sales/orders/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $order = SalesOrder::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'customer_id'       => ['sometimes', 'required', 'integer'],
            'order_date'        => ['sometimes', 'required', 'date'],
            'shipment_date'     => ['nullable', 'date'],
            'status'            => ['nullable', 'string', 'in:Draft,Confirmed,Processing,Closed,Cancelled'],
            'billing_address'   => ['nullable', 'string'],
            'shipping_address'  => ['nullable', 'string'],
            'payment_terms'     => ['nullable', 'string'],
            'sales_person_id'   => ['nullable', 'integer'],
            'discount_type'     => ['nullable', 'in:fixed,percentage'],
            'tax_type'          => ['nullable', 'in:exclusive,inclusive'],
            'gst_type'          => ['nullable', 'in:cgst_sgst,igst,none'],
            'freight_terms'     => ['nullable', 'string'],
            'freight_amount'    => ['nullable', 'numeric'],
            'shipping_charges'  => ['nullable', 'numeric'],
            'adjustment'        => ['nullable', 'numeric'],
            'terms_conditions'  => ['nullable', 'string'],
            'notes'             => ['nullable', 'string'],
            'items'             => ['nullable', 'array'],
            'items.*.product_id'   => ['nullable', 'integer'],
            'items.*.warehouse_id' => ['nullable', 'integer'],
            'items.*.item_name'    => ['required', 'string', 'max:255'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'     => ['nullable', 'numeric', 'min:0'],
            'items.*.discount'     => ['nullable', 'numeric', 'min:0'],
            'items.*.description'  => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $order = DB::transaction(function () use ($order, $validated, $tenantId, $companyId, $branchId) {
            if (isset($validated['items'])) {
                $subtotal = 0;
                $totalTax = 0;

                SalesOrderItem::where('sales_order_id', $order->id)->delete();

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

                    SalesOrderItem::create([
                        'tenant_id'      => $tenantId,
                        'company_id'     => $companyId,
                        'branch_id'      => $branchId,
                        'sales_order_id' => $order->id,
                        'product_id'     => $row['product_id'] ?? null,
                        'warehouse_id'   => $row['warehouse_id'] ?? null,
                        'item_name'      => $row['item_name'],
                        'description'    => $row['description'] ?? null,
                        'quantity'       => $qty,
                        'unit_price'     => $price,
                        'tax_rate'       => $taxRate,
                        'discount'       => $discount,
                        'amount'         => $lineTotal,
                    ]);
                }

                $freight    = (float)($validated['freight_amount'] ?? $order->freight_amount);
                $shipping   = (float)($validated['shipping_charges'] ?? $order->shipping_charges);
                $adjustment = (float)($validated['adjustment'] ?? $order->adjustment);
                $grandTotal = $subtotal + $totalTax + $freight + $shipping + $adjustment;

                $validated['subtotal']     = $subtotal;
                $validated['tax']          = $totalTax;
                $validated['total_amount'] = $grandTotal;
            }

            unset($validated['items']);
            $order->update($validated);

            return $order->fresh()->load(['items.product', 'customer', 'salesPerson']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Sales Order updated successfully',
            'data'    => $order,
        ]);
    }

    /**
     * PATCH /api/sales/orders/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order      = SalesOrder::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:Draft,Confirmed,Processing,Closed,Cancelled'],
            'notes'  => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $order->status = $request->input('status');
        if ($notes = $request->input('notes')) {
            $order->notes = ($order->notes ? $order->notes . "\n" : '') . "[" . now()->toDateTimeString() . "] Status changed to {$order->status}: {$notes}";
        }
        $order->save();

        return response()->json([
            'success' => true,
            'message' => "Sales order status updated to {$order->status}",
            'data'    => $order,
        ]);
    }

    /**
     * POST /api/sales/orders/{id}/convert-to-invoice
     */
    public function convertToInvoice(Request $request, int $id): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $order = SalesOrder::where('tenant_id', $tenantId)->with('items')->findOrFail($id);

        $invoiceNumber = 'INV-' . strtoupper(bin2hex(random_bytes(4)));

        $invoice = DB::transaction(function () use ($order, $invoiceNumber, $tenantId, $companyId, $branchId, $request) {
            $inv = Invoice::create([
                'tenant_id'        => $tenantId,
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'customer_id'      => $order->customer_id,
                'sales_order_id'   => $order->id,
                'invoice_number'   => $invoiceNumber,
                'invoice_date'     => $request->input('invoice_date', now()->toDateString()),
                'due_date'         => $request->input('due_date', now()->addDays(30)->toDateString()),
                'payment_terms'    => $order->payment_terms,
                'status'           => 'Draft',
                'subtotal'         => $order->subtotal,
                'tax_amount'       => $order->tax,
                'tax_type'         => $order->tax_type,
                'discount_type'    => $order->discount_type,
                'order_tax_rate'   => $order->order_tax_rate,
                'gst_type'         => $order->gst_type,
                'freight_amount'   => $order->freight_amount,
                'adjustment'       => $order->adjustment,
                'total_amount'     => $order->total_amount,
                'amount_paid'      => 0,
                'balance_due'      => $order->total_amount,
                'notes'            => 'Generated from Sales Order #' . $order->sales_order_number,
            ]);

            foreach ($order->items as $item) {
                InvoiceItem::create([
                    'tenant_id'    => $tenantId,
                    'company_id'   => $companyId,
                    'branch_id'    => $branchId,
                    'invoice_id'   => $inv->id,
                    'product_id'   => $item->product_id,
                    'item_name'    => $item->item_name,
                    'description'  => $item->description,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->unit_price,
                    'tax_rate'     => $item->tax_rate,
                    'discount'     => $item->discount,
                    'amount'       => $item->amount,
                ]);
            }

            return $inv->load(['items', 'customer']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Sales Order successfully converted to Invoice',
            'data'    => $invoice,
        ], 201);
    }

    /**
     * POST /api/sales/orders/{id}/convert-to-dispatch
     */
    public function convertToDispatch(Request $request, int $id): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $order = SalesOrder::where('tenant_id', $tenantId)->with('items')->findOrFail($id);

        $dispatchNo = 'DO-' . strtoupper(bin2hex(random_bytes(4)));

        $dispatch = DB::transaction(function () use ($order, $dispatchNo, $tenantId, $companyId, $branchId, $request) {
            $do = DispatchOrder::create([
                'tenant_id'             => $tenantId,
                'company_id'            => $companyId,
                'branch_id'             => $branchId,
                'sales_order_id'        => $order->id,
                'dispatch_order_number' => $dispatchNo,
                'dispatch_date'         => $request->input('dispatch_date', now()->toDateString()),
                'status'                => 'Draft',
                'transporter_name'      => $request->input('transporter_name'),
                'tracking_number'       => $request->input('tracking_number'),
                'notes'                 => $request->input('notes'),
            ]);

            foreach ($order->items as $item) {
                DispatchOrderItem::create([
                    'tenant_id'         => $tenantId,
                    'company_id'        => $companyId,
                    'branch_id'         => $branchId,
                    'dispatch_order_id' => $do->id,
                    'product_id'        => $item->product_id,
                    'warehouse_id'      => $item->warehouse_id,
                    'item_name'         => $item->item_name,
                    'quantity'          => $item->quantity,
                ]);
            }

            return $do->load(['items.product', 'salesOrder']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Sales Order converted to Dispatch Order / Challan successfully',
            'data'    => $dispatch,
        ], 201);
    }

    /**
     * DELETE /api/sales/orders/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order      = SalesOrder::where('tenant_id', $tenantId)->findOrFail($id);

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sales Order deleted successfully',
        ]);
    }

    /**
     * POST /api/sales/orders/{id}/restore
     */
    public function restore(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order      = SalesOrder::where('tenant_id', $tenantId)->onlyTrashed()->findOrFail($id);

        $order->restore();

        return response()->json([
            'success' => true,
            'message' => 'Sales Order restored successfully',
            'data'    => $order,
        ]);
    }

    /**
     * POST /api/sales/orders/{id}/confirm
     * Confirm a draft Sales Order.
     */
    public function confirm(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order = SalesOrder::where('tenant_id', $tenantId)->findOrFail($id);
        $this->authorize('update', $order);

        $order->status = 'Confirmed';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => "Sales Order {$order->order_number} confirmed successfully.",
            'data'    => $order->fresh(['customer', 'items.product']),
        ]);
    }

    /**
     * POST /api/sales/orders/{id}/cancel
     * Cancel a Sales Order.
     */
    public function cancel(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order = SalesOrder::where('tenant_id', $tenantId)->findOrFail($id);
        $this->authorize('update', $order);

        $order->status = 'Cancelled';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => "Sales Order {$order->order_number} cancelled.",
            'data'    => $order->fresh(),
        ]);
    }

    /**
     * GET /api/sales/orders/export
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', SalesOrder::class);
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\SalesOrderExport($tenantId, $request->all()),
            'sales_orders_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
