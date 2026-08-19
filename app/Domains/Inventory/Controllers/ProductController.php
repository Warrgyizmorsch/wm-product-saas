<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Repositories\ProductRepository;
use App\Domains\Inventory\Repositories\WarehouseRepository;
use App\Domains\Inventory\Repositories\UomRepository;
use App\Domains\Inventory\Services\ProductService;
use App\Exports\ProductExport;
use App\Exports\ProductSampleExport;
use App\Imports\ProductImport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function __construct(
        protected ProductRepository $productRepo,
        protected ProductService $productService,
        protected WarehouseRepository $warehouseRepo,
        protected UomRepository $uomRepo
    ) {}

    public function downloadSample()
    {
        $this->authorize('viewAny', Product::class);
        return Excel::download(new ProductSampleExport, 'product_sample.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        try {
            Excel::import(new ProductImport, $request->file('file'));
            return redirect()->route('inventory.products.index')->with('success', 'Products imported successfully!');
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
            }
            return redirect()->route('inventory.products.index')->withErrors($errors);
        } catch (\Exception $e) {
            return redirect()->route('inventory.products.index')->withErrors(['file' => 'Failed to import file: ' . $e->getMessage()]);
        }
    }

    public function export()
    {
        $this->authorize('viewAny', Product::class);
        return Excel::download(new ProductExport, 'products_export.xlsx');
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);
        $products = $this->productRepo->getPaginatedProducts($request->all(), 10);
        return view('modules.inventory.products.index', compact('products'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        $uoms = $this->uomRepo->getAll();
        $vendors = Vendor::query()->where('status', 'active')->get();
        $warehouses = $this->warehouseRepo->getActive();
        $accountsData = $this->productRepo->getAccountsData();

        return view('modules.inventory.products.create', array_merge(
            compact('uoms', 'vendors', 'warehouses'),
            $accountsData
        ));
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
            'selling_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sales_account' => 'required|string|max:255',
            'purchase_account' => 'required|string|max:255',
            'inventory_account' => 'nullable|string|max:255',
            'reorder_point' => 'nullable|numeric|min:0',
            'opening_stock' => 'nullable|numeric|min:0',
            'opening_stock_rate' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'brand' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'mpn' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'upc' => 'nullable|string|max:255',
            'ean' => 'nullable|string|max:255',
            'isbn' => 'nullable|string|max:255',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'dimension_unit' => 'nullable|string|in:cm,in,mm,m',
            'weight_unit' => 'nullable|string|in:kg,g,lb,oz',
            'track_serial_number' => 'nullable|boolean',
            'track_batch' => 'nullable|boolean',
            'inventory_valuation_method' => 'nullable|string|in:FIFO,Weighted Average',
            'default_production_model' => 'nullable|string|in:pure_manufacturing,subcontract_complete,subcontract_company_material,hybrid',
            'attributes' => 'nullable|array',
            'variants' => 'nullable|array',
            'warehouse_stocks' => 'nullable|array',
        ]);

        $this->productService->createProduct($validated, $tenantId);

        return redirect()->route('inventory.products.index')->with('success', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);
        $product = $this->productRepo->findWithDetails($product);
        $warehouses = $this->warehouseRepo->getActive();

        return view('modules.inventory.products.show', compact('product', 'warehouses'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $product->load(['uom', 'vendor', 'warehouseStocks', 'variants.warehouseStocks']);
        $uoms = $this->uomRepo->getAll();
        $vendors = Vendor::query()->where('status', 'active')->get();
        $warehouses = $this->warehouseRepo->getActive();

        $warehouseStocksMap = $product->warehouseStocks->pluck('quantity', 'warehouse_id')->toArray();
        $warehouseCostsMap = $product->warehouseStocks->pluck('unit_cost', 'warehouse_id')->toArray();
        $accountsData = $this->productRepo->getAccountsData();

        return view('modules.inventory.products.edit', array_merge(
            compact('product', 'uoms', 'vendors', 'warehouses', 'warehouseStocksMap', 'warehouseCostsMap'),
            $accountsData
        ));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:finished_good,semi_finished,raw_material,component,service',
            'supplier_method' => 'nullable|in:buy,manufacture',
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->where(fn($q) => $q->where('tenant_id', $tenantId))->ignore($product->id)
            ],
            'uom_id' => 'nullable|exists:uoms,id',
            'hsn_sac' => 'nullable|string|max:50',
            'gst_rate' => 'nullable|numeric|min:0|max:100',
            'preferred_vendor_id' => 'nullable|exists:vendors,id',
            'selling_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sales_account' => 'required|string|max:255',
            'purchase_account' => 'required|string|max:255',
            'inventory_account' => 'nullable|string|max:255',
            'reorder_point' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'brand' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'mpn' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'upc' => 'nullable|string|max:255',
            'ean' => 'nullable|string|max:255',
            'isbn' => 'nullable|string|max:255',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'dimension_unit' => 'nullable|string|in:cm,in,mm,m',
            'weight_unit' => 'nullable|string|in:kg,g,lb,oz',
            'track_serial_number' => 'nullable|boolean',
            'track_batch' => 'nullable|boolean',
            'inventory_valuation_method' => 'nullable|string|in:FIFO,Weighted Average',
            'default_production_model' => 'nullable|string|in:pure_manufacturing,subcontract_complete,subcontract_company_material,hybrid',
            'warehouse_stocks' => 'nullable|array',
            'variants' => 'nullable|array',
            'variants.*.name' => 'nullable|string|max:255',
            'variants.*.sku' => 'nullable|string|max:255',
            'variants.*.selling_price' => 'nullable|numeric|min:0',
            'variants.*.cost_price' => 'nullable|numeric|min:0',
            'variants.*.reorder_point' => 'nullable|numeric|min:0',
            'variants.*.status' => 'nullable|in:active,inactive',
            'status' => 'required|in:active,inactive',
        ]);

        $this->productService->updateProduct($product, $validated, $tenantId, $request->all());

        return redirect()->route('inventory.products.index')->with('success', 'Product updated successfully.');
    }

    public function openingStock(Product $product): View
    {
        $this->authorize('update', $product);

        $product->load(['uom', 'warehouseStocks.warehouse', 'variants.warehouseStocks.warehouse']);
        $warehouses = $this->warehouseRepo->getActive();

        $stockMap = $product->warehouseStocks->keyBy('warehouse_id')->map(fn($ws) => [
            'quantity'  => $ws->quantity,
            'unit_cost' => $ws->unit_cost,
        ])->toArray();

        $variantStockMap = [];
        foreach ($product->variants as $variant) {
            $variantStockMap[$variant->id] = $variant->warehouseStocks->keyBy('warehouse_id')->map(fn($ws) => [
                'quantity'  => $ws->quantity,
                'unit_cost' => $ws->unit_cost,
            ])->toArray();
        }

        return view('modules.inventory.products.opening-stock', compact('product', 'warehouses', 'stockMap', 'variantStockMap'));
    }

    public function saveOpeningStock(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $request->validate([
            'warehouse_stocks'                  => 'nullable|array',
            'warehouse_stocks.*.quantity'       => 'nullable|numeric|min:0',
            'warehouse_stocks.*.unit_cost'      => 'nullable|numeric|min:0',
            'warehouse_stocks.*.batch_number'   => 'nullable|string',
            'warehouse_stocks.*.serial_numbers' => 'nullable|string',
            'variant_stocks'                    => 'nullable|array',
            'variant_stocks.*.*.quantity'       => 'nullable|numeric|min:0',
            'variant_stocks.*.*.unit_cost'      => 'nullable|numeric|min:0',
            'variant_stocks.*.*.batch_number'   => 'nullable|string',
            'variant_stocks.*.*.serial_numbers' => 'nullable|string',
        ]);

        $this->productService->saveOpeningStock($product, $request->all(), $tenantId);

        return redirect()->route('inventory.products.show', $product)->with('success', 'Opening stock updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $this->productRepo->delete($product);

        return redirect()->route('inventory.products.index')->with('success', 'Product deleted successfully.');
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $defaultUomId = $request->input('uom_id') ?? \App\Domains\Inventory\Models\Uom::where('tenant_id', $tenantId)->value('id');
        $request->merge([
            'supplier_method' => $request->input('supplier_method', 'buy'),
            'uom_id' => $defaultUomId,
            'inventory_valuation_method' => $request->input('inventory_valuation_method', 'FIFO'),
            'sales_account' => $request->input('sales_account', '4000 Sales'),
            'purchase_account' => $request->input('purchase_account', '5000 COGS'),
            'inventory_account' => $request->input('inventory_account', '1400 Inventory'),
        ]);

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

        $product = $this->productService->quickCreateProduct($validated, $tenantId);

        return response()->json([
            'id'   => $product->id,
            'name' => $product->name,
            'type' => $product->type,
        ]);
    }

    public function barcodeLookup(Request $request): JsonResponse
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;
        $code = trim($request->input('code', ''));

        if (empty($code)) {
            return response()->json(['success' => false, 'message' => 'No barcode provided'], 400);
        }

        // Extract embedded @warehouse_id if barcode was printed with specific warehouse
        $embeddedWhId = null;
        if (str_contains($code, '@')) {
            $parts = explode('@', $code, 2);
            $code = trim($parts[0]);
            $embeddedWhId = trim($parts[1] ?? '');
        }

        $isSerial = false;
        $serialNumber = null;
        $snRecord = null;

        // 1. Try finding product directly by barcode, sku, id, or name
        $product = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function($q) use ($code) {
                $q->where('barcode', $code)
                  ->orWhere('sku', $code)
                  ->orWhere('id', $code)
                  ->orWhere('name', 'LIKE', "%{$code}%");
            })
            ->sellable()
            ->first();

        // 2. If not found directly, check SerialNumber table by exact serial_number string
        if (!$product) {
            $snRecord = SerialNumber::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('serial_number', $code)
                ->with(['product', 'warehouse'])
                ->first();

            if (!$snRecord) {
                // Case-insensitive or without tenant filter fallback
                $snRecord = SerialNumber::withoutGlobalScopes()
                    ->where('serial_number', 'LIKE', $code)
                    ->with(['product', 'warehouse'])
                    ->first();
            }

            if ($snRecord && $snRecord->product) {
                $product = $snRecord->product;
                $isSerial = true;
                $serialNumber = $snRecord->serial_number;
            }
        }

        // 3. Fallback: If code is formatted as SKU-XXXX / PROD-XXXX / LAP123-1001
        if (!$product && str_contains($code, '-')) {
            $parts = explode('-', $code);
            $possibleSku = trim($parts[0]);
            if (!empty($possibleSku)) {
                $possibleProduct = Product::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where(function($q) use ($possibleSku) {
                        $q->where('sku', $possibleSku)
                          ->orWhere('barcode', $possibleSku);
                    })
                    ->where('track_serial_number', true)
                    ->sellable()
                    ->first();

                if ($possibleProduct) {
                    $product = $possibleProduct;
                    $isSerial = true;
                    $serialNumber = $code;
                }
            }
        }

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product or Serial Number not found for code: ' . $code], 404);
        }

        // 4. Resolve Warehouse: Embedded WH ID > Serial WH ID > Default WH ID
        $warehouseId = null;
        $warehouseName = null;

        if (!empty($embeddedWhId)) {
            $whObj = Warehouse::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', $embeddedWhId)
                ->first();
            if ($whObj) {
                $warehouseId = $whObj->id;
                $warehouseName = $whObj->name;
            }
        }

        if (!$warehouseId && $snRecord && $snRecord->warehouse_id) {
            $warehouseId = $snRecord->warehouse_id;
            $warehouseName = $snRecord->warehouse?->name;
        }

        if (!$warehouseId) {
            $defaultWh = Warehouse::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->first() ?? Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();

            if ($defaultWh) {
                $warehouseId = $defaultWh->id;
                $warehouseName = $defaultWh->name;
            }
        }

        return response()->json([
            'success' => true,
            'is_serial' => $isSerial,
            'serial_number' => $serialNumber,
            'warehouse_id' => $warehouseId,
            'warehouse_name' => $warehouseName,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'track_serial_number' => (bool)$product->track_serial_number,
                'track_batch' => (bool)$product->track_batch,
                'selling_price' => (float)($product->selling_price ?: $product->unit_cost),
                'cost_price' => (float)($product->cost_price ?: $product->unit_cost),
                'unit_cost' => (float)$product->unit_cost,
                'gst_rate' => (float)$product->gst_rate,
                'hsn_sac' => $product->hsn_sac,
                'uom' => $product->uom?->name ?? 'Pcs',
                'total_stock' => $product->total_stock,
            ]
        ]);
    }

    public function stockCheck(Request $request): JsonResponse
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;
        $productId = $request->input('product_id');
        $warehouseId = $request->input('warehouse_id');
        $mrItemId = $request->input('material_requirement_item_id');

        if (!$productId || !$warehouseId) {
            return response()->json(['success' => false, 'message' => 'Missing product or warehouse ID'], 400);
        }

        $stock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $totalReservedInWh = \App\Domains\Inventory\Models\StockReservation::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'Active')
            ->sum('reserved_qty');

        $itemReservedQty = 0;
        if ($mrItemId) {
            $mrItem = \App\Domains\Sales\Models\MaterialRequirementItem::find($mrItemId);
            if ($mrItem) {
                $res = \App\Domains\Inventory\Models\StockReservation::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where(function($q) use ($mrItem) {
                        $q->where(function($q2) use ($mrItem) {
                            $q2->whereIn('reference_type', ['DeliveryOrder', 'MaterialRequirement'])
                               ->where('reference_id', $mrItem->material_requirement_id);
                        })->orWhere('reference_item_id', $mrItem->id);
                    })
                    ->where('status', 'Active')
                    ->sum('reserved_qty');

                $itemReservedQty = (float)$res;

                if ($itemReservedQty <= 0 && (int)($mrItem->warehouse_id ?: 1) === (int)$warehouseId) {
                    $itemReservedQty = (float)$mrItem->quantity_reserved;
                }
            }
        }

        $physicalQty = (float)($stock?->quantity ?? 0);
        $reservedQty = (float)$totalReservedInWh;
        $availableQty = max(0, $physicalQty - $reservedQty);

        return response()->json([
            'success' => true,
            'physical_qty' => $physicalQty,
            'reserved_qty' => $reservedQty,
            'item_reserved_qty' => $itemReservedQty,
            'available_qty' => $availableQty,
        ]);
    }
}
