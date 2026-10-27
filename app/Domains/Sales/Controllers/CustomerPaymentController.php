<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\PaymentAllocation;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Events\CustomerPaymentReceived;
use App\Domains\Sales\Repositories\CustomerPaymentRepository;
use App\Domains\CRM\Models\Customer;
use App\Exports\CustomerPaymentExport;
use Maatwebsite\Excel\Facades\Excel;
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

    /**
     * Export Customer Payment Receipts to Excel with custom columns and active query filters
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', CustomerPayment::class);
        $tenantId = tenant_id() ?? auth()->user()->tenant_id ?? 1;

        return Excel::download(
            new CustomerPaymentExport($tenantId, $request->all()),
            'customer_payments_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    public function create(Request $request): View
    {
        $this->authorize('create', CustomerPayment::class);

        $customers = Customer::query()->orderBy('name')->get();
        $invoices = Invoice::whereIn('status', ['Posted', 'Partially Paid', 'Overdue'])->where('balance_due', '>', 0)->latest()->get();
        $salesOrders = SalesOrder::whereIn('status', ['Confirmed', 'Partially Shipped'])->latest()->get();

        $tenantId = require_tenant_id();
        $latest = CustomerPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
        $nextSeq = $latest ? intval(str_replace('PAY-', '', $latest->payment_number)) + 1 : 1;
        $nextPaymentNumber = 'PAY-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        while (CustomerPayment::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('payment_number', $nextPaymentNumber)->exists()) {
            $nextSeq++;
            $nextPaymentNumber = 'PAY-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        }

        $prefillCustomerId    = $request->input('customer_id') ?? $request->input('amp;customer_id');
        $prefillInvoiceId     = $request->input('invoice_id') ?? $request->input('amp;invoice_id');
        $prefillSalesOrderId  = $request->input('sales_order_id') ?? $request->input('amp;sales_order_id');
        $prefillAmount        = null;

        if ($prefillInvoiceId) {
            $prefillInvoice = Invoice::find($prefillInvoiceId);
            if ($prefillInvoice && in_array($prefillInvoice->status, ['Posted', 'Partially Paid', 'Overdue'])) {
                $prefillAmount      = $prefillInvoice->balance_due;
                $prefillCustomerId  = $prefillCustomerId ?: ($prefillInvoice->customer_id ?? $prefillInvoice->salesOrder?->customer_id);
            } else {
                $prefillInvoiceId = null;
            }
        }

        if ($prefillSalesOrderId) {
            $prefillOrder = SalesOrder::with('allocations')->find($prefillSalesOrderId);
            if ($prefillOrder) {
                $prefillCustomerId = $prefillCustomerId ?: $prefillOrder->customer_id;
                if ($prefillAmount === null) {
                    $paid = (float) $prefillOrder->allocations->sum('allocated_amount');
                    $unpaid = max(0, (float) $prefillOrder->total_amount - $paid);
                    if ($unpaid > 0) {
                        $prefillAmount = $unpaid;
                    }
                }
            } else {
                $prefillSalesOrderId = null;
            }
        }

        $bankAccounts = \App\Domains\Accounting\Models\ChartOfAccount::query()
            ->where('is_cash_or_bank', true)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        return view('modules.sales.payments.create', compact(
            'customers', 'invoices', 'salesOrders', 'nextPaymentNumber', 'bankAccounts',
            'prefillCustomerId', 'prefillInvoiceId', 'prefillSalesOrderId', 'prefillAmount'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = require_tenant_id();

        // If the submitted payment number already exists, auto-increment to the next unique payment number
        if (!empty($request->payment_number) && CustomerPayment::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('payment_number', $request->payment_number)->exists()) {
            $latest = CustomerPayment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->latest('id')
                ->first();
            $nextSeq = $latest ? intval(str_replace('PAY-', '', $latest->payment_number)) + 1 : 1;
            $newPayNum = 'PAY-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            while (CustomerPayment::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('payment_number', $newPayNum)->exists()) {
                $nextSeq++;
                $newPayNum = 'PAY-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            }
            $request->merge(['payment_number' => $newPayNum]);
        }

        $validated = $request->validate([
            'customer_id'    => ['required', 'exists:customers,id'],
            'payment_number' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('customer_payments', 'payment_number')->where('tenant_id', $tenantId),
            ],
            'payment_date'   => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:100'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'reference_no'   => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string'],
            'allocate_to'    => ['nullable', 'string', 'in:invoice,sales_order,unallocated,opening_balance'],
            'invoice_id'     => ['nullable', 'exists:invoices,id'],
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
            'bank_account_id' => ['nullable', 'integer'],
            'tds_amount'     => ['nullable', 'numeric', 'min:0'],
            'tds_section_code' => ['nullable', 'string', 'max:20'],
        ]);

        // Only this tenant's own cash/bank ledgers can receive the money.
        if (!empty($validated['bank_account_id'])) {
            $isBankLedger = \App\Domains\Accounting\Models\ChartOfAccount::query()
                ->whereKey($validated['bank_account_id'])->where('is_cash_or_bank', true)->exists();
            if (!$isBankLedger) {
                return back()->withInput()->withErrors(['bank_account_id' => 'Choose a cash or bank ledger.']);
            }
        }

        $baseAmount = convert_to_base((float) $validated['amount']);
        $tdsAmount = round(convert_to_base((float) ($validated['tds_amount'] ?? 0)), 2);

        $payment = DB::transaction(function () use ($validated, $baseAmount, $tdsAmount) {
            $payment = CustomerPayment::create([
                'tenant_id'      => tenant_id() ?? 1,
                'customer_id'    => $validated['customer_id'],
                'payment_number' => $validated['payment_number'],
                'payment_date'   => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'amount'         => $baseAmount,
                'reference_no'   => $validated['reference_no'] ?? null,
                'notes'          => ($validated['allocate_to'] ?? null) === 'opening_balance'
                    ? trim('Against opening balance. ' . ($validated['notes'] ?? ''))
                    : ($validated['notes'] ?? null),
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'tds_amount'     => $tdsAmount,
                'tds_section_code' => $tdsAmount > 0 ? ($validated['tds_section_code'] ?? null) : null,
                'status'         => 'Posted',   // Direct post — Odoo/Tally style
            ]);

            $allocateTo    = $validated['allocate_to'] ?? 'unallocated';
            // TDS withheld by the customer settles the invoice too.
            $allocatedAmt  = $payment->settledAmount();

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
            // else: unallocated / against opening balance — no PaymentAllocation
            // row; posted Dr Bank / Cr the customer's receivable, which settles
            // opening dues or stays as a credit for later invoices.

            event(new CustomerPaymentReceived($payment));

            return $payment;
        });

        \App\Domains\Platform\Services\NotificationRuleService::trigger('sales.payment.received', [
            'doc_no' => $payment->payment_number,
            'customer_name' => $payment->customer?->name ?? 'Customer',
            'amount' => format_currency((float)$payment->amount),
            'payment_method' => $payment->payment_method ?? 'Bank/Cash',
            'date' => $payment->payment_date ? (is_string($payment->payment_date) ? $payment->payment_date : $payment->payment_date->format('Y-m-d')) : now()->format('Y-m-d'),
        ], route('sales.payments.show', $payment->id), $payment);

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
