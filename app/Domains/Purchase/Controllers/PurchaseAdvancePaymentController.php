<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Repositories\PurchaseAdvancePaymentRepository;
use App\Domains\Purchase\Repositories\PurchaseOrderRepository;
use App\Domains\Purchase\Services\PurchaseAdvancePaymentService;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use App\Domains\Inventory\Models\Vendor;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;

class PurchaseAdvancePaymentController extends Controller
{
    public function __construct(
        protected PurchaseAdvancePaymentRepository $advanceRepo,
        protected PurchaseOrderRepository $orderRepo,
        protected PurchaseAdvancePaymentService $advanceService
    ) {}

    public function index(Request $request)
    {
        $this->authorizePurchase('purchase.advances.view');

        $advances = $this->advanceRepo->getPaginatedAdvances($request->all(), 10);
        return view('modules.purchase.advances.index', compact('advances'));
    }

    public function create(Request $request)
    {
        $this->authorizePurchase('purchase.advances.create');

        $tenantId = require_tenant_id();

        $poId = $request->query('purchase_order_id');
        $prefillPo = null;
        if ($poId) {
            $prefillPo = $this->orderRepo->find($poId);
        }

        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();

        // Only show active/approved POs that have pending advance limit left (considering bills & unapplied advances)
        $purchaseOrders = PurchaseOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['Completed', 'Cancelled', 'Draft'])
            ->with(['vendor'])
            ->orderBy('id', 'desc')
            ->get()
            ->filter(function ($po) {
                return $po->getRemainingAdvanceLimit() > 0.01;
            });

        $bankAccounts = \App\Domains\Accounting\Models\ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('is_cash_or_bank', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('modules.purchase.advances.create', compact('vendors', 'purchaseOrders', 'prefillPo', 'bankAccounts'));
    }

    public function store(Request $request)
    {
        $this->authorizePurchase('purchase.advances.create');

        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'vendor_id' => 'required|integer|exists:vendors,id',
            'purchase_order_id' => 'nullable|integer|exists:purchase_orders,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:Bank Transfer,Cash,Cheque,UPI,Credit Card',
            'bank_account_id' => 'nullable|integer',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
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

        if (!empty($validated['purchase_order_id'])) {
            $po = PurchaseOrder::where('tenant_id', $tenantId)->find($validated['purchase_order_id']);
            if ($po) {
                if (in_array($po->status, ['Completed', 'Cancelled'])) {
                    return back()->withInput()->withErrors([
                        'purchase_order_id' => "Cannot record advance payment for a {$po->status} Purchase Order."
                    ]);
                }
                $remainingAdvance = $po->getRemainingAdvanceLimit();
                $enteredBase = convert_to_base((float) $validated['amount']);

                if ($enteredBase > ($remainingAdvance + 0.01)) {
                    $formattedRemaining = format_currency($remainingAdvance);
                    return back()->withInput()->withErrors([
                        'amount' => "Advance amount cannot exceed remaining PO unbilled advance limit of {$formattedRemaining}."
                    ]);
                }
            }
        }

        $advance = $this->advanceService->recordAdvancePayment($validated, $tenantId);

        \App\Domains\Purchase\Services\PurchaseNotificationService::notifyAdvanceRecorded($advance);

        if ($advance->purchase_order_id) {
            return redirect()->route('purchase.orders.show', $advance->purchase_order_id)
                ->with('success', "Advance Payment {$advance->advance_number} recorded successfully.");
        }

        return redirect()->route('purchase.advances.index')
            ->with('success', "Advance Payment {$advance->advance_number} recorded successfully.");
    }

    public function show(int $id)
    {
        $this->authorizePurchase('purchase.advances.view');

        $advance = $this->advanceRepo->find($id);
        if (!$advance) abort(404);

        $advance->load(['vendor', 'purchaseOrder.items', 'bankAccount', 'creator']);

        // Find linked vendor payment (which holds allocations when this advance is applied to bills)
        $vendorPayment = \App\Domains\Purchase\Models\VendorPayment::with([
            'vendor',
            'bankAccount',
            'creator',
            'purchaseOrder',
            'allocations.bill.purchaseOrder',
            'allocations.vendorBill.purchaseOrder',
        ])
        ->where('payment_number', $advance->payment_number)
        ->orWhere(function ($q) use ($advance) {
            $q->where('payment_type', 'Advance')
              ->where('purchase_order_id', $advance->purchase_order_id)
              ->where('amount', $advance->amount);
        })
        ->first();

        return view('modules.purchase.advances.show', compact('advance', 'vendorPayment'));
    }

    public function downloadPdf(int $id)
    {
        $this->authorizePurchase('purchase.advances.view');

        $advance = $this->advanceRepo->find($id);
        if (!$advance) abort(404);

        $advance->load(['vendor', 'purchaseOrder.items', 'bankAccount', 'creator']);

        $vendorPayment = \App\Domains\Purchase\Models\VendorPayment::with([
            'vendor',
            'bankAccount',
            'creator',
            'purchaseOrder',
            'allocations.vendorBill.purchaseOrder',
            'allocations.bill.purchaseOrder',
            'allocations.vendorBill.allocations.vendorPayment',
            'allocations.bill.allocations.vendorPayment',
        ])
        ->where('payment_number', $advance->payment_number)
        ->first();

        $payment = $vendorPayment ?: $advance;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('modules.purchase.payments.pdf', compact('payment'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
            ]);

        $fileName = 'Advance_Receipt_' . str_replace(['/', '\\', ' '], '_', $advance->payment_number) . '.pdf';

        return $pdf->download($fileName);
    }

    public function edit(int $id)
    {
        return $this->show($id);
    }

    public function update(Request $request, int $id)
    {
        return redirect()->route('purchase.advances.index');
    }

    public function destroy(int $id)
    {
        return redirect()->route('purchase.advances.index');
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
