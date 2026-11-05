<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Repositories\VendorPaymentRepository;
use App\Domains\Purchase\Repositories\VendorBillRepository;
use App\Domains\Purchase\Services\VendorPaymentService;
use App\Domains\Inventory\Models\Vendor;
use App\Services\Access\AccessService;
use App\Exports\VendorPaymentExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class VendorPaymentController extends Controller
{
    public function __construct(
        protected VendorPaymentRepository $paymentRepo,
        protected VendorBillRepository $billRepo,
        protected VendorPaymentService $paymentService
    ) {}

    public function index(Request $request)
    {
        $this->authorizePurchase('purchase.payments.view');

        $payments = $this->paymentRepo->getPaginatedPayments($request->all(), 10);
        return view('modules.purchase.payments.index', compact('payments'));
    }

    /**
     * Export Vendor Payments to Excel with custom columns and active query filters
     */
    public function export(Request $request)
    {
        $this->authorizePurchase('purchase.payments.view');
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        return Excel::download(
            new VendorPaymentExport($tenantId, $request->all()),
            'vendor_payments_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    public function create(Request $request)
    {
        $this->authorizePurchase('purchase.payments.create');

        $tenantId = require_tenant_id();

        $billId = $request->query('bill_id') ?? $request->query('vendor_bill_id');
        $selectedBill = null;
        if ($billId) {
            $selectedBill = $this->billRepo->findWithDetails($billId);
        }

        $availableAdvance = 0.0;
        $totalAdvancePaid = 0.0;
        if ($selectedBill) {
            $poId = $selectedBill->purchase_order_id ?? $selectedBill->goodsReceiptNote?->purchase_order_id;
            $availableAdvance = app(\App\Domains\Purchase\Services\VendorBillService::class)
                ->getAvailableVendorAdvance((int)$selectedBill->vendor_id, $tenantId, $poId);
            if ($selectedBill->purchaseOrder) {
                $totalAdvancePaid = (float)($selectedBill->purchaseOrder->total_advance_paid ?? 0);
            }
        }

        $dueAmount = $selectedBill ? (float)($selectedBill->due_amount ?? 0) : 0.0;
        $suggestedNetPayable = max(0.0, $dueAmount - $availableAdvance);

        $vendorId = $request->query('vendor_id');
        $preselectedVendor = $vendorId ? Vendor::where('tenant_id', $tenantId)->find($vendorId) : null;
        if (!$selectedBill && $preselectedVendor && (float)$preselectedVendor->opening_balance > 0) {
            $unallocatedPaid = (float) \App\Domains\Purchase\Models\VendorPayment::where('tenant_id', $tenantId)
                ->where('vendor_id', $preselectedVendor->id)
                ->whereIn('payment_type', ['Opening Balance', 'Advance'])
                ->whereDoesntHave('allocations')
                ->where('status', '!=', 'cancelled')
                ->sum('amount');
            $remainingOpening = max(0.0, (float)$preselectedVendor->opening_balance - $unallocatedPaid);
            if ($remainingOpening > 0.001) {
                $suggestedNetPayable = $remainingOpening;
            }
        }

        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $unpaidBills = $this->billRepo->getPaginatedBills(['status' => 'Unpaid'], 100);

        $bankAccounts = \App\Domains\Accounting\Models\ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('is_cash_or_bank', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('modules.purchase.payments.create', compact(
            'vendors',
            'unpaidBills',
            'selectedBill',
            'availableAdvance',
            'totalAdvancePaid',
            'suggestedNetPayable',
            'preselectedVendor',
            'bankAccounts'
        ));
    }

    public function store(Request $request)
    {
        $this->authorizePurchase('purchase.payments.create');

        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'vendor_id' => 'required|integer|exists:vendors,id',
            'vendor_bill_id' => 'nullable|integer|exists:vendor_bills,id',
            'allocations' => 'nullable|array',
            'allocations.*.vendor_bill_id' => 'nullable|integer',
            'allocations.*.allocated_amount' => 'nullable|numeric|min:0',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'bank_account_id' => 'nullable|integer',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'auto_apply_advance' => 'nullable|boolean',
        ]);

        if (!empty($validated['bank_account_id'])) {
            $isBankLedger = \App\Domains\Accounting\Models\ChartOfAccount::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($validated['bank_account_id'])
                ->where('is_cash_or_bank', true)
                ->exists();
            if (!$isBankLedger) {
                return back()->withInput()->withErrors(['bank_account_id' => 'Please select a valid cash or bank account.']);
            }
        }

        if (empty($validated['vendor_bill_id']) && !empty($validated['allocations'][0]['vendor_bill_id'])) {
            $validated['vendor_bill_id'] = $validated['allocations'][0]['vendor_bill_id'];
        }

        if (!empty($validated['vendor_bill_id'])) {
            $targetBill = $this->billRepo->find($validated['vendor_bill_id']);
            if ($targetBill) {
                $maxPayable = (float) $targetBill->due_amount;
                $attemptedAmount = (float) ($validated['allocations'][0]['allocated_amount'] ?? $validated['amount']);
                if ($attemptedAmount > ($maxPayable + 0.01)) {
                    $errorMsg = "Payment amount (" . format_currency($attemptedAmount) . ") cannot exceed the bill's net balance due of " . format_currency($maxPayable) . ".";
                    return back()->withInput()
                        ->with('error', $errorMsg)
                        ->withErrors(['amount' => $errorMsg]);
                }
            }
        }

        try {
            $payment = $this->paymentService->recordPayment($validated, $tenantId);
            \App\Domains\Purchase\Services\PurchaseNotificationService::notifyPaymentRecorded($payment);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()
                ->with('error', $e->getMessage())
                ->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('purchase.payments.index')
            ->with('success', "Payment {$payment->payment_number} recorded successfully.");
    }

    public function show(int $id)
    {
        $this->authorizePurchase('purchase.payments.view');

        $payment = $this->paymentRepo->find($id);
        if (!$payment) abort(404);

        $payment->load([
            'vendor',
            'bankAccount',
            'creator',
            'purchaseOrder',
            'allocations.bill.allocations.payment',
        ]);
        return view('modules.purchase.payments.show', compact('payment'));
    }

    public function downloadPdf(int $id)
    {
        $this->authorizePurchase('purchase.payments.view');

        $payment = $this->paymentRepo->find($id);
        if (!$payment) abort(404);

        $payment->load([
            'vendor',
            'bankAccount',
            'creator',
            'purchaseOrder',
            'allocations.bill.allocations.payment',
            'allocations.bill.allocations.vendorPayment',
        ]);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('modules.purchase.payments.pdf', compact('payment'));
        return $pdf->download("Payment_Voucher_{$payment->payment_number}.pdf");
    }

    public function edit(int $id)
    {
        $this->authorizePurchase('purchase.payments.edit');

        $payment = $this->paymentRepo->find($id);
        if (!$payment) abort(404);
        return view('modules.purchase.payments.edit', compact('payment'));
    }

    public function update(Request $request, int $id)
    {
        $this->authorizePurchase('purchase.payments.edit');

        $payment = $this->paymentRepo->find($id);
        if (!$payment) abort(404);

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $payment->update($validated);

        return redirect()->route('purchase.payments.show', $id)
            ->with('success', 'Payment updated successfully.');
    }

    public function destroy(int $id)
    {
        $this->authorizePurchase('purchase.payments.delete');

        $payment = $this->paymentRepo->find($id);
        if (!$payment) abort(404);

        $payment->delete();

        return redirect()->route('purchase.payments.index')
            ->with('success', 'Payment deleted successfully.');
    }

    private function authorizePurchase(string $permission): void
    {
        abort_unless(
            app(AccessService::class)->allows(auth()->user(), $permission, [
                'tenant_id' => auth()->user()?->tenant_id,
            ]),
            403
        );
    }
}
