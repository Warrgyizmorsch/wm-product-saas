<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductImage;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * ProductApiController
 * 
 * Location: app/Domains/Inventory/Controllers/Api/ProductApiController.php (Inventory Module)
 * 
 * Enterprise-Grade Product Export & Import / Sync REST API Kit.
 * Features:
 * - GET  /api/inventory/products/export  (Full Big-ERP Export API with complete categorized structure, variants, images, barcodes, stock)
 * - GET  /api/inventory/products         (Alias to Export / List API)
 * - POST /api/inventory/products         (Single & Bulk Ingest / Import API with nested & flat payload support, variants, & image syncing)
 * 
 * Authentication: Laravel Sanctum (Bearer Token)
 * Authorization: ProductPolicy (inventory.products.view, inventory.products.create)
 */
class ProductApiController extends Controller
{
    /**
     * Resolve Tenant, Company, and Branch Context
     */
    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/inventory/products/export (and GET /api/inventory/products)
     * 
     * Full Enterprise Product Export API Kit for 3rd-party ERPs & platforms.
     * Outputs all parent products and child variants with clean, well-categorized domain objects.
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        [$tenantId] = $this->resolveTenantContext();

        $query = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('parent_id') // Export parent products (and their embedded variants)
            ->with([
                'uom',
                'vendor',
                'images',
                'primaryImage',
                'detailImages',
                'warehouseStocks.warehouse',
                'variants.images',
                'variants.primaryImage',
                'variants.detailImages',
                'variants.warehouseStocks.warehouse',
            ]);

        // 1. Filter: Status (active, inactive)
        if ($request->filled('status')) {
            $statuses = is_array($request->input('status'))
                ? $request->input('status')
                : explode(',', (string) $request->input('status'));
            $query->whereIn('status', array_map('trim', $statuses));
        }

        // 2. Filter: Product Type (finished_good, semi_finished, raw_material, component, service)
        if ($request->filled('type')) {
            $types = is_array($request->input('type'))
                ? $request->input('type')
                : explode(',', (string) $request->input('type'));
            $query->whereIn('type', array_map('trim', $types));
        }

        // 3. Filter: Item Type (Goods, Service)
        if ($request->filled('item_type')) {
            $query->where('item_type', trim((string) $request->input('item_type')));
        }

        // 4. Filter: Variation Type (Single, Variant)
        if ($request->filled('variation_type')) {
            $query->where('variation_type', trim((string) $request->input('variation_type')));
        }

        // 5. Filter: Brand & Manufacturer
        if ($request->filled('brand')) {
            $query->where('brand', trim((string) $request->input('brand')));
        }
        if ($request->filled('manufacturer')) {
            $query->where('manufacturer', trim((string) $request->input('manufacturer')));
        }

        // 6. Filter: UoM
        if ($request->filled('uom_id')) {
            $query->where('uom_id', (int) $request->input('uom_id'));
        } elseif ($request->filled('uom_code')) {
            $code = trim((string) $request->input('uom_code'));
            $query->whereHas('uom', fn($q) => $q->where('code', $code)->orWhere('name', $code));
        }

        // 7. Filter: Preferred Vendor ID
        if ($request->filled('preferred_vendor_id')) {
            $query->where('preferred_vendor_id', (int) $request->input('preferred_vendor_id'));
        } elseif ($request->filled('vendor_id')) {
            $query->where('preferred_vendor_id', (int) $request->input('vendor_id'));
        }

        // 8. Filter: Price Range (Selling Price & Cost Price)
        if ($request->filled('min_price')) {
            $query->where('selling_price', '>=', (float) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('selling_price', '<=', (float) $request->input('max_price'));
        }
        if ($request->filled('min_cost')) {
            $query->where('cost_price', '>=', (float) $request->input('min_cost'));
        }
        if ($request->filled('max_cost')) {
            $query->where('cost_price', '<=', (float) $request->input('max_cost'));
        }

        // 9. Filter: Taxation (HSN/SAC & GST Rate)
        if ($request->filled('hsn_sac')) {
            $query->where('hsn_sac', trim((string) $request->input('hsn_sac')));
        }
        if ($request->filled('gst_rate')) {
            $query->where('gst_rate', (float) $request->input('gst_rate'));
        }

        // 10. Filter: Specific Warehouse ID
        if ($request->filled('warehouse_id')) {
            $whId = (int) $request->input('warehouse_id');
            $query->where(function ($q) use ($whId) {
                $q->whereHas('warehouseStocks', fn($wq) => $wq->where('warehouse_id', $whId))
                  ->orWhereHas('variants.warehouseStocks', fn($wq) => $wq->where('warehouse_id', $whId));
            });
        }

        // 11. Filter: In Stock (Stock availability)
        if ($request->has('in_stock')) {
            $inStock = filter_var($request->input('in_stock'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($inStock === true) {
                $query->where(function ($q) {
                    $q->where('opening_stock', '>', 0)
                      ->orWhereHas('warehouseStocks', fn($wq) => $wq->where('quantity', '>', 0))
                      ->orWhereHas('variants', function ($vq) {
                          $vq->where('opening_stock', '>', 0)
                             ->orWhereHas('warehouseStocks', fn($wq) => $wq->where('quantity', '>', 0));
                      });
                });
            } elseif ($inStock === false) {
                $query->where(function ($q) {
                    $q->where('opening_stock', '<=', 0)
                      ->whereDoesntHave('warehouseStocks', fn($wq) => $wq->where('quantity', '>', 0))
                      ->where(function ($sq) {
                          $sq->where('variation_type', '!=', 'Variant')
                             ->orWhereDoesntHave('variants.warehouseStocks', fn($wq) => $wq->where('quantity', '>', 0));
                      });
                });
            }
        }

        // 12. Filter: Has Images
        if ($request->has('has_images')) {
            $hasImg = filter_var($request->input('has_images'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($hasImg === true) {
                $query->where(function ($q) {
                    $q->whereNotNull('image_path')
                      ->orWhereHas('images')
                      ->orWhereHas('variants.images');
                });
            } elseif ($hasImg === false) {
                $query->whereNull('image_path')
                      ->whereDoesntHave('images')
                      ->whereDoesntHave('variants.images');
            }
        }

        // 13. Filter: Tracking flags
        if ($request->has('track_serial_number')) {
            $val = filter_var($request->input('track_serial_number'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $query->where('track_serial_number', $val);
            }
        }
        if ($request->has('track_batch')) {
            $val = filter_var($request->input('track_batch'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $query->where('track_batch', $val);
            }
        }

        // 14. Filter: Date Range
        $dateColumn = in_array($request->input('date_filter_by'), ['updated_at'], true) ? 'updated_at' : 'created_at';
        if ($request->filled('from_date')) {
            try {
                $query->whereDate($dateColumn, '>=', Carbon::parse($request->input('from_date')));
            } catch (\Throwable $e) {}
        }
        if ($request->filled('to_date')) {
            try {
                $query->whereDate($dateColumn, '<=', Carbon::parse($request->input('to_date')));
            } catch (\Throwable $e) {}
        }

        // 15. Search: Keyword across key product attributes and child variants
        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('sku', 'like', $search)
                  ->orWhere('barcode', 'like', $search)
                  ->orWhere('brand', 'like', $search)
                  ->orWhere('manufacturer', 'like', $search)
                  ->orWhere('mpn', 'like', $search)
                  ->orWhere('hsn_sac', 'like', $search)
                  ->orWhere('description', 'like', $search)
                  ->orWhereHas('variants', function ($vq) use ($search) {
                      $vq->where('name', 'like', $search)
                         ->orWhere('sku', 'like', $search)
                         ->orWhere('barcode', 'like', $search);
                  });
            });
        }

        // 16. Sorting
        $sortBy = in_array($request->input('sort_by'), ['created_at', 'id', 'name', 'sku', 'selling_price', 'cost_price', 'updated_at'], true)
            ? $request->input('sort_by')
            : 'id';
        $sortDir = strtolower((string) $request->input('sort_direction', $request->input('order', 'asc'))) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        $totalCount = (clone $query)->count();

        // 17. Pagination / Limit Handling (Supports both limit and per_page, plus page number)
        $limit = $request->filled('limit')
            ? max((int)$request->input('limit'), 1)
            : ($request->filled('per_page') ? max((int)$request->input('per_page'), 1) : null);
        
        $page = $request->filled('page') ? max((int)$request->input('page'), 1) : 1;

        if ($limit !== null) {
            $query->forPage($page, $limit);
        }

        $products = $query->get();

        // 18. Transform records into enterprise export format
        $exportedProducts = $products->map(function (Product $product) {
            return $this->formatProductForExport($product);
        });

        $totalPages = $limit ? (int)ceil($totalCount / $limit) : 1;

        $responsePayload = [
            'success'         => true,
            'api_version'     => 'v1',
            'exported_at'     => now()->toIso8601String(),
            'total_records'   => $totalCount,
            'count'           => $exportedProducts->count(),
            'current_page'    => $limit ? $page : 1,
            'per_page'        => $limit ?: $totalCount,
            'total_pages'     => $totalPages,
            'filters_applied' => array_filter($request->only([
                'status', 'type', 'item_type', 'variation_type', 'brand', 'manufacturer',
                'uom_id', 'uom_code', 'preferred_vendor_id', 'vendor_id', 'min_price', 'max_price',
                'min_cost', 'max_cost', 'hsn_sac', 'gst_rate', 'warehouse_id', 'in_stock', 'has_images',
                'track_serial_number', 'track_batch', 'from_date', 'to_date', 'search', 'date_filter_by',
                'limit', 'per_page', 'page', 'sort_by', 'sort_direction'
            ])),
            'data'            => $exportedProducts,
        ];

        return response()->json($responsePayload, 200);
    }

    /**
     * Format a single Product into a clean, categorized enterprise JSON representation.
     */
    private function formatProductForExport(Product $product): array
    {
        return [
            // Primary Identifiers
            'id'                       => $product->id,
            'name'                     => $product->name,
            'sku'                      => $product->sku,
            'status'                   => $product->status ?? 'active',
            'description'              => $product->description,

            // Brand & Manufacturer
            'brand_and_manufacturer'   => [
                'brand'                => $product->brand,
                'manufacturer'         => $product->manufacturer,
                'mpn'                  => $product->mpn,
            ],

            // Categorization & Production Type
            'category_and_type'        => [
                'item_type'                => $product->item_type ?? 'Goods',
                'type'                     => $product->type ?? 'finished_good',
                'variation_type'           => $product->variation_type ?? 'Single',
                'supplier_method'          => $product->supplier_method ?? 'buy',
                'default_production_model' => $product->default_production_model ?? 'pure_manufacturing',
            ],

            // Unit of Measurement
            'uom'                      => [
                'id'                   => $product->uom?->id ?? $product->uom_id,
                'name'                 => $product->uom?->name,
                'code'                 => $product->uom?->code,
                'category'             => $product->uom?->category,
            ],

            // Pricing & Valuation
            'pricing'                  => [
                'selling_price'        => (float)$product->selling_price,
                'cost_price'           => (float)$product->cost_price,
                'unit_cost'            => (float)($product->unit_cost ?? $product->cost_price),
            ],

            // Tax & Statutory
            'taxation'                 => [
                'hsn_sac'              => $product->hsn_sac,
                'gst_rate'             => (float)$product->gst_rate,
            ],

            // Barcodes & Global Trade Identifiers
            'barcodes_and_identifiers' => [
                'barcode'              => $product->barcode,
                'upc'                  => $product->upc,
                'ean'                  => $product->ean,
                'isbn'                 => $product->isbn,
            ],

            // Dimensions & Shipping Weight
            'dimensions_and_weight'    => [
                'length'               => $product->length !== null ? (float)$product->length : null,
                'width'                => $product->width !== null ? (float)$product->width : null,
                'height'               => $product->height !== null ? (float)$product->height : null,
                'dimension_unit'       => $product->dimension_unit,
                'weight'               => $product->weight !== null ? (float)$product->weight : null,
                'weight_unit'          => $product->weight_unit,
            ],

            // Inventory Stock & Warehouse Allocations
            'inventory_and_stock'      => [
                'reorder_point'              => (float)$product->reorder_point,
                'minimum_order_qty'          => (float)$product->minimum_order_qty,
                'order_multiple'             => (float)$product->order_multiple,
                'opening_stock'              => (float)$product->opening_stock,
                'opening_stock_rate'         => (float)$product->opening_stock_rate,
                'total_current_stock'        => (float)$product->total_stock,
                'inventory_valuation_method' => $product->inventory_valuation_method ?? 'FIFO',
                'warehouse_stocks'           => $product->warehouseStocks->map(fn($ws) => [
                    'warehouse_id'           => $ws->warehouse_id,
                    'warehouse_name'         => $ws->warehouse?->name,
                    'warehouse_code'         => $ws->warehouse?->code,
                    'quantity'               => (float)$ws->quantity,
                ])->values()->all(),
            ],

            // Accounting Ledgers
            'accounting'               => [
                'sales_account'        => $product->sales_account,
                'purchase_account'     => $product->purchase_account,
                'inventory_account'    => $product->inventory_account,
            ],

            // Serial & Batch Tracking
            'tracking'                 => [
                'track_serial_number'  => (bool)$product->track_serial_number,
                'track_batch'          => (bool)$product->track_batch,
            ],

            // Preferred Vendor / Supplier
            'vendor'                   => [
                'id'                   => $product->vendor?->id ?? $product->preferred_vendor_id,
                'name'                 => $product->vendor?->name,
                'company_name'         => $product->vendor?->company_name,
                'email'                => $product->vendor?->email,
                'phone'                => $product->vendor?->phone,
            ],

            // Product Images & Media
            'images'                   => [
                'primary_image_url'    => $product->main_image_url,
                'detail_images'        => $product->detailImages->map(fn($img) => [
                    'id'               => $img->id,
                    'url'              => $img->url,
                    'file_name'        => $img->file_name,
                    'mime_type'        => $img->mime_type,
                    'is_primary'       => (bool)$img->is_primary,
                    'sort_order'       => (int)$img->sort_order,
                    'alt_text'         => $img->alt_text,
                ])->values()->all(),
                'all_images'           => $product->images->map(fn($img) => $img->url)->values()->all(),
            ],

            // Attributes Configuration (for Variant Matrix)
            'attributes_config'        => $product->attributes_config ?? [],

            // Child Product Variants (with Variant Pricing, Stock, & Media)
            'variants'                 => $product->variants->map(function (Product $variant) {
                return [
                    'id'                       => $variant->id,
                    'parent_id'                => $variant->parent_id,
                    'sku'                      => $variant->sku,
                    'name'                     => $variant->name,
                    'status'                   => $variant->status ?? 'active',
                    'variant_values'           => $variant->variant_values ?? [],
                    'pricing'                  => [
                        'selling_price'        => (float)$variant->selling_price,
                        'cost_price'           => (float)$variant->cost_price,
                        'unit_cost'            => (float)($variant->unit_cost ?? $variant->cost_price),
                    ],
                    'inventory_and_stock'      => [
                        'opening_stock'        => (float)$variant->opening_stock,
                        'opening_stock_rate'   => (float)$variant->opening_stock_rate,
                        'reorder_point'        => (float)$variant->reorder_point,
                        'total_current_stock'  => (float)$variant->total_stock,
                        'warehouse_stocks'     => $variant->warehouseStocks->map(fn($ws) => [
                            'warehouse_id'     => $ws->warehouse_id,
                            'warehouse_name'   => $ws->warehouse?->name,
                            'warehouse_code'   => $ws->warehouse?->code,
                            'quantity'         => (float)$ws->quantity,
                        ])->values()->all(),
                    ],
                    'barcodes'                 => [
                        'barcode'              => $variant->barcode,
                        'upc'                  => $variant->upc,
                        'ean'                  => $variant->ean,
                        'isbn'                 => $variant->isbn,
                    ],
                    'images'                   => [
                        'primary_image_url'    => $variant->main_image_url,
                        'detail_images'        => $variant->detailImages->map(fn($img) => [
                            'id'               => $img->id,
                            'url'              => $img->url,
                            'file_name'        => $img->file_name,
                            'mime_type'        => $img->mime_type,
                            'is_primary'       => (bool)$img->is_primary,
                            'sort_order'       => (int)$img->sort_order,
                            'alt_text'         => $img->alt_text,
                        ])->values()->all(),
                        'all_images'           => $variant->images->map(fn($img) => $img->url)->values()->all(),
                    ],
                    'timeline'                 => [
                        'created_at'           => $variant->created_at?->toIso8601String(),
                        'updated_at'           => $variant->updated_at?->toIso8601String(),
                    ],
                ];
            })->values()->all(),

            // Timeline
            'timeline'                 => [
                'created_at'           => $product->created_at?->toIso8601String(),
                'updated_at'           => $product->updated_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * POST /api/inventory/products
     * 
     * Single & Bulk Product Import / Ingestion API.
     * Supports single product object, array of products, or { "products": [...] }.
     * Supports both nested structured payloads and flat database payloads.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $payload = $request->all();

        // 1) Direct JSON array: [ {...}, {...} ]
        if (is_array($payload) && array_is_list($payload)) {
            return $this->processBulkProducts($payload);
        }

        // 2) Object with "products" or "data" array: { "products": [ {...}, {...} ] }
        if (isset($payload['products']) && is_array($payload['products'])) {
            return $this->processBulkProducts($payload['products']);
        }
        if (isset($payload['data']) && is_array($payload['data']) && array_is_list($payload['data'])) {
            return $this->processBulkProducts($payload['data']);
        }

        // 3) Single Product Object: { "name": "...", "sku": "...", ... }
        $validator = Validator::make($payload, $this->productRules($payload));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed for product.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
            $product = $this->saveProductFromPayload($payload, $tenantId, $companyId, $branchId);

            return response()->json([
                'success' => true,
                'type'    => 'single',
                'message' => 'Product created successfully.',
                'data'    => $this->formatProductForExport($product),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process bulk list of products (up to 500 per batch)
     */
    private function processBulkProducts(array $productsList): JsonResponse
    {
        if (count($productsList) > 500) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum 500 products allowed per import request.',
            ], 422);
        }

        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $created = [];
        $failed  = [];

        foreach ($productsList as $index => $row) {
            if (!is_array($row)) {
                $failed[] = [
                    'row'    => $index + 1,
                    'errors' => ['Row must be a valid JSON object'],
                ];
                continue;
            }

            $rowValidator = Validator::make($row, $this->productRules($row));
            if ($rowValidator->fails()) {
                $failed[] = [
                    'row'    => $index + 1,
                    'sku'    => $row['sku'] ?? null,
                    'name'   => $row['name'] ?? null,
                    'errors' => $rowValidator->errors(),
                ];
                continue;
            }

            try {
                $product = $this->saveProductFromPayload($row, $tenantId, $companyId, $branchId);
                $created[] = [
                    'row'            => $index + 1,
                    'id'             => $product->id,
                    'name'           => $product->name,
                    'sku'            => $product->sku,
                    'variation_type' => $product->variation_type,
                    'variants_count' => $product->variants()->count(),
                    'status'         => $product->status,
                ];
            } catch (\Throwable $e) {
                $failed[] = [
                    'row'   => $index + 1,
                    'sku'   => $row['sku'] ?? null,
                    'name'  => $row['name'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success'       => true,
            'type'          => 'bulk',
            'total_sent'    => count($productsList),
            'total_created' => count($created),
            'total_failed'  => count($failed),
            'created'       => $created,
            'failed'        => $failed,
        ], 200);
    }

    /**
     * Validation rules for Product Ingest
     */
    private function productRules(array $data): array
    {
        $tenantId = auth()->user()?->tenant_id ?? (tenant_id() ?? 1);
        $varType = $data['variation_type'] ?? ($data['category_and_type']['variation_type'] ?? 'Single');

        return [
            'name'                     => 'required|string|max:255',
            'sku'                      => [
                $varType === 'Single' ? 'required' : 'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if (!empty($value)) {
                        $exists = Product::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->where('sku', $value)
                            ->exists();
                        if ($exists) {
                            $fail("The SKU '{$value}' is already taken.");
                        }
                    }
                }
            ],
            'item_type'                => 'nullable|string|in:Goods,Service',
            'type'                     => 'nullable|string|in:finished_good,semi_finished,raw_material,component,service',
            'variation_type'           => 'nullable|string|in:Single,Variant',
            'selling_price'            => 'nullable|numeric|min:0',
            'cost_price'               => 'nullable|numeric|min:0',
            'gst_rate'                 => 'nullable|numeric|min:0|max:100',
            'hsn_sac'                  => 'nullable|string|max:50',
            'uom_id'                   => 'nullable|integer',
            'uom_code'                 => 'nullable|string|max:50',
            'uom_name'                 => 'nullable|string|max:100',
            'preferred_vendor_id'      => 'nullable|integer',
            'reorder_point'            => 'nullable|numeric|min:0',
            'opening_stock'            => 'nullable|numeric|min:0',
            'opening_stock_rate'       => 'nullable|numeric|min:0',
            'variants'                 => 'nullable|array',
            'variants.*.sku'           => 'nullable|string|max:255',
            'variants.*.name'          => 'nullable|string|max:255',
            'variants.*.selling_price' => 'nullable|numeric|min:0',
            'variants.*.cost_price'    => 'nullable|numeric|min:0',
            'variants.*.opening_stock' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Create Parent Product, Child Variants, Media, and Stock Inflows inside a Database Transaction.
     */
    private function saveProductFromPayload(array $data, int $tenantId, int $companyId, ?int $branchId): Product
    {
        return DB::transaction(function () use ($data, $tenantId, $companyId, $branchId) {
            // 1. Resolve normalized values from either flat or nested objects
            $name          = trim($data['name'] ?? '');
            $itemType      = $data['item_type'] ?? ($data['category_and_type']['item_type'] ?? 'Goods');
            $type          = $data['type'] ?? ($data['category_and_type']['type'] ?? ($itemType === 'Service' ? 'service' : 'finished_good'));
            $variationType = $data['variation_type'] ?? ($data['category_and_type']['variation_type'] ?? 'Single');
            $supplierMethod = $data['supplier_method'] ?? ($data['category_and_type']['supplier_method'] ?? 'buy');
            $prodModel     = $data['default_production_model'] ?? ($data['category_and_type']['default_production_model'] ?? 'pure_manufacturing');

            $sku = trim($data['sku'] ?? '');
            if (!$sku) {
                $sku = $variationType === 'Single'
                    ? 'PRD-' . strtoupper(Str::random(6))
                    : strtoupper(Str::slug($name, '-')) . '-VAR';
            }

            // Pricing
            $sellingPrice = isset($data['selling_price']) ? (float)$data['selling_price'] : (isset($data['pricing']['selling_price']) ? (float)$data['pricing']['selling_price'] : 0.0);
            $costPrice    = isset($data['cost_price']) ? (float)$data['cost_price'] : (isset($data['pricing']['cost_price']) ? (float)$data['pricing']['cost_price'] : 0.0);
            $unitCost     = isset($data['unit_cost']) ? (float)$data['unit_cost'] : (isset($data['pricing']['unit_cost']) ? (float)$data['pricing']['unit_cost'] : $costPrice);

            // Taxation
            $hsnSac  = $data['hsn_sac'] ?? ($data['taxation']['hsn_sac'] ?? null);
            $gstRate = isset($data['gst_rate']) ? (float)$data['gst_rate'] : (isset($data['taxation']['gst_rate']) ? (float)$data['taxation']['gst_rate'] : 18.0);

            // Brand & Identifiers
            $brand        = $data['brand'] ?? ($data['brand_and_manufacturer']['brand'] ?? null);
            $manufacturer = $data['manufacturer'] ?? ($data['brand_and_manufacturer']['manufacturer'] ?? null);
            $mpn          = $data['mpn'] ?? ($data['brand_and_manufacturer']['mpn'] ?? null);

            $barcode = $data['barcode'] ?? ($data['barcodes_and_identifiers']['barcode'] ?? null);
            $upc     = $data['upc'] ?? ($data['barcodes_and_identifiers']['upc'] ?? null);
            $ean     = $data['ean'] ?? ($data['barcodes_and_identifiers']['ean'] ?? null);
            $isbn    = $data['isbn'] ?? ($data['barcodes_and_identifiers']['isbn'] ?? null);

            // Dimensions & Weight
            $length        = isset($data['length']) ? (float)$data['length'] : (isset($data['dimensions_and_weight']['length']) ? (float)$data['dimensions_and_weight']['length'] : null);
            $width         = isset($data['width']) ? (float)$data['width'] : (isset($data['dimensions_and_weight']['width']) ? (float)$data['dimensions_and_weight']['width'] : null);
            $height        = isset($data['height']) ? (float)$data['height'] : (isset($data['dimensions_and_weight']['height']) ? (float)$data['dimensions_and_weight']['height'] : null);
            $dimensionUnit = $data['dimension_unit'] ?? ($data['dimensions_and_weight']['dimension_unit'] ?? 'cm');
            $weight        = isset($data['weight']) ? (float)$data['weight'] : (isset($data['dimensions_and_weight']['weight']) ? (float)$data['dimensions_and_weight']['weight'] : null);
            $weightUnit    = $data['weight_unit'] ?? ($data['dimensions_and_weight']['weight_unit'] ?? 'kg');

            // Inventory & Stock
            $reorderPoint   = isset($data['reorder_point']) ? (float)$data['reorder_point'] : (isset($data['inventory_and_stock']['reorder_point']) ? (float)$data['inventory_and_stock']['reorder_point'] : 0.0);
            $minOrderQty    = isset($data['minimum_order_qty']) ? (float)$data['minimum_order_qty'] : (isset($data['inventory_and_stock']['minimum_order_qty']) ? (float)$data['inventory_and_stock']['minimum_order_qty'] : 0.0);
            $orderMultiple  = isset($data['order_multiple']) ? (float)$data['order_multiple'] : (isset($data['inventory_and_stock']['order_multiple']) ? (float)$data['inventory_and_stock']['order_multiple'] : 0.0);
            $openingStock   = isset($data['opening_stock']) ? (float)$data['opening_stock'] : (isset($data['inventory_and_stock']['opening_stock']) ? (float)$data['inventory_and_stock']['opening_stock'] : 0.0);
            $openingStockRt = isset($data['opening_stock_rate']) ? (float)$data['opening_stock_rate'] : (isset($data['inventory_and_stock']['opening_stock_rate']) ? (float)$data['inventory_and_stock']['opening_stock_rate'] : $costPrice);
            $valuation      = $data['inventory_valuation_method'] ?? ($data['inventory_and_stock']['inventory_valuation_method'] ?? 'FIFO');

            // Accounting
            $salesAccount     = $data['sales_account'] ?? ($data['accounting']['sales_account'] ?? 'Sales - Goods');
            $purchaseAccount  = $data['purchase_account'] ?? ($data['accounting']['purchase_account'] ?? 'Cost of Goods Sold');
            $inventoryAccount = $data['inventory_account'] ?? ($data['accounting']['inventory_account'] ?? ($itemType === 'Goods' ? 'Inventory Asset' : null));

            // Tracking
            $trackSerial = !empty($data['track_serial_number']) || !empty($data['tracking']['track_serial_number']);
            $trackBatch  = !empty($data['track_batch']) || !empty($data['tracking']['track_batch']);

            // Resolve UoM and Vendor
            $uomId = $this->resolveUomId(
                $data['uom_id'] ?? ($data['uom']['id'] ?? null),
                $data['uom_name'] ?? ($data['uom']['name'] ?? null),
                $data['uom_code'] ?? ($data['uom']['code'] ?? null),
                $tenantId
            );

            $vendorId = $this->resolveVendorId(
                $data['preferred_vendor_id'] ?? ($data['vendor_id'] ?? ($data['vendor']['id'] ?? null)),
                $data['vendor_name'] ?? ($data['vendor']['name'] ?? ($data['vendor']['company_name'] ?? null)),
                $data['vendor_email'] ?? ($data['vendor']['email'] ?? null),
                $tenantId
            );

            // Attributes configuration
            $attributesConfig = $data['attributes_config'] ?? ($data['attributes'] ?? null);

            // 2. Create Master / Parent Product
            $product = Product::create([
                'tenant_id'                  => $tenantId,
                'company_id'                 => $companyId,
                'branch_id'                  => $branchId,
                'name'                       => $name,
                'sku'                        => $sku,
                'status'                     => $data['status'] ?? 'active',
                'description'                => $data['description'] ?? null,
                'item_type'                  => $itemType,
                'type'                       => $type,
                'variation_type'             => $variationType,
                'supplier_method'            => $supplierMethod,
                'default_production_model'   => $prodModel,
                'uom_id'                     => $uomId,
                'preferred_vendor_id'        => $vendorId,
                'selling_price'              => $sellingPrice,
                'cost_price'                 => $costPrice,
                'unit_cost'                  => $unitCost,
                'hsn_sac'                    => $hsnSac,
                'gst_rate'                   => $gstRate,
                'brand'                      => $brand,
                'manufacturer'               => $manufacturer,
                'mpn'                        => $mpn,
                'barcode'                    => $barcode,
                'upc'                        => $upc,
                'ean'                        => $ean,
                'isbn'                       => $isbn,
                'length'                     => $length,
                'width'                      => $width,
                'height'                     => $height,
                'dimension_unit'             => $dimensionUnit,
                'weight'                     => $weight,
                'weight_unit'                => $weightUnit,
                'reorder_point'              => $reorderPoint,
                'minimum_order_qty'          => $minOrderQty,
                'order_multiple'             => $orderMultiple,
                'opening_stock'              => $variationType === 'Single' ? $openingStock : 0,
                'opening_stock_rate'         => $variationType === 'Single' ? $openingStockRt : 0,
                'inventory_valuation_method' => $valuation,
                'sales_account'              => $salesAccount,
                'purchase_account'           => $purchaseAccount,
                'inventory_account'          => $inventoryAccount,
                'track_serial_number'        => $trackSerial,
                'track_batch'                => $trackBatch,
                'attributes_config'          => is_array($attributesConfig) ? $attributesConfig : null,
            ]);

            // 3. Attach Master Images
            $this->attachImagesToProduct($product, $data, $tenantId);

            // 4. Record Opening Stock for Single Products
            if ($variationType === 'Single' && $itemType === 'Goods') {
                $whStocks = $data['warehouse_stocks'] ?? ($data['inventory_and_stock']['warehouse_stocks'] ?? []);
                if (!empty($whStocks) && is_array($whStocks)) {
                    foreach ($whStocks as $ws) {
                        $whId = $ws['warehouse_id'] ?? null;
                        $qty  = (float)($ws['quantity'] ?? 0);
                        if ($whId && $qty > 0) {
                            StockService::recordInflow(
                                $tenantId,
                                $product->id,
                                (int)$whId,
                                $qty,
                                $costPrice > 0 ? $costPrice : $openingStockRt,
                                'API Product Ingest'
                            );
                        }
                    }
                } elseif ($openingStock > 0) {
                    $defaultWarehouse = Warehouse::ensureDefaultWarehouse($tenantId);
                    if ($defaultWarehouse) {
                        StockService::recordInflow(
                            $tenantId,
                            $product->id,
                            $defaultWarehouse->id,
                            $openingStock,
                            $costPrice > 0 ? $costPrice : $openingStockRt,
                            'API Product Ingest'
                        );
                    }
                }
            }

            // 5. Create Child Variants if variation_type is 'Variant'
            if ($variationType === 'Variant') {
                $variants = $data['variants'] ?? [];
                if (!empty($variants) && is_array($variants)) {
                    $defaultWarehouse = Warehouse::ensureDefaultWarehouse($tenantId);

                    foreach ($variants as $vIdx => $vData) {
                        if (!is_array($vData)) continue;

                        $vSku = trim($vData['sku'] ?? '');
                        if (!$vSku) {
                            $vSku = $product->sku . '-V' . ($vIdx + 1);
                        }

                        $vValues = $vData['variant_values'] ?? ($vData['attributes'] ?? []);
                        $vLabel  = is_string($vValues) ? $vValues : ($vValues['label'] ?? (is_array($vValues) ? implode(' / ', $vValues) : ''));
                        $vName   = !empty($vData['name']) ? trim($vData['name']) : ($product->name . ($vLabel ? " ({$vLabel})" : " (Variant #" . ($vIdx + 1) . ")"));

                        $vSellingPrice = isset($vData['selling_price']) ? (float)$vData['selling_price'] : (isset($vData['pricing']['selling_price']) ? (float)$vData['pricing']['selling_price'] : $sellingPrice);
                        $vCostPrice    = isset($vData['cost_price']) ? (float)$vData['cost_price'] : (isset($vData['pricing']['cost_price']) ? (float)$vData['pricing']['cost_price'] : $costPrice);
                        $vReorder      = isset($vData['reorder_point']) ? (float)$vData['reorder_point'] : (isset($vData['inventory_and_stock']['reorder_point']) ? (float)$vData['inventory_and_stock']['reorder_point'] : 0.0);
                        $vOpeningStock = isset($vData['opening_stock']) ? (float)$vData['opening_stock'] : (isset($vData['inventory_and_stock']['opening_stock']) ? (float)$vData['inventory_and_stock']['opening_stock'] : 0.0);

                        $vBarcode = $vData['barcode'] ?? ($vData['barcodes']['barcode'] ?? null);
                        $vUpc     = $vData['upc'] ?? ($vData['barcodes']['upc'] ?? null);
                        $vEan     = $vData['ean'] ?? ($vData['barcodes']['ean'] ?? null);
                        $vIsbn    = $vData['isbn'] ?? ($vData['barcodes']['isbn'] ?? null);

                        $variantProduct = Product::create([
                            'tenant_id'                  => $tenantId,
                            'company_id'                 => $companyId,
                            'branch_id'                  => $branchId,
                            'parent_id'                  => $product->id,
                            'name'                       => $vName,
                            'sku'                        => $vSku,
                            'status'                     => $vData['status'] ?? 'active',
                            'item_type'                  => $itemType,
                            'type'                       => $type,
                            'variation_type'             => 'Single',
                            'supplier_method'            => $supplierMethod,
                            'default_production_model'   => $prodModel,
                            'uom_id'                     => $uomId,
                            'preferred_vendor_id'        => $vendorId,
                            'selling_price'              => $vSellingPrice,
                            'cost_price'                 => $vCostPrice,
                            'unit_cost'                  => $vCostPrice,
                            'hsn_sac'                    => $hsnSac,
                            'gst_rate'                   => $gstRate,
                            'brand'                      => $brand,
                            'manufacturer'               => $manufacturer,
                            'mpn'                        => $mpn,
                            'barcode'                    => $vBarcode,
                            'upc'                        => $vUpc,
                            'ean'                        => $vEan,
                            'isbn'                       => $vIsbn,
                            'reorder_point'              => $vReorder,
                            'opening_stock'              => $vOpeningStock,
                            'opening_stock_rate'         => $vCostPrice,
                            'inventory_valuation_method' => $valuation,
                            'variant_values'             => is_array($vValues) ? $vValues : ['label' => $vLabel],
                            'track_serial_number'        => $trackSerial,
                            'track_batch'                => $trackBatch,
                        ]);

                        // Attach variant media
                        $this->attachImagesToProduct($variantProduct, $vData, $tenantId);

                        // Record variant opening stock
                        if ($vOpeningStock > 0 && $defaultWarehouse) {
                            StockService::recordInflow(
                                $tenantId,
                                $variantProduct->id,
                                $defaultWarehouse->id,
                                $vOpeningStock,
                                $vCostPrice,
                                'API Product Variant Ingest'
                            );
                        }
                    }
                }
            }

            return $product->load([
                'uom', 'vendor', 'images', 'primaryImage', 'detailImages', 'warehouseStocks.warehouse',
                'variants.images', 'variants.primaryImage', 'variants.detailImages', 'variants.warehouseStocks.warehouse'
            ]);
        });
    }

    /**
     * Attach and sync primary & detail images for a product/variant from API payload.
     * Supports URLs, base64 strings, or file paths.
     */
    private function attachImagesToProduct(Product $product, array $data, int $tenantId): void
    {
        // 1. Primary Image
        $primaryImg = $data['primary_image_url'] 
            ?? ($data['main_image_url'] 
            ?? ($data['image_url'] 
            ?? ($data['images']['primary_image_url'] ?? null)));

        if ($primaryImg && is_string($primaryImg)) {
            $this->storeImageRecord($product, $primaryImg, true, 0, $tenantId);
        }

        // 2. Detail Images
        $detailImgs = $data['detail_images'] 
            ?? ($data['images']['detail_images'] 
            ?? ($data['all_images'] 
            ?? ($data['images']['all_images'] ?? [])));

        if (!empty($detailImgs) && is_array($detailImgs)) {
            $order = 1;
            foreach ($detailImgs as $dImg) {
                $imgUrl = is_string($dImg) ? $dImg : ($dImg['url'] ?? ($dImg['file_path'] ?? null));
                if ($imgUrl && is_string($imgUrl) && $imgUrl !== $primaryImg) {
                    $alt = is_array($dImg) ? ($dImg['alt_text'] ?? null) : null;
                    $this->storeImageRecord($product, $imgUrl, false, $order++, $tenantId, $alt);
                }
            }
        }
    }

    /**
     * Store individual ProductImage record (handles Remote URL, Base64, or local path)
     */
    private function storeImageRecord(Product $product, string $imageStr, bool $isPrimary, int $sortOrder, int $tenantId, ?string $altText = null): ?ProductImage
    {
        $imageStr = trim($imageStr);
        if (empty($imageStr)) return null;

        $filePath = $imageStr;
        $fileName = basename(parse_url($imageStr, PHP_URL_PATH) ?: 'image.jpg');
        $mimeType = 'image/jpeg';
        $fileSize = 0;

        // Case A: Base64 data URL
        if (str_starts_with($imageStr, 'data:image/')) {
            try {
                if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/', $imageStr, $matches)) {
                    $mimeType = $matches[1];
                    $ext = explode('/', $mimeType)[1] ?? 'jpg';
                    $decoded = base64_decode($matches[2]);
                    $fileName = 'api_' . time() . '_' . Str::random(6) . '.' . $ext;
                    $filePath = "uploads/tenants/{$tenantId}/products/{$product->id}/{$fileName}";
                    Storage::disk('public')->put($filePath, $decoded);
                    $fileSize = strlen($decoded);
                }
            } catch (\Throwable $e) {}
        }
        // Case B: Public HTTP / HTTPS URL
        elseif (str_starts_with($imageStr, 'http://') || str_starts_with($imageStr, 'https://')) {
            // Check if it's already hosted on this server's storage
            if (str_contains($imageStr, '/storage/')) {
                $parts = explode('/storage/', $imageStr);
                $filePath = $parts[1] ?? $imageStr;
            } else {
                $filePath = $imageStr; // Keep full remote URL
            }
        }

        if ($isPrimary) {
            ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
            $product->update(['image_path' => $filePath]);
        }

        return ProductImage::create([
            'tenant_id'   => $tenantId,
            'company_id'  => $product->company_id,
            'branch_id'   => $product->branch_id,
            'product_id'  => $product->id,
            'file_path'   => $filePath,
            'file_name'   => $fileName,
            'file_size'   => $fileSize,
            'mime_type'   => $mimeType,
            'image_type'  => $isPrimary ? 'primary' : 'detail',
            'is_primary'  => $isPrimary,
            'sort_order'  => $sortOrder,
            'alt_text'    => $altText ?: $product->name,
        ]);
    }

    /**
     * Resolve or auto-create Unit of Measurement (UoM)
     */
    private function resolveUomId(?int $uomId, ?string $uomName, ?string $uomCode, int $tenantId): ?int
    {
        if ($uomId && Uom::where('id', $uomId)->exists()) {
            return $uomId;
        }

        if (!empty($uomCode) || !empty($uomName)) {
            $search = $uomCode ?: $uomName;
            $uom = Uom::where(function ($q) use ($uomCode, $uomName) {
                if ($uomCode) $q->where('code', $uomCode);
                if ($uomName) $q->orWhere('name', $uomName);
            })->first();

            if ($uom) {
                return $uom->id;
            }

            // Auto-create standard UoM
            $newUom = Uom::create([
                'tenant_id' => $tenantId,
                'name'      => $uomName ?: strtoupper($uomCode),
                'code'      => strtoupper($uomCode ?: substr($uomName, 0, 4)),
                'category'  => 'Goods',
            ]);

            return $newUom->id;
        }

        return null;
    }

    /**
     * Resolve or auto-create Preferred Vendor
     */
    private function resolveVendorId(?int $vendorId, ?string $vendorName, ?string $vendorEmail, int $tenantId): ?int
    {
        if ($vendorId && Vendor::where('tenant_id', $tenantId)->where('id', $vendorId)->exists()) {
            return $vendorId;
        }

        if (!empty($vendorEmail)) {
            $vendor = Vendor::where('tenant_id', $tenantId)->where('email', $vendorEmail)->first();
            if ($vendor) return $vendor->id;
        }

        if (!empty($vendorName)) {
            $vendor = Vendor::where('tenant_id', $tenantId)
                ->where(function ($q) use ($vendorName) {
                    $q->where('name', $vendorName)->orWhere('company_name', $vendorName);
                })->first();

            if ($vendor) return $vendor->id;

            $newVendor = Vendor::create([
                'tenant_id'    => $tenantId,
                'name'         => $vendorName,
                'company_name' => $vendorName,
                'email'        => $vendorEmail,
                'status'       => 'active',
            ]);

            return $newVendor->id;
        }

        return null;
    }
}
