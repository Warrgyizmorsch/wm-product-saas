<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\PaymentAllocation;
use App\Domains\Sales\Events\InvoicePosted;
use App\Domains\Sales\Repositories\InvoiceRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepo
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);
        $invoices = $this->invoiceRepo->getPaginated($request->all(), 15);

        return view('modules.sales.invoices.index', compact('invoices'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Invoice::class);

        $salesOrderId = $request->input('sales_order_id');
        $deliveryOrderId = $request->input('material_requirement_id');

        $deliveryOrder = null;
        if ($deliveryOrderId) {
            $deliveryOrder = MaterialRequirement::with('items.product', 'items.warehouse', 'items.salesOrderItem')->findOrFail($deliveryOrderId);
            $salesOrderId = $deliveryOrder->sales_order_id;
        }

        $salesOrder = SalesOrder::with('items.product', 'items.warehouse', 'customer')->findOrFail($salesOrderId);

        $latest = Invoice::latest('id')->first();
        $nextSeq = $latest ? intval(str_replace('INV-', '', $latest->invoice_number)) + 1 : 1;
        $nextInvoiceNumber = 'INV-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        $advanceAllocations = PaymentAllocation::where('sales_order_id', $salesOrder->id)
            ->whereNull('invoice_id')
            ->sum('allocated_amount');

        $invoiceItems = [];
        if ($deliveryOrder) {
            foreach ($deliveryOrder->items as $doItem) {
                $soItem = $doItem->salesOrderItem;
                $quantity = floatval($doItem->quantity);
                $unitPrice = $soItem ? floatval($soItem->unit_price) : 0.0;
                $taxRate = $soItem ? floatval($soItem->tax_rate) : 0.0;
                $discount = $soItem ? floatval($soItem->discount) : 0.0;

                $subtotal = $quantity * $unitPrice;
                $discountAmt = $discount;
                $taxable = max(0, $subtotal - $discountAmt);
                $taxAmt = $taxable * ($taxRate / 100);

                $invoiceItems[] = [
                    'sales_order_item_id' => $soItem?->id,
                    'product_id'          => $doItem->product_id,
                    'item_name'           => $doItem->product?->name ?? 'Item',
                    'description'         => $doItem->description,
                    'quantity'            => $quantity,
                    'unit_price'          => $unitPrice,
                    'discount'            => $discount,
                    'tax_rate'            => $taxRate,
                    'tax_amount'          => $taxAmt,
                    'total_amount'        => $taxable + $taxAmt,
                ];
            }
        } else {
            foreach ($salesOrder->items as $soItem) {
                $quantity = floatval($soItem->quantity);
                $unitPrice = floatval($soItem->unit_price);
                $taxRate = floatval($soItem->tax_rate);
                $discount = floatval($soItem->discount);

                $subtotal = $quantity * $unitPrice;
                $discountAmt = $discount;
                $taxable = max(0, $subtotal - $discountAmt);
                $taxAmt = $taxable * ($taxRate / 100);

                $invoiceItems[] = [
                    'sales_order_item_id' => $soItem->id,
                    'product_id'          => $soItem->product_id,
                    'item_name'           => $soItem->product?->name ?? 'Item',
                    'description'         => $soItem->description,
                    'quantity'            => $quantity,
                    'unit_price'          => $unitPrice,
                    'discount'            => $discount,
                    'tax_rate'            => $taxRate,
                    'tax_amount'          => $taxAmt,
                    'total_amount'        => $taxable + $taxAmt,
                ];
            }
        }

        return view('modules.sales.invoices.create', compact('salesOrder', 'deliveryOrder', 'nextInvoiceNumber', 'invoiceItems', 'advanceAllocations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $validated = $request->validate([
            'sales_order_id'          => ['required', 'exists:sales_orders,id'],
            'material_requirement_id' => ['nullable', 'exists:material_requirements,id'],
            'invoice_number'          => ['required', 'string', 'max:255', 'unique:invoices,invoice_number'],
            'invoice_date'            => ['required', 'date'],
            'due_date'                => ['required', 'date', 'after_or_equal:invoice_date'],
            'notes'                   => ['nullable', 'string'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.sales_order_item_id' => ['nullable', 'exists:sales_order_items,id'],
            'items.*.product_id'      => ['required', 'integer', 'exists:products,id'],
            'items.*.item_name'       => ['required', 'string', 'max:255'],
            'items.*.description'     => ['nullable', 'string'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'items.*.discount'        => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate'        => ['nullable', 'numeric', 'min:0'],
        ], [
            'items.*.product_id.required' => 'Please select a valid Product from the dropdown for each item line.',
            'items.*.product_id.exists'   => 'The selected product does not exist in inventory.',
        ]);

        $salesOrder = SalesOrder::findOrFail($validated['sales_order_id']);

        $invoice = DB::transaction(function () use ($validated, $salesOrder) {
            $subtotal = 0;
            $taxAmount = 0;
            $discountAmount = 0;

            foreach ($validated['items'] as $item) {
                $qty = floatval($item['quantity']);
                $price = floatval($item['unit_price']);
                $disc = floatval($item['discount'] ?? 0);
                $taxR = floatval($item['tax_rate'] ?? 0);

                $lineSubtotal = $qty * $price;
                $lineTaxable = max(0, $lineSubtotal - $disc);
                $lineTax = $lineTaxable * ($taxR / 100);

                $subtotal += $lineSubtotal;
                $discountAmount += $disc;
                $taxAmount += $lineTax;
            }

            $totalAmount = max(0, $subtotal - $discountAmount + $taxAmount);

            $invoice = Invoice::create([
                'tenant_id'               => $salesOrder->tenant_id,
                'sales_order_id'          => $salesOrder->id,
                'customer_id'             => $salesOrder->customer_id,
                'material_requirement_id' => $validated['material_requirement_id'] ?? null,
                'invoice_number'          => $validated['invoice_number'],
                'invoice_date'            => $validated['invoice_date'],
                'due_date'                => $validated['due_date'],
                'status'                  => 'Draft',
                'subtotal'                => $subtotal,
                'tax_amount'              => $taxAmount,
                'discount_amount'         => $discountAmount,
                'total_amount'            => $totalAmount,
                'amount_paid'             => 0,
                'balance_due'             => $totalAmount,
                'notes'                   => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $qty = floatval($item['quantity']);
                $price = floatval($item['unit_price']);
                $disc = floatval($item['discount'] ?? 0);
                $taxR = floatval($item['tax_rate'] ?? 0);

                $lineSubtotal = $qty * $price;
                $lineTaxable = max(0, $lineSubtotal - $disc);
                $lineTax = $lineTaxable * ($taxR / 100);

                InvoiceItem::create([
                    'invoice_id'          => $invoice->id,
                    'sales_order_item_id' => $item['sales_order_item_id'] ?? null,
                    'product_id'          => $item['product_id'] ?? null,
                    'item_name'           => $item['item_name'],
                    'description'         => $item['description'] ?? null,
                    'quantity'            => $qty,
                    'unit_price'          => $price,
                    'discount'            => $disc,
                    'tax_rate'            => $taxR,
                    'tax_amount'          => $lineTax,
                    'total_amount'        => $lineTaxable + $lineTax,
                ]);
            }

            $unallocatedAdvances = PaymentAllocation::where('sales_order_id', $salesOrder->id)
                ->whereNull('invoice_id')
                ->get();

            $remainingBalance = $invoice->balance_due;
            foreach ($unallocatedAdvances as $allocation) {
                if ($remainingBalance <= 0) break;

                $applyAmount = min($allocation->allocated_amount, $remainingBalance);
                $allocation->update([
                    'invoice_id' => $invoice->id,
                    'allocated_amount' => $applyAmount,
                ]);

                $invoice->amount_paid += $applyAmount;
                $invoice->balance_due -= $applyAmount;
                $remainingBalance -= $applyAmount;
            }

            if ($invoice->amount_paid > 0) {
                $invoice->status = $invoice->balance_due <= 0 ? 'Paid' : 'Partially Paid';
                $invoice->save();
            }

            return $invoice;
        });

        return redirect()->route('sales.invoices.show', $invoice->id)->with('success', "Invoice {$invoice->invoice_number} created successfully.");
    }

    public function show(int $id): View
    {
        $invoice = $this->invoiceRepo->find($id);
        if (!$invoice) abort(404);
        $this->authorize('view', $invoice);

        return view('modules.sales.invoices.show', compact('invoice'));
    }

    public function post(int $id): RedirectResponse
    {
        $invoice = $this->invoiceRepo->find($id);
        if (!$invoice) abort(404);
        $this->authorize('update', $invoice);

        if ($invoice->status !== 'Draft') {
            return back()->withErrors(['status' => 'Only Draft invoices can be posted.']);
        }

        $invoice->status = 'Posted';
        $invoice->save();

        event(new InvoicePosted($invoice));

        return redirect()->route('sales.invoices.show', $invoice->id)->with('success', "Invoice {$invoice->invoice_number} posted successfully.");
    }
}
