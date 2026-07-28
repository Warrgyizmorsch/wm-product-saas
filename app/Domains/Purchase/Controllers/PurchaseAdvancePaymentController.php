<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Repositories\PurchaseAdvancePaymentRepository;
use App\Domains\Purchase\Repositories\PurchaseOrderRepository;
use App\Domains\Purchase\Services\PurchaseAdvancePaymentService;
use App\Domains\Inventory\Models\Vendor;
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
        $advances = $this->advanceRepo->getPaginatedAdvances($request->all(), 10);
        return view('modules.purchase.advances.index', compact('advances'));
    }

    public function create(Request $request)
    {
        $tenantId = require_tenant_id();

        $poId = $request->query('purchase_order_id');
        $prefillPo = null;
        if ($poId) {
            $prefillPo = $this->orderRepo->find($poId);
        }

        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $purchaseOrders = $this->orderRepo->getPaginatedOrders([], 100);

        return view('modules.purchase.advances.create', compact('vendors', 'purchaseOrders', 'prefillPo'));
    }

    public function store(Request $request)
    {
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
}
