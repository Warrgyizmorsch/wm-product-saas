<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Repositories\GoodsReceiptNoteRepository;
use App\Domains\Purchase\Services\GoodsReceiptNoteService;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class GoodsReceiptNoteController extends Controller
{
    public function __construct(
        protected GoodsReceiptNoteRepository $grnRepo,
        protected GoodsReceiptNoteService $grnService,
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {}

    public function indexPending(Request $request)
    {
        $tenantId = require_tenant_id();

        $query = PurchaseOrder::where('tenant_id', $tenantId)
            ->whereIn(DB::raw('LOWER(status)'), ['approved', 'partially received', 'partially_received'])
            ->with(['vendor', 'warehouse', 'items.product']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('purchase_order_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $allOrders = $query->latest()->get();

        $pendingOrders = $allOrders->filter(function ($order) {
            $rem = $order->items->sum(function ($item) {
                return max(0, (float)$item->quantity - (float)($item->received_qty ?? 0));
            });
            return $rem > 0;
        });

        return view('modules.purchase.grns.pending', compact('pendingOrders'));
    }

    public function index(Request $request)
    {
        $grns = $this->grnRepo->getPaginatedGrns($request->all(), 15);
        return view('modules.purchase.grns.index', compact('grns'));
    }

    public function create(Request $request)
    {
        $tenantId = require_tenant_id();

        $approvedOrders = PurchaseOrder::where('tenant_id', $tenantId)
            ->whereIn(DB::raw('LOWER(status)'), ['approved', 'partially received', 'partially_received'])
            ->with(['vendor', 'warehouse', 'items.product.uom'])
            ->latest()
            ->get()
            ->filter(function ($order) {
                $rem = $order->items->sum(function ($item) {
                    return max(0, (float)$item->quantity - (float)($item->received_qty ?? 0));
                });
                return $rem > 0;
            });

        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $vendors = Vendor::where('tenant_id', $tenantId)->get();

        $selectedPo = null;
        if ($request->filled('po_id')) {
            $selectedPo = PurchaseOrder::where('tenant_id', $tenantId)
                ->with(['vendor', 'warehouse', 'items.product.uom'])
                ->find($request->po_id);
        }

        $grnNumber = $this->grnRepo->getNextGrnNumber($tenantId);

        return view('modules.purchase.grns.create', compact(
            'approvedOrders',
            'warehouses',
            'vendors',
            'selectedPo',
            'grnNumber'
        ));
    }

    public function getPurchaseOrderItems(Request $request, $poId)
    {
        $tenantId = require_tenant_id();

        $order = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['vendor', 'warehouse', 'items.product.uom'])
            ->findOrFail($poId);

        $items = $order->items->groupBy('product_id')->map(function ($productItems) {
            $first = $productItems->first();
            $orderedQty = (float)$productItems->sum('quantity');
            $prevReceived = (float)$productItems->sum('received_qty');
            $remainingQty = max(0.0, $orderedQty - $prevReceived);

            return [
                'purchase_order_item_id' => $first->id,
                'product_id' => $first->product_id,
                'product_name' => $first->product?->name ?? 'Product #' . $first->product_id,
                'product_code' => $first->product?->sku ?? $first->product?->code ?? '',
                'uom_name' => $first->product?->uom?->name ?? 'Pcs',
                'ordered_qty' => $orderedQty,
                'previous_received_qty' => $prevReceived,
                'remaining_qty' => $remainingQty,
                'unit_rate' => (float)$first->rate,
                'track_serial_number' => (bool)($first->product?->track_serial_number ?? false),
                'track_batch' => (bool)($first->product?->track_batch ?? false),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'purchase_order_id' => $order->id,
            'purchase_order_number' => $order->purchase_order_number,
            'vendor_id' => $order->vendor_id,
            'vendor_name' => $order->vendor?->name ?? 'N/A',
            'warehouse_id' => $order->warehouse?->id ?? null,
            'warehouse_name' => $order->location ?? $order->warehouse?->name ?? 'Main Warehouse',
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'vendor_id' => 'required|exists:vendors,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'received_date' => 'required|date',
            'challan_number' => 'nullable|string|max:100',
            'challan_date' => 'nullable|date',
            'vehicle_number' => 'nullable|string|max:50',
            'transporter_name' => 'nullable|string|max:100',
            'lr_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.received_qty' => 'required|numeric|min:0',
            'items.*.rejected_qty' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string',
            'items.*.batch_number' => 'nullable|string',
            'items.*.manufacturing_date' => 'nullable|date',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.serial_numbers' => 'nullable|string',
            'items.*.batches' => 'nullable|array',
            'items.*.batches.*.batch_number' => 'nullable|string',
            'items.*.batches.*.received_qty' => 'nullable|numeric|min:0.0001',
            'items.*.batches.*.manufacturing_date' => 'nullable|date',
            'items.*.batches.*.expiry_date' => 'nullable|date',
        ]);

        $grn = $this->grnService->storeGrn($validated, $tenantId);

        return redirect()->route('purchase.grns.show', $grn->id)
            ->with('success', "Goods Receipt Note {$grn->grn_number} created and approved successfully.");
    }

    public function show($id)
    {
        $grn = $this->grnRepo->findWithDetails($id);
        return view('modules.purchase.grns.show', compact('grn'));
    }

    public function edit($id)
    {
        $tenantId = require_tenant_id();
        $grn = $this->grnRepo->findWithDetails($id);

        if ($grn->status !== 'Draft') {
            return redirect()->route('purchase.grns.show', $grn->id)->with('error', 'Approved or Cancelled GRNs cannot be edited.');
        }

        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $vendors = Vendor::where('tenant_id', $tenantId)->get();

        return view('modules.purchase.grns.edit', compact('grn', 'warehouses', 'vendors'));
    }

    public function update(Request $request, $id)
    {
        $grn = $this->grnRepo->find($id);
        if (!$grn) abort(404);

        if ($grn->status !== 'Draft') {
            return redirect()->route('purchase.grns.show', $grn->id)->with('error', 'Only Draft GRNs can be updated.');
        }

        $validated = $request->validate([
            'received_date' => 'required|date',
            'challan_number' => 'nullable|string|max:100',
            'challan_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $this->grnRepo->update($grn, $validated);

        return redirect()->route('purchase.grns.show', $grn->id)
            ->with('success', "Goods Receipt Note {$grn->grn_number} updated successfully.");
    }

    public function approve($id)
    {
        $grn = $this->grnRepo->find($id);
        if (!$grn) abort(404);

        if ($grn->status !== 'Draft') {
            return redirect()->back()->with('error', 'Only Draft GRNs can be approved.');
        }

        $this->grnRepo->update($grn, ['status' => 'Approved']);

        return redirect()->back()
            ->with('success', "Goods Receipt Note {$grn->grn_number} has been Approved!");
    }

    public function downloadPdf($id)
    {
        $grn = $this->grnRepo->findWithDetails($id);
        $pdf = Pdf::loadView('modules.purchase.grns.pdf', compact('grn'));
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download("GRN-{$grn->grn_number}.pdf");
    }

    public function destroy($id)
    {
        $grn = $this->grnRepo->find($id);
        if (!$grn) abort(404);

        if ($grn->status !== 'Draft') {
            return redirect()->back()->with('error', 'Only Draft GRNs can be deleted.');
        }

        $this->grnRepo->delete($grn);

        return redirect()->route('purchase.grns.index')
            ->with('success', "Goods Receipt Note {$grn->grn_number} deleted successfully.");
    }
}
