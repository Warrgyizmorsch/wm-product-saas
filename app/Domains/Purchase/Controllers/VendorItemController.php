<?php

declare(strict_types=1);

namespace App\Domains\Purchase\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\VendorItem;
use App\Domains\Purchase\Models\VendorItemPriceHistory;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controller for managing Vendor Supplied Items Catalog, Lead Time, MOQ & Price History (FRS 2.3 / Task C-19).
 */
class VendorItemController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
    ) {
    }

    /** @param  string|list<string>  $permissions  any one of these is enough */
    private function authorizeVendor(string|array $permissions): void
    {
        $user = auth()->user();

        abort_unless(
            $user && collect((array) $permissions)->contains(
                fn (string $permission) => $this->access->allows($user, $permission, ['tenant_id' => $user->tenant_id])
            ),
            403
        );
    }

    /**
     * GET /purchase/vendors/{vendor}/items/lookup
     * Returns JSON list of vendor items mapped to this supplier for PO/RFQ line autofill.
     */
    public function lookup(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorizeVendor(['purchase.vendors.view', 'purchase.orders.create', 'purchase.orders.edit']);
        $tenantId = require_tenant_id();

        $items = VendorItem::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendor->id)
            ->with(['product:id,name,sku,unit_cost,cost_price,selling_price,minimum_order_qty'])
            ->get()
            ->map(function (VendorItem $vi) {
                return [
                    'id' => $vi->id,
                    'product_id' => $vi->product_id,
                    'internal_sku' => $vi->product?->sku,
                    'internal_name' => $vi->product?->name,
                    'vendor_item_code' => $vi->vendor_item_code,
                    'vendor_item_name' => $vi->vendor_item_name,
                    'unit_price' => $vi->unit_price,
                    'moq' => (float) ($vi->moq ?? $vi->product?->minimum_order_qty ?? 1),
                    'lead_time_days' => (int) ($vi->lead_time_days ?? 0),
                    'is_preferred' => (bool) $vi->is_preferred,
                ];
            });

        return response()->json([
            'success' => true,
            'vendor_id' => $vendor->id,
            'items' => $items,
        ]);
    }

    /**
     * POST /purchase/vendors/{vendor}/items
     * Map a product to this vendor with vendor item code, MOQ, lead time, and rate.
     */
    public function store(Request $request, Vendor $vendor): RedirectResponse|JsonResponse
    {
        $this->authorizeVendor('purchase.vendors.edit');
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'vendor_item_code' => 'nullable|string|max:100',
            'vendor_item_name' => 'nullable|string|max:255',
            'unit_price' => 'nullable|numeric|min:0',
            'moq' => 'nullable|numeric|min:0.01',
            'lead_time_days' => 'nullable|integer|min:0|max:3650',
            'is_preferred' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        // A removed mapping is soft-deleted but still holds the (vendor, product) unique key,
        // so adding the product again brings that row back instead of inserting a new one.
        $existing = VendorItem::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $vendor->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing && ! $existing->trashed()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'This product is already mapped for this vendor.'], 422);
            }
            return back()->with('error', 'This product is already mapped for this vendor.');
        }

        $attributes = [
            'company_id' => $vendor->company_id,
            'branch_id' => $vendor->branch_id,
            'vendor_item_code' => $validated['vendor_item_code'] ?? null,
            'vendor_item_name' => $validated['vendor_item_name'] ?? null,
            'unit_price' => $validated['unit_price'] ?? null,
            'moq' => $validated['moq'] ?? 1.00,
            'lead_time_days' => $validated['lead_time_days'] ?? 0,
            'is_preferred' => (bool) ($validated['is_preferred'] ?? false),
            'notes' => $validated['notes'] ?? null,
        ];

        $oldPrice = $existing?->unit_price;

        if ($existing) {
            $existing->restore();
            $existing->update($attributes + ['updated_by' => auth()->id()]);
            $vendorItem = $existing;
        } else {
            $vendorItem = VendorItem::create($attributes + [
                'tenant_id' => $tenantId,
                'vendor_id' => $vendor->id,
                'product_id' => $validated['product_id'],
                'created_by' => auth()->id(),
            ]);
        }

        if (isset($validated['unit_price']) && ($oldPrice === null || (float) $validated['unit_price'] !== (float) $oldPrice)) {
            VendorItemPriceHistory::create([
                'tenant_id' => $tenantId,
                'vendor_item_id' => $vendorItem->id,
                'old_price' => $oldPrice,
                'new_price' => (float) $validated['unit_price'],
                'effective_from' => now()->toDateString(),
                'reason' => $existing ? 'Rate set when the item was mapped again' : 'Initial contracted rate setup',
                'approved_by' => auth()->id(),
                'created_by' => auth()->id(),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'item' => $vendorItem]);
        }

        return back()->with('success', 'Supplied item mapped successfully.');
    }

    /**
     * PUT /purchase/vendors/{vendor}/items/{vendorItem}
     * Update mapping, lead time, MOQ, or rate with price history logging.
     */
    public function update(Request $request, Vendor $vendor, VendorItem $vendorItem): RedirectResponse|JsonResponse
    {
        $this->authorizeVendor('purchase.vendors.edit');
        $tenantId = require_tenant_id();

        if ((int) $vendorItem->vendor_id !== (int) $vendor->id || (int) $vendorItem->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        $validated = $request->validate([
            'vendor_item_code' => 'nullable|string|max:100',
            'vendor_item_name' => 'nullable|string|max:255',
            'unit_price' => 'nullable|numeric|min:0',
            'moq' => 'nullable|numeric|min:0.01',
            'lead_time_days' => 'nullable|integer|min:0|max:3650',
            'is_preferred' => 'nullable|boolean',
            'price_change_reason' => 'nullable|string|max:255',
            'effective_from' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $oldPrice = $vendorItem->unit_price;
        // A blank rate means "no change": the history table cannot record a rate with no value.
        $newPrice = isset($validated['unit_price']) && $validated['unit_price'] !== '' ? (float) $validated['unit_price'] : $oldPrice;

        if ($newPrice !== null && ($oldPrice === null || (float) $oldPrice !== (float) $newPrice)) {
            VendorItemPriceHistory::create([
                'tenant_id' => $tenantId,
                'vendor_item_id' => $vendorItem->id,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'effective_from' => $validated['effective_from'] ?? now()->toDateString(),
                'reason' => $validated['price_change_reason'] ?: 'Rate update',
                'approved_by' => auth()->id(),
                'created_by' => auth()->id(),
            ]);
        }

        // Fields left out of the request keep their saved value instead of being reset.
        $vendorItem->update([
            'vendor_item_code' => array_key_exists('vendor_item_code', $validated) ? $validated['vendor_item_code'] : $vendorItem->vendor_item_code,
            'vendor_item_name' => array_key_exists('vendor_item_name', $validated) ? $validated['vendor_item_name'] : $vendorItem->vendor_item_name,
            'unit_price' => $newPrice,
            'moq' => $validated['moq'] ?? $vendorItem->moq,
            'lead_time_days' => $validated['lead_time_days'] ?? $vendorItem->lead_time_days,
            'is_preferred' => (bool) ($validated['is_preferred'] ?? false),
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $vendorItem->notes,
            'updated_by' => auth()->id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'item' => $vendorItem]);
        }

        return back()->with('success', 'Supplied item updated successfully.');
    }

    /**
     * DELETE /purchase/vendors/{vendor}/items/{vendorItem}
     */
    public function destroy(Request $request, Vendor $vendor, VendorItem $vendorItem): RedirectResponse|JsonResponse
    {
        $this->authorizeVendor('purchase.vendors.edit');
        $tenantId = require_tenant_id();

        if ((int) $vendorItem->vendor_id !== (int) $vendor->id || (int) $vendorItem->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        $vendorItem->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Supplied item mapping removed.');
    }

    /**
     * GET /purchase/vendors/{vendor}/items/{vendorItem}/price-history
     */
    public function priceHistory(Request $request, Vendor $vendor, VendorItem $vendorItem): JsonResponse
    {
        $this->authorizeVendor('purchase.vendors.view');
        $tenantId = require_tenant_id();

        if ((int) $vendorItem->vendor_id !== (int) $vendor->id || (int) $vendorItem->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        $history = $vendorItem->priceHistories()
            ->with(['approver:id,name', 'creator:id,name'])
            ->get()
            ->map(function (VendorItemPriceHistory $h) {
                return [
                    'id' => $h->id,
                    'old_price' => $h->old_price,
                    'new_price' => $h->new_price,
                    'effective_from' => $h->effective_from?->format('d-M-Y'),
                    'reason' => $h->reason ?: '—',
                    'approved_by' => $h->approver?->name ?: 'System',
                    'created_at' => $h->created_at?->format('d-M-Y H:i'),
                ];
            });

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }
}
