<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\CRM\Models\Customer;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\PaymentAllocation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class CustomerPaymentController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CustomerPayment::class);

        $payments = CustomerPayment::with('customer')->latest()->get();

        return view('modules.sales.payments.index', [
            'payments' => $payments,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', CustomerPayment::class);

        $customers = Customer::orderBy('name')->get();
        
        // Confirmed sales orders to receive advance for
        $salesOrders = SalesOrder::whereIn('status', ['Confirmed', 'Partially Delivered'])->orderBy('sales_order_number')->get();
        
        // Unpaid or partially paid invoices to pay
        $invoices = Invoice::whereIn('status', ['Draft', 'Sent', 'Partially Paid'])->orderBy('invoice_number')->get();

        // Calculate next payment number
        $latest = CustomerPayment::latest('id')->first();
        $nextSeq = $latest ? intval(str_replace('PAY-', '', $latest->payment_number)) + 1 : 1;
        $nextPaymentNumber = 'PAY-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        return view('modules.sales.payments.create', [
            'customers' => $customers,
            'salesOrders' => $salesOrders,
            'invoices' => $invoices,
            'nextPaymentNumber' => $nextPaymentNumber,
            'prefillSalesOrderId' => $request->input('sales_order_id'),
            'prefillInvoiceId' => $request->input('invoice_id'),
            'prefillCustomerId' => $request->input('customer_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CustomerPayment::class);

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payment_number' => 'required|string',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'reference_no' => 'nullable|string',
            'notes' => 'nullable|string',
            'allocate_to' => 'nullable|string|in:sales_order,invoice,unallocated',
            'sales_order_id' => 'required_if:allocate_to,sales_order|nullable|exists:sales_orders,id',
            'invoice_id' => 'required_if:allocate_to,invoice|nullable|exists:invoices,id',
        ]);

        $payment = DB::transaction(function () use ($validated) {
            $payment = CustomerPayment::create([
                'customer_id' => $validated['customer_id'],
                'payment_number' => $validated['payment_number'],
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'status' => 'Confirmed', // Automatically confirmed on creation
                'notes' => $validated['notes'] ?? null,
            ]);

            // Handle Allocation
            $allocateTo = $validated['allocate_to'] ?? 'unallocated';
            if ($allocateTo === 'sales_order' && !empty($validated['sales_order_id'])) {
                PaymentAllocation::create([
                    'customer_payment_id' => $payment->id,
                    'sales_order_id' => $validated['sales_order_id'],
                    'invoice_id' => null,
                    'allocated_amount' => $validated['amount'],
                ]);
            } elseif ($allocateTo === 'invoice' && !empty($validated['invoice_id'])) {
                PaymentAllocation::create([
                    'customer_payment_id' => $payment->id,
                    'sales_order_id' => null,
                    'invoice_id' => $validated['invoice_id'],
                    'allocated_amount' => $validated['amount'],
                ]);

                // Update Invoice Status
                $invoice = Invoice::findOrFail($validated['invoice_id']);
                $totalAllocated = PaymentAllocation::where('invoice_id', $invoice->id)->sum('allocated_amount');

                if ($totalAllocated >= $invoice->grand_total) {
                    $invoice->update(['status' => 'Paid']);
                } else {
                    $invoice->update(['status' => 'Partially Paid']);
                }
            }

            return $payment;
        });

        return redirect()
            ->route('sales.payments.show', $payment->id)
            ->with('success', 'Customer payment recorded successfully!');
    }

    public function show(int $id): View
    {
        $payment = CustomerPayment::with(['customer', 'allocations.salesOrder', 'allocations.invoice'])->findOrFail($id);

        $this->authorize('view', $payment);

        return view('modules.sales.payments.show', [
            'payment' => $payment,
        ]);
    }
}
