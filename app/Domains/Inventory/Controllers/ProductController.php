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
        $this->authorize('viewAny', Product::class);

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

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['name', 'sku', 'selling_price', 'cost_price', 'status'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $products = $query->paginate(10)->withQueryString();

        return view('modules.inventory.products.index', compact('products'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        $uoms = Uom::query()->get();
        $vendors = Vendor::query()->where('status', 'active')->get();
        $warehouses = Warehouse::query()->where('status', 'active')->get();

        return view('modules.inventory.products.create', compact('uoms', 'vendors', 'warehouses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'item_type' => 'required|in:Goods,Service',
            'supplier_method' => 'nullable|in:buy,manufacture',
            'type' => 'required|in:finished_good,semi_finished,raw_material,component,service',
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
            'inventory_valuation_method' => 'nullable|string|in:FIFO,Weighted Average',

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
                'type' => $validated['type'],
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
                'inventory_valuation_method' => $validated['inventory_valuation_method'] ?? 'FIFO',
                'attributes_config' => $validated['attributes'] ?? null,
                'supplier_method' => $validated['supplier_method'] ?? 'buy',
            ]);

            if ($validated['variation_type'] === 'Single') {
                // Save stock per warehouse for Single product
                if (!empty($validated['warehouse_stocks'])) {
                    foreach ($validated['warehouse_stocks'] as $whId => $stockData) {
                        $qty = (float)($stockData['quantity'] ?? 0);
                        $cost = (float)($stockData['unit_cost'] ?? 0);
                        if ($qty > 0) {
                            \App\Domains\Inventory\Services\StockService::recordInflow(
                                $tenantId,
                                $parentProduct->id,
                                $whId,
                                $qty,
                                $cost > 0 ? $cost : $parentProduct->cost_price,
                                'Opening Stock'
                            );
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
                            'type' => $parentProduct->type,
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
                            'supplier_method' => $parentProduct->supplier_method,
                        ]);

                        // Save Variant Opening Stock to the default warehouse
                        $openingQty = (float)($vData['opening_stock'] ?? 0);
                        if ($openingQty > 0 && $defaultWarehouse) {
                            \App\Domains\Inventory\Services\StockService::recordInflow(
                                $tenantId,
                                $variantProduct->id,
                                $defaultWarehouse->id,
                                $openingQty,
                                $variantProduct->cost_price,
                                'Opening Stock'
                            );
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
        $this->authorize('view', $product);

        $product->load([
            'uom', 
            'vendor', 
            'warehouseStocks.warehouse', 
            'variants.warehouseStocks.warehouse',
            'stockTransactions.warehouse',
            'serialNumbers.warehouse',
            'serialNumbers.batch',
            'serialNumbers.transactionIn',
            'serialNumbers.transactionOut',
            'batches.stockTransactions',
            'stockReservations.warehouse'
        ]);
        
        $warehouses = Warehouse::query()->where('status', 'active')->get();

        return view('modules.inventory.products.show', compact('product', 'warehouses'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

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
        $this->authorize('update', $product);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:finished_good,semi_finished,raw_material,component,service',
            'supplier_method' => 'nullable|in:buy,manufacture',
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
            'inventory_valuation_method' => 'nullable|string|in:FIFO,Weighted Average',

            'warehouse_stocks' => 'nullable|array',
            'status' => 'required|in:active,inactive',
        ]);

        DB::transaction(function () use ($validated, $product, $tenantId) {
            $product->update([
                'name' => $validated['name'],
                'type' => $validated['type'],
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
                'inventory_valuation_method' => $validated['inventory_valuation_method'] ?? 'FIFO',
                'supplier_method' => $validated['supplier_method'] ?? 'buy',
            ]);

            // Sync type and supplier method to variants
            Product::where('parent_id', $product->id)->update([
                'type' => $validated['type'],
                'supplier_method' => $validated['supplier_method'] ?? 'buy'
            ]);

            if ($product->variation_type === 'Single') {
                // Update stock per warehouse
                if (!empty($validated['warehouse_stocks'])) {
                    foreach ($validated['warehouse_stocks'] as $whId => $stockData) {
                        $qty = (float)($stockData['quantity'] ?? 0);
                        $cost = (float)($stockData['unit_cost'] ?? 0);

                        $oldStock = ProductWarehouseStock::query()
                            ->where('tenant_id', $tenantId)
                            ->where('product_id', $product->id)
                            ->where('warehouse_id', $whId)
                            ->first();

                        $oldQty = $oldStock ? (float)$oldStock->quantity : 0.0;
                        $rate = $cost > 0 ? $cost : $product->cost_price;

                        if ($qty != $oldQty) {
                            if ($qty > $oldQty) {
                                $diff = $qty - $oldQty;
                                \App\Domains\Inventory\Services\StockService::recordInflow(
                                    $tenantId,
                                    $product->id,
                                    $whId,
                                    $diff,
                                    $rate,
                                    'Adjustment'
                                );
                            } else {
                                $diff = $oldQty - $qty;
                                \App\Domains\Inventory\Services\StockService::recordOutflow(
                                    $tenantId,
                                    $product->id,
                                    $whId,
                                    $diff,
                                    'Adjustment'
                                );
                            }
                        }
                    }
                }
            }
        });

        return redirect()->route('inventory.products.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Show the Opening Stock entry form for a product (single & variant-aware).
     */
    public function openingStock(Product $product): View
    {
        $this->authorize('update', $product);

        $product->load(['uom', 'warehouseStocks.warehouse', 'variants.warehouseStocks.warehouse']);
        $warehouses = Warehouse::query()->where('status', 'active')->get();

        // For Single: map warehouse_id => {quantity, unit_cost}
        $stockMap = $product->warehouseStocks->keyBy('warehouse_id')->map(fn($ws) => [
            'quantity'  => $ws->quantity,
            'unit_cost' => $ws->unit_cost,
        ])->toArray();

        // For Variants: map variant_id => warehouse_id => {quantity, unit_cost}
        $variantStockMap = [];
        foreach ($product->variants as $variant) {
            $variantStockMap[$variant->id] = $variant->warehouseStocks->keyBy('warehouse_id')->map(fn($ws) => [
                'quantity'  => $ws->quantity,
                'unit_cost' => $ws->unit_cost,
            ])->toArray();
        }

        return view('modules.inventory.products.opening-stock',
            compact('product', 'warehouses', 'stockMap', 'variantStockMap'));
    }

    /**
     * Save / Update Opening Stock (single & variant-aware).
     */
    public function saveOpeningStock(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $request->validate([
            'warehouse_stocks'                          => 'nullable|array',
            'warehouse_stocks.*.quantity'               => 'nullable|numeric|min:0',
            'warehouse_stocks.*.unit_cost'              => 'nullable|numeric|min:0',
            'warehouse_stocks.*.batch_number'           => 'nullable|string',
            'warehouse_stocks.*.serial_numbers'         => 'nullable|string',
            'variant_stocks'                            => 'nullable|array',
            'variant_stocks.*.*.quantity'               => 'nullable|numeric|min:0',
            'variant_stocks.*.*.unit_cost'              => 'nullable|numeric|min:0',
            'variant_stocks.*.*.batch_number'           => 'nullable|string',
            'variant_stocks.*.*.serial_numbers'         => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $product, $tenantId) {

            if ($product->variation_type === 'Variant') {
                // variant_stocks[variant_id][warehouse_id][quantity/unit_cost]
                $variantStocks = $request->input('variant_stocks', []);
                foreach ($variantStocks as $variantId => $whData) {
                    // Ensure variant belongs to this parent product
                    $variant = $product->variants->firstWhere('id', $variantId);
                    if (!$variant) continue;

                    foreach ($whData as $warehouseId => $data) {
                        $qty  = (float)($data['quantity']  ?? 0);
                        $cost = (float)($data['unit_cost'] ?? 0);
                        $batchNumber = $data['batch_number'] ?? null;
                        $snRaw = $data['serial_numbers'] ?? '';
                        $serialNumbers = array_filter(array_map('trim', explode(',', $snRaw)));

                        $stock = ProductWarehouseStock::query()
                            ->where('tenant_id', $tenantId)
                            ->where('product_id', $variantId)
                            ->where('warehouse_id', $warehouseId)
                            ->first();

                        $oldQty = $stock ? (float)$stock->quantity : 0.0;
                        $rate = $cost > 0 ? $cost : $variant->cost_price;

                        if ($qty != $oldQty) {
                            if ($qty > $oldQty) {
                                $diff = $qty - $oldQty;
                                \App\Domains\Inventory\Services\StockService::recordInflow(
                                    $tenantId,
                                    $variantId,
                                    $warehouseId,
                                    $diff,
                                    $rate,
                                    'Opening Stock',
                                    null,
                                    $batchNumber,
                                    $serialNumbers
                                );
                            } else {
                                $diff = $oldQty - $qty;
                                \App\Domains\Inventory\Services\StockService::recordOutflow(
                                    $tenantId,
                                    $variantId,
                                    $warehouseId,
                                    $diff,
                                    'Adjustment',
                                    null,
                                    $serialNumbers
                                );
                            }
                        }
                    }
                }
            } else {
                // Single product: warehouse_stocks[warehouse_id][quantity/unit_cost]
                $stocks = $request->input('warehouse_stocks', []);
                foreach ($stocks as $warehouseId => $data) {
                    $qty  = (float)($data['quantity']  ?? 0);
                    $cost = (float)($data['unit_cost'] ?? 0);
                    $batchNumber = $data['batch_number'] ?? null;
                    $snRaw = $data['serial_numbers'] ?? '';
                    $serialNumbers = array_filter(array_map('trim', explode(',', $snRaw)));

                    $stock = ProductWarehouseStock::query()
                        ->where('tenant_id', $tenantId)
                        ->where('product_id', $product->id)
                        ->where('warehouse_id', $warehouseId)
                        ->first();

                    $oldQty = $stock ? (float)$stock->quantity : 0.0;
                    $rate = $cost > 0 ? $cost : $product->cost_price;

                    if ($qty != $oldQty) {
                        if ($qty > $oldQty) {
                            $diff = $qty - $oldQty;
                            \App\Domains\Inventory\Services\StockService::recordInflow(
                                $tenantId,
                                $product->id,
                                $warehouseId,
                                $diff,
                                $rate,
                                'Opening Stock',
                                null,
                                $batchNumber,
                                $serialNumbers
                            );
                        } else {
                            $diff = $oldQty - $qty;
                            \App\Domains\Inventory\Services\StockService::recordOutflow(
                                $tenantId,
                                $product->id,
                                $warehouseId,
                                $diff,
                                'Adjustment',
                                null,
                                $serialNumbers
                            );
                        }
                    }
                }
            }
        });

        return redirect()->route('inventory.products.show', $product)
            ->with('success', 'Opening stock updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('inventory.products.index')
            ->with('success', 'Product deleted successfully.');
    }


    public function quickCreate(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

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
            'type' => 'required|in:finished_good,semi_finished,raw_material,component,service',
            'supplier_method' => 'required|in:buy,manufacture',
            'uom_id' => 'required|exists:uoms,id',
            'inventory_valuation_method' => 'required|in:FIFO,Weighted Average',
            'unit_cost' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'sales_account' => 'required|string|max:255',
            'purchase_account' => 'required|string|max:255',
            'inventory_account' => 'required|string|max:255',
            'preferred_vendor_id' => 'nullable|exists:vendors,id',
        ]);

        $product = Product::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'type' => $validated['type'],
            'supplier_method' => $validated['supplier_method'],
            'uom_id' => $validated['uom_id'],
            'inventory_valuation_method' => $validated['inventory_valuation_method'],
            'unit_cost' => $validated['unit_cost'] ?? 0.0,
            'selling_price' => $validated['selling_price'] ?? 0.0,
            'cost_price' => $validated['unit_cost'] ?? 0.0,
            'sales_account' => $validated['sales_account'],
            'purchase_account' => $validated['purchase_account'],
            'inventory_account' => $validated['inventory_account'],
            'preferred_vendor_id' => $validated['preferred_vendor_id'],
            'status' => 'active',
            'planning_type' => $validated['supplier_method'] === 'manufacture' ? 'manufacture' : 'purchase',
            'variation_type' => 'Single',
        ]);

        return response()->json([
            'id'   => $product->id,
            'name' => $product->name,
            'type' => $product->type,
        ]);
    }
}
