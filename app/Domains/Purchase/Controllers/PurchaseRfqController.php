<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\PurchaseRfq;
use App\Domains\Purchase\Models\PurchaseRfqItem;
use App\Domains\Purchase\Models\PurchaseRfqVendor;
use App\Domains\Purchase\Models\PurchaseRfqVendorRate;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseRfqController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = require_tenant_id();
        $user = auth()->user();
        $isAdmin = in_array($user->role ?? '', ['admin', 'super_admin', 'tenant_owner', 'company_admin']);

        $query = PurchaseRfq::where('tenant_id', $tenantId)
            ->with(['rfqVendors.vendor', 'rfqVendors.rates', 'requisition', 'creator', 'items.product']);

        // User Scope / Filter
        if (! $isAdmin) {
            $query->where('created_by', $user->id);
        } elseif ($request->filled('created_by')) {
            $query->where('created_by', $request->input('created_by'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('rfq_number', 'like', $search)
                  ->orWhereHas('rfqVendors.vendor', function ($v) use ($search) {
                      $v->where('name', 'like', $search);
                  })
                  ->orWhereHas('creator', function ($c) use ($search) {
                      $c->where('name', 'like', $search);
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('rfq_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('rfq_date', '<=', $request->input('date_to'));
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['id', 'rfq_number', 'rfq_date', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        // ── Calculate Total Savings across ALL matching records (Unpaginated Full Set) ──
        $allFilteredRfqs = (clone $query)->get();
        $totalFilteredCount = $allFilteredRfqs->count();
        $totalFilteredSavings = 0;
        $totalFilteredSpend = 0;

        $rfqNumbers = $allFilteredRfqs->pluck('rfq_number')->filter()->toArray();
        $matchingPos = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)
            ->where('source_type', 'rfq')
            ->where(function ($q) use ($rfqNumbers) {
                foreach ($rfqNumbers as $rfqNum) {
                    $q->orWhere('reference', 'like', '%' . $rfqNum . '%');
                }
            })
            ->with(['items'])
            ->get();

        $poByRfqMap = [];
        foreach ($matchingPos as $po) {
            if ($po->reference && preg_match('/RFQ:\s*([^\s\|]+)/i', $po->reference, $matches)) {
                $num = trim($matches[1]);
                $poByRfqMap[$num] = $po;
            } else {
                $num = str_replace('RFQ: ', '', $po->reference);
                $poByRfqMap[$num] = $po;
            }
        }

        $calcRfqSavings = function ($rfq) use ($poByRfqMap) {
            $rfqNum = $rfq->rfq_number;
            $po = $poByRfqMap[$rfqNum] ?? null;
            if (! $po) {
                return ['savings' => 0, 'spend' => 0, 'percent' => 0, 'po_number' => null];
            }

            $poTotal = (float) $po->grand_total;
            $poHighestTotal = 0;
            $poSavings = 0;

            foreach ($po->items as $item) {
                $qty = (float) $item->quantity;
                $poRate = (float) $item->rate;

                $highestRate = $poRate;
                $allVendorRates = [];
                foreach ($rfq->rfqVendors as $rv) {
                    foreach ($rv->rates as $vRate) {
                        if ((int)$vRate->product_id === (int)$item->product_id && (float)$vRate->rate > 0) {
                            $allVendorRates[] = (float)$vRate->rate;
                        }
                    }
                }
                if (! empty($allVendorRates)) {
                    $highestRate = max($allVendorRates);
                }

                if ($highestRate <= $poRate && $item->product?->estimated_cost > $poRate) {
                    $highestRate = (float) $item->product->estimated_cost;
                }

                $itemHighestTotal = $highestRate * $qty;
                $poHighestTotal += $itemHighestTotal;
                $poSavings += max(0, $itemHighestTotal - ($poRate * $qty));
            }

            $percent = $poHighestTotal > 0 ? ($poSavings / $poHighestTotal) * 100 : 0;
            return [
                'savings' => $poSavings,
                'spend' => $poTotal,
                'percent' => round($percent, 2),
                'po_number' => $po->purchase_order_number,
            ];
        };

        foreach ($allFilteredRfqs as $rItem) {
            $sData = $calcRfqSavings($rItem);
            $totalFilteredSavings += $sData['savings'];
            $totalFilteredSpend += $sData['spend'];
        }

        // Paginate the main RFQ list
        $rfqs = $query->paginate(10)->withQueryString();

        foreach ($rfqs as $rfqItem) {
            $sData = $calcRfqSavings($rfqItem);
            $rfqItem->savings_amount = $sData['savings'];
            $rfqItem->savings_percent = $sData['percent'];
            $rfqItem->po_number = $sData['po_number'];
        }

        $allPurchasers = \App\Models\User::where('tenant_id', $tenantId)->get(['id', 'name']);

        return view('modules.purchase.rfqs.index', compact(
            'rfqs',
            'isAdmin',
            'allPurchasers',
            'totalFilteredCount',
            'totalFilteredSavings',
            'totalFilteredSpend'
        ));
    }

    public function create(Request $request)
    {
        $tenantId = require_tenant_id();
        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $products = Product::where('tenant_id', $tenantId)->get();
        
        $requisitions = PurchaseRequisition::where('tenant_id', $tenantId)
            ->where('status', 'Approved')
            ->get();

        $selectedRequisitionId = $request->query('requisition_id');
        $requisitionItemIds = $request->query('requisition_item_ids', []);
        $prefilledItems = [];

        if ($selectedRequisitionId) {
            $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
                ->with('items.product')
                ->find($selectedRequisitionId);
            
            if ($requisition) {
                $existingPos = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)
                    ->where('purchase_requisition_id', $selectedRequisitionId)
                    ->where('status', '!=', 'Cancelled')
                    ->with('items')
                    ->get();

                $groupedItems = [];
                foreach ($requisition->items as $item) {
                    $alreadyOrderedQty = (float) $existingPos
                        ->flatMap(fn($po) => $po->items)
                        ->where('product_id', $item->product_id)
                        ->sum('quantity');

                    $pendingQty = max(0.0, (float)$item->quantity - $alreadyOrderedQty);

                    if ($pendingQty > 0.0001) {
                        $vendorId = null;
                        if ($item->product->preferred_vendor_id) {
                            $vendorId = $item->product->preferred_vendor_id;
                        } else {
                            $lastPoItem = \App\Domains\Purchase\Models\PurchaseOrderItem::where('tenant_id', $tenantId)
                                ->where('product_id', $item->product_id)
                                ->whereHas('order', function ($q) {
                                    $q->where('status', 'Approved');
                                })
                                ->orderBy('id', 'desc')
                                ->first();
                            $vendorId = $lastPoItem?->order?->vendor_id;
                        }

                        if (isset($groupedItems[$item->product_id])) {
                            $groupedItems[$item->product_id]['quantity'] += $pendingQty;
                            $groupedItems[$item->product_id]['estimated_cost'] = max($groupedItems[$item->product_id]['estimated_cost'], (float)$item->estimated_cost);
                        } else {
                            $groupedItems[$item->product_id] = [
                                'product_id' => $item->product_id,
                                'product_name' => $item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : ''),
                                'quantity' => $pendingQty,
                                'estimated_cost' => (float)$item->estimated_cost,
                                'vendor_id' => $vendorId,
                            ];
                        }
                    }
                }
                $prefilledItems = array_values($groupedItems);
            }
        } elseif (!empty($requisitionItemIds)) {
            $items = PurchaseRequisitionItem::whereIn('id', $requisitionItemIds)
                ->with(['product', 'requisition'])
                ->get();

            if ($items->isNotEmpty()) {
                $requisitionIds = $items->pluck('purchase_requisition_id')->unique()->toArray();
                $existingPos = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)
                    ->whereIn('purchase_requisition_id', $requisitionIds)
                    ->where('status', '!=', 'Cancelled')
                    ->with('items')
                    ->get();

                $selectedRequisitionId = $items->first()->purchase_requisition_id;

                $groupedItems = [];
                foreach ($items as $item) {
                    $alreadyOrderedQty = (float) $existingPos
                        ->where('purchase_requisition_id', $item->purchase_requisition_id)
                        ->flatMap(fn($po) => $po->items)
                        ->where('product_id', $item->product_id)
                        ->sum('quantity');

                    $pendingQty = max(0.0, (float)$item->quantity - $alreadyOrderedQty);

                    if ($pendingQty > 0.0001) {
                        $vendorId = null;
                        if ($item->product->preferred_vendor_id) {
                            $vendorId = $item->product->preferred_vendor_id;
                        } else {
                            $lastPoItem = \App\Domains\Purchase\Models\PurchaseOrderItem::where('tenant_id', $tenantId)
                                ->where('product_id', $item->product_id)
                                ->whereHas('order', function ($q) {
                                    $q->where('status', 'Approved');
                                })
                                ->orderBy('id', 'desc')
                                ->first();
                            $vendorId = $lastPoItem?->order?->vendor_id;
                        }

                        if (isset($groupedItems[$item->product_id])) {
                            $groupedItems[$item->product_id]['quantity'] += $pendingQty;
                            $groupedItems[$item->product_id]['estimated_cost'] = max($groupedItems[$item->product_id]['estimated_cost'], (float)$item->estimated_cost);
                        } else {
                            $groupedItems[$item->product_id] = [
                                'product_id' => $item->product_id,
                                'product_name' => $item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : ''),
                                'quantity' => $pendingQty,
                                'estimated_cost' => (float)$item->estimated_cost,
                                'vendor_id' => $vendorId,
                            ];
                        }
                    }
                }
                $prefilledItems = array_values($groupedItems);
            }
        }

        return view('modules.purchase.rfqs.create', compact('vendors', 'warehouses', 'products', 'requisitions', 'selectedRequisitionId', 'prefilledItems'));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $request->validate([
            'rfq_date' => 'required|date',
            'purchase_requisition_id' => 'nullable|exists:purchase_requisitions,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.estimated_cost' => 'nullable|numeric|min:0',
            'items.*.vendor_ids' => 'required|array|min:1',
            'items.*.vendor_ids.*' => 'exists:vendors,id',
        ]);

        return DB::transaction(function () use ($request, $tenantId) {
            // Generate sequence number
            $year = now()->format('Y');
            $prefix = "RFQ-{$year}-";
            $latest = PurchaseRfq::where('tenant_id', $tenantId)
                ->where('rfq_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($latest) {
                $lastNumStr = str_replace($prefix, '', $latest->rfq_number);
                $nextNum = intval($lastNumStr) + 1;
            }
            $rfqNumber = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $rfq = PurchaseRfq::create([
                'tenant_id' => $tenantId,
                'rfq_number' => $rfqNumber,
                'purchase_requisition_id' => $request->input('purchase_requisition_id'),
                'rfq_date' => $request->input('rfq_date'),
                'status' => 'Draft',
                'notes' => $request->input('notes'),
                'created_by' => auth()->id() ?: 1,
            ]);

            // Save Inquired Items and mapping
            $uniqueVendorIds = [];
            foreach ($request->input('items') as $itemData) {
                $rfqItem = PurchaseRfqItem::create([
                    'purchase_rfq_id' => $rfq->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'estimated_cost' => $itemData['estimated_cost'] ?? 0.00,
                ]);

                if (isset($itemData['vendor_ids']) && is_array($itemData['vendor_ids'])) {
                    foreach ($itemData['vendor_ids'] as $vendorId) {
                        $uniqueVendorIds[$vendorId] = $vendorId;
                        DB::table('purchase_rfq_item_vendors')->insert([
                            'tenant_id' => $tenantId,
                            'purchase_rfq_item_id' => $rfqItem->id,
                            'vendor_id' => $vendorId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Bind unique Vendors with Unique Access Tokens
            foreach (array_values($uniqueVendorIds) as $vendorId) {
                PurchaseRfqVendor::create([
                    'tenant_id' => $tenantId,
                    'purchase_rfq_id' => $rfq->id,
                    'vendor_id' => $vendorId,
                    'token' => Str::random(40),
                    'status' => 'Sent',
                ]);
            }

            return redirect()->route('purchase.rfqs.show', $rfq->id)
                ->with('success', "RFQ {$rfqNumber} created successfully.");
        });
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = PurchaseRfq::where('tenant_id', $tenantId)
            ->with(['requisition', 'items.product', 'items.vendors', 'rfqVendors.vendor', 'rfqVendors.rates.product'])
            ->findOrFail($id);

        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.purchase.rfqs.show', compact('rfq', 'warehouses'));
    }

    public function edit(int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = PurchaseRfq::where('tenant_id', $tenantId)->with(['items.vendors', 'rfqVendors'])->findOrFail($id);
        
        if ($rfq->status !== 'Draft') {
            return redirect()->route('purchase.rfqs.show', $id)
                ->with('error', 'Only draft RFQs can be edited.');
        }

        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $products = Product::where('tenant_id', $tenantId)->get();
        
        $requisitions = PurchaseRequisition::where('tenant_id', $tenantId)
            ->where('status', 'Approved')
            ->get();

        $linkedVendorIds = $rfq->rfqVendors->pluck('vendor_id')->toArray();

        return view('modules.purchase.rfqs.edit', compact('rfq', 'vendors', 'warehouses', 'products', 'requisitions', 'linkedVendorIds'));
    }

    public function update(Request $request, int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = PurchaseRfq::where('tenant_id', $tenantId)->findOrFail($id);

        if ($rfq->status !== 'Draft') {
            return redirect()->route('purchase.rfqs.show', $id)
                ->with('error', 'Only draft RFQs can be updated.');
        }

        $request->validate([
            'rfq_date' => 'required|date',
            'purchase_requisition_id' => 'nullable|exists:purchase_requisitions,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.estimated_cost' => 'nullable|numeric|min:0',
            'items.*.vendor_ids' => 'required|array|min:1',
            'items.*.vendor_ids.*' => 'exists:vendors,id',
        ]);

        return DB::transaction(function () use ($request, $rfq, $tenantId) {
            $rfq->update([
                'purchase_requisition_id' => $request->input('purchase_requisition_id'),
                'rfq_date' => $request->input('rfq_date'),
                'notes' => $request->input('notes'),
            ]);

            // Re-sync items (Cascade deletes database records in pivot automatically)
            $rfq->items()->delete();

            $uniqueVendorIds = [];
            foreach ($request->input('items') as $itemData) {
                $rfqItem = PurchaseRfqItem::create([
                    'purchase_rfq_id' => $rfq->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'estimated_cost' => $itemData['estimated_cost'] ?? 0.00,
                ]);

                if (isset($itemData['vendor_ids']) && is_array($itemData['vendor_ids'])) {
                    foreach ($itemData['vendor_ids'] as $vendorId) {
                        $uniqueVendorIds[$vendorId] = $vendorId;
                        DB::table('purchase_rfq_item_vendors')->insert([
                            'tenant_id' => $tenantId,
                            'purchase_rfq_item_id' => $rfqItem->id,
                            'vendor_id' => $vendorId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Sync vendors
            $existingVendors = $rfq->rfqVendors;
            $newVendorIds = array_values($uniqueVendorIds);

            // Delete removed vendors
            foreach ($existingVendors as $ev) {
                if (!in_array($ev->vendor_id, $newVendorIds)) {
                    $ev->delete();
                }
            }

            // Add new vendors
            $existingVendorIds = $existingVendors->pluck('vendor_id')->toArray();
            foreach ($newVendorIds as $vendorId) {
                if (!in_array($vendorId, $existingVendorIds)) {
                    PurchaseRfqVendor::create([
                        'tenant_id' => $tenantId,
                        'purchase_rfq_id' => $rfq->id,
                        'vendor_id' => $vendorId,
                        'token' => Str::random(40),
                        'status' => 'Sent',
                    ]);
                }
            }

            return redirect()->route('purchase.rfqs.show', $rfq->id)
                ->with('success', "RFQ updated successfully.");
        });
    }

    public function destroy(int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = PurchaseRfq::where('tenant_id', $tenantId)->findOrFail($id);
        
        $rfq->delete();

        return redirect()->route('purchase.rfqs.index')
            ->with('success', 'RFQ deleted successfully.');
    }

    public function sendRfq(int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = PurchaseRfq::where('tenant_id', $tenantId)->findOrFail($id);

        if ($rfq->status === 'Draft') {
            $rfq->update(['status' => 'Sent']);
            return redirect()->back()->with('success', 'RFQ marked as Sent to all selected Vendors.');
        }

        return redirect()->back()->with('error', 'Only draft RFQs can be sent.');
    }

    public function confirmRfq(int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = PurchaseRfq::where('tenant_id', $tenantId)->findOrFail($id);

        $rfq->update(['status' => 'Confirmed']);

        return redirect()->route('purchase.rfqs.show', $id)
            ->with('success', 'RFQ confirmed. Ready to convert to Purchase Order.');
    }

    public function getRequisitionItems(Request $request)
    {
        $tenantId = require_tenant_id();
        $requisitionId = (int) $request->query('requisition_id');
        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with(['items.product', 'items.warehouse'])
            ->find($requisitionId);

        if (!$requisition) {
            return response()->json(['success' => false, 'error' => 'Requisition not found.'], 404);
        }

        $items = [];
        foreach ($requisition->items as $item) {
            $vendorId = null;
            if ($item->product->preferred_vendor_id) {
                $vendorId = $item->product->preferred_vendor_id;
            } else {
                $lastPoItem = \App\Domains\Purchase\Models\PurchaseOrderItem::where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->whereHas('order', function ($q) {
                        $q->where('status', 'Approved');
                    })
                    ->orderBy('id', 'desc')
                    ->first();
                $vendorId = $lastPoItem?->order?->vendor_id;
            }

            $items[] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : ''),
                'warehouse_id' => $item->warehouse_id,
                'warehouse_name' => $item->warehouse->name ?? '—',
                'quantity' => (float)$item->quantity,
                'estimated_cost' => (float)$item->estimated_cost,
                'vendor_id' => $vendorId,
            ];
        }

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public Vendor Portal Handlers (Requires NO Auth)
    // ─────────────────────────────────────────────────────────────────────────
    public function showPortal(string $token)
    {
        // Lookup using token (ignoring auth check, but tenant scope is resolved automatically via token)
        $rfqVendor = PurchaseRfqVendor::where('token', $token)
            ->with(['rfq.items.product', 'vendor', 'rates'])
            ->firstOrFail();

        $rfq = $rfqVendor->rfq;
        $vendor = $rfqVendor->vendor;

        // Fetch only items mapped to this vendor in purchase_rfq_item_vendors
        $mappedItemIds = DB::table('purchase_rfq_item_vendors')
            ->where('vendor_id', $vendor->id)
            ->whereIn('purchase_rfq_item_id', $rfq->items->pluck('id'))
            ->pluck('purchase_rfq_item_id')
            ->toArray();

        $filteredItems = $rfq->items->filter(function ($item) use ($mappedItemIds) {
            return in_array($item->id, $mappedItemIds);
        });

        // Set the filtered collection on the relation
        $rfq->setRelation('items', $filteredItems);

        // Map existing quoted rates if any
        $existingRates = $rfqVendor->rates->keyBy('product_id');

        return view('modules.purchase.rfqs.portal', compact('rfqVendor', 'rfq', 'vendor', 'existingRates'));
    }

    public function submitPortal(Request $request, string $token)
    {
        $rfqVendor = PurchaseRfqVendor::where('token', $token)
            ->with(['rfq.items'])
            ->firstOrFail();

        $rfq = $rfqVendor->rfq;
        $vendorId = $rfqVendor->vendor_id;

        // Get allowed product IDs for this vendor
        $mappedItemIds = DB::table('purchase_rfq_item_vendors')
            ->where('vendor_id', $vendorId)
            ->whereIn('purchase_rfq_item_id', $rfq->items->pluck('id'))
            ->pluck('purchase_rfq_item_id')
            ->toArray();

        $allowedProductIds = $rfq->items
            ->filter(fn($item) => in_array($item->id, $mappedItemIds))
            ->pluck('product_id')
            ->toArray();

        $request->validate([
            'payment_type' => 'nullable|string|max:255',
            'quotation_number' => 'nullable|string|max:255',
            'terms_conditions' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,zip,xls,xlsx|max:10240', // Max 10MB
            'rates' => 'required|array',
            'rates.*.product_id' => 'required|exists:products,id',
            'rates.*.rate' => 'required|numeric|min:0',
            'rates.*.quantity' => 'required|numeric|min:0.0001',
            'rates.*.delivery_date' => 'nullable|date',
            'rates.*.validity_date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($request, $rfqVendor, $allowedProductIds) {
            $attachmentPath = $rfqVendor->attachment_path;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('rfq_attachments', 'public');
            }

            // Update Quotation Header Info
            $rfqVendor->update([
                'payment_type' => $request->input('payment_type'),
                'quotation_number' => $request->input('quotation_number'),
                'terms_conditions' => $request->input('terms_conditions'),
                'attachment_path' => $attachmentPath,
                'status' => 'Received',
            ]);

            // Save new Rate Submission (append to historical rate log) for allowed products only
            foreach ($request->input('rates') as $quote) {
                if (in_array($quote['product_id'], $allowedProductIds)) {
                    PurchaseRfqVendorRate::create([
                        'tenant_id' => $rfqVendor->tenant_id,
                        'purchase_rfq_vendor_id' => $rfqVendor->id,
                        'product_id' => $quote['product_id'],
                        'rate' => $quote['rate'],
                        'quantity' => $quote['quantity'],
                        'delivery_date' => $quote['delivery_date'] ?? null,
                        'validity_date' => $quote['validity_date'] ?? null,
                    ]);
                }
            }

            // Check if all vendors have responded to auto-toggle main RFQ status
            $mainRfq = $rfqVendor->rfq;
            $allResponded = ! $mainRfq->rfqVendors()->where('status', 'Sent')->exists();
            if ($allResponded) {
                $mainRfq->update(['status' => 'Received']);
            }
        });

        return redirect()->back()->with('success', 'Thank you! Your quotation details have been recorded successfully.');
    }

    public function saveComparison(Request $request, int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = PurchaseRfq::where('tenant_id', $tenantId)->findOrFail($id);

        DB::transaction(function () use ($request, $rfq) {
            // 1. Save vendor header fields
            if ($request->has('vendors')) {
                foreach ($request->input('vendors') as $rvId => $vendorData) {
                    $rfqVendor = PurchaseRfqVendor::where('purchase_rfq_id', $rfq->id)->findOrFail($rvId);
                    
                    // Handle file upload if any
                    $attachmentPath = $rfqVendor->attachment_path;
                    if ($request->hasFile("vendors.{$rvId}.attachment")) {
                        $attachmentPath = $request->file("vendors.{$rvId}.attachment")->store('rfq_attachments', 'public');
                    }

                    $rfqVendor->update([
                        'quotation_number' => $vendorData['quotation_number'] ?? null,
                        'payment_type' => $vendorData['payment_type'] ?? null,
                        'terms_conditions' => $vendorData['terms_conditions'] ?? null,
                        'attachment_path' => $attachmentPath,
                        'status' => 'Received', // automatically mark as received if manual updates are saved
                    ]);
                }
            }

            // 2. Save vendor rates
            if ($request->has('vendor_quotes')) {
                foreach ($request->input('vendor_quotes') as $rvId => $productsData) {
                    $rfqVendor = PurchaseRfqVendor::where('purchase_rfq_id', $rfq->id)->findOrFail($rvId);

                    foreach ($productsData as $productId => $quoteData) {
                        // Avoid inserting empty rates
                        if ($quoteData['rate'] === null || $quoteData['rate'] === '') {
                            continue;
                        }

                        PurchaseRfqVendorRate::create([
                            'tenant_id' => $rfqVendor->tenant_id,
                            'purchase_rfq_vendor_id' => $rfqVendor->id,
                            'product_id' => $productId,
                            'rate' => $quoteData['rate'],
                            'quantity' => $quoteData['quantity'] ?? 0.00,
                            'delivery_date' => $quoteData['delivery_date'] ?? null,
                            'validity_date' => $quoteData['validity_date'] ?? null,
                        ]);
                    }
                }
            }

            // Auto-toggle main RFQ status
            $allResponded = ! $rfq->rfqVendors()->where('status', 'Sent')->exists();
            if ($allResponded) {
                $rfq->update(['status' => 'Received']);
            }
        });

        return redirect()->route('purchase.rfqs.show', $rfq->id)
            ->with('success', 'Vendor quotation rates and details updated successfully.');
    }

    public function createPo(Request $request, int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = PurchaseRfq::where('tenant_id', $tenantId)->findOrFail($id);

        if ($rfq->status === 'Confirmed') {
            return redirect()->route('purchase.rfqs.show', $id)
                ->with('error', 'A Purchase Order has already been created from this RFQ (Status is Confirmed). Duplicate PO creation is not allowed.');
        }

        $validated = $request->validate([
            'vendor_id' => 'required|integer|exists:vendors,id',
            'location' => 'required|string|exists:warehouses,name',
            'date' => 'required|date',
            'delivery_date' => 'nullable|date|after_or_equal:date',
            'reference' => 'nullable|string|max:255',
            'supplier_quotation_number' => 'nullable|string|max:255',
            'discount_type' => 'required|string|in:without_discount,item_wise,order_wise',
            'tax_type' => 'required|string|in:without_tax,item_wise_tax,order_wise_tax',
            'gst_type' => 'required|string|in:cgst_sgst,igst',
            'notes' => 'nullable|string',
            'subtotal' => 'required|numeric|min:0',
            'discount_amount' => 'required|numeric|min:0',
            'cgst_amount' => 'required|numeric|min:0',
            'sgst_amount' => 'required|numeric|min:0',
            'igst_amount' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_percent' => 'nullable|numeric|min:0',
            'items.*.cgst_percent' => 'nullable|numeric|min:0',
            'items.*.sgst_percent' => 'nullable|numeric|min:0',
            'items.*.igst_percent' => 'nullable|numeric|min:0',
            'items.*.cgst_amount' => 'nullable|numeric|min:0',
            'items.*.sgst_amount' => 'nullable|numeric|min:0',
            'items.*.igst_amount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.total_amount' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $rfq, $tenantId) {
            // Find warehouse ID
            $warehouse = Warehouse::where('tenant_id', $tenantId)
                ->where('name', $validated['location'])
                ->first();
            $warehouseId = $warehouse ? $warehouse->id : null;

            // Generate sequence number YYYY-000001
            $year = now()->format('Y');
            $prefix = "PO-{$year}-";
            $latest = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)
                ->where('purchase_order_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($latest) {
                $lastNumStr = str_replace($prefix, '', $latest->purchase_order_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $poNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $rfqVendor = $rfq->rfqVendors()->where('vendor_id', $validated['vendor_id'])->first();
            $quoteNo = $validated['supplier_quotation_number'] ?? ($rfqVendor?->quotation_number);

            $po = \App\Domains\Purchase\Models\PurchaseOrder::create([
                'tenant_id' => $tenantId,
                'purchase_order_number' => $poNumber,
                'purchase_requisition_id' => $rfq->purchase_requisition_id,
                'source_type' => 'rfq',
                'vendor_id' => $validated['vendor_id'],
                'location' => $validated['location'],
                'reference' => $validated['reference'] ?? $rfq->rfq_number,
                'supplier_quotation_number' => $quoteNo,
                'date' => $validated['date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'discount_type' => $validated['discount_type'],
                'tax_type' => $validated['tax_type'],
                'gst_type' => $validated['gst_type'],
                'subtotal' => $validated['subtotal'],
                'discount_amount' => $validated['discount_amount'],
                'cgst_amount' => $validated['cgst_amount'],
                'sgst_amount' => $validated['sgst_amount'],
                'igst_amount' => $validated['igst_amount'],
                'tax_amount' => $validated['tax_amount'],
                'grand_total' => $validated['grand_total'],
                'status' => 'Draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id() ?: 1,
            ]);

            foreach ($validated['items'] as $item) {
                $amount = $item['quantity'] * $item['rate'];
                \App\Domains\Purchase\Models\PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'amount' => $amount,
                    'discount_percent' => $item['discount_percent'] ?? 0.00,
                    'discount_amount' => $item['discount_amount'] ?? 0.00,
                    'tax_percent' => $item['tax_percent'] ?? 0.00,
                    'cgst_percent' => $item['cgst_percent'] ?? 0.00,
                    'sgst_percent' => $item['sgst_percent'] ?? 0.00,
                    'igst_percent' => $item['igst_percent'] ?? 0.00,
                    'cgst_amount' => $item['cgst_amount'] ?? 0.00,
                    'sgst_amount' => $item['sgst_amount'] ?? 0.00,
                    'igst_amount' => $item['igst_amount'] ?? 0.00,
                    'tax_amount' => $item['tax_amount'] ?? 0.00,
                    'total_amount' => $item['total_amount'] ?? $amount,
                ]);
            }

            // Update RFQ status to Confirmed
            $rfq->update(['status' => 'Confirmed']);

            return redirect()->route('purchase.rfqs.index')
                ->with('success', "Purchase Order {$poNumber} created from RFQ successfully.");
        });
    }

    public function savingsDashboard(Request $request)
    {
        $tenantId = require_tenant_id();
        $user = auth()->user();

        // Check if user is Admin / Super Admin / Tenant Owner
        $isAdmin = in_array($user->role ?? '', ['admin', 'super_admin', 'tenant_owner', 'company_admin']);

        // Query Purchase Orders sourced from RFQ
        $poQuery = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)
            ->where('source_type', 'rfq')
            ->with(['vendor', 'creator', 'items.product', 'requisition']);

        // Role-based scoping: non-admins only see their own POs
        if (! $isAdmin) {
            $poQuery->where('created_by', $user->id);
        } elseif ($request->filled('purchaser_id')) {
            $poQuery->where('created_by', $request->input('purchaser_id'));
        }

        // Apply Filters
        if ($request->filled('date_from')) {
            $poQuery->whereDate('date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $poQuery->whereDate('date', '<=', $request->input('date_to'));
        }
        if ($request->filled('vendor_id')) {
            $poQuery->where('vendor_id', $request->input('vendor_id'));
        }
        if ($request->filled('po_number')) {
            $poQuery->where('purchase_order_number', 'like', '%' . $request->input('po_number') . '%');
        }
        if ($request->filled('rfq_number')) {
            $searchRfq = $request->input('rfq_number');
            $poQuery->where('reference', 'like', '%' . $searchRfq . '%');
        }
        if ($request->filled('product_id')) {
            $productId = $request->input('product_id');
            $poQuery->whereHas('items', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            });
        }
        if ($request->filled('department')) {
            $dept = $request->input('department');
            $poQuery->whereHas('requisition', function ($q) use ($dept) {
                $q->where('department', 'like', '%' . $dept . '%');
            });
        }

        $allOrders = $poQuery->orderBy('id', 'desc')->get();

        // ── Data Processing & Calculations ──
        $processedOrders = [];
        $totalSavings = 0;
        $totalSpend = 0;
        $highestSingleSavings = 0;
        $bestPurchaserName = 'N/A';
        $purchaserSavings = [];
        $deptSavings = [];
        $vendorStats = [];
        $monthlySavings = array_fill(1, 12, 0);

        foreach ($allOrders as $order) {
            $poTotal = (float) $order->grand_total;
            $totalSpend += $poTotal;

            $rfqNumber = $order->reference ? str_replace('RFQ: ', '', $order->reference) : null;

            $rfq = null;
            if ($rfqNumber) {
                $rfq = PurchaseRfq::where('tenant_id', $tenantId)
                    ->where('rfq_number', $rfqNumber)
                    ->with('rfqVendors.rates')
                    ->first();
            }

            $poHighestQuoteTotal = 0;
            $poSavings = 0;

            foreach ($order->items as $item) {
                $qty = (float) $item->quantity;
                $poRate = (float) $item->rate;

                // Find highest quoted rate across all vendor rate submissions for this item in RFQ
                $highestRate = $poRate;
                if ($rfq) {
                    $allVendorRates = [];
                    foreach ($rfq->rfqVendors as $rv) {
                        foreach ($rv->rates as $vRate) {
                            if ((int)$vRate->product_id === (int)$item->product_id && (float)$vRate->rate > 0) {
                                $allVendorRates[] = (float)$vRate->rate;
                            }
                        }
                    }
                    if (! empty($allVendorRates)) {
                        $highestRate = max($allVendorRates);
                    }
                }

                if ($highestRate <= $poRate && $item->product?->estimated_cost > $poRate) {
                    $highestRate = (float) $item->product->estimated_cost;
                }

                $itemHighestTotal = $highestRate * $qty;
                $poHighestQuoteTotal += $itemHighestTotal;
                $itemSavings = max(0, $itemHighestTotal - ($poRate * $qty));
                $poSavings += $itemSavings;
            }

            $totalSavings += $poSavings;
            if ($poSavings > $highestSingleSavings) {
                $highestSingleSavings = $poSavings;
            }

            $savingPercent = $poHighestQuoteTotal > 0 ? ($poSavings / $poHighestQuoteTotal) * 100 : 0;

            // Group by Purchaser
            $creatorId = $order->created_by ?: 0;
            $creatorName = $order->creator?->name ?? 'System User';
            if (! isset($purchaserSavings[$creatorId])) {
                $purchaserSavings[$creatorId] = [
                    'id' => $creatorId,
                    'name' => $creatorName,
                    'po_count' => 0,
                    'total_spend' => 0,
                    'total_savings' => 0,
                ];
            }
            $purchaserSavings[$creatorId]['po_count']++;
            $purchaserSavings[$creatorId]['total_spend'] += $poTotal;
            $purchaserSavings[$creatorId]['total_savings'] += $poSavings;

            // Group by Department
            $deptName = $order->requisition?->department ?? 'General Procurement';
            if (! isset($deptSavings[$deptName])) {
                $deptSavings[$deptName] = [
                    'department' => $deptName,
                    'total_spend' => 0,
                    'total_savings' => 0,
                ];
            }
            $deptSavings[$deptName]['total_spend'] += $poTotal;
            $deptSavings[$deptName]['total_savings'] += $poSavings;

            // Group by Vendor
            $vId = $order->vendor_id;
            $vName = $order->vendor?->name ?? 'Unknown Vendor';
            if (! isset($vendorStats[$vId])) {
                $vendorStats[$vId] = [
                    'vendor_id' => $vId,
                    'name' => $vName,
                    'rfqs_won' => 0,
                    'total_spend' => 0,
                    'total_savings' => 0,
                ];
            }
            $vendorStats[$vId]['rfqs_won']++;
            $vendorStats[$vId]['total_spend'] += $poTotal;
            $vendorStats[$vId]['total_savings'] += $poSavings;

            // Monthly breakdown
            $monthNum = (int) ($order->date ? $order->date->format('n') : now()->format('n'));
            $monthlySavings[$monthNum] += $poSavings;

            $processedOrders[] = [
                'id' => $order->id,
                'order' => $order,
                'po_number' => $order->purchase_order_number,
                'rfq_number' => $rfqNumber ?: ($rfq?->rfq_number ?? '—'),
                'supplier_quotation_number' => $order->supplier_quotation_number ?: '—',
                'purchaser_name' => $creatorName,
                'vendor_name' => $vName,
                'po_amount' => $poTotal,
                'highest_quote_amount' => $poHighestQuoteTotal,
                'savings_amount' => $poSavings,
                'savings_percent' => round($savingPercent, 2),
                'status' => $order->status,
                'date' => $order->date ? $order->date->format('d-M-Y') : '—',
            ];
        }

        // Leaderboard sorting
        uasort($purchaserSavings, fn($a, $b) => $b['total_savings'] <=> $a['total_savings']);
        $topPurchaser = reset($purchaserSavings);
        if ($topPurchaser && $topPurchaser['total_savings'] > 0) {
            $bestPurchaserName = $topPurchaser['name'];
        }

        uasort($vendorStats, fn($a, $b) => $b['total_savings'] <=> $a['total_savings']);
        uasort($deptSavings, fn($a, $b) => $b['total_savings'] <=> $a['total_savings']);

        $avgSavingPercent = ($totalSpend + $totalSavings) > 0 ? ($totalSavings / ($totalSpend + $totalSavings)) * 100 : 0;

        // Fetch filter dropdown lists
        $allPurchasers = \App\Models\User::where('tenant_id', $tenantId)->get(['id', 'name']);
        $allVendors = \App\Domains\Inventory\Models\Vendor::where('tenant_id', $tenantId)->get(['id', 'name']);
        $allProducts = \App\Domains\Inventory\Models\Product::where('tenant_id', $tenantId)->get(['id', 'name', 'sku']);

        return view('modules.purchase.rfqs.savings-dashboard', compact(
            'isAdmin',
            'processedOrders',
            'totalSavings',
            'totalSpend',
            'highestSingleSavings',
            'bestPurchaserName',
            'avgSavingPercent',
            'purchaserSavings',
            'deptSavings',
            'vendorStats',
            'monthlySavings',
            'allPurchasers',
            'allVendors',
            'allProducts'
        ));
    }

    public function poSavingsDetails(int $orderId)
    {
        $tenantId = require_tenant_id();
        $order = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['vendor', 'creator', 'items.product', 'requisition'])
            ->findOrFail($orderId);

        $rfqNumber = $order->reference ? str_replace('RFQ: ', '', $order->reference) : null;

        $rfq = null;
        if ($rfqNumber) {
            $rfq = PurchaseRfq::where('tenant_id', $tenantId)
                ->where('rfq_number', $rfqNumber)
                ->with('rfqVendors.rates')
                ->first();
        }

        $itemDetails = [];
        $totalHighest = 0;
        $totalSelected = 0;

        foreach ($order->items as $item) {
            $qty = (float) $item->quantity;
            $selectedRate = (float) $item->rate;
            $selectedTotal = (float) $item->total_amount;

            $highestRate = $selectedRate;
            if ($rfq) {
                $allVendorRates = [];
                foreach ($rfq->rfqVendors as $rv) {
                    foreach ($rv->rates as $vRate) {
                        if ((int)$vRate->product_id === (int)$item->product_id && (float)$vRate->rate > 0) {
                            $allVendorRates[] = (float)$vRate->rate;
                        }
                    }
                }
                if (! empty($allVendorRates)) {
                    $highestRate = max($allVendorRates);
                }
            }

            $itemHighestTotal = $highestRate * $qty;
            $itemSavings = max(0, $itemHighestTotal - $selectedTotal);

            $totalHighest += $itemHighestTotal;
            $totalSelected += $selectedTotal;

            $itemDetails[] = [
                'product_name' => $item->product?->name ?? 'Item #' . $item->product_id,
                'sku' => $item->product?->sku ?? '—',
                'highest_rate' => $highestRate,
                'selected_rate' => $selectedRate,
                'quantity' => $qty,
                'savings' => $itemSavings,
            ];
        }

        $netSavings = max(0, $totalHighest - $totalSelected);
        $savingsPercent = $totalHighest > 0 ? ($netSavings / $totalHighest) * 100 : 0;

        return response()->json([
            'success' => true,
            'po_number' => $order->purchase_order_number,
            'rfq_number' => $rfqNumber ?: '—',
            'supplier_quotation_number' => $order->supplier_quotation_number ?: '—',
            'vendor_name' => $order->vendor?->name ?? '—',
            'purchaser_name' => $order->creator?->name ?? 'System',
            'approved_by' => $order->creator?->name ?? 'System Admin',
            'total_amount' => $order->grand_total,
            'highest_quote_total' => $totalHighest,
            'net_savings' => $netSavings,
            'savings_percent' => round($savingsPercent, 2),
            'created_date' => $order->date ? $order->date->format('d M Y') : '—',
            'items' => $itemDetails,
        ]);
    }
}
