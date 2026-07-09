<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\DeliveryOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\PaymentAllocation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(): View
    {
        $invoices = Invoice::with('salesOrder.customer')->latest()->get();

        return view('modules.sales.invoices.index', [
            'invoices' => $invoices,
        ]);
    }

    public function create(Request $request): View
    {
        $salesOrderId = $request->input('sales_order_id');
        $deliveryOrderId = $request->input('delivery_order_id');

        $deliveryOrder = null;
        if ($deliveryOrderId) {
            $deliveryOrder = DeliveryOrder::with('items.product', 'items.warehouse', 'items.salesOrderItem')->findOrFail($deliveryOrderId);
            $salesOrderId = $deliveryOrder->sales_order_id;
        }

        $salesOrder = SalesOrder::with('items.product', 'items.warehouse', 'customer')->findOrFail($salesOrderId);

        // Calculate next invoice number
        $latest = Invoice::latest('id')->first();
        $nextSeq = $latest ? intval(str_replace('INV-', '', $latest->invoice_number)) + 1 : 1;
        $nextInvoiceNumber = 'INV-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        // Fetch any advances already paid on this sales order
        $advanceAllocations = PaymentAllocation::where('sales_order_id', $salesOrder->id)
            ->whereNull('invoice_id')
            ->sum('allocated_amount');

        // Map items from delivery order or sales order
        $invoiceItems = [];
        if ($deliveryOrder) {
            foreach ($deliveryOrder->items as $doItem) {
                $soItem = $doItem->salesOrderItem;
                $quantity = floatval($doItem->quantity);
                $unitPrice = $soItem ? floatval($soItem->unit_price) : 0;
                $taxRate = $soItem ? floatval($soItem->tax_rate) : 0;
                
                // Calculate pro-rata discount based on relative quantities
                $discount = 0;
                if ($soItem && floatval($soItem->quantity) > 0) {
                    $discount = (floatval($soItem->discount) / floatval($soItem->quantity)) * $quantity;
                }

                $subtotal = ($quantity * $unitPrice) - $discount;

                $invoiceItems[] = [
                    'sales_order_item_id' => $soItem?->id,
                    'delivery_order_item_id' => $doItem->id,
                    'product_id' => $doItem->product_id,
                    'product_name' => $doItem->product?->name ?? ($soItem?->item_name ?? 'Product'),
                    'sku' => $doItem->product?->sku,
                    'warehouse_id' => $doItem->warehouse_id,
                    'warehouse_name' => $doItem->warehouse?->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'discount' => $discount,
                    'subtotal' => $subtotal,
                ];
            }
        } else {
            foreach ($salesOrder->items as $soItem) {
                if (!$soItem->product_id) continue;
                $invoiceItems[] = [
                    'sales_order_item_id' => $soItem->id,
                    'delivery_order_item_id' => null,
                    'product_id' => $soItem->product_id,
                    'product_name' => $soItem->item_name,
                    'sku' => $soItem->product?->sku,
                    'warehouse_id' => $soItem->warehouse_id,
                    'warehouse_name' => $soItem->warehouse?->name,
                    'quantity' => floatval($soItem->quantity),
                    'unit_price' => floatval($soItem->unit_price),
                    'tax_rate' => floatval($soItem->tax_rate),
                    'discount' => floatval($soItem->discount),
                    'subtotal' => floatval($soItem->amount),
                ];
            }
        }

        return view('modules.sales.invoices.create', [
            'salesOrder' => $salesOrder,
            'deliveryOrder' => $deliveryOrder,
            'invoiceItems' => $invoiceItems,
            'nextInvoiceNumber' => $nextInvoiceNumber,
            'advanceAllocations' => $advanceAllocations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'delivery_order_id' => 'nullable|exists:delivery_orders,id',
            'invoice_number' => 'required|string',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'items.*.sales_order_item_id' => 'nullable|integer',
            'items.*.delivery_order_item_id' => 'nullable|integer',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        $invoice = DB::transaction(function () use ($validated) {
            $salesOrder = SalesOrder::findOrFail($validated['sales_order_id']);

            // Calculate totals
            $subtotal = 0;
            $taxTotal = 0;
            $discountTotal = 0;

            foreach ($validated['items'] as $itemData) {
                $qty = floatval($itemData['quantity']);
                $price = floatval($itemData['unit_price']);
                $taxRate = floatval($itemData['tax_rate'] ?? 0);
                $discount = floatval($itemData['discount'] ?? 0);

                $lineSubtotal = ($qty * $price) - $discount;
                $lineTax = $lineSubtotal * ($taxRate / 100);

                $subtotal += $qty * $price;
                $discountTotal += $discount;
                $taxTotal += $lineTax;
            }

            $grandTotal = $subtotal - $discountTotal + $taxTotal;

            // Create invoice in draft status
            $invoice = Invoice::create([
                'tenant_id' => $salesOrder->tenant_id,
                'sales_order_id' => $salesOrder->id,
                'delivery_order_id' => $validated['delivery_order_id'] ?? null,
                'invoice_number' => $validated['invoice_number'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'status' => 'Draft',
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'discount' => $discountTotal,
                'grand_total' => $grandTotal,
                'notes' => $validated['notes'],
            ]);

            // Save items
            foreach ($validated['items'] as $itemData) {
                $qty = floatval($itemData['quantity']);
                $price = floatval($itemData['unit_price']);
                $taxRate = floatval($itemData['tax_rate'] ?? 0);
                $discount = floatval($itemData['discount'] ?? 0);
                $lineSubtotal = ($qty * $price) - $discount;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'sales_order_item_id' => $itemData['sales_order_item_id'] ?? null,
                    'delivery_order_item_id' => $itemData['delivery_order_item_id'] ?? null,
                    'product_id' => $itemData['product_id'],
                    'warehouse_id' => $itemData['warehouse_id'],
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'discount' => $discount,
                    'subtotal' => $lineSubtotal,
                ]);
            }

            // Automate Advance Allocation: Link any existing advance allocations from this SO to this invoice
            $advances = PaymentAllocation::where('sales_order_id', $salesOrder->id)
                ->whereNull('invoice_id')
                ->get();

            $allocatedSum = 0;
            foreach ($advances as $adv) {
                $adv->update(['invoice_id' => $invoice->id]);
                $allocatedSum += $adv->allocated_amount;
            }

            if ($allocatedSum > 0) {
                if ($allocatedSum >= $grandTotal) {
                    $invoice->update(['status' => 'Paid']);
                } else {
                    $invoice->update(['status' => 'Partially Paid']);
                }
            }

            return $invoice;
        });

        return redirect()
            ->route('sales.invoices.show', $invoice->id)
            ->with('success', 'Invoice generated successfully!');
    }

    public function show(int $id): View
    {
        $invoice = Invoice::with(['salesOrder.customer', 'items.product', 'items.warehouse', 'allocations.payment', 'deliveryOrder'])->findOrFail($id);

        // Sum of all allocations applied to this invoice
        $adjustedAmount = $invoice->allocations->sum('allocated_amount');
        $balanceDue = max(0.00, $invoice->grand_total - $adjustedAmount);

        return view('modules.sales.invoices.show', [
            'invoice' => $invoice,
            'adjustedAmount' => $adjustedAmount,
            'balanceDue' => $balanceDue,
        ]);
    }

    public function send(int $id): RedirectResponse
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status === 'Draft') {
            $invoice->update(['status' => 'Sent']);
        }

        return back()->with('success', 'Invoice sent successfully to customer!');
    }
}
