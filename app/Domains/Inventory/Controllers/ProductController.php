<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
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
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'sales_account' => 'nullable|string|max:255',
            'purchase_account' => 'nullable|string|max:255',
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
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'sales_account' => 'nullable|string|max:255',
            'purchase_account' => 'nullable|string|max:255',
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
}
