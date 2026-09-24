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
        protected ProductRepository $productRepo,
        protected ProductImageService $imageService
    ) {}

    /**
     * Create product (Single or Variant) along with warehouse stock records and media.
     */
    public function createProduct(array $validated, int $tenantId, array $requestInput = []): Product
    {
        return DB::transaction(function () use ($validated, $tenantId, $requestInput) {
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
                'selling_price' => (float)($validated['selling_price'] ?? 0),
                'cost_price' => (float)($validated['cost_price'] ?? 0),
                'unit_cost' => (float)($validated['cost_price'] ?? 0),
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
                'default_production_model' => $validated['default_production_model'] ?? 'pure_manufacturing',
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
                $openingStock = (float)($validated['opening_stock'] ?? 0);
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
                } elseif ($openingStock > 0) {
                    $defaultWarehouse = Warehouse::ensureDefaultWarehouse($tenantId);
                    StockService::recordInflow(
                        $tenantId,
                        $parentProduct->id,
                        $defaultWarehouse->id,
                        $openingStock,
                        $parentProduct->cost_price > 0 ? $parentProduct->cost_price : (float)($validated['opening_stock_rate'] ?? 0),
                        'Opening Stock'
                    );
                }
            } else {
                $submittedVariants = $requestInput['variants'] ?? $validated['variants'] ?? [];
                if (!empty($submittedVariants)) {
                    $defaultWarehouse = Warehouse::ensureDefaultWarehouse($tenantId);

                    foreach ($submittedVariants as $idx => $vData) {
                        $variantSku = trim($vData['sku'] ?? '');
                        if (!$variantSku) {
                            continue;
                        }
                        $attrLabel = trim($vData['attributes'] ?? '');
                        $variantProduct = $this->productRepo->create([
                            'tenant_id' => $tenantId,
                            'parent_id' => $parentProduct->id,
                            'name' => $parentProduct->name . ($attrLabel ? " ({$attrLabel})" : ''),
                            'sku' => $variantSku,
                            'type' => $parentProduct->type,
                            'item_type' => $parentProduct->item_type,
                            'variation_type' => 'Single',
                            'uom_id' => $parentProduct->uom_id,
                            'status' => $vData['status'] ?? 'active',
                            'selling_price' => !empty($vData['selling_price']) ? (float)$vData['selling_price'] : $parentProduct->selling_price,
                            'cost_price' => !empty($vData['cost_price']) ? (float)$vData['cost_price'] : $parentProduct->cost_price,
                            'unit_cost' => !empty($vData['cost_price']) ? (float)$vData['cost_price'] : $parentProduct->cost_price,
                            'reorder_point' => !empty($vData['reorder_point']) ? (float)$vData['reorder_point'] : 0,
                            'opening_stock' => !empty($vData['opening_stock']) ? (float)$vData['opening_stock'] : 0,
                            'opening_stock_rate' => !empty($vData['cost_price']) ? (float)$vData['cost_price'] : $parentProduct->cost_price,
                            'variant_values' => ['label' => $attrLabel],
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

                        // Variant media upload (main image & detail gallery photos)
                        $this->imageService->saveVariantMedia($variantProduct, $vData, $tenantId);
                    }
                }
            }

            // Save parent product Main Image & Detail Images
            if (!empty($requestInput['main_image']) && $requestInput['main_image'] instanceof \Illuminate\Http\UploadedFile) {
                $this->imageService->saveMainImage($parentProduct, $requestInput['main_image'], $tenantId);
            }
            if (!empty($requestInput['detail_images']) && is_array($requestInput['detail_images'])) {
                $this->imageService->saveDetailImages($parentProduct, $requestInput['detail_images'], $tenantId);
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
                'selling_price' => (float)($validated['selling_price'] ?? $product->selling_price ?? 0),
                'cost_price' => (float)($validated['cost_price'] ?? $product->cost_price ?? 0),
                'unit_cost' => (float)($validated['cost_price'] ?? $product->unit_cost ?? $product->cost_price ?? 0),
                'sales_account' => $validated['sales_account'] ?? null,
                'default_production_model' => $validated['default_production_model'] ?? $product->default_production_model ?? 'pure_manufacturing',
                'purchase_account' => $validated['purchase_account'] ?? null,
                'inventory_account' => $isService ? null : ($validated['inventory_account'] ?? null),
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
                'supplier_method' => ($validated['supplier_method'] ?? 'trade') === 'buy' ? 'trade' : ($validated['supplier_method'] ?? 'trade'),
            ]);

            Product::where('parent_id', $product->id)->update([
                'type' => $isService ? 'service' : ($validated['type'] ?? $product->type),
                'supplier_method' => ($validated['supplier_method'] ?? 'trade') === 'buy' ? 'trade' : ($validated['supplier_method'] ?? 'trade')
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
                $defaultWarehouse = Warehouse::ensureDefaultWarehouse($tenantId);
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

                        // Variant media update (main & detail images)
                        $this->imageService->saveVariantMedia($variant, $vData, $tenantId);
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

                        // Variant media upload
                        $this->imageService->saveVariantMedia($newVariant, $vData, $tenantId);

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
                $stocksInput = $validated['warehouse_stocks'] ?? ($requestInput['warehouse_stocks'] ?? []);
                if (!empty($stocksInput) && is_array($stocksInput)) {
                    foreach ($stocksInput as $whId => $stockData) {
                        $qty = (float)($stockData['quantity'] ?? 0);
                        $cost = (float)($stockData['unit_cost'] ?? 0);

                        $oldStock = ProductWarehouseStock::query()
                            ->where('tenant_id', $tenantId)
                            ->where('product_id', $product->id)
                            ->where('warehouse_id', $whId)
                            ->first();

                        $oldQty = $oldStock ? (float)$oldStock->quantity : 0.0;
                        $oldCost = $oldStock ? (float)$oldStock->unit_cost : 0.0;
                        $rate = $cost > 0 ? $cost : ($product->cost_price > 0 ? (float)$product->cost_price : $oldCost);

                        $oldTotalVal = $oldQty * $oldCost;
                        $newTotalVal = $qty * $rate;
                        $netValDiff = round($newTotalVal - $oldTotalVal, 2);

                        if ($qty != $oldQty) {
                            if ($qty > $oldQty) {
                                $diff = $qty - $oldQty;
                                StockService::recordInflow(
                                    $tenantId,
                                    $product->id,
                                    $whId,
                                    $diff,
                                    $rate,
                                    'Opening Stock'
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

                        // Always update unit_cost on ProductWarehouseStock if cost was provided or changed
                        $targetCost = $cost > 0 ? $cost : (float)$product->cost_price;
                        if ($targetCost > 0) {
                            $currentStock = ProductWarehouseStock::query()
                                ->where('tenant_id', $tenantId)
                                ->where('product_id', $product->id)
                                ->where('warehouse_id', $whId)
                                ->first();

                            if ($currentStock && (float)$currentStock->unit_cost != $targetCost) {
                                $currentStock->update(['unit_cost' => $targetCost]);
                            }
                        }

                        // If quantity was the same but unit rate was changed, post the net difference journal entry to accounting
                        if ($qty == $oldQty && $qty > 0 && abs($netValDiff) > 0.005) {
                            StockService::postOpeningStockAccountingJournal($tenantId, $product->id, $netValDiff);
                        }
                    }
                }
            }

            // Media & Image updates for parent product
            if (!empty($requestInput['deleted_image_ids']) && is_array($requestInput['deleted_image_ids'])) {
                foreach ($requestInput['deleted_image_ids'] as $delId) {
                    if (!empty($delId)) {
                        $this->imageService->deleteImage((int)$delId, $product);
                    }
                }
            }

            if (!empty($requestInput['primary_image_id'])) {
                $this->imageService->setPrimary((int)$requestInput['primary_image_id'], $product);
            }

            if (!empty($requestInput['main_image']) && $requestInput['main_image'] instanceof \Illuminate\Http\UploadedFile) {
                $this->imageService->saveMainImage($product, $requestInput['main_image'], $tenantId);
            }

            if (!empty($requestInput['detail_images']) && is_array($requestInput['detail_images'])) {
                $this->imageService->saveDetailImages($product, $requestInput['detail_images'], $tenantId);
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
                        $oldCost = $stock ? (float)$stock->unit_cost : 0.0;
                        $rate = $cost > 0 ? $cost : ($variant->cost_price > 0 ? (float)$variant->cost_price : $oldCost);

                        $oldTotalVal = $oldQty * $oldCost;
                        $newTotalVal = $qty * $rate;
                        $netValDiff = round($newTotalVal - $oldTotalVal, 2);

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

                        // Always update unit_cost on ProductWarehouseStock
                        $targetCost = $cost > 0 ? $cost : (float)$variant->cost_price;
                        if ($targetCost > 0) {
                            $currentStock = ProductWarehouseStock::query()
                                ->where('tenant_id', $tenantId)
                                ->where('product_id', $variantId)
                                ->where('warehouse_id', $warehouseId)
                                ->first();

                            if ($currentStock && (float)$currentStock->unit_cost != $targetCost) {
                                $currentStock->update(['unit_cost' => $targetCost]);
                            }
                        }

                        // If quantity was the same but unit rate was changed, post accounting journal
                        if ($qty == $oldQty && $qty > 0 && abs($netValDiff) > 0.005) {
                            StockService::postOpeningStockAccountingJournal($tenantId, $variantId, $netValDiff);
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
                    $oldCost = $stock ? (float)$stock->unit_cost : 0.0;
                    $rate = $cost > 0 ? $cost : ($product->cost_price > 0 ? (float)$product->cost_price : $oldCost);

                    $oldTotalVal = $oldQty * $oldCost;
                    $newTotalVal = $qty * $rate;
                    $netValDiff = round($newTotalVal - $oldTotalVal, 2);

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

                    // Always update unit_cost on ProductWarehouseStock
                    $targetCost = $cost > 0 ? $cost : (float)$product->cost_price;
                    if ($targetCost > 0) {
                        $currentStock = ProductWarehouseStock::query()
                            ->where('tenant_id', $tenantId)
                            ->where('product_id', $product->id)
                            ->where('warehouse_id', $warehouseId)
                            ->first();

                        if ($currentStock && (float)$currentStock->unit_cost != $targetCost) {
                            $currentStock->update(['unit_cost' => $targetCost]);
                        }
                    }

                    // If quantity was the same but unit rate was changed, post accounting journal
                    if ($qty == $oldQty && $qty > 0 && abs($netValDiff) > 0.005) {
                        StockService::postOpeningStockAccountingJournal($tenantId, $product->id, $netValDiff);
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
            'sales_account' => $validated['sales_account'] ?? null,
            'purchase_account' => $validated['purchase_account'] ?? null,
            'inventory_account' => $validated['inventory_account'] ?? null,
            'preferred_vendor_id' => $validated['preferred_vendor_id'] ?? null,
            'status' => 'active',
            'planning_type' => $validated['supplier_method'] === 'manufacture' ? 'manufacture' : 'purchase',
            'variation_type' => 'Single',
        ]);
    }
}
