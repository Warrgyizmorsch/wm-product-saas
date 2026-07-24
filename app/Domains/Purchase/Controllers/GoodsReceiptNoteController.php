<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class GoodsReceiptNoteController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {
    }
    /**
     * Screen 1: Pending Goods Receipts
     * Shows Approved POs with remaining quantity > 0.
     */
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

        // Filter out POs where remaining qty is zero
        $pendingOrders = $allOrders->filter(function ($order) {
            $rem = $order->items->sum(function ($item) {
                return max(0, (float)$item->quantity - (float)($item->received_qty ?? 0));
            });
            return $rem > 0;
        });

        return view('modules.purchase.grns.pending', compact('pendingOrders'));
    }

    /**
     * Screen 2: All Goods Receipt Notes
     */
    public function index(Request $request)
    {
        $tenantId = require_tenant_id();

        $query = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->with(['purchaseOrder', 'vendor', 'warehouse', 'creator', 'items']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('grn_number', 'like', "%{$search}%")
                  ->orWhere('challan_number', 'like', "%{$search}%")
                  ->orWhereHas('purchaseOrder', function ($pq) use ($search) {
                      $pq->where('purchase_order_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('received_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('received_date', '<=', $request->date_to);
        }

        $grns = $query->latest()->paginate(15);

        return view('modules.purchase.grns.index', compact('grns'));
    }

    /**
     * Show form to create new GRN
     */
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

        // Generate Next GRN Number
        $count = GoodsReceiptNote::where('tenant_id', $tenantId)->count() + 1;
        $grnNumber = 'GRN-' . date('Y') . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);

        return view('modules.purchase.grns.create', compact(
            'approvedOrders',
            'warehouses',
            'vendors',
            'selectedPo',
            'grnNumber'
        ));
    }

    /**
     * Fetch Purchase Order Items via AJAX
     */
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

    /**
     * Store new GRN in Draft status
     */
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
        ]);

        $po = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($request->purchase_order_id);

        // Validation: Ensure received qty does not exceed remaining qty for each item (consolidated)
        $hasPositiveReceive = false;
        foreach ($request->items as $idx => $itemData) {
            $poItem = PurchaseOrderItem::findOrFail($itemData['purchase_order_item_id']);
            
            // Get all items of the same product in this PO to sum their totals
            $matchingPoItems = PurchaseOrderItem::where('purchase_order_id', $poItem->purchase_order_id)
                ->where('product_id', $itemData['product_id'])
                ->get();

            $orderedQty = (float)$matchingPoItems->sum('quantity');
            $prevReceived = (float)$matchingPoItems->sum('received_qty');
            $remainingQty = max(0.0, $orderedQty - $prevReceived);

            $recQty = (float)$itemData['received_qty'];
            $rejQty = (float)($itemData['rejected_qty'] ?? 0);

            if ($recQty > $remainingQty) {
                return redirect()->back()->withInput()->with('error', "Received Qty for item {$poItem->product?->name} cannot exceed Remaining Qty ({$remainingQty}).");
            }

            if ($rejQty > $recQty) {
                return redirect()->back()->withInput()->with('error', "Rejected Qty for item {$poItem->product?->name} cannot exceed Received Qty ({$recQty}).");
            }

            if ($recQty > 0) {
                $hasPositiveReceive = true;
            }
        }

        if (!$hasPositiveReceive) {
            return redirect()->back()->withInput()->with('error', 'Please enter at least one non-zero Received Quantity.');
        }

        // Generate unique GRN number
        $count = GoodsReceiptNote::where('tenant_id', $tenantId)->count() + 1;
        $grnNumber = 'GRN-' . date('Y') . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);

        $grn = DB::transaction(function () use ($tenantId, $request, $grnNumber, $po) {
            $grn = GoodsReceiptNote::create([
                'tenant_id' => $tenantId,
                'grn_number' => $grnNumber,
                'purchase_order_id' => $po->id,
                'vendor_id' => $request->vendor_id,
                'warehouse_id' => $request->warehouse_id ?: ($po->warehouse?->id ?? null),
                'received_date' => $request->received_date,
                'challan_number' => $request->challan_number,
                'challan_date' => $request->challan_date,
                'vehicle_number' => $request->vehicle_number,
                'transporter_name' => $request->transporter_name,
                'lr_number' => $request->lr_number,
                'status' => 'Approved',
                'notes' => $request->notes,
                'created_by' => auth()->id(),
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            foreach ($request->items as $itemData) {
                $productId = $itemData['product_id'];
                $recQty = (float)$itemData['received_qty'];
                $rejQty = (float)($itemData['rejected_qty'] ?? 0);

                // Fetch matching PO items for this product
                $poItems = PurchaseOrderItem::where('purchase_order_id', $po->id)
                    ->where('product_id', $productId)
                    ->orderBy('id')
                    ->get();

                $leftRec = $recQty;
                $leftRej = $rejQty;

                foreach ($poItems as $poItem) {
                    $ordered = (float)$poItem->quantity;
                    $prevRec = (float)($poItem->received_qty ?? 0);
                    $remaining = max(0.0, $ordered - $prevRec);

                    if ($remaining <= 0) {
                        continue;
                    }

                    // Allocate received qty to this PO item
                    $allocatedRec = min($leftRec, $remaining);
                    $leftRec -= $allocatedRec;

                    // Allocate rejected qty
                    $allocatedRej = 0.0;
                    if ($allocatedRec > 0 && $leftRej > 0) {
                        $allocatedRej = min($leftRej, $allocatedRec);
                        $leftRej -= $allocatedRej;
                    }

                    $allocatedAcc = max(0.0, $allocatedRec - $allocatedRej);
                    $allocatedRem = max(0.0, $ordered - ($prevRec + $allocatedAcc));

                    $unitRate = (float)$poItem->rate;
                    $totalAmt = $allocatedAcc * $unitRate;

                    GoodsReceiptNoteItem::create([
                        'tenant_id' => $tenantId,
                        'goods_receipt_note_id' => $grn->id,
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $productId,
                        'ordered_qty' => $ordered,
                        'previous_received_qty' => $prevRec,
                        'received_qty' => $allocatedRec,
                        'accepted_qty' => $allocatedAcc,
                        'rejected_qty' => $allocatedRej,
                        'remaining_qty' => $allocatedRem,
                        'unit_rate' => $unitRate,
                        'total_amount' => $totalAmt,
                        'remarks' => $itemData['remarks'] ?? null,
                    ]);
                }

                // If there is still excess received quantity left, save it on the last PO item
                if ($leftRec > 0) {
                    $poItem = $poItems->last() ?: $poItems->first();
                    $unitRate = (float)($poItem->rate ?? 0);
                    $allocatedAcc = max(0.0, $leftRec - $leftRej);
                    $totalAmt = $allocatedAcc * $unitRate;

                    GoodsReceiptNoteItem::create([
                        'tenant_id' => $tenantId,
                        'goods_receipt_note_id' => $grn->id,
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $productId,
                        'ordered_qty' => (float)$poItem->quantity,
                        'previous_received_qty' => (float)($poItem->received_qty ?? 0),
                        'received_qty' => $leftRec,
                        'accepted_qty' => $allocatedAcc,
                        'rejected_qty' => $leftRej,
                        'remaining_qty' => 0,
                        'unit_rate' => $unitRate,
                        'total_amount' => $totalAmt,
                        'remarks' => ($itemData['remarks'] ?? '') . ' (Excess received)',
                    ]);
                }
            }

            // Load items relationship to loop over it
            $grn->load('items');

            // Default warehouse if not specified
            $warehouseId = $grn->warehouse_id ?: Warehouse::where('tenant_id', $tenantId)->first()?->id;

            foreach ($grn->items as $item) {
                $acceptedQty = (float)$item->accepted_qty;
                $unitRate = (float)$item->unit_rate;

                if ($acceptedQty > 0 && $warehouseId) {
                    // Increase stock inside product_warehouse_stocks
                    $stock = ProductWarehouseStock::firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'product_id' => $item->product_id,
                            'warehouse_id' => $warehouseId,
                        ],
                        [
                            'quantity' => 0,
                            'reserved_qty' => 0,
                            'available_qty' => 0,
                            'unit_cost' => $unitRate,
                        ]
                    );

                    $newQty = (float)$stock->quantity + $acceptedQty;
                    $newAvailable = (float)$stock->available_qty + $acceptedQty;

                    $stock->update([
                        'quantity' => $newQty,
                        'available_qty' => $newAvailable,
                        'unit_cost' => $unitRate,
                    ]);

                    // Insert stock movement inside stock_transactions
                    StockTransaction::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouseId,
                        'type' => 'IN',
                        'reference_type' => 'GoodsReceiptNote',
                        'reference_id' => $grn->id,
                        'quantity' => $acceptedQty,
                        'unit_cost' => $unitRate,
                        'total_value' => $acceptedQty * $unitRate,
                        'balance_qty' => $newQty,
                    ]);
                }

                // Update Purchase Order Item received_qty
                if ($item->purchase_order_item_id) {
                    $poItem = PurchaseOrderItem::find($item->purchase_order_item_id);
                    if ($poItem) {
                        $poItem->increment('received_qty', $acceptedQty);
                    }
                }
            }

            // Automatically update Purchase Order Status
            if ($grn->purchase_order_id) {
                $po = PurchaseOrder::where('tenant_id', $tenantId)
                    ->with('items')
                    ->find($grn->purchase_order_id);

                if ($po) {
                    $allFullyReceived = $po->items->every(function ($pi) {
                        return (float)$pi->quantity - (float)($pi->received_qty ?? 0) <= 0.0001;
                    });

                    $newPoStatus = $allFullyReceived ? 'Fully Received' : 'Partially Received';
                    $po->update(['status' => $newPoStatus]);
                }
            }

            // Directly post Accounting Journal Entry via global Accounting JournalService (Dr: Inventory 1400, Cr: GRNI 2100)
            try {
                $totalAmount = (float) $grn->items->sum('total_amount');
                if ($totalAmount > 0) {
                    $inventoryAccount = $this->accounts->findByCode('1100', $grn->tenant_id) 
                                     ?? $this->accounts->findByCode('1400', $grn->tenant_id);

                    $grniAccount = $this->accounts->findByCode('2100', $grn->tenant_id) 
                                ?? $this->accounts->findByCode('2000', $grn->tenant_id);

                    if ($inventoryAccount && $grniAccount) {
                        $this->journals->post([
                            [
                                'chart_of_account_id' => $inventoryAccount->id,
                                'debit' => $totalAmount,
                                'description' => "Inventory Received for GRN {$grn->grn_number}",
                            ],
                            [
                                'chart_of_account_id' => $grniAccount->id,
                                'credit' => $totalAmount,
                                'description' => "Goods Received Not Invoiced (GRNI) for GRN {$grn->grn_number}",
                            ],
                        ], [
                            'tenant_id' => $grn->tenant_id,
                            'source' => 'purchase',
                            'reference_type' => GoodsReceiptNote::class,
                            'reference_id' => $grn->id,
                            'memo' => "Goods Receipt Note {$grn->grn_number} - Stock Credited",
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Accounting Journal Posting Exception on GRN Creation: ' . $e->getMessage());
            }

            return $grn;
        });

        return redirect()->route('purchase.grns.show', $grn->id)->with('success', "Goods Receipt Note {$grn->grn_number} created and approved successfully.");
    }

    /**
     * Display single GRN Odoo sheet view
     */
    public function show($id)
    {
        $tenantId = require_tenant_id();

        $grn = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->with(['purchaseOrder', 'vendor', 'warehouse', 'creator', 'approver', 'items.product.uom', 'items.purchaseOrderItem'])
            ->findOrFail($id);

        return view('modules.purchase.grns.show', compact('grn'));
    }

    /**
     * Show edit form for Draft GRN
     */
    public function edit($id)
    {
        $tenantId = require_tenant_id();

        $grn = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->with(['purchaseOrder', 'vendor', 'warehouse', 'items.product.uom'])
            ->findOrFail($id);

        if ($grn->status !== 'Draft') {
            return redirect()->route('purchase.grns.show', $grn->id)->with('error', 'Approved or Cancelled GRNs cannot be edited.');
        }

        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $vendors = Vendor::where('tenant_id', $tenantId)->get();

        return view('modules.purchase.grns.edit', compact('grn', 'warehouses', 'vendors'));
    }

    /**
     * Update Draft GRN
     */
    public function update(Request $request, $id)
    {
        $tenantId = require_tenant_id();

        $grn = GoodsReceiptNote::where('tenant_id', $tenantId)->findOrFail($id);

        if ($grn->status !== 'Draft') {
            return redirect()->route('purchase.grns.show', $grn->id)->with('error', 'Only Draft GRNs can be updated.');
        }

        $validated = $request->validate([
            'received_date' => 'required|date',
            'challan_number' => 'nullable|string|max:100',
            'challan_date' => 'nullable|date',
            'vehicle_number' => 'nullable|string|max:50',
            'transporter_name' => 'nullable|string|max:100',
            'lr_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:goods_receipt_note_items,id',
            'items.*.received_qty' => 'required|numeric|min:0',
            'items.*.rejected_qty' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($grn, $request) {
            $grn->update([
                'received_date' => $request->received_date,
                'challan_number' => $request->challan_number,
                'challan_date' => $request->challan_date,
                'vehicle_number' => $request->vehicle_number,
                'transporter_name' => $request->transporter_name,
                'lr_number' => $request->lr_number,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $itemData) {
                $grnItem = GoodsReceiptNoteItem::where('goods_receipt_note_id', $grn->id)->findOrFail($itemData['id']);

                $recQty = (float)$itemData['received_qty'];
                $rejQty = (float)($itemData['rejected_qty'] ?? 0);
                $accQty = max(0.0, $recQty - $rejQty);
                $remQty = max(0.0, $grnItem->ordered_qty - ($grnItem->previous_received_qty + $accQty));

                $grnItem->update([
                    'received_qty' => $recQty,
                    'accepted_qty' => $accQty,
                    'rejected_qty' => $rejQty,
                    'remaining_qty' => $remQty,
                    'total_amount' => $accQty * $grnItem->unit_rate,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);
            }
        });

        return redirect()->route('purchase.grns.show', $grn->id)->with('success', "Goods Receipt Note {$grn->grn_number} updated successfully.");
    }

    /**
     * Approve GRN & Atomic Stock Update
     */
    public function approve($id)
    {
        $tenantId = require_tenant_id();

        $grn = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->with(['items', 'purchaseOrder.items'])
            ->findOrFail($id);

        if ($grn->status !== 'Draft') {
            return redirect()->back()->with('error', 'Only Draft GRNs can be approved.');
        }

        DB::transaction(function () use ($tenantId, $grn) {
            // 1. Update GRN status
            $grn->update([
                'status' => 'Approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Default warehouse if not specified
            $warehouseId = $grn->warehouse_id ?: Warehouse::where('tenant_id', $tenantId)->first()?->id;

            foreach ($grn->items as $item) {
                $acceptedQty = (float)$item->accepted_qty;
                $unitRate = (float)$item->unit_rate;

                if ($acceptedQty > 0 && $warehouseId) {
                    // 2. Increase stock inside product_warehouse_stocks
                    $stock = ProductWarehouseStock::firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'product_id' => $item->product_id,
                            'warehouse_id' => $warehouseId,
                        ],
                        [
                            'quantity' => 0,
                            'reserved_qty' => 0,
                            'available_qty' => 0,
                            'unit_cost' => $unitRate,
                        ]
                    );

                    $newQty = (float)$stock->quantity + $acceptedQty;
                    $newAvailable = (float)$stock->available_qty + $acceptedQty;

                    $stock->update([
                        'quantity' => $newQty,
                        'available_qty' => $newAvailable,
                        'unit_cost' => $unitRate,
                    ]);

                    // 3. Insert stock movement inside stock_transactions
                    StockTransaction::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouseId,
                        'type' => 'IN',
                        'reference_type' => 'GoodsReceiptNote',
                        'reference_id' => $grn->id,
                        'quantity' => $acceptedQty,
                        'unit_cost' => $unitRate,
                        'total_value' => $acceptedQty * $unitRate,
                        'balance_qty' => $newQty,
                    ]);
                }

                // 4. Update Purchase Order Item received_qty
                if ($item->purchase_order_item_id) {
                    $poItem = PurchaseOrderItem::find($item->purchase_order_item_id);
                    if ($poItem) {
                        $poItem->increment('received_qty', $acceptedQty);
                    }
                }
            }

            // 5. Automatically update Purchase Order Status
            if ($grn->purchase_order_id) {
                $po = PurchaseOrder::where('tenant_id', $tenantId)
                    ->with('items')
                    ->find($grn->purchase_order_id);

                if ($po) {
                    $allFullyReceived = $po->items->every(function ($pi) {
                        return (float)$pi->quantity - (float)($pi->received_qty ?? 0) <= 0.0001;
                    });

                    $newPoStatus = $allFullyReceived ? 'Fully Received' : 'Partially Received';
                    $po->update(['status' => $newPoStatus]);
                }
            }

            // Directly post Accounting Journal Entry via global Accounting JournalService (Dr: Inventory 1400, Cr: GRNI 2100)
            try {
                $totalAmount = (float) $grn->items->sum('total_amount');
                if ($totalAmount > 0) {
                    $inventoryAccount = $this->accounts->findByCode('1100', $grn->tenant_id) 
                                     ?? $this->accounts->findByCode('1400', $grn->tenant_id);

                    $grniAccount = $this->accounts->findByCode('2100', $grn->tenant_id) 
                                ?? $this->accounts->findByCode('2000', $grn->tenant_id);

                    if ($inventoryAccount && $grniAccount) {
                        $this->journals->post([
                            [
                                'chart_of_account_id' => $inventoryAccount->id,
                                'debit' => $totalAmount,
                                'description' => "Inventory Received for GRN {$grn->grn_number}",
                            ],
                            [
                                'chart_of_account_id' => $grniAccount->id,
                                'credit' => $totalAmount,
                                'description' => "Goods Received Not Invoiced (GRNI) for GRN {$grn->grn_number}",
                            ],
                        ], [
                            'tenant_id' => $grn->tenant_id,
                            'source' => 'purchase',
                            'reference_type' => GoodsReceiptNote::class,
                            'reference_id' => $grn->id,
                            'memo' => "Goods Receipt Note {$grn->grn_number} - Stock Credited",
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Accounting Journal Posting Exception on GRN Approval: ' . $e->getMessage());
            }
        });

        return redirect()->route('purchase.grns.show', $grn->id)->with('success', "Goods Receipt Note {$grn->grn_number} has been Approved! Warehouse inventory stocks and Purchase Order status have been updated.");
    }

    /**
     * Download PDF for Goods Receipt Note
     */
    public function downloadPdf($id)
    {
        $tenantId = require_tenant_id();

        $grn = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->with(['purchaseOrder', 'vendor', 'warehouse', 'creator', 'approver', 'items.product.uom'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('modules.purchase.grns.pdf', compact('grn'));
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download("GRN-{$grn->grn_number}.pdf");
    }

    /**
     * Delete Draft GRN
     */
    public function destroy($id)
    {
        $tenantId = require_tenant_id();

        $grn = GoodsReceiptNote::where('tenant_id', $tenantId)->findOrFail($id);

        if ($grn->status !== 'Draft') {
            return redirect()->back()->with('error', 'Only Draft GRNs can be deleted.');
        }

        $grn->delete();

        return redirect()->route('purchase.grns.index')->with('success', "Goods Receipt Note {$grn->grn_number} deleted successfully.");
    }
}
