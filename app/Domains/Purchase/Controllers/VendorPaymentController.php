<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Repositories\VendorPaymentRepository;
use App\Domains\Purchase\Repositories\VendorBillRepository;
use App\Domains\Purchase\Services\VendorPaymentService;
use App\Domains\Inventory\Models\Vendor;
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
        $payments = $this->paymentRepo->getPaginatedPayments($request->all(), 10);
        return view('modules.purchase.payments.index', compact('payments'));
    }

    public function create(Request $request)
    {
        $tenantId = require_tenant_id();

        $billId = $request->query('bill_id') ?? $request->query('vendor_bill_id');
        $selectedBill = null;
        if ($billId) {
            $selectedBill = $this->billRepo->findWithDetails($billId);
        }

        $totalAdvancePaid = 0.0;
        if ($selectedBill && $selectedBill->purchaseOrder) {
            $totalAdvancePaid = (float)($selectedBill->purchaseOrder->total_advance_paid ?? 0);
        }

        $dueAmount = $selectedBill ? (float)($selectedBill->due_amount ?? 0) : 0.0;
        $suggestedNetPayable = max(0.0, $dueAmount - $totalAdvancePaid);

        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $unpaidBills = $this->billRepo->getPaginatedBills(['status' => 'Unpaid'], 100);

        return view('modules.purchase.payments.create', compact(
            'vendors',
            'unpaidBills',
            'selectedBill',
            'totalAdvancePaid',
            'suggestedNetPayable'
        ));
    }

    public function store(Request $request)
    {
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
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if (empty($validated['vendor_bill_id']) && !empty($validated['allocations'][0]['vendor_bill_id'])) {
            $validated['vendor_bill_id'] = $validated['allocations'][0]['vendor_bill_id'];
        }

        $payment = $this->paymentService->recordPayment($validated, $tenantId);

        return redirect()->route('purchase.payments.index')
            ->with('success', "Payment {$payment->payment_number} recorded successfully.");
    }

    public function show(int $id)
    {
        $payment = $this->paymentRepo->find($id);
        if (!$payment) abort(404);

        $payment->load(['vendor', 'vendorBill']);
        return view('modules.purchase.payments.show', compact('payment'));
    }

    public function edit(int $id)
    {
        $payment = $this->paymentRepo->find($id);
        if (!$payment) abort(404);
        return view('modules.purchase.payments.edit', compact('payment'));
    }

    public function update(Request $request, int $id)
    {
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
        $payment = $this->paymentRepo->find($id);
        if (!$payment) abort(404);

        $payment->delete();

        return redirect()->route('purchase.payments.index')
            ->with('success', 'Payment deleted successfully.');
    }
}
