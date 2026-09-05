<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Repositories\VendorBillRepository;
use App\Domains\Purchase\Services\VendorBillService;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Events\BillPosted;
use Illuminate\Http\Request;

class VendorBillController extends Controller
{
    public function __construct(
        protected VendorBillRepository $billRepo,
        protected VendorBillService $billService
    ) {}

    protected function getPendingGrnsQuery(int $tenantId)
    {
        return GoodsReceiptNote::where('tenant_id', $tenantId)
            ->whereIn('status', ['Approved', 'Completed'])
            ->where(function ($q) {
                // Either missing Material Bill (vendorBill where vendor_id == grns.vendor_id)
                $q->whereDoesntHave('vendorBills', function ($vbq) {
                    $vbq->where('status', '!=', 'Cancelled')
                        ->whereColumn('vendor_bills.vendor_id', 'goods_receipt_notes.vendor_id');
                })
                // OR has 'to_pay' PO AND missing Freight Bill (vendorBill where vendor_id != grns.vendor_id)
                ->orWhere(function ($frq) {
                    $frq->whereHas('purchaseOrder', function ($poq) {
                        $poq->whereIn(\DB::raw('LOWER(freight_terms)'), ['to_pay', 'to pay']);
                    })
                    ->whereDoesntHave('vendorBills', function ($vbq) {
                        $vbq->where('status', '!=', 'Cancelled')
                            ->whereColumn('vendor_bills.vendor_id', '!=', 'goods_receipt_notes.vendor_id');
                    });
                });
            })
            ->with([
                'purchaseOrder',
                'vendor',
                'warehouse',
                'items',
                'vendorBills' => fn($vbq) => $vbq->where('status', '!=', 'Cancelled')->with('items')
            ]);
    }

    public function index(Request $request)
    {
        $tenantId = require_tenant_id();
        $bills = $this->billRepo->getPaginatedBills($request->all(), 15);

        $pendingGrnsCount = $this->getPendingGrnsQuery($tenantId)->count();

        $pendingFreightCount = \App\Domains\Sales\Models\DispatchOrder::where('tenant_id', $tenantId)
            ->pendingFreightBill()
            ->count();

        return view('modules.purchase.bills.index', compact('bills', 'pendingGrnsCount', 'pendingFreightCount'));
    }

    public function pendingGrns(Request $request)
    {
        $tenantId = require_tenant_id();

        $query = $this->getPendingGrnsQuery($tenantId);

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

        $pendingFreightCount = \App\Domains\Sales\Models\DispatchOrder::where('tenant_id', $tenantId)
            ->pendingFreightBill()
            ->count();

        return view('modules.purchase.bills.pending', compact('pendingGrns', 'pendingGrnsCount', 'pendingFreightCount'));
    }

    public function pendingFreight(Request $request)
    {
        $tenantId = require_tenant_id();

        $query = \App\Domains\Sales\Models\DispatchOrder::where('tenant_id', $tenantId)
            ->pendingFreightBill()
            ->with(['transporter', 'customer', 'invoice', 'salesOrder']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('dispatch_number', 'like', "%{$search}%")
                  ->orWhere('lr_number', 'like', "%{$search}%")
                  ->orWhereHas('transporter', fn($tq) => $tq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('invoice', fn($iq) => $iq->where('invoice_number', 'like', "%{$search}%"))
                  ->orWhereHas('salesOrder', fn($sq) => $sq->where('sales_order_number', 'like', "%{$search}%"));
            });
        }

        $pendingFreightDispatches = $query->latest()->paginate(15);
        $pendingFreightCount = $pendingFreightDispatches->total();

        $pendingGrnsCount = $this->getPendingGrnsQuery($tenantId)->count();

        return view('modules.purchase.bills.pending-freight', compact(
            'pendingFreightDispatches',
            'pendingFreightCount',
            'pendingGrnsCount'
        ));
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
        $availableAdvance = $selectedGrn ? $this->billService->getAvailableVendorAdvance($selectedGrn->vendor_id, $tenantId) : 0.0;

        return view('modules.purchase.bills.create', compact('selectedGrn', 'vendors', 'warehouses', 'availableAdvance'));
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
            'discount_type'         => 'nullable|string',
            'tax_type'              => 'nullable|string',
            'gst_type'              => 'nullable|string',
            'discount_amount'       => 'nullable|numeric|min:0',
            'order_tax_percent'     => 'nullable|numeric|min:0',
            'freight_terms'         => 'nullable|string',
            'freight_amount'        => 'nullable|numeric|min:0',
            'freight_tax_percent'   => 'nullable|numeric|min:0',
            'freight_tax_method'    => 'nullable|string',
            'freight_allocation_method' => 'nullable|string|in:by_amount,by_quantity,none,direct_expense',
            'adjustment'            => 'nullable|numeric',
            'notes'                 => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.purchase_order_item_id' => 'nullable|integer',
            'items.*.goods_receipt_note_item_id' => 'nullable|integer',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.unit_rate' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.tax_percentage' => 'nullable|numeric|min:0',
        ]);

        if (empty($validated['vendor_bill_number']) && !empty($validated['vendor_invoice_number'])) {
            $validated['vendor_bill_number'] = $validated['vendor_invoice_number'];
        }

        $bill = $this->billService->storeBill($validated, $tenantId);

        event(new BillPosted($bill));

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

    public function createService(Request $request)
    {
        $tenantId = require_tenant_id();

        $mode = $request->query('mode', $request->query('dispatch_order_id') ? 'outbound' : ($request->query('grn_id') ? 'inbound' : 'outbound'));

        $grnId = $request->query('grn_id');
        $selectedGrn = null;
        if ($grnId) {
            $selectedGrn = GoodsReceiptNote::where('tenant_id', $tenantId)
                ->with(['purchaseOrder', 'vendor'])
                ->find($grnId);
        }

        $dispatchId = $request->query('dispatch_order_id') ?? $request->query('dispatch_id');
        $selectedDispatch = null;
        if ($dispatchId) {
            $selectedDispatch = \App\Domains\Sales\Models\DispatchOrder::where('tenant_id', $tenantId)
                ->with('transporter')
                ->find($dispatchId);
        }

        // Auto-extract form information from Dispatch / GRN payload
        $prefilledVendorId    = $request->query('vendor_id');
        $prefilledAmount      = $request->query('amount');
        $prefilledInvoice     = $request->query('vendor_invoice_number');
        $prefilledNotes       = $request->query('notes');
        $prefilledServiceHead = $request->query('service_head');
        $prefilledGstType     = $request->query('gst_type');
        $prefilledTaxRate     = $request->query('tax_rate');

        if ($selectedDispatch) {
            $transporter = $selectedDispatch->transporter;
            $prefilledVendorId = $prefilledVendorId ?: ($transporter?->vendor_id ?? null);
            if (!$prefilledVendorId && $transporter) {
                $matchedVendor = Vendor::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($transporter) {
                        $q->where('name', $transporter->name);
                        if (!empty($transporter->gstin) && \Illuminate\Support\Facades\Schema::hasColumn('vendors', 'gstin')) {
                            $q->orWhere('gstin', $transporter->gstin);
                        }
                    })->first();
                if ($matchedVendor) {
                    $prefilledVendorId = $matchedVendor->id;
                }
            }
            if (!$prefilledVendorId) {
                $prefilledVendorId = $selectedDispatch->transporter_id;
            }

            $prefilledAmount      = $prefilledAmount      ?: $selectedDispatch->freight_amount;
            $prefilledInvoice     = $prefilledInvoice     ?: ('LR-' . ($selectedDispatch->lr_number ?: ($selectedDispatch->dispatch_number ?? $selectedDispatch->id)));
            $prefilledNotes       = $prefilledNotes       ?: "Freight Obligation Bill for Dispatch #{$selectedDispatch->dispatch_number} | Customer: " . ($selectedDispatch->customer?->name ?? 'N/A') . " | Vehicle: " . ($selectedDispatch->vehicle_number ?: 'N/A');
            $prefilledServiceHead = $prefilledServiceHead ?: 'Freight & Transport';
            $prefilledGstType     = $prefilledGstType     ?: 'rcm';
            $prefilledTaxRate     = $prefilledTaxRate     ?: 5;
        } elseif ($selectedGrn) {
            $prefilledVendorId    = $prefilledVendorId    ?: $selectedGrn->vendor_id;
            $prefilledServiceHead = $prefilledServiceHead ?: 'Freight & Transport';
        }

        $grns = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->whereIn('status', ['Approved', 'Completed'])
            ->with('vendor')
            ->orderBy('id', 'desc')
            ->get();

        $dispatches = \App\Domains\Sales\Models\DispatchOrder::where('tenant_id', $tenantId)
            ->where('freight_amount', '>', 0)
            ->with('transporter')
            ->latest('id')
            ->get();

        $vendors = Vendor::where('tenant_id', $tenantId)->get();
        $transporterVendorIds = [];
        if (\Illuminate\Support\Facades\Schema::hasColumn('transporters', 'vendor_id')) {
            $transporterVendorIds = \App\Domains\Platform\Models\Transporter::where('tenant_id', $tenantId)
                ->whereNotNull('vendor_id')
                ->pluck('vendor_id')
                ->toArray();
        }

        $vendors->each(function ($v) use ($transporterVendorIds) {
            $nameLower = strtolower($v->name);
            $v->is_transporter = in_array($v->id, $transporterVendorIds)
                || str_contains($nameLower, 'logistics')
                || str_contains($nameLower, 'transport')
                || str_contains($nameLower, 'express')
                || str_contains($nameLower, 'carrier');
            $v->type_label = $v->is_transporter ? 'Transporter / Logistics' : 'Supplier / Vendor';
        });

        $prefilled = [
            'vendor_id'             => $prefilledVendorId,
            'amount'                => $prefilledAmount,
            'vendor_invoice_number' => $prefilledInvoice,
            'notes'                 => $prefilledNotes,
            'service_head'          => $prefilledServiceHead,
            'gst_type'              => $prefilledGstType,
            'tax_rate'              => $prefilledTaxRate,
        ];

        return view('modules.purchase.bills.create-service', compact(
            'mode',
            'grns',
            'selectedGrn',
            'dispatches',
            'selectedDispatch',
            'vendors',
            'prefilled'
        ));
    }

    public function storeService(Request $request)
    {
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'vendor_id'             => 'required|integer|exists:vendors,id',
            'goods_receipt_note_id' => 'nullable|integer|exists:goods_receipt_notes,id',
            'dispatch_order_id'     => 'nullable|integer|exists:dispatch_orders,id',
            'service_head'          => 'required|string',
            'amount'                => 'required|numeric|min:0.01',
            'tax_rate'              => 'required|numeric|min:0',
            'gst_type'              => 'required|string|in:cgst_sgst,igst,rcm,rcm_cgst_sgst,rcm_igst',
            'bill_date'             => 'required|date',
            'due_date'              => 'required|date|after_or_equal:bill_date',
            'vendor_invoice_number' => 'nullable|string|max:255',
            'notes'                 => 'nullable|string',
        ]);

        $subtotal   = (float) $validated['amount'];
        $taxRate    = (float) $validated['tax_rate'];
        $gstType    = $validated['gst_type'];
        $isRcm      = in_array($gstType, ['rcm', 'rcm_cgst_sgst', 'rcm_igst']);
        $isIgst     = in_array($gstType, ['igst', 'rcm_igst']);

        $taxAmount  = ($subtotal * $taxRate) / 100;
        $grandTotal = $isRcm ? $subtotal : ($subtotal + $taxAmount);

        $cgstAmount = $isIgst ? 0 : round($taxAmount / 2, 2);
        $sgstAmount = $isIgst ? 0 : round($taxAmount - $cgstAmount, 2);
        $igstAmount = $isIgst ? round($taxAmount, 2) : 0;

        $billNumber = $this->billRepo->getNextBillNumber($tenantId);

        $grn = !empty($validated['goods_receipt_note_id'])
            ? GoodsReceiptNote::find($validated['goods_receipt_note_id'])
            : null;

        $dispatchOrder = !empty($validated['dispatch_order_id'])
            ? \App\Domains\Sales\Models\DispatchOrder::find($validated['dispatch_order_id'])
            : null;

        $serviceHead = $validated['service_head'];
        $notesParts = [];
        if (!empty($validated['notes'])) {
            $notesParts[] = $validated['notes'];
        } else {
            $notesParts[] = "{$serviceHead} Service Charge";
        }
        if ($dispatchOrder) {
            $notesParts[] = "Dispatch #{$dispatchOrder->dispatch_number}";
        }
        if ($grn) {
            $notesParts[] = "GRN #{$grn->grn_number}";
        }
        $notes = implode(' | ', array_filter($notesParts));

        $bill = \App\Domains\Purchase\Models\VendorBill::create([
            'tenant_id'             => $tenantId,
            'company_id'            => require_company_id(),
            'branch_id'             => require_branch_id(),
            'bill_number'           => $billNumber,
            'vendor_invoice_number' => $validated['vendor_invoice_number'] ?: "SRV-BILL-" . time(),
            'vendor_id'             => (int) $validated['vendor_id'],
            'goods_receipt_note_id' => $grn?->id,
            'dispatch_order_id'     => $dispatchOrder?->id,
            'purchase_order_id'     => $grn?->purchase_order_id,
            'bill_date'             => $validated['bill_date'],
            'due_date'              => $validated['due_date'],
            'status'                => 'Unpaid',
            'gst_type'              => $gstType,
            'subtotal'              => $subtotal,
            'tax_amount'            => $taxAmount,
            'cgst_amount'           => $cgstAmount,
            'sgst_amount'           => $sgstAmount,
            'igst_amount'           => $igstAmount,
            'grand_total'           => $grandTotal,
            'paid_amount'           => 0,
            'due_amount'            => round($grandTotal, 2),
            'notes'                 => $notes,
            'created_by'            => auth()->id() ?: 1,
        ]);

        \App\Domains\Purchase\Models\VendorBillItem::create([
            'tenant_id'      => $tenantId,
            'vendor_bill_id' => $bill->id,
            'quantity'       => 1,
            'unit_rate'      => $subtotal,
            'tax_percentage' => $taxRate,
            'total_amount'   => $grandTotal,
        ]);

        // Dispatch BillPosted event to trigger GL Journal Entry Posting
        event(new \App\Domains\Purchase\Events\BillPosted($bill));

        return redirect()->route('purchase.bills.show', $bill->id)
            ->with('success', "Service Bill {$bill->bill_number} created successfully.");
    }
}
