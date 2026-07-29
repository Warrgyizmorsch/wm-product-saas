<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\PaymentAllocation;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Events\CustomerPaymentReceived;
use App\Domains\Sales\Repositories\CustomerPaymentRepository;
use App\Domains\CRM\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class CustomerPaymentController extends Controller
{
    public function __construct(
        private readonly CustomerPaymentRepository $paymentRepo
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomerPayment::class);
        $payments = $this->paymentRepo->getPaginated($request->all(), 15);

        return view('modules.sales.payments.index', compact('payments'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', CustomerPayment::class);

        $customers = Customer::query()->orderBy('name')->get();
        $invoices = Invoice::whereIn('status', ['Draft', 'Sent', 'Posted', 'Partially Paid', 'Overdue'])->latest()->get();
        $salesOrders = SalesOrder::whereIn('status', ['Confirmed', 'Partially Shipped'])->latest()->get();

        $latest = CustomerPayment::latest('id')->first();
        $nextSeq = $latest ? intval(str_replace('PAY-', '', $latest->payment_number)) + 1 : 1;
        $nextPaymentNumber = 'PAY-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        $prefillCustomerId    = $request->input('customer_id');
        $prefillInvoiceId     = $request->input('invoice_id');
        $prefillSalesOrderId  = $request->input('sales_order_id');
        $prefillAmount        = null;

        if ($prefillInvoiceId) {
            $prefillInvoice = Invoice::find($prefillInvoiceId);
            if ($prefillInvoice) {
                $prefillAmount      = $prefillInvoice->balance_due;
                $prefillCustomerId  = $prefillCustomerId ?: $prefillInvoice->customer_id;
            }
        }

        return view('modules.sales.payments.create', compact(
            'customers', 'invoices', 'salesOrders', 'nextPaymentNumber',
            'prefillCustomerId', 'prefillInvoiceId', 'prefillSalesOrderId', 'prefillAmount'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CustomerPayment::class);

        $validated = $request->validate([
            'customer_id'    => ['required', 'exists:customers,id'],
            'payment_number' => ['required', 'string', 'max:255', 'unique:customer_payments,payment_number'],
            'payment_date'   => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:100'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'reference_no'   => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string'],
            'allocate_to'    => ['nullable', 'string', 'in:invoice,sales_order,unallocated'],
            'invoice_id'     => ['nullable', 'exists:invoices,id'],
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
        ]);

        $payment = DB::transaction(function () use ($validated) {
            $payment = CustomerPayment::create([
                'tenant_id'      => tenant_id() ?? 1,
                'customer_id'    => $validated['customer_id'],
                'payment_number' => $validated['payment_number'],
                'payment_date'   => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'amount'         => $validated['amount'],
                'reference_no'   => $validated['reference_no'] ?? null,
                'notes'          => $validated['notes'] ?? null,
                'status'         => 'Posted',   // Direct post — Odoo/Tally style
            ]);

            $allocateTo    = $validated['allocate_to'] ?? 'unallocated';
            $allocatedAmt  = (float) $validated['amount'];

            // ── Link to Invoice ─────────────────────────────────────────────
            if ($allocateTo === 'invoice' && !empty($validated['invoice_id'])) {
                $invoice = Invoice::find($validated['invoice_id']);
                if ($invoice) {
                    $apply = min($allocatedAmt, (float) $invoice->balance_due);

                    PaymentAllocation::create([
                        'customer_payment_id' => $payment->id,
                        'sales_order_id'      => $invoice->sales_order_id,
                        'invoice_id'          => $invoice->id,
                        'allocated_amount'    => $apply,
                    ]);

                    $invoice->amount_paid = (float) $invoice->amount_paid + $apply;
                    $invoice->balance_due = max(0, (float) $invoice->total_amount - $invoice->amount_paid);
                    $invoice->status      = $invoice->balance_due <= 0 ? 'Paid' : 'Partially Paid';
                    $invoice->save();
                }
            }

            // ── Link to Sales Order (advance) ────────────────────────────────
            elseif ($allocateTo === 'sales_order' && !empty($validated['sales_order_id'])) {
                PaymentAllocation::create([
                    'customer_payment_id' => $payment->id,
                    'sales_order_id'      => $validated['sales_order_id'],
                    'invoice_id'          => null,
                    'allocated_amount'    => $allocatedAmt,
                ]);
            }
            // else: unallocated — no PaymentAllocation row, kept as advance credit

            event(new CustomerPaymentReceived($payment));

            return $payment;
        });

        return redirect()->route('sales.payments.show', $payment->id)->with('success', "Payment {$payment->payment_number} recorded successfully.");
    }

    public function show(int $id): View
    {
        $payment = $this->paymentRepo->find($id);
        if (!$payment) abort(404);
        $this->authorize('view', $payment);

        return view('modules.sales.payments.show', compact('payment'));
    }
}
