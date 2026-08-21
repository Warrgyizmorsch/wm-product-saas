<?php

namespace App\Domains\Purchase\Controllers;

use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Events\PurchaseReturnApproved;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseReturn;
use App\Domains\Purchase\Models\PurchaseReturnItem;
use App\Domains\Purchase\Repositories\PurchaseReturnRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private readonly PurchaseReturnRepository $returnRepo
    ) {}

    public function index(Request $request): View
    {
        $returns = $this->returnRepo->getPaginated($request->all(), 15);

        return view('modules.purchase.returns.index', compact('returns'));
    }

    public function create(Request $request): View
    {
        $tenantId = require_tenant_id();
        $purchaseOrderId = $request->input('purchase_order_id');
        $goodsReceiptNoteId = $request->input('goods_receipt_note_id');

        $mode = $request->input('mode');
        if (!$mode || $mode === 'po') {
            $mode = 'grn';
        }

        $goodsReceiptNote = null;
        if ($goodsReceiptNoteId) {
            $goodsReceiptNote = \App\Domains\Purchase\Models\GoodsReceiptNote::with(['items.product', 'vendor', 'purchaseOrder'])->find($goodsReceiptNoteId);
        } elseif ($purchaseOrderId) {
            $goodsReceiptNote = \App\Domains\Purchase\Models\GoodsReceiptNote::with(['items.product', 'vendor', 'purchaseOrder'])
                ->where('purchase_order_id', $purchaseOrderId)
                ->latest()
                ->first();
            if ($goodsReceiptNote) {
                $goodsReceiptNoteId = $goodsReceiptNote->id;
            }
        }

        $purchaseOrder = null;
        if ($purchaseOrderId) {
            $purchaseOrder = PurchaseOrder::with('items.product', 'vendor')->find($purchaseOrderId);
        } elseif ($goodsReceiptNote?->purchase_order_id) {
            $purchaseOrder = $goodsReceiptNote->purchaseOrder;
            $purchaseOrderId = $goodsReceiptNote->purchase_order_id;
        }

        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get();
        $purchaseOrders = PurchaseOrder::with('vendor')->whereIn('status', ['Approved', 'Partially Received', 'Received', 'Completed'])->latest()->get();
        $goodsReceiptNotes = \App\Domains\Purchase\Models\GoodsReceiptNote::with('vendor')->latest()->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get();
        $products = \App\Domains\Inventory\Models\Product::where('tenant_id', $tenantId)->orderBy('name')->get();

        $latest = PurchaseReturn::latest('id')->first();
        $nextSeq = $latest ? intval(str_replace('PRET-', '', $latest->return_number)) + 1 : 1;
        $nextReturnNumber = 'PRET-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        $prefillPurchaseOrderId = $purchaseOrderId;
        $prefillGrnId = $goodsReceiptNoteId;
        $prefillVendorId = $goodsReceiptNote?->vendor_id ?: $purchaseOrder?->vendor_id;

        $firstWhId = $warehouses->first()?->id;

        $productsJson = $products->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?: '',
            'price' => floatval($p->cost_price ?: $p->unit_cost ?? 0),
        ])->values()->toArray();

        $warehousesJson = $warehouses->map(fn($w) => [
            'id' => $w->id,
            'name' => $w->name,
        ])->values()->toArray();

        $prefillItemsJson = [];
        if ($mode === 'grn' && $goodsReceiptNote && $goodsReceiptNote->items) {
            $grnWhId = $goodsReceiptNote->warehouse_id ?? $firstWhId;
            $prefillItemsJson = $goodsReceiptNote->items->map(fn($i) => [
                'product_id'   => $i->product_id,
                'warehouse_id' => $i->warehouse_id ?? $grnWhId,
                'quantity'     => floatval(($i->accepted_qty > 0) ? $i->accepted_qty : (($i->received_qty > 0) ? $i->received_qty : 1)),
                'unit_price'   => floatval($i->unit_rate ?? 0),
            ])->values()->toArray();
        } elseif ($mode === 'po' && $purchaseOrder && $purchaseOrder->items) {
            $prefillItemsJson = $purchaseOrder->items->map(fn($i) => [
                'product_id'   => $i->product_id,
                'warehouse_id' => $i->warehouse_id ?? $firstWhId,
                'quantity'     => floatval($i->quantity),
                'unit_price'   => floatval($i->unit_price),
            ])->values()->toArray();
        }

        return view('modules.purchase.returns.create', compact(
            'vendors', 'purchaseOrders', 'goodsReceiptNotes', 'purchaseOrder', 'goodsReceiptNote', 'warehouses', 'nextReturnNumber',
            'prefillPurchaseOrderId', 'prefillGrnId', 'prefillVendorId', 'products', 'mode',
            'productsJson', 'warehousesJson', 'prefillItemsJson'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        if (!$request->filled('vendor_id') && $request->filled('purchase_order_id')) {
            $po = PurchaseOrder::find($request->input('purchase_order_id'));
            if ($po) {
                $request->merge(['vendor_id' => $po->vendor_id]);
            }
        }
        if (!$request->filled('vendor_id') && $request->filled('goods_receipt_note_id')) {
            $grn = \App\Domains\Purchase\Models\GoodsReceiptNote::find($request->input('goods_receipt_note_id'));
            if ($grn) {
                $request->merge(['vendor_id' => $grn->vendor_id]);
            }
        }

        $validated = $request->validate([
            'vendor_id'             => ['required', 'exists:vendors,id'],
            'purchase_order_id'     => ['nullable', 'exists:purchase_orders,id'],
            'goods_receipt_note_id' => ['nullable', 'exists:goods_receipt_notes,id'],
            'vendor_bill_id'        => ['nullable', 'exists:vendor_bills,id'],
            'return_number'         => ['required', 'string', 'max:255', 'unique:purchase_returns,return_number'],
            'return_date'           => ['required', 'date'],
            'reason'                => ['nullable', 'string'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'exists:products,id'],
            'items.*.warehouse_id'  => ['required', 'exists:warehouses,id'],
            'items.*.quantity'      => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'    => ['required', 'numeric', 'min:0'],
            'items.*.serial_numbers' => ['nullable', 'string'],
        ]);

        $return = DB::transaction(function () use ($validated) {
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += floatval($item['quantity']) * floatval($item['unit_price']);
            }

            $purchaseReturn = PurchaseReturn::create([
                'tenant_id'             => tenant_id() ?? 1,
                'vendor_id'             => $validated['vendor_id'],
                'purchase_order_id'     => $validated['purchase_order_id'] ?? null,
                'goods_receipt_note_id' => $validated['goods_receipt_note_id'] ?? null,
                'vendor_bill_id'        => $validated['vendor_bill_id'] ?? null,
                'return_number'         => $validated['return_number'],
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
                    'total_amount'       => floatval($item['quantity']) * floatval($item['unit_price']),
                    'serial_numbers'     => $item['serial_numbers'] ?? null,
                ]);
            }

            return $purchaseReturn;
        });

        return redirect()->route('purchase.returns.show', $return->id)
            ->with('success', "Purchase Return {$return->return_number} recorded successfully.");
    }

    public function show(int $id): View
    {
        $return = $this->returnRepo->find($id);
        if (!$return) abort(404);

        return view('modules.purchase.returns.show', compact('return'));
    }

    public function approve(int $id): RedirectResponse
    {
        $purchaseReturn = $this->returnRepo->find($id);
        if (!$purchaseReturn) abort(404);

        if (!in_array($purchaseReturn->status, ['Pending', 'Draft'])) {
            return redirect()->back()->with('error', 'Only Pending or Draft Purchase Returns can be approved.');
        }

        DB::transaction(function () use ($purchaseReturn) {
            $tenantId = $purchaseReturn->tenant_id ?: (tenant_id() ?? 1);

            foreach ($purchaseReturn->items as $item) {
                $serials = [];
                if (!empty($item->serial_numbers)) {
                    $serials = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $item->serial_numbers)));
                }

                \App\Domains\Inventory\Services\StockService::recordOutflow(
                    $tenantId,
                    (int) $item->product_id,
                    (int) $item->warehouse_id,
                    (float) $item->quantity,
                    'PurchaseReturn',
                    (int) $purchaseReturn->id,
                    $serials
                );
            }

            $purchaseReturn->update(['status' => 'Completed']);
        });

        event(new PurchaseReturnApproved($purchaseReturn));

        return redirect()->route('purchase.returns.show', $purchaseReturn->id)
            ->with('success', "Purchase Return {$purchaseReturn->return_number} approved and stock removed from inventory successfully.");
    }
}
