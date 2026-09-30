<?php

namespace App\Domains\Purchase\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\PurchaseReturn;
use App\Domains\Purchase\Models\PurchaseReturnItem;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Purchase\Events\PurchaseReturnApproved;
use App\Exports\PurchaseReturnExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PurchaseReturnApiController extends Controller
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
     * GET /api/purchase/returns/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $vendors    = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'company_name', 'phone']);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);
        $products   = Product::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'sku', 'cost_price', 'unit_cost']);

        return response()->json([
            'success' => true,
            'data'    => [
                'statuses'   => ['Pending', 'Completed', 'Cancelled'],
                'vendors'    => $vendors,
                'warehouses' => $warehouses,
                'products'   => $products,
            ],
        ]);
    }

    /**
     * GET /api/purchase/returns
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = PurchaseReturn::query()
            ->where('tenant_id', $tenantId)
            ->with(['vendor:id,name,company_name', 'purchaseOrder:id,purchase_order_number', 'goodsReceiptNote:id,grn_number', 'items.product:id,name,sku']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($vendorId = $request->input('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $returns = $query->orderBy('id', 'desc')->paginate($perPage);

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
     * POST /api/purchase/returns
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vendor_id'              => ['required', 'integer'],
            'purchase_order_id'      => ['nullable', 'integer'],
            'goods_receipt_note_id'  => ['nullable', 'integer'],
            'vendor_bill_id'         => ['nullable', 'integer'],
            'return_date'            => ['required', 'date'],
            'reason'                 => ['nullable', 'string', 'max:500'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'integer'],
            'items.*.warehouse_id'   => ['required', 'integer'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.serial_numbers' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $validated = $validator->validated();

        $return = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId) {
            $lastId   = (PurchaseReturn::where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
            $returnNo = 'PRET-' . str_pad((string)$lastId, 5, '0', STR_PAD_LEFT);

            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += (float)$item['quantity'] * (float)$item['unit_price'];
            }

            $purchaseReturn = PurchaseReturn::create([
                'tenant_id'             => $tenantId,
                'company_id'            => $companyId,
                'branch_id'             => $branchId,
                'vendor_id'             => $validated['vendor_id'],
                'purchase_order_id'     => $validated['purchase_order_id'] ?? null,
                'goods_receipt_note_id' => $validated['goods_receipt_note_id'] ?? null,
                'vendor_bill_id'        => $validated['vendor_bill_id'] ?? null,
                'return_number'         => $returnNo,
                'return_date'           => $validated['return_date'],
                'status'                => 'Pending',
                'reason'                => $validated['reason'] ?? null,
                'total_amount'          => $totalAmount,
                'total_refund_amount'   => $totalAmount,
            ]);

            foreach ($validated['items'] as $item) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_id'         => $item['product_id'],
                    'warehouse_id'       => $item['warehouse_id'],
                    'quantity'           => $item['quantity'],
                    'unit_price'         => $item['unit_price'],
                    'total_amount'       => (float)$item['quantity'] * (float)$item['unit_price'],
                    'serial_numbers'     => $item['serial_numbers'] ?? null,
                ]);
            }

            return $purchaseReturn->load(['vendor', 'items.product', 'items.warehouse']);
        });

        return response()->json([
            'success' => true,
            'message' => "Purchase Return {$return->return_number} recorded successfully.",
            'data'    => $return,
        ], 201);
    }

    /**
     * GET /api/purchase/returns/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $return = PurchaseReturn::where('tenant_id', $tenantId)
            ->with(['vendor', 'purchaseOrder', 'goodsReceiptNote', 'vendorBill', 'items.product', 'items.warehouse'])
            ->find($id);

        if (!$return) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Return not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $return,
        ]);
    }

    /**
     * POST /api/purchase/returns/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $purchaseReturn = PurchaseReturn::where('tenant_id', $tenantId)->with('items')->find($id);

        if (!$purchaseReturn) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Return not found',
            ], 404);
        }

        if (!in_array($purchaseReturn->status, ['Pending', 'Draft'])) {
            return response()->json(['success' => false, 'message' => 'Only Pending or Draft Purchase Returns can be approved.'], 422);
        }

        try {
            DB::transaction(function () use ($purchaseReturn, $tenantId) {
                foreach ($purchaseReturn->items as $item) {
                    $serials = [];
                    if (!empty($item->serial_numbers)) {
                        $serials = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $item->serial_numbers)));
                    }

                    StockService::recordOutflow(
                        tenantId: $tenantId,
                        productId: (int)$item->product_id,
                        warehouseId: (int)$item->warehouse_id,
                        quantity: (float)$item->quantity,
                        referenceType: 'PurchaseReturn',
                        referenceId: (int)$purchaseReturn->id,
                        serialNumbers: $serials
                    );
                }

                if ($purchaseReturn->vendor_bill_id) {
                    $bill = VendorBill::where('tenant_id', $tenantId)->find($purchaseReturn->vendor_bill_id);
                    if ($bill) {
                        $due = (float)($bill->due_amount ?? $bill->balance_due);
                        $apply = min((float)$purchaseReturn->total_refund_amount, $due);
                        $bill->due_amount = max(0, $due - $apply);
                        $bill->paid_amount = max(0, (float)($bill->grand_total ?? $bill->total_amount) - (float)$bill->due_amount);
                        $bill->status = $bill->due_amount <= 0.001 ? 'Paid' : 'Partially Paid';
                        $bill->save();
                    }
                }

                $purchaseReturn->update(['status' => 'Completed']);
                event(new PurchaseReturnApproved($purchaseReturn));
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to approve return: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Purchase Return {$purchaseReturn->return_number} approved and stock deducted from inventory.",
            'data'    => $purchaseReturn->fresh(['vendor', 'items.product']),
        ]);
    }

    /**
     * GET /api/purchase/returns/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return Excel::download(
            new PurchaseReturnExport($tenantId, $request->all()),
            'purchase_returns_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
