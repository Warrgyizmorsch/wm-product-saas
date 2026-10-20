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

        // Only show active/approved POs that have pending advance balance left
        $purchaseOrders = PurchaseOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['Completed', 'Cancelled', 'Draft'])
            ->with(['vendor'])
            ->orderBy('id', 'desc')
            ->get()
            ->filter(function ($po) {
                $paidAdvance = (float) PurchaseAdvancePayment::where('purchase_order_id', $po->id)
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
                $poTotal = (float) ($po->grand_total ?: $po->total_amount);
                return ($poTotal - $paidAdvance) > 0.01;
            });

        return view('modules.purchase.advances.create', compact('vendors', 'purchaseOrders', 'prefillPo'));
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
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if (!empty($validated['purchase_order_id'])) {
            $po = PurchaseOrder::where('tenant_id', $tenantId)->find($validated['purchase_order_id']);
            if ($po) {
                if (in_array($po->status, ['Completed', 'Cancelled'])) {
                    return back()->withInput()->withErrors([
                        'purchase_order_id' => "Cannot record advance payment for a {$po->status} Purchase Order."
                    ]);
                }
                $paidAdvance = (float) PurchaseAdvancePayment::where('purchase_order_id', $po->id)
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
                $poTotal = (float) ($po->grand_total ?: $po->total_amount);
                $remainingAdvance = max(0.0, $poTotal - $paidAdvance);
                $enteredBase = convert_to_base((float) $validated['amount']);

                if ($enteredBase > ($remainingAdvance + 0.01)) {
                    $formattedRemaining = format_currency($remainingAdvance);
                    return back()->withInput()->withErrors([
                        'amount' => "Advance amount cannot exceed remaining PO advance limit of {$formattedRemaining}."
                    ]);
                }
            }
        }

        $advance = $this->advanceService->recordAdvancePayment($validated, $tenantId);

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

        $advance->load(['vendor', 'purchaseOrder']);
        return view('modules.purchase.advances.show', compact('advance'));
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
