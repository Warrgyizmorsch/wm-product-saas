<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Repositories\VendorBillRepository;
use App\Domains\Purchase\Services\VendorBillService;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Http\Request;

class VendorBillController extends Controller
{
    public function __construct(
        protected VendorBillRepository $billRepo,
        protected VendorBillService $billService
    ) {}

    public function index(Request $request)
    {
        $tenantId = require_tenant_id();
        $bills = $this->billRepo->getPaginatedBills($request->all(), 15);

        $pendingGrnsCount = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->whereIn('status', ['Approved', 'Completed'])
            ->whereDoesntHave('vendorBills', function ($q) {
                $q->where('status', '!=', 'Cancelled');
            })
            ->count();

        return view('modules.purchase.bills.index', compact('bills', 'pendingGrnsCount'));
    }

    public function pendingGrns(Request $request)
    {
        $tenantId = require_tenant_id();

        $query = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->whereIn('status', ['Approved', 'Completed'])
            ->whereDoesntHave('vendorBills', function ($q) {
                $q->where('status', '!=', 'Cancelled');
            })
            ->with(['purchaseOrder', 'vendor', 'warehouse', 'items']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('grn_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', fn($vq) => $vq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('purchaseOrder', fn($pq) => $pq->where('purchase_order_number', 'like', "%{$search}%"));
            });
        }

        $pendingGrns = $query->latest()->paginate(15);

        $pendingGrnsCount = $pendingGrns->total();

        return view('modules.purchase.bills.pending', compact('pendingGrns', 'pendingGrnsCount'));
    }

    public function create(Request $request)
    {
        $tenantId = require_tenant_id();

        $grnId = $request->query('grn_id');
        $poId = $request->query('purchase_order_id');

        $selectedGrn = null;
        if ($grnId) {
            $selectedGrn = GoodsReceiptNote::where('tenant_id', $tenantId)
                ->with(['purchaseOrder', 'vendor', 'items.product', 'items.purchaseOrderItem'])
                ->find($grnId);
        }

        if (!$selectedGrn && $poId) {
            $selectedGrn = GoodsReceiptNote::where('tenant_id', $tenantId)
                ->where('purchase_order_id', $poId)
                ->whereIn('status', ['Approved', 'Completed'])
                ->with(['purchaseOrder', 'vendor', 'items.product', 'items.purchaseOrderItem'])
                ->latest()
                ->first();
        }

        if (!$selectedGrn) {
            $selectedGrn = GoodsReceiptNote::where('tenant_id', $tenantId)
                ->whereIn('status', ['Approved', 'Completed'])
                ->with(['purchaseOrder', 'vendor', 'items.product', 'items.purchaseOrderItem'])
                ->latest()
                ->first();
        }

        if (!$selectedGrn) {
            return redirect()->route('purchase.grns.index')
                ->with('error', 'No Approved Goods Receipt Note found to create a bill.');
        }

        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.purchase.bills.create', compact('selectedGrn', 'vendors', 'warehouses'));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'bill_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:bill_date',
            'purchase_order_id' => 'nullable|integer',
            'goods_receipt_note_id' => 'nullable|integer',
            'vendor_id' => 'required|integer|exists:vendors,id',
            'vendor_bill_number'    => 'nullable|string|max:255',
            'vendor_invoice_number' => 'nullable|string|max:255',
            'notes'                 => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.purchase_order_item_id' => 'nullable|integer',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.unit_rate' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
        ]);

        if (empty($validated['vendor_bill_number']) && !empty($validated['vendor_invoice_number'])) {
            $validated['vendor_bill_number'] = $validated['vendor_invoice_number'];
        }

        $bill = $this->billService->storeBill($validated, $tenantId);

        return redirect()->route('purchase.bills.show', $bill->id)
            ->with('success', "Vendor Bill {$bill->bill_number} created successfully.");
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();
        $bill = $this->billRepo->findWithDetails($id);
        $availableAdvance = $bill ? $this->billService->getAvailableVendorAdvance($bill->vendor_id, $tenantId) : 0.0;
        return view('modules.purchase.bills.show', compact('bill', 'availableAdvance'));
    }

    public function applyAdvance(int $id)
    {
        $tenantId = require_tenant_id();
        $bill = $this->billRepo->find($id);
        if (!$bill) abort(404);

        $res = $this->billService->applyAdvanceCredit($bill, $tenantId);
        if ($res) {
            return redirect()->route('purchase.bills.show', $id)
                ->with('success', 'Vendor Advance Credit applied successfully!');
        }

        return redirect()->route('purchase.bills.show', $id)
            ->with('error', 'No available vendor advance credit found to apply.');
    }

    public function edit(int $id)
    {
        $bill = $this->billRepo->findWithDetails($id);
        return view('modules.purchase.bills.edit', compact('bill'));
    }

    public function update(Request $request, int $id)
    {
        $bill = $this->billRepo->find($id);
        if (!$bill) abort(404);

        $validated = $request->validate([
            'due_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $this->billRepo->update($bill, $validated);

        return redirect()->route('purchase.bills.show', $id)
            ->with('success', 'Vendor bill updated successfully.');
    }

    public function destroy(int $id)
    {
        $bill = $this->billRepo->find($id);
        if (!$bill) abort(404);

        $bill->delete();

        return redirect()->route('purchase.bills.index')
            ->with('success', 'Vendor bill deleted successfully.');
    }

    public function detailPartial(int $id)
    {
        $bill = $this->billRepo->findWithDetails($id);
        return view('modules.purchase.bills.detail-partial', compact('bill'));
    }
}
