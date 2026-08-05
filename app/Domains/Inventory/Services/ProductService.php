<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Inventory\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        protected ProductRepository $productRepo
    ) {}

    /**
     * Create product (Single or Variant) along with warehouse stock records.
     */
    public function createProduct(array $validated, int $tenantId): Product
    {
        return DB::transaction(function () use ($validated, $tenantId) {
            $isService = ($validated['item_type'] ?? '') === 'Service';

            // 1. Create Parent Product
            $parentProduct = $this->productRepo->create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'sku' => $validated['variation_type'] === 'Single' ? $validated['sku'] : ($validated['sku'] ?? strtoupper($validated['name'] . '-VAR')),
                'type' => $isService ? 'service' : $validated['type'],
                'item_type' => $validated['item_type'],
                'variation_type' => $validated['variation_type'],
                'uom_id' => $validated['uom_id'] ?? null,
                'status' => 'active',
                'hsn_sac' => $validated['hsn_sac'] ?? null,
                'gst_rate' => $validated['gst_rate'] ?? 18.00,
                'preferred_vendor_id' => $isService ? null : ($validated['preferred_vendor_id'] ?? null),
                'selling_price' => $validated['selling_price'],
                'cost_price' => $validated['cost_price'],
                'unit_cost' => $validated['cost_price'],
                'sales_account' => $validated['sales_account'] ?? null,
                'purchase_account' => $validated['purchase_account'] ?? null,
                'inventory_account' => $isService ? null : ($validated['inventory_account'] ?? null),
                'reorder_point' => $isService ? 0 : ($validated['reorder_point'] ?? 0),
                'opening_stock' => ($validated['variation_type'] === 'Single' && !$isService) ? ($validated['opening_stock'] ?? 0) : 0,
                'opening_stock_rate' => ($validated['variation_type'] === 'Single' && !$isService) ? ($validated['opening_stock_rate'] ?? 0) : 0,
                'description' => $validated['description'] ?? null,
                'brand' => $isService ? null : ($validated['brand'] ?? null),
                'manufacturer' => $isService ? null : ($validated['manufacturer'] ?? null),
                'mpn' => $isService ? null : ($validated['mpn'] ?? null),
                'barcode' => $isService ? null : ($validated['barcode'] ?? null),
                'upc' => $isService ? null : ($validated['upc'] ?? null),
                'ean' => $isService ? null : ($validated['ean'] ?? null),
                'isbn' => $isService ? null : ($validated['isbn'] ?? null),
                'length' => $isService ? null : ($validated['length'] ?? null),
                'width' => $isService ? null : ($validated['width'] ?? null),
                'height' => $isService ? null : ($validated['height'] ?? null),
                'weight' => $isService ? null : ($validated['weight'] ?? null),
                'dimension_unit' => $isService ? null : ($validated['dimension_unit'] ?? null),
                'weight_unit' => $isService ? null : ($validated['weight_unit'] ?? null),
                'track_serial_number' => $isService ? false : !empty($validated['track_serial_number']),
                'track_batch' => $isService ? false : !empty($validated['track_batch']),
                'inventory_valuation_method' => $validated['inventory_valuation_method'] ?? 'FIFO',
                'attributes_config' => !empty($validated['attributes'])
                    ? array_values(array_filter(array_map(function($attr) {
                        $name = trim($attr['name'] ?? '');
                        $options = array_values(array_filter(array_map('trim', $attr['options'] ?? $attr['values'] ?? [])));
                        return ($name && !empty($options)) ? ['name' => $name, 'values' => array_unique($options)] : null;
                    }, $validated['attributes'])))
                    : null,
                'supplier_method' => $validated['supplier_method'] ?? 'buy',
            ]);

            if ($validated['variation_type'] === 'Single' && !$isService) {
                if (!empty($validated['warehouse_stocks'])) {
                    foreach ($validated['warehouse_stocks'] as $whId => $stockData) {
                        $qty = (float)($stockData['quantity'] ?? 0);
                        $cost = (float)($stockData['unit_cost'] ?? 0);
                        if ($qty > 0) {
                            StockService::recordInflow(
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
                if (!empty($validated['variants'])) {
                    $defaultWarehouse = Warehouse::query()->where('is_default', true)->first() ?? Warehouse::query()->first();

                    foreach ($validated['variants'] as $vData) {
                        $variantProduct = $this->productRepo->create([
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

                        $openingQty = (float)($vData['opening_stock'] ?? 0);
                        if ($openingQty > 0 && $defaultWarehouse) {
                            StockService::recordInflow(
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

            return $parentProduct;
        });
    }

    /**
     * Update existing Product and synchronize variants/stock entries.
     */
    public function updateProduct(Product $product, array $validated, int $tenantId, array $requestInput): bool
    {
        return DB::transaction(function () use ($validated, $product, $tenantId, $requestInput) {
            $isService = $product->item_type === 'Service' || ($validated['item_type'] ?? '') === 'Service';

            $product->update([
                'name' => $validated['name'],
                'type' => $isService ? 'service' : ($validated['type'] ?? $product->type),
                'sku' => $validated['sku'],
                'uom_id' => $validated['uom_id'] ?? null,
                'status' => $validated['status'],
                'hsn_sac' => $validated['hsn_sac'] ?? null,
                'gst_rate' => $validated['gst_rate'] ?? 18.00,
                'preferred_vendor_id' => $isService ? null : ($validated['preferred_vendor_id'] ?? null),
                'selling_price' => $validated['selling_price'],
                'cost_price' => $validated['cost_price'],
                'unit_cost' => $validated['cost_price'],
                'sales_account' => $validated['sales_account'] ?? $product->sales_account ?? 'Sales Income',
                'purchase_account' => $validated['purchase_account'] ?? $product->purchase_account ?? 'Cost of Goods Sold',
                'inventory_account' => $isService ? null : ($validated['inventory_account'] ?? $product->inventory_account ?? 'Inventory Asset'),
                'reorder_point' => $isService ? 0 : ($validated['reorder_point'] ?? 0),
                'description' => $validated['description'] ?? null,
                'brand' => $isService ? null : ($validated['brand'] ?? null),
                'manufacturer' => $isService ? null : ($validated['manufacturer'] ?? null),
                'mpn' => $isService ? null : ($validated['mpn'] ?? null),
                'barcode' => $isService ? null : ($validated['barcode'] ?? null),
                'upc' => $isService ? null : ($validated['upc'] ?? null),
                'ean' => $isService ? null : ($validated['ean'] ?? null),
                'isbn' => $isService ? null : ($validated['isbn'] ?? null),
                'length' => $isService ? null : ($validated['length'] ?? null),
                'width' => $isService ? null : ($validated['width'] ?? null),
                'height' => $isService ? null : ($validated['height'] ?? null),
                'weight' => $isService ? null : ($validated['weight'] ?? null),
                'dimension_unit' => $isService ? null : ($validated['dimension_unit'] ?? null),
                'weight_unit' => $isService ? null : ($validated['weight_unit'] ?? null),
                'track_serial_number' => $isService ? false : !empty($validated['track_serial_number']),
                'track_batch' => $isService ? false : !empty($validated['track_batch']),
                'inventory_valuation_method' => $validated['inventory_valuation_method'] ?? 'FIFO',
                'supplier_method' => $validated['supplier_method'] ?? 'buy',
            ]);

            Product::where('parent_id', $product->id)->update([
                'type' => $isService ? 'service' : ($validated['type'] ?? $product->type),
                'supplier_method' => $validated['supplier_method'] ?? 'buy'
            ]);

            if ($product->variation_type === 'Variant') {
                $attributesConfig = [];
                if (!empty($requestInput['attributes'])) {
                    foreach ($requestInput['attributes'] as $attr) {
                        $name = trim($attr['name'] ?? '');
                        $options = array_filter(array_map('trim', $attr['options'] ?? []));
                        if ($name && !empty($options)) {
                            $attributesConfig[] = [
                                'name' => $name,
                                'values' => array_values(array_unique($options)),
                            ];
                        }
                    }
                }
                if (!empty($attributesConfig)) {
                    $product->attributes_config = $attributesConfig;
                    $product->save();
                }

                $submittedVariants = $requestInput['variants'] ?? [];
                $defaultWarehouse = Warehouse::query()->where('is_default', true)->first() ?? Warehouse::query()->first();
                $processedIds = [];

                foreach ($submittedVariants as $vData) {
                    $variantId = $vData['id'] ?? null;
                    $variantSku = trim($vData['sku'] ?? '');
                    $attrLabel = trim($vData['attributes'] ?? '');
                    $name = $product->name . ($attrLabel ? " ({$attrLabel})" : '');
                    $vSelling = isset($vData['selling_price']) && is_numeric($vData['selling_price']) ? (float)$vData['selling_price'] : $product->selling_price;
                    $vCost = isset($vData['cost_price']) && is_numeric($vData['cost_price']) ? (float)$vData['cost_price'] : $product->cost_price;
                    $vReorder = isset($vData['reorder_point']) && is_numeric($vData['reorder_point']) ? (float)$vData['reorder_point'] : 0;
                    $vOpening = isset($vData['opening_stock']) && is_numeric($vData['opening_stock']) ? (float)$vData['opening_stock'] : 0;

                    $variant = null;
                    if ($variantId) {
                        $variant = Product::where('parent_id', $product->id)->where('id', $variantId)->first();
                    }
                    if (!$variant && $variantSku) {
                        $variant = Product::where('parent_id', $product->id)->where('sku', $variantSku)->first();
                    }

                    if ($variant) {
                        $variant->update([
                            'name' => $name,
                            'sku' => $variantSku ?: $variant->sku,
                            'selling_price' => $vSelling,
                            'cost_price' => $vCost,
                            'unit_cost' => $vCost,
                            'reorder_point' => $vReorder,
                            'status' => $vData['status'] ?? 'active',
                            'type' => $validated['type'],
                            'supplier_method' => $validated['supplier_method'] ?? 'buy',
                            'uom_id' => $validated['uom_id'] ?? null,
                        ]);
                        $processedIds[] = $variant->id;
                    } else if (!empty($variantSku)) {
                        $newVariant = Product::create([
                            'tenant_id' => $tenantId,
                            'parent_id' => $product->id,
                            'name' => $name,
                            'sku' => $variantSku,
                            'type' => $validated['type'],
                            'item_type' => $product->item_type,
                            'variation_type' => 'Single',
                            'uom_id' => $validated['uom_id'] ?? null,
                            'status' => 'active',
                            'selling_price' => $vSelling,
                            'cost_price' => $vCost,
                            'unit_cost' => $vCost,
                            'reorder_point' => $vReorder,
                            'opening_stock' => $vOpening,
                            'opening_stock_rate' => $vCost,
                            'variant_values' => ['label' => $attrLabel],
                            'track_serial_number' => $product->track_serial_number,
                            'track_batch' => $product->track_batch,
                            'supplier_method' => $validated['supplier_method'] ?? 'buy',
                        ]);
                        $processedIds[] = $newVariant->id;

                        if ($vOpening > 0 && $defaultWarehouse) {
                            try {
                                StockService::recordInflow(
                                    $tenantId,
                                    $newVariant->id,
                                    $defaultWarehouse->id,
                                    $vOpening,
                                    $vCost,
                                    'Opening Stock'
                                );
                            } catch (\Exception $e) {}
                        }
                    }
                }

                if (!empty($processedIds)) {
                    Product::where('parent_id', $product->id)
                        ->whereNotIn('id', $processedIds)
                        ->delete();
                }
            }

            if ($product->variation_type === 'Single') {
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
                                StockService::recordInflow(
                                    $tenantId,
                                    $product->id,
                                    $whId,
                                    $diff,
                                    $rate,
                                    'Adjustment'
                                );
                            } else {
                                $diff = $oldQty - $qty;
                                StockService::recordOutflow(
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

            return true;
        });
    }

    /**
     * Save or update opening stock allocations for single and variant products.
     */
    public function saveOpeningStock(Product $product, array $requestInput, int $tenantId): void
    {
        DB::transaction(function () use ($requestInput, $product, $tenantId) {
            if ($product->variation_type === 'Variant') {
                $variantStocks = $requestInput['variant_stocks'] ?? [];
                foreach ($variantStocks as $variantId => $whData) {
                    $variant = $product->variants->firstWhere('id', $variantId);
                    if (!$variant) continue;

                    foreach ($whData as $warehouseId => $data) {
                        $qty  = (float)($data['quantity']  ?? 0);
                        $cost = (float)($data['unit_cost'] ?? 0);
                        $batchNumber = $data['batch_number'] ?? null;
                        $snRaw = $data['serial_numbers'] ?? '';
                        $serialNumbers = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string)$snRaw))));

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
                                StockService::recordInflow(
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
                                StockService::recordOutflow(
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
                $stocks = $requestInput['warehouse_stocks'] ?? [];
                foreach ($stocks as $warehouseId => $data) {
                    $qty  = (float)($data['quantity']  ?? 0);
                    $cost = (float)($data['unit_cost'] ?? 0);
                    $batchNumber = $data['batch_number'] ?? null;
                    $snRaw = $data['serial_numbers'] ?? '';
                    $serialNumbers = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string)$snRaw))));

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
                            StockService::recordInflow(
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
                            StockService::recordOutflow(
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
    }

    /**
     * Create product via Quick Modal AJAX endpoint.
     */
    public function quickCreateProduct(array $validated, int $tenantId): Product
    {
        return $this->productRepo->create([
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
            'preferred_vendor_id' => $validated['preferred_vendor_id'] ?? null,
            'status' => 'active',
            'planning_type' => $validated['supplier_method'] === 'manufacture' ? 'manufacture' : 'purchase',
            'variation_type' => 'Single',
        ]);
    }
}
