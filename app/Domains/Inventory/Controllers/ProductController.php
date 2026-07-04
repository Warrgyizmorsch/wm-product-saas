<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->whereNull('parent_id')->with(['uom', 'variants']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('item_type')) {
            $query->where('item_type', $request->input('item_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $products = $query->latest()->paginate(15);

        return view('modules.inventory.products.index', compact('products'));
    }

    public function create(): View
    {
        $uoms = Uom::query()->get();
        $vendors = Vendor::query()->where('status', 'active')->get();
        $warehouses = Warehouse::query()->where('status', 'active')->get();

        return view('modules.inventory.products.create', compact('uoms', 'vendors', 'warehouses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'item_type' => 'required|in:Goods,Service',
            'variation_type' => 'required|in:Single,Variant',
            'sku' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($tenantId, $request) {
                    if ($request->input('variation_type') === 'Single' && empty($value)) {
                        $fail("The SKU field is required for single items.");
                    }
                    if (!empty($value) && Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sku', $value)->exists()) {
                        $fail("The SKU '{$value}' has already been taken.");
                    }
                }
            ],
            'uom_id' => 'nullable|exists:uoms,id',
            'hsn_sac' => 'nullable|string|max:50',
            'gst_rate' => 'nullable|numeric|min:0|max:100',
            'preferred_vendor_id' => 'nullable|exists:vendors,id',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'sales_account' => 'nullable|string|max:255',
            'purchase_account' => 'nullable|string|max:255',
            'inventory_account' => 'nullable|string|max:255',
            'reorder_point' => 'nullable|numeric|min:0',
            'opening_stock' => 'nullable|numeric|min:0',
            'opening_stock_rate' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            
            // Brand & Identifiers
            'brand' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'mpn' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'upc' => 'nullable|string|max:255',
            'ean' => 'nullable|string|max:255',
            'isbn' => 'nullable|string|max:255',

            // Dimensions & Weight
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'dimension_unit' => 'nullable|string|in:cm,in,mm,m',
            'weight_unit' => 'nullable|string|in:kg,g,lb,oz',

            // Tracking
            'track_serial_number' => 'nullable|boolean',
            'track_batch' => 'nullable|boolean',

            // Attributes config for variants
            'attributes' => 'nullable|array',
            'variants' => 'nullable|array',
            'warehouse_stocks' => 'nullable|array',
        ]);

        DB::transaction(function () use ($validated, $tenantId) {
            // 1. Create Parent Product
            $parentProduct = Product::create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'sku' => $validated['variation_type'] === 'Single' ? $validated['sku'] : ($validated['sku'] ?? strtoupper($validated['name'] . '-VAR')),
                'type' => 'component', // default type
                'item_type' => $validated['item_type'],
                'variation_type' => $validated['variation_type'],
                'uom_id' => $validated['uom_id'],
                'status' => 'active',
                'hsn_sac' => $validated['hsn_sac'],
                'gst_rate' => $validated['gst_rate'] ?? 18.00,
                'preferred_vendor_id' => $validated['preferred_vendor_id'],
                'selling_price' => $validated['selling_price'],
                'cost_price' => $validated['cost_price'],
                'unit_cost' => $validated['cost_price'],
                'sales_account' => $validated['sales_account'],
                'purchase_account' => $validated['purchase_account'],
                'inventory_account' => $validated['inventory_account'],
                'reorder_point' => $validated['reorder_point'] ?? 0,
                'opening_stock' => $validated['variation_type'] === 'Single' ? ($validated['opening_stock'] ?? 0) : 0,
                'opening_stock_rate' => $validated['variation_type'] === 'Single' ? ($validated['opening_stock_rate'] ?? 0) : 0,
                'description' => $validated['description'],
                'brand' => $validated['brand'],
                'manufacturer' => $validated['manufacturer'],
                'mpn' => $validated['mpn'],
                'barcode' => $validated['barcode'],
                'upc' => $validated['upc'],
                'ean' => $validated['ean'],
                'isbn' => $validated['isbn'],
                'length' => $validated['length'],
                'width' => $validated['width'],
                'height' => $validated['height'],
                'weight' => $validated['weight'],
                'dimension_unit' => $validated['dimension_unit'],
                'weight_unit' => $validated['weight_unit'],
                'track_serial_number' => !empty($validated['track_serial_number']),
                'track_batch' => !empty($validated['track_batch']),
                'attributes_config' => $validated['attributes'] ?? null,
            ]);

            if ($validated['variation_type'] === 'Single') {
                // Save stock per warehouse for Single product
                if (!empty($validated['warehouse_stocks'])) {
                    foreach ($validated['warehouse_stocks'] as $whId => $stockData) {
                        $qty = (float)($stockData['quantity'] ?? 0);
                        $cost = (float)($stockData['unit_cost'] ?? 0);
                        if ($qty > 0) {
                            ProductWarehouseStock::create([
                                'tenant_id' => $tenantId,
                                'product_id' => $parentProduct->id,
                                'warehouse_id' => $whId,
                                'quantity' => $qty,
                                'unit_cost' => $cost > 0 ? $cost : $parentProduct->cost_price,
                            ]);
                        }
                    }
                }
            } else {
                // Save Variants
                if (!empty($validated['variants'])) {
                    $defaultWarehouse = Warehouse::query()->where('is_default', true)->first() ?? Warehouse::query()->first();

                    foreach ($validated['variants'] as $vData) {
                        // Create Variant Product
                        $variantProduct = Product::create([
                            'tenant_id' => $tenantId,
                            'parent_id' => $parentProduct->id,
                            'name' => $parentProduct->name . ' (' . ($vData['attributes'] ?? '') . ')',
                            'sku' => $vData['sku'],
                            'type' => 'component',
                            'item_type' => $parentProduct->item_type,
                            'variation_type' => 'Single',
                            'uom_id' => $parentProduct->uom_id,
                            'status' => 'active',
                            'selling_price' => $vData['selling_price'] ?? $parentProduct->selling_price,
                            'cost_price' => $vData['cost_price'] ?? $parentProduct->cost_price,
                            'unit_cost' => $vData['cost_price'] ?? $parentProduct->cost_price,
                            'reorder_point' => $vData['reorder_point'] ?? 0,
                            'opening_stock' => $vData['opening_stock'] ?? 0,
                            'opening_stock_rate' => $vData['cost_price'] ?? $parentProduct->cost_price,
                            'variant_values' => ['label' => $vData['attributes'] ?? ''],
                            'track_serial_number' => $parentProduct->track_serial_number,
                            'track_batch' => $parentProduct->track_batch,
                        ]);

                        // Save Variant Opening Stock to the default warehouse
                        $openingQty = (float)($vData['opening_stock'] ?? 0);
                        if ($openingQty > 0 && $defaultWarehouse) {
                            ProductWarehouseStock::create([
                                'tenant_id' => $tenantId,
                                'product_id' => $variantProduct->id,
                                'warehouse_id' => $defaultWarehouse->id,
                                'quantity' => $openingQty,
                                'unit_cost' => $variantProduct->cost_price,
                            ]);
                        }
                    }
                }
            }
        });

        return redirect()->route('inventory.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        $product->load(['uom', 'vendor', 'warehouseStocks.warehouse', 'variants.warehouseStocks.warehouse']);
        
        $warehouses = Warehouse::query()->where('status', 'active')->get();

        return view('modules.inventory.products.show', compact('product', 'warehouses'));
    }

    public function edit(Product $product): View
    {
        $product->load(['uom', 'vendor', 'warehouseStocks']);
        $uoms = Uom::query()->get();
        $vendors = Vendor::query()->where('status', 'active')->get();
        $warehouses = Warehouse::query()->where('status', 'active')->get();

        // Get stock maps
        $warehouseStocksMap = $product->warehouseStocks->pluck('quantity', 'warehouse_id')->toArray();
        $warehouseCostsMap = $product->warehouseStocks->pluck('unit_cost', 'warehouse_id')->toArray();

        return view('modules.inventory.products.edit', compact('product', 'uoms', 'vendors', 'warehouses', 'warehouseStocksMap', 'warehouseCostsMap'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')
                    ->where(fn($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($product->id)
            ],
            'uom_id' => 'nullable|exists:uoms,id',
            'hsn_sac' => 'nullable|string|max:50',
            'gst_rate' => 'nullable|numeric|min:0|max:100',
            'preferred_vendor_id' => 'nullable|exists:vendors,id',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'sales_account' => 'nullable|string|max:255',
            'purchase_account' => 'nullable|string|max:255',
            'inventory_account' => 'nullable|string|max:255',
            'reorder_point' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            
            // Brand & Identifiers
            'brand' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'mpn' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'upc' => 'nullable|string|max:255',
            'ean' => 'nullable|string|max:255',
            'isbn' => 'nullable|string|max:255',

            // Dimensions & Weight
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'dimension_unit' => 'nullable|string|in:cm,in,mm,m',
            'weight_unit' => 'nullable|string|in:kg,g,lb,oz',

            // Tracking
            'track_serial_number' => 'nullable|boolean',
            'track_batch' => 'nullable|boolean',

            'warehouse_stocks' => 'nullable|array',
            'status' => 'required|in:active,inactive',
        ]);

        DB::transaction(function () use ($validated, $product, $tenantId) {
            $product->update([
                'name' => $validated['name'],
                'sku' => $validated['sku'],
                'uom_id' => $validated['uom_id'],
                'status' => $validated['status'],
                'hsn_sac' => $validated['hsn_sac'],
                'gst_rate' => $validated['gst_rate'] ?? 18.00,
                'preferred_vendor_id' => $validated['preferred_vendor_id'],
                'selling_price' => $validated['selling_price'],
                'cost_price' => $validated['cost_price'],
                'unit_cost' => $validated['cost_price'],
                'sales_account' => $validated['sales_account'],
                'purchase_account' => $validated['purchase_account'],
                'inventory_account' => $validated['inventory_account'],
                'reorder_point' => $validated['reorder_point'] ?? 0,
                'description' => $validated['description'],
                'brand' => $validated['brand'],
                'manufacturer' => $validated['manufacturer'],
                'mpn' => $validated['mpn'],
                'barcode' => $validated['barcode'],
                'upc' => $validated['upc'],
                'ean' => $validated['ean'],
                'isbn' => $validated['isbn'],
                'length' => $validated['length'],
                'width' => $validated['width'],
                'height' => $validated['height'],
                'weight' => $validated['weight'],
                'dimension_unit' => $validated['dimension_unit'],
                'weight_unit' => $validated['weight_unit'],
                'track_serial_number' => !empty($validated['track_serial_number']),
                'track_batch' => !empty($validated['track_batch']),
            ]);

            if ($product->variation_type === 'Single') {
                // Update stock per warehouse
                if (!empty($validated['warehouse_stocks'])) {
                    foreach ($validated['warehouse_stocks'] as $whId => $stockData) {
                        $qty = (float)($stockData['quantity'] ?? 0);
                        $cost = (float)($stockData['unit_cost'] ?? 0);

                        ProductWarehouseStock::query()->updateOrCreate(
                            ['tenant_id' => $tenantId, 'product_id' => $product->id, 'warehouse_id' => $whId],
                            [
                                'quantity' => $qty,
                                'unit_cost' => $cost > 0 ? $cost : $product->cost_price,
                            ]
                        );
                    }
                }
            }
        });

        return redirect()->route('inventory.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('inventory.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if (Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sku', $value)->exists()) {
                        $fail("The SKU '{$value}' has already been taken.");
                    }
                }
            ],
            'type' => 'required|in:finished_good,semi_finished,raw_material,component',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $product = Product::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'type' => $validated['type'],
            'unit_cost' => $validated['unit_cost'] ?? 0.0,
            'status' => 'active',
            'selling_price' => $validated['unit_cost'] ?? 0.0,
            'cost_price' => $validated['unit_cost'] ?? 0.0,
        ]);

        return response()->json([
            'id'   => $product->id,
            'name' => $product->name,
            'type' => $product->type,
        ]);
    }
}
