<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\PurchaseRfq;
use App\Domains\Purchase\Models\PurchaseRfqItem;
use App\Domains\Purchase\Models\PurchaseRfqVendor;
use App\Domains\Purchase\Models\PurchaseRfqVendorRate;
use App\Domains\Purchase\Models\PurchaseRequisition;
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
        $query = PurchaseRfq::where('tenant_id', $tenantId)->with(['rfqVendors.vendor', 'requisition']);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('rfq_number', 'like', $search)
                  ->orWhereHas('rfqVendors.vendor', function ($v) use ($search) {
                      $v->where('name', 'like', $search);
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['id', 'rfq_number', 'rfq_date', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $rfqs = $query->paginate(10)->withQueryString();

        return view('modules.purchase.rfqs.index', compact('rfqs'));
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
        $prefilledItems = [];

        if ($selectedRequisitionId) {
            $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
                ->with('items.product')
                ->find($selectedRequisitionId);
            
            if ($requisition) {
                foreach ($requisition->items as $item) {
                    $prefilledItems[] = [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : ''),
                        'quantity' => $item->quantity,
                        'warehouse_id' => $item->warehouse_id,
                        'estimated_cost' => $item->estimated_cost,
                    ];
                }
            }
        }

        return view('modules.purchase.rfqs.create', compact('vendors', 'warehouses', 'products', 'requisitions', 'selectedRequisitionId', 'prefilledItems'));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $request->validate([
            'vendor_ids' => 'required|array|min:1',
            'vendor_ids.*' => 'exists:vendors,id',
            'rfq_date' => 'required|date',
            'purchase_requisition_id' => 'nullable|exists:purchase_requisitions,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'items.*.estimated_cost' => 'nullable|numeric|min:0',
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

            // Save Inquired Items
            foreach ($request->input('items') as $item) {
                PurchaseRfqItem::create([
                    'purchase_rfq_id' => $rfq->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'estimated_cost' => $item['estimated_cost'] ?? 0.00,
                ]);
            }

            // Bind Vendors with Unique Access Tokens
            foreach ($request->input('vendor_ids') as $vendorId) {
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
            ->with(['requisition', 'items.product', 'items.warehouse', 'rfqVendors.vendor', 'rfqVendors.rates.product'])
            ->findOrFail($id);

        return view('modules.purchase.rfqs.show', compact('rfq'));
    }

    public function edit(int $id)
    {
        $tenantId = require_tenant_id();
        $rfq = PurchaseRfq::where('tenant_id', $tenantId)->with(['items', 'rfqVendors'])->findOrFail($id);
        
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
            'vendor_ids' => 'required|array|min:1',
            'vendor_ids.*' => 'exists:vendors,id',
            'rfq_date' => 'required|date',
            'purchase_requisition_id' => 'nullable|exists:purchase_requisitions,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'items.*.estimated_cost' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request, $rfq, $tenantId) {
            $rfq->update([
                'purchase_requisition_id' => $request->input('purchase_requisition_id'),
                'rfq_date' => $request->input('rfq_date'),
                'notes' => $request->input('notes'),
            ]);

            // Re-sync items
            $rfq->items()->delete();
            foreach ($request->input('items') as $item) {
                PurchaseRfqItem::create([
                    'purchase_rfq_id' => $rfq->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'estimated_cost' => $item['estimated_cost'] ?? 0.00,
                ]);
            }

            // Sync vendors
            $existingVendors = $rfq->rfqVendors;
            $newVendorIds = $request->input('vendor_ids');

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
            $items[] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : ''),
                'warehouse_id' => $item->warehouse_id,
                'warehouse_name' => $item->warehouse->name ?? '—',
                'quantity' => (float)$item->quantity,
                'estimated_cost' => (float)$item->estimated_cost,
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

        // Map existing quoted rates if any
        $existingRates = $rfqVendor->rates->keyBy('product_id');

        return view('modules.purchase.rfqs.portal', compact('rfqVendor', 'rfq', 'vendor', 'existingRates'));
    }

    public function submitPortal(Request $request, string $token)
    {
        $rfqVendor = PurchaseRfqVendor::where('token', $token)
            ->with(['rfq.items'])
            ->firstOrFail();

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

        DB::transaction(function () use ($request, $rfqVendor) {
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

            // Save / Update Rates
            $rfqVendor->rates()->delete();
            foreach ($request->input('rates') as $quote) {
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

                        PurchaseRfqVendorRate::updateOrCreate([
                            'tenant_id' => $rfqVendor->tenant_id,
                            'purchase_rfq_vendor_id' => $rfqVendor->id,
                            'product_id' => $productId,
                        ], [
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
}
