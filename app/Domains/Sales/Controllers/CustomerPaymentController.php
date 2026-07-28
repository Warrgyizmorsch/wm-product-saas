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
        $invoices = Invoice::whereIn('status', ['Posted', 'Partially Paid', 'Overdue'])->latest()->get();
        $salesOrders = SalesOrder::whereIn('status', ['Confirmed', 'Partially Shipped'])->latest()->get();

        $latest = CustomerPayment::latest('id')->first();
        $nextSeq = $latest ? intval(str_replace('PAY-', '', $latest->payment_number)) + 1 : 1;
        $nextPaymentNumber = 'PAY-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        return view('modules.sales.payments.create', compact('customers', 'invoices', 'salesOrders', 'nextPaymentNumber'));
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
            'allocations'    => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['nullable', 'exists:invoices,id'],
            'allocations.*.sales_order_id' => ['nullable', 'exists:sales_orders,id'],
            'allocations.*.allocated_amount' => ['required', 'numeric', 'min:0.01'],
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
            ]);

            if (!empty($validated['allocations'])) {
                foreach ($validated['allocations'] as $alloc) {
                    PaymentAllocation::create([
                        'customer_payment_id' => $payment->id,
                        'invoice_id'          => $alloc['invoice_id'] ?? null,
                        'sales_order_id'      => $alloc['sales_order_id'] ?? null,
                        'allocated_amount'    => $alloc['allocated_amount'],
                    ]);

                    if (!empty($alloc['invoice_id'])) {
                        $invoice = Invoice::find($alloc['invoice_id']);
                        if ($invoice) {
                            $invoice->amount_paid += $alloc['allocated_amount'];
                            $invoice->balance_due = max(0, $invoice->total_amount - $invoice->amount_paid);
                            $invoice->status = $invoice->balance_due <= 0 ? 'Paid' : 'Partially Paid';
                            $invoice->save();
                        }
                    }
                }
            }

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
