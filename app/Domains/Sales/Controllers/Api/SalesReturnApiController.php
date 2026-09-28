<?php

namespace App\Domains\Sales\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Sales\Models\SalesReturn;
use App\Domains\Sales\Models\SalesReturnItem;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Repositories\SalesReturnRepository;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Services\StockService;
use App\Exports\SalesReturnExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesReturnApiController extends Controller
{
    public function __construct(
        private readonly SalesReturnRepository $returnRepo
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
     * GET /api/sales/returns/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $customers = Customer::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email', 'phone', 'gstin']);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']);
        $products = Product::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'sku', 'selling_price', 'track_serial_number']);

        return response()->json([
            'success' => true,
            'data'    => [
                'customers'  => $customers,
                'warehouses' => $warehouses,
                'products'   => $products,
                'statuses'   => ['Pending', 'Draft', 'Completed', 'Cancelled'],
            ],
        ]);
    }

    /**
     * GET /api/sales/returns
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesReturn::class);

        $perPage = min((int)$request->input('per_page', 15), 100);
        $returns = $this->returnRepo->getPaginated($request->all(), $perPage);

        return response()->json([
            'success' => true,
            'data'    => $returns->items(),
            'meta'    => [
                'current_page' => $returns->currentPage(),
                'last_page'    => $returns->lastPage(),
                'per_page'     => $returns->perPage(),
                'total'        => $returns->total(),
            ],
        ]);
    }

    /**
     * GET /api/sales/returns/{id}
     */
    public function show(int $id): JsonResponse
    {
        $return = SalesReturn::with(['customer', 'salesOrder', 'invoice', 'items.product', 'items.warehouse'])->find($id);

        if (!$return) {
            return response()->json(['success' => false, 'message' => "Sales Return #{$id} not found."], 404);
        }

        $this->authorize('view', $return);

        return response()->json([
            'success' => true,
            'data'    => $return,
        ]);
    }

    /**
     * POST /api/sales/returns
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SalesReturn::class);
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        // Auto-fill sales_order_id and customer_id from Invoice if provided
        if ($request->filled('invoice_id')) {
            $inv = Invoice::find($request->input('invoice_id'));
            if ($inv) {
                if (!$request->filled('customer_id')) {
                    $request->merge(['customer_id' => $inv->customer_id]);
                }
                if (!$request->filled('sales_order_id') && $inv->sales_order_id) {
                    $request->merge(['sales_order_id' => $inv->sales_order_id]);
                }
            }
        }

        if (!$request->filled('customer_id') && $request->filled('sales_order_id')) {
            $so = SalesOrder::find($request->input('sales_order_id'));
            if ($so) {
                $request->merge(['customer_id' => $so->customer_id]);
            }
        }

        $validator = Validator::make($request->all(), [
            'customer_id'            => ['required', 'exists:customers,id'],
            'sales_order_id'         => ['nullable', 'exists:sales_orders,id'],
            'invoice_id'             => ['nullable', 'exists:invoices,id'],
            'return_number'          => ['nullable', 'string', 'max:255'],
            'return_date'            => ['required', 'date'],
            'reason'                 => ['nullable', 'string'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'exists:products,id'],
            'items.*.warehouse_id'   => ['nullable', 'exists:warehouses,id'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.serial_numbers' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $defaultWhId = Warehouse::where('tenant_id', $tenantId)->orderByDesc('is_default')->first()?->id ?? 1;

        if (empty($validated['return_number'])) {
            $latest = SalesReturn::where('tenant_id', $tenantId)->latest('id')->first();
            $nextSeq = $latest ? intval(str_replace('RET-', '', $latest->return_number)) + 1 : 1;
            $validated['return_number'] = 'RET-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        }

        $salesReturn = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId, $defaultWhId) {
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += floatval($item['quantity']) * floatval($item['unit_price']);
            }

            $salesReturn = SalesReturn::create([
                'tenant_id'           => $tenantId,
                'company_id'          => $companyId,
                'branch_id'           => $branchId,
                'customer_id'         => $validated['customer_id'],
                'sales_order_id'      => $validated['sales_order_id'] ?? null,
                'invoice_id'          => $validated['invoice_id'] ?? null,
                'return_number'       => $validated['return_number'],
                'return_date'         => $validated['return_date'],
                'status'              => 'Pending',
                'reason'              => $validated['reason'] ?? null,
                'total_amount'        => $totalAmount,
                'total_refund_amount' => $totalAmount,
            ]);

            foreach ($validated['items'] as $item) {
                SalesReturnItem::create([
                    'sales_return_id' => $salesReturn->id,
                    'product_id'      => $item['product_id'],
                    'warehouse_id'    => $item['warehouse_id'] ?? $defaultWhId,
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'total_amount'    => floatval($item['quantity']) * floatval($item['unit_price']),
                    'serial_numbers'  => $item['serial_numbers'] ?? null,
                ]);
            }

            return $salesReturn;
        });

        return response()->json([
            'success' => true,
            'message' => "Sales Return {$salesReturn->return_number} recorded successfully.",
            'data'    => $salesReturn->load(['items.product', 'items.warehouse', 'customer']),
        ], 201);
    }

    /**
     * POST /api/sales/returns/{id}/approve
     * Approves the return, restocks inventory, and adjusts invoice balance.
     */
    public function approve(int $id): JsonResponse
    {
        $returnOrder = SalesReturn::with(['items', 'customer'])->find($id);

        if (!$returnOrder) {
            return response()->json(['success' => false, 'message' => "Sales Return #{$id} not found."], 404);
        }

        $this->authorize('update', $returnOrder);

        if (!in_array($returnOrder->status, ['Pending', 'Draft'])) {
            return response()->json(['success' => false, 'message' => 'Only Pending or Draft Sales Returns can be approved.'], 422);
        }

        DB::transaction(function () use ($returnOrder) {
            $tenantId = $returnOrder->tenant_id ?: 1;

            foreach ($returnOrder->items as $item) {
                $serials = [];
                if (!empty($item->serial_numbers)) {
                    $serials = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $item->serial_numbers)));
                }

                $costPrice = 0.0;
                if (!empty($serials)) {
                    $snRate = SerialNumber::where('tenant_id', $tenantId)
                        ->where('product_id', $item->product_id)
                        ->whereIn('serial_number', $serials)
                        ->where('purchase_rate', '>', 0)
                        ->avg('purchase_rate');
                    if ($snRate && $snRate > 0) $costPrice = (float)$snRate;
                }

                if ($costPrice <= 0 && $returnOrder->sales_order_id) {
                    $soOutTx = StockTransaction::where('tenant_id', $tenantId)
                        ->where('product_id', $item->product_id)
                        ->where('reference_type', 'SalesOrder')
                        ->where('reference_id', $returnOrder->sales_order_id)
                        ->first();
                    if ($soOutTx && (float)$soOutTx->unit_cost > 0) $costPrice = (float)$soOutTx->unit_cost;
                }

                if ($costPrice <= 0) {
                    $whStock = ProductWarehouseStock::where('tenant_id', $tenantId)
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $item->warehouse_id)
                        ->first();
                    if ($whStock && (float)$whStock->unit_cost > 0) $costPrice = (float)$whStock->unit_cost;
                }

                if ($costPrice <= 0) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $costPrice = (float)($product->cost_price ?: ($product->unit_cost ?: $item->unit_price));
                    } else {
                        $costPrice = (float)$item->unit_price;
                    }
                }

                StockService::recordInflow(
                    $tenantId,
                    (int)$item->product_id,
                    (int)$item->warehouse_id,
                    (float)$item->quantity,
                    $costPrice,
                    'SalesReturn',
                    (int)$returnOrder->id,
                    null,
                    $serials
                );
            }

            if ($returnOrder->invoice_id) {
                $invoice = Invoice::find($returnOrder->invoice_id);
                if ($invoice) {
                    $apply = min((float) $returnOrder->total_refund_amount, (float) $invoice->balance_due);
                    $invoice->balance_due = max(0, (float) $invoice->balance_due - $apply);
                    $invoice->status = $invoice->balance_due <= 0 ? 'Paid' : 'Partially Paid';
                    $invoice->save();
                }
            }

            $returnOrder->update(['status' => 'Completed']);
        });

        return response()->json([
            'success' => true,
            'message' => "Sales Return {$returnOrder->return_number} approved and stock restored to inventory successfully.",
            'data'    => $returnOrder->fresh(['items.product', 'items.warehouse', 'customer']),
        ]);
    }

    /**
     * GET /api/sales/returns/export
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', SalesReturn::class);
        [$tenantId] = $this->resolveTenantContext();

        return Excel::download(
            new SalesReturnExport($tenantId, $request->all()),
            'sales_returns_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
