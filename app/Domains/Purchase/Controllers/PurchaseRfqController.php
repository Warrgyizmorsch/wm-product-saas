<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Repositories\PurchaseRfqRepository;
use App\Domains\Purchase\Services\PurchaseRfqService;
use App\Domains\Purchase\Models\PurchaseRfq;
use App\Domains\Purchase\Models\PurchaseRfqVendor;
use App\Domains\Purchase\Models\PurchaseRfqVendorRate;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRfqController extends Controller
{
    public function __construct(
        protected PurchaseRfqRepository $rfqRepo,
        protected PurchaseRfqService $rfqService
    ) {}

    public function index(Request $request)
    {
        $data = $this->rfqRepo->getPaginatedRfqsData($request->all(), 10);
        return view('modules.purchase.rfqs.index', $data);
    }

    public function savingsDashboard(Request $request)
    {
        $tenantId = require_tenant_id();
        $user = auth()->user();
        $data = $this->rfqService->getSavingsDashboardData($request, $tenantId, $user);
        return view('modules.purchase.rfqs.savings-dashboard', $data);
    }

    public function poSavingsDetails(int $id)
    {
        $tenantId = require_tenant_id();
        $order = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['vendor', 'creator', 'items.product', 'requisition'])
            ->findOrFail($id);

        $rfqNumber = $order->reference ? str_replace('RFQ: ', '', $order->reference) : null;
        $rfq = null;
        if ($rfqNumber) {
            $rfq = PurchaseRfq::where('tenant_id', $tenantId)
                ->where('rfq_number', $rfqNumber)
                ->with(['rfqVendors.vendor', 'rfqVendors.rates.product', 'items.product'])
                ->first();
        }

        return view('modules.purchase.rfqs.po-savings-details', compact('order', 'rfq'));
    }

    public function create(Request $request)
    {
        $tenantId = require_tenant_id();

        $requisitions = PurchaseRequisition::where('tenant_id', $tenantId)
            ->where('status', 'Approved')
            ->get();
        $products = Product::where('tenant_id', $tenantId)->get();
        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();

        $selectedRequisitionId = $request->input('requisition_id');
        $requisitionItemIds = (array) $request->input('requisition_item_ids', []);
        $prefilledItems = [];

        if (!empty($requisitionItemIds)) {
            $prItems = PurchaseRequisitionItem::where('tenant_id', $tenantId)
                ->whereIn('id', $requisitionItemIds)
                ->with(['product', 'warehouse', 'requisition'])
                ->get();

            $mergedItems = [];
            foreach ($prItems as $prItem) {
                if (!$selectedRequisitionId && $prItem->purchase_requisition_id) {
                    $selectedRequisitionId = $prItem->purchase_requisition_id;
                }

                $alreadyOrderedQty = (float) $prItem->ordered_qty;
                $poOrderedQty = (float) PurchaseOrderItem::where('tenant_id', $tenantId)
                    ->where('product_id', $prItem->product_id)
                    ->whereHas('order', function ($q) use ($prItem) {
                        $q->where('purchase_requisition_id', $prItem->purchase_requisition_id)
                          ->where('status', '!=', 'Cancelled');
                    })
                    ->sum('quantity');

                $alreadyOrderedQty = max($alreadyOrderedQty, $poOrderedQty);
                $pendingQty = max(0.0, (float) $prItem->quantity - $alreadyOrderedQty);
                if ($pendingQty <= 0) {
                    continue;
                }

                $productId = $prItem->product_id;
                $product   = $prItem->product;
                $costRate  = (float) ($prItem->estimated_cost ?: ($product?->cost_price ?: ($product?->unit_cost ?: 0.00)));

                if (isset($mergedItems[$productId])) {
                    $mergedItems[$productId]['quantity'] += $pendingQty;
                    $mergedItems[$productId]['requisition_item_ids'][] = $prItem->id;
                } else {
                    $mergedItems[$productId] = [
                        'requisition_item_id'  => $prItem->id,
                        'requisition_item_ids' => [$prItem->id],
                        'product_id'           => $productId,
                        'product_name'         => $product?->name ?? 'Product #' . $productId,
                        'quantity'             => $pendingQty,
                        'estimated_cost'       => $costRate,
                        'vendor_id'            => $product?->preferred_vendor_id,
                    ];
                }
            }
            $prefilledItems = array_values($mergedItems);
        } elseif ($selectedRequisitionId) {
            $prItems = PurchaseRequisitionItem::where('tenant_id', $tenantId)
                ->where('purchase_requisition_id', $selectedRequisitionId)
                ->with(['product', 'warehouse'])
                ->get();

            $mergedItems = [];
            foreach ($prItems as $prItem) {
                $alreadyOrderedQty = (float) $prItem->ordered_qty;
                $poOrderedQty = (float) PurchaseOrderItem::where('tenant_id', $tenantId)
                    ->where('product_id', $prItem->product_id)
                    ->whereHas('order', function ($q) use ($prItem) {
                        $q->where('purchase_requisition_id', $prItem->purchase_requisition_id)
                          ->where('status', '!=', 'Cancelled');
                    })
                    ->sum('quantity');

                $alreadyOrderedQty = max($alreadyOrderedQty, $poOrderedQty);
                $pendingQty = max(0.0, (float) $prItem->quantity - $alreadyOrderedQty);
                if ($pendingQty <= 0) {
                    continue;
                }

                $productId = $prItem->product_id;
                $product   = $prItem->product;
                $costRate  = (float) ($prItem->estimated_cost ?: ($product?->cost_price ?: ($product?->unit_cost ?: 0.00)));

                if (isset($mergedItems[$productId])) {
                    $mergedItems[$productId]['quantity'] += $pendingQty;
                    $mergedItems[$productId]['requisition_item_ids'][] = $prItem->id;
                } else {
                    $mergedItems[$productId] = [
                        'requisition_item_id'  => $prItem->id,
                        'requisition_item_ids' => [$prItem->id],
                        'product_id'           => $productId,
                        'product_name'         => $product?->name ?? 'Product #' . $productId,
                        'quantity'             => $pendingQty,
                        'estimated_cost'       => $costRate,
                        'vendor_id'            => $product?->preferred_vendor_id,
                    ];
                }

                $requisitionItemIds[] = $prItem->id;
            }
            $prefilledItems = array_values($mergedItems);
        }

        return view('modules.purchase.rfqs.create', compact(
            'requisitions',
            'products',
            'vendors',
            'selectedRequisitionId',
            'requisitionItemIds',
            'prefilledItems'
        ));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'rfq_date' => 'required|date',
            'purchase_requisition_id' => 'nullable|integer|exists:purchase_requisitions,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.estimated_cost' => 'nullable|numeric|min:0',
            'items.*.vendor_ids' => 'nullable|array',
            'items.*.vendor_ids.*' => 'integer|exists:vendors,id',
        ]);

        $rfq = $this->rfqService->storeRfq($validated, $tenantId);

        return redirect()->route('purchase.rfqs.show', $rfq->id)
            ->with('success', "RFQ {$rfq->rfq_number} created successfully.");
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = $this->rfqRepo->findWithDetails($id);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.purchase.rfqs.show', compact('rfq', 'warehouses'));
    }

    public function edit(int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = $this->rfqRepo->findWithDetails($id);

        if ($rfq->status !== 'Draft') {
            return redirect()->route('purchase.rfqs.show', $id)
                ->with('error', 'Only Draft RFQs can be edited.');
        }

        $requisitions = PurchaseRequisition::where('tenant_id', $tenantId)->where('status', 'Approved')->get();
        $products = Product::where('tenant_id', $tenantId)->get();
        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();

        return view('modules.purchase.rfqs.edit', compact('rfq', 'requisitions', 'products', 'vendors'));
    }

    public function update(Request $request, int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = $this->rfqRepo->find($id);
        if (!$rfq) abort(404);

        if ($rfq->status !== 'Draft') {
            return redirect()->route('purchase.rfqs.show', $id)
                ->with('error', 'Only Draft RFQs can be updated.');
        }

        $validated = $request->validate([
            'rfq_date' => 'required|date',
            'purchase_requisition_id' => 'nullable|integer|exists:purchase_requisitions,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.estimated_cost' => 'nullable|numeric|min:0',
            'items.*.vendor_ids' => 'nullable|array',
            'items.*.vendor_ids.*' => 'integer|exists:vendors,id',
        ]);

        $this->rfqService->updateRfq($rfq, $validated, $tenantId);

        return redirect()->route('purchase.rfqs.show', $id)
            ->with('success', 'RFQ updated successfully.');
    }

    public function enterQuotes(int $id)
    {
        $rfq = $this->rfqRepo->findWithDetails($id);
        return view('modules.purchase.rfqs.enter-quotes', compact('rfq'));
    }

    public function storeQuotes(Request $request, int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = $this->rfqRepo->find($id);
        if (!$rfq) abort(404);

        $validated = $request->validate([
            'quotes' => 'required|array',
            'quotes.*.quotation_number' => 'nullable|string|max:255',
            'quotes.*.rates' => 'required|array',
            'quotes.*.rates.*.rate' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $rfq, $tenantId) {
            foreach ($validated['quotes'] as $vendorId => $qData) {
                $rfqVendor = PurchaseRfqVendor::where('purchase_rfq_id', $rfq->id)
                    ->where('vendor_id', $vendorId)
                    ->first();

                if ($rfqVendor) {
                    $rfqVendor->update([
                        'quotation_number' => $qData['quotation_number'] ?? null,
                        'status' => 'Submitted',
                        'submitted_at' => now(),
                    ]);

                    foreach ($qData['rates'] as $productId => $rData) {
                        PurchaseRfqVendorRate::updateOrCreate(
                            [
                                'purchase_rfq_vendor_id' => $rfqVendor->id,
                                'product_id' => $productId,
                            ],
                            [
                                'rate' => $rData['rate'],
                            ]
                        );
                    }
                }
            }
        });

        return redirect()->route('purchase.rfqs.show', $id)
            ->with('success', 'Supplier quotes recorded successfully.');
    }

    public function sendRfq(Request $request, int $id)
    {
        $rfq = $this->rfqRepo->find($id);
        if (!$rfq) abort(404);

        $this->rfqRepo->update($rfq, ['status' => 'Sent']);

        return redirect()->route('purchase.rfqs.show', $id)
            ->with('success', "RFQ {$rfq->rfq_number} sent to vendors.");
    }

    public function confirmRfq(Request $request, int $id)
    {
        $rfq = $this->rfqRepo->find($id);
        if (!$rfq) abort(404);

        $this->rfqRepo->update($rfq, ['status' => 'Confirmed']);

        return redirect()->route('purchase.rfqs.show', $id)
            ->with('success', "RFQ {$rfq->rfq_number} confirmed.");
    }

    public function createPo(Request $request, int $id)
    {
        return $this->convertPo($request, $id);
    }

    public function saveComparison(Request $request, int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = $this->rfqRepo->findWithDetails($id);
        if (!$rfq) abort(404);

        $vendorsData = $request->input('vendors', []);
        $quotesData = $request->input('vendor_quotes', []);
        $files = $request->file('vendors', []);

        DB::transaction(function () use ($rfq, $vendorsData, $quotesData, $files) {
            foreach ($rfq->rfqVendors as $rv) {
                $rvId = $rv->id;
                $vData = $vendorsData[$rvId] ?? [];

                $updateData = [];
                if (isset($vData['payment_type'])) {
                    $updateData['payment_type'] = $vData['payment_type'];
                }
                if (isset($vData['quotation_number'])) {
                    $updateData['quotation_number'] = $vData['quotation_number'];
                }
                if (isset($vData['terms_conditions'])) {
                    $updateData['terms_conditions'] = $vData['terms_conditions'];
                }

                if (isset($files[$rvId]['attachment']) && $files[$rvId]['attachment']->isValid()) {
                    $updateData['attachment_path'] = $files[$rvId]['attachment']->store('rfq_attachments', 'public');
                }

                $hasRates = false;
                if (isset($quotesData[$rvId])) {
                    foreach ($quotesData[$rvId] as $productId => $qItem) {
                        if (isset($qItem['rate']) && $qItem['rate'] !== null && $qItem['rate'] !== '') {
                            $hasRates = true;
                            PurchaseRfqVendorRate::updateOrCreate(
                                [
                                    'purchase_rfq_vendor_id' => $rvId,
                                    'product_id'             => $productId,
                                ],
                                [
                                    'rate'          => (float) $qItem['rate'],
                                    'quantity'      => isset($qItem['quantity']) && $qItem['quantity'] !== '' ? (float) $qItem['quantity'] : null,
                                    'delivery_date' => !empty($qItem['delivery_date']) ? $qItem['delivery_date'] : null,
                                    'validity_date' => !empty($qItem['validity_date']) ? $qItem['validity_date'] : null,
                                ]
                            );
                        }
                    }
                }

                if ($hasRates || !empty($vData['quotation_number'])) {
                    $updateData['status'] = 'Received';
                    $updateData['submitted_at'] = $rv->submitted_at ?: now();
                }

                if (!empty($updateData)) {
                    $rv->update($updateData);
                }
            }
        });

        return redirect()->route('purchase.rfqs.show', $id)
            ->with('success', 'Vendor quotation matrix & supplier rates saved successfully.');
    }

    public function getRequisitionItems(Request $request)
    {
        $tenantId = require_tenant_id();
        $requisitionId = (int) $request->query('requisition_id');
        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with(['items.product'])
            ->find($requisitionId);

        if (!$requisition) {
            return response()->json(['success' => false, 'error' => 'Requisition not found.'], 404);
        }

        $items = [];
        foreach ($requisition->items as $item) {
            $items[] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : ''),
                'quantity' => (float)$item->quantity,
                'estimated_cost' => (float)$item->estimated_cost,
            ];
        }

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function quotesComparison(int $id)
    {
        $rfq = $this->rfqRepo->findWithDetails($id);
        return view('modules.purchase.rfqs.quotes-comparison', compact('rfq'));
    }

    public function convertPoForm(int $id, int $vendor_id)
    {
        $tenantId = require_tenant_id();
        $rfq = $this->rfqRepo->findWithDetails($id);
        $vendor = Vendor::where('tenant_id', $tenantId)->findOrFail($vendor_id);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        $rfqVendor = $rfq->rfqVendors->firstWhere('vendor_id', $vendor_id);

        return view('modules.purchase.rfqs.convert-po', compact('rfq', 'vendor', 'warehouses', 'rfqVendor'));
    }

    public function convertPo(Request $request, int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = $this->rfqRepo->find($id);
        if (!$rfq) abort(404);

        $validated = $request->validate([
            'vendor_id' => 'required|integer|exists:vendors,id',
            'location' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'supplier_quotation_number' => 'nullable|string|max:255',
            'date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'discount_type' => 'required|string',
            'tax_type' => 'required|string',
            'gst_type' => 'required|string',
            'subtotal' => 'required|numeric',
            'discount_amount' => 'required|numeric',
            'cgst_amount' => 'required|numeric',
            'sgst_amount' => 'required|numeric',
            'igst_amount' => 'required|numeric',
            'tax_amount' => 'required|numeric',
            'grand_total' => 'required|numeric',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric',
            'items.*.rate' => 'required|numeric',
        ]);

        $po = $this->rfqService->createPoFromRfq($rfq, $validated, $tenantId);

        return redirect()->route('purchase.orders.show', $po->id)
            ->with('success', "Draft PO {$po->purchase_order_number} created successfully from RFQ {$rfq->rfq_number}.");
    }

    public function showPortal(string $token)
    {
        $rfqVendor = PurchaseRfqVendor::where('token', $token)
            ->with(['rfq.items.product', 'vendor', 'rates'])
            ->firstOrFail();

        $rfq = $rfqVendor->rfq;
        $vendor = $rfqVendor->vendor;
        $existingRates = $rfqVendor->rates->keyBy('product_id');

        return view('modules.purchase.rfqs.portal', compact('rfqVendor', 'rfq', 'vendor', 'existingRates'));
    }

    public function submitPortal(Request $request, string $token)
    {
        $rfqVendor = PurchaseRfqVendor::where('token', $token)->firstOrFail();

        $validated = $request->validate([
            'quotation_number' => 'required|string|max:255',
            'payment_type'     => 'nullable|string|max:255',
            'terms_conditions' => 'nullable|string',
            'attachment'       => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:10240',
            'rates'            => 'required|array|min:1',
            'rates.*.product_id' => 'required|integer',
            'rates.*.quantity' => 'required|numeric|min:0.0001',
            'rates.*.rate'     => 'required|numeric|min:0',
            'rates.*.delivery_date' => 'nullable|date',
            'rates.*.validity_date' => 'nullable|date',
        ]);

        $attachmentPath = $rfqVendor->attachment_path;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('rfq_attachments', 'public');
        }

        DB::transaction(function () use ($validated, $rfqVendor, $attachmentPath) {
            $rfqVendor->update([
                'quotation_number' => $validated['quotation_number'] ?? null,
                'payment_type'     => $validated['payment_type'] ?? null,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
                'attachment_path'  => $attachmentPath,
                'status'           => 'Submitted',
                'submitted_at'     => now(),
            ]);

            foreach ($validated['rates'] as $itemData) {
                $productId = (int) $itemData['product_id'];
                PurchaseRfqVendorRate::updateOrCreate(
                    [
                        'purchase_rfq_vendor_id' => $rfqVendor->id,
                        'product_id'             => $productId,
                    ],
                    [
                        'quantity'      => $itemData['quantity'] ?? null,
                        'rate'          => $itemData['rate'],
                        'delivery_date' => $itemData['delivery_date'] ?? null,
                        'validity_date' => $itemData['validity_date'] ?? null,
                    ]
                );
            }
        });

        return view('modules.purchase.rfqs.portal-submitted', compact('rfqVendor'));
    }

    public function destroy(int $id)
    {
        $rfq = $this->rfqRepo->find($id);
        if (!$rfq) abort(404);

        if ($rfq->status !== 'Draft') {
            return redirect()->route('purchase.rfqs.show', $id)
                ->with('error', 'Only Draft RFQs can be deleted.');
        }

        $this->rfqRepo->delete($rfq);

        return redirect()->route('purchase.rfqs.index')
            ->with('success', 'RFQ deleted successfully.');
    }
}
