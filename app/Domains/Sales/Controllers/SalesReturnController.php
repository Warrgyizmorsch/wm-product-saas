<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\DeliveryOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesReturn;
use App\Domains\Sales\Models\SalesReturnItem;
use App\Domains\Inventory\Services\StockService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class SalesReturnController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SalesReturn::class);

        $returns = SalesReturn::with(['salesOrder.customer', 'deliveryOrder', 'invoice'])->latest()->get();

        return view('modules.sales.returns.index', [
            'returns' => $returns,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SalesReturn::class);

        $salesOrders = SalesOrder::with('customer')->orderBy('sales_order_number')->get();
        $deliveries = DeliveryOrder::orderBy('delivery_number')->get();
        $invoices = Invoice::orderBy('invoice_number')->get();

        // Calculate next return number
        $latest = SalesReturn::latest('id')->first();
        $nextSeq = $latest ? intval(str_replace('RET-', '', $latest->return_number)) + 1 : 1;
        $nextReturnNumber = 'RET-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        return view('modules.sales.returns.create', [
            'salesOrders' => $salesOrders,
            'deliveries' => $deliveries,
            'invoices' => $invoices,
            'nextReturnNumber' => $nextReturnNumber,
            'prefillSalesOrderId' => $request->input('sales_order_id'),
            'prefillDeliveryOrderId' => $request->input('delivery_order_id'),
            'prefillInvoiceId' => $request->input('invoice_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SalesReturn::class);

        $validated = $request->validate([
            'return_number' => 'required|string',
            'return_date' => 'required|date',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'delivery_order_id' => 'nullable|exists:delivery_orders,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'reason' => 'nullable|string',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $return = DB::transaction(function () use ($validated) {
            $totalRefundAmount = 0;
            foreach ($validated['items'] as $itemData) {
                $totalRefundAmount += floatval($itemData['quantity']) * floatval($itemData['unit_price']);
            }

            $return = SalesReturn::create([
                'sales_order_id' => $validated['sales_order_id'] ?? null,
                'delivery_order_id' => $validated['delivery_order_id'] ?? null,
                'invoice_id' => $validated['invoice_id'] ?? null,
                'return_number' => $validated['return_number'],
                'return_date' => $validated['return_date'],
                'status' => 'Draft',
                'total_refund_amount' => $totalRefundAmount,
                'reason' => $validated['reason'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                SalesReturnItem::create([
                    'sales_return_id' => $return->id,
                    'product_id' => $itemData['product_id'],
                    'warehouse_id' => $itemData['warehouse_id'],
                    'quantity' => floatval($itemData['quantity']),
                    'unit_price' => floatval($itemData['unit_price']),
                ]);
            }

            return $return;
        });

        return redirect()
            ->route('sales.returns.show', $return->id)
            ->with('success', 'Sales Return created in Draft status.');
    }

    public function show(int $id): View
    {
        $return = SalesReturn::with(['salesOrder.customer', 'deliveryOrder', 'invoice', 'items.product', 'items.warehouse'])->findOrFail($id);

        $this->authorize('view', $return);

        return view('modules.sales.returns.show', [
            'return' => $return,
        ]);
    }

    public function complete(int $id): RedirectResponse
    {
        $return = SalesReturn::with('items.product')->findOrFail($id);

        $this->authorize('complete', $return);

        if ($return->status !== 'Draft') {
            return back()->withErrors(['status' => 'Only Draft Returns can be completed.']);
        }

        DB::transaction(function () use ($return) {
            $return->update(['status' => 'Completed']);

            foreach ($return->items as $item) {
                // Restock inventory using StockService::recordInflow
                StockService::recordInflow(
                    $return->tenant_id,
                    $item->product_id,
                    $item->warehouse_id,
                    $item->quantity,
                    $item->unit_price, // returned cost rate
                    'SalesReturn',
                    $return->id
                );
            }
        });

        return back()->with('success', 'Sales Return completed! Inventory has been returned and restocked in the warehouse.');
    }
}
