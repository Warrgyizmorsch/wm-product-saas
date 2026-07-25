<?php

namespace App\Imports;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ProductImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $name = $row['item_name'] ?? ($row['name'] ?? null);
        $sku = $row['sku'] ?? null;

        if (empty($name) || empty($sku)) {
            return null;
        }

        $tenantId = auth()->user()->tenant_id ?? null;
        $openingStock = isset($row['opening_stock']) && is_numeric($row['opening_stock']) ? (float)$row['opening_stock'] : 0.0;
        $costPrice = isset($row['cost_price']) && is_numeric($row['cost_price']) ? (float)$row['cost_price'] : 0.0;

        // Parse Variant Attributes string e.g. "Color: Red | Size: M"
        $attrStr = $row['variant_attributes'] ?? ($row['attributes'] ?? null);
        $variantValues = [];

        if (!empty($attrStr)) {
            $pairs = preg_split('/[|;,]/', $attrStr);
            foreach ($pairs as $pair) {
                if (str_contains($pair, ':')) {
                    list($k, $v) = explode(':', $pair, 2);
                } elseif (str_contains($pair, '=')) {
                    list($k, $v) = explode('=', $pair, 2);
                } else {
                    continue;
                }
                $k = trim($k);
                $v = trim($v);
                if (!empty($k) && !empty($v)) {
                    $variantValues[$k] = $v;
                }
            }
        }

        // Parent SKU Resolution
        $parentSku = trim($row['parent_sku'] ?? ($row['parent_product_sku'] ?? ''));
        $parentId = null;

        if (!empty($parentSku)) {
            $parentProduct = Product::where('tenant_id', $tenantId)->where('sku', $parentSku)->first();
            if ($parentProduct) {
                $parentId = $parentProduct->id;

                // Sync attributes config to parent product
                if (!empty($variantValues)) {
                    $existingConfig = $parentProduct->attributes_config ?? [];
                    foreach ($variantValues as $attrName => $attrVal) {
                        $found = false;
                        foreach ($existingConfig as &$cfg) {
                            if (strcasecmp($cfg['name'] ?? '', $attrName) === 0) {
                                $found = true;
                                if (!in_array($attrVal, $cfg['values'] ?? [])) {
                                    $cfg['values'][] = $attrVal;
                                }
                                break;
                            }
                        }
                        if (!$found) {
                            $existingConfig[] = [
                                'name' => $attrName,
                                'values' => [$attrVal]
                            ];
                        }
                    }
                    $parentProduct->attributes_config = $existingConfig;
                    $parentProduct->variation_type = 'Variant';
                    $parentProduct->save();
                }
            }
        }

        // Variation type
        $variationInput = trim($row['variation_type'] ?? '');
        $variationType = !empty($variationInput) 
            ? (strcasecmp($variationInput, 'Variant') === 0 ? 'Variant' : 'Single')
            : ($parentId ? 'Single' : 'Single');

        // Supplier Method normalization (buy, manufacture)
        $supplierMethodInput = strtolower(trim($row['supplier_method'] ?? ($row['supplier'] ?? ($row['sourcing_method'] ?? 'buy'))));
        $supplierMethod = in_array($supplierMethodInput, ['buy', 'manufacture']) ? $supplierMethodInput : 'buy';
        $planningType = $supplierMethod === 'manufacture' ? 'manufacture' : 'purchase';

        // Auto-resolve UOM / Unit
        $uomInput = $row['unit_uom'] ?? ($row['uom'] ?? ($row['unit'] ?? null));
        $uomId = null;
        if (!empty($uomInput)) {
            $uom = Uom::where('tenant_id', $tenantId)
                ->where(function($q) use ($uomInput) {
                    $q->where('name', $uomInput)->orWhere('code', $uomInput);
                })->first();

            if (!$uom && $tenantId) {
                $uom = Uom::create([
                    'tenant_id' => $tenantId,
                    'name' => strtoupper($uomInput),
                    'code' => strtoupper($uomInput),
                ]);
            }
            if ($uom) {
                $uomId = $uom->id;
            }
        }

        // Hard purge any soft-deleted product with same tenant_id & sku to prevent 1062 unique constraint error
        DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('sku', $sku)
            ->whereNotNull('deleted_at')
            ->delete();

        // Valuation method normalization
        $valuationInput = $row['valuation_method'] ?? ($row['inventory_valuation_method'] ?? 'FIFO');
        $valuationMethod = in_array(strtoupper($valuationInput), ['FIFO', 'WEIGHTED AVERAGE', 'WEIGHTED_AVERAGE']) 
            ? (strtoupper($valuationInput) === 'FIFO' ? 'FIFO' : 'Weighted Average')
            : 'FIFO';

        // Dimension and Weight unit normalization
        $dimensionUnit = $row['dimension_unit'] ?? 'cm';
        $weightUnit = $row['weight_unit'] ?? 'kg';

        // Format parent attributes_config if this is a parent variant
        $attributesConfig = null;
        if ($variationType === 'Variant' && !empty($variantValues)) {
            $attributesConfig = [];
            foreach ($variantValues as $k => $v) {
                $vals = array_filter(array_map('trim', explode(',', $v)));
                $attributesConfig[] = [
                    'name' => $k,
                    'values' => array_values($vals)
                ];
            }
        }

        // Update or create product
        $product = Product::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'sku' => $sku,
            ],
            [
                'parent_id' => $parentId,
                'variation_type' => $variationType,
                'variant_values' => !empty($variantValues) ? $variantValues : null,
                'attributes_config' => $attributesConfig,
                'name' => $name,
                'type' => $row['type'] ?? 'finished_good',
                'item_type' => $row['item_type'] ?? 'Goods',
                'supplier_method' => $supplierMethod,
                'planning_type' => $planningType,
                'uom_id' => $uomId,
                'selling_price' => isset($row['selling_price']) && is_numeric($row['selling_price']) ? (float)$row['selling_price'] : 0.0,
                'cost_price' => $costPrice,
                'unit_cost' => $costPrice,
                'opening_stock' => $openingStock,
                'reorder_point' => isset($row['reorder_point']) && is_numeric($row['reorder_point']) ? (float)$row['reorder_point'] : 0.0,
                'hsn_sac' => $row['hsn_sac'] ?? ($row['hsn'] ?? null),
                'gst_rate' => isset($row['gst_rate']) && is_numeric($row['gst_rate']) ? (float)$row['gst_rate'] : 0.0,
                'inventory_valuation_method' => $valuationMethod,
                'length' => isset($row['length']) && is_numeric($row['length']) ? (float)$row['length'] : null,
                'width' => isset($row['width']) && is_numeric($row['width']) ? (float)$row['width'] : null,
                'height' => isset($row['height']) && is_numeric($row['height']) ? (float)$row['height'] : null,
                'dimension_unit' => $dimensionUnit,
                'weight' => isset($row['weight']) && is_numeric($row['weight']) ? (float)$row['weight'] : null,
                'weight_unit' => $weightUnit,
                'description' => $row['description'] ?? null,
                'status' => !empty($row['status']) ? strtolower($row['status']) : 'active',
            ]
        );

        // If opening stock > 0, allocate to warehouse automatically
        if ($openingStock > 0) {
            $warehouseInput = $row['warehouse'] ?? ($row['warehouse_code'] ?? null);
            $warehouse = null;

            if (!empty($warehouseInput)) {
                $warehouse = Warehouse::where('tenant_id', $tenantId)
                    ->where(function($q) use ($warehouseInput) {
                        $q->where('name', $warehouseInput)->orWhere('code', $warehouseInput);
                    })->first();
            }

            if (!$warehouse && $tenantId) {
                $warehouse = Warehouse::where('tenant_id', $tenantId)->where('is_default', true)->first()
                    ?? Warehouse::where('tenant_id', $tenantId)->first();
            }

            if (!$warehouse) {
                $warehouse = Warehouse::create([
                    'tenant_id' => $tenantId,
                    'name' => 'Main Warehouse',
                    'code' => 'MAIN',
                    'status' => 'active',
                    'is_default' => true,
                ]);
            }

            try {
                StockService::recordInflow(
                    $tenantId,
                    $product->id,
                    $warehouse->id,
                    $openingStock,
                    $costPrice,
                    'Opening Stock'
                );
            } catch (\Exception $e) {
                // Silently ignore if already logged
            }
        }

        return $product;
    }

    public function prepareForValidation($data, $index)
    {
        $numericFields = ['opening_stock', 'selling_price', 'cost_price', 'reorder_point', 'gst_rate', 'length', 'width', 'height', 'weight'];
        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                $val = trim((string)$data[$field]);
                if ($val === '-' || $val === 'N/A' || $val === '') {
                    $data[$field] = 0;
                }
            }
        }
        return $data;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'item_name' => 'nullable|max:255',
            'name' => 'nullable|max:255',
            'sku' => 'required|max:255',
            'variation_type' => 'nullable|max:255',
            'parent_sku' => 'nullable|max:255',
            'variant_attributes' => 'nullable',
            'type' => 'nullable|max:255',
            'item_type' => 'nullable|max:255',
            'supplier_method' => 'nullable|max:255',
            'unit_uom' => 'nullable|max:255',
            'uom' => 'nullable|max:255',
            'selling_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'opening_stock' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'hsn_sac' => 'nullable|max:255',
            'valuation_method' => 'nullable|max:255',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'description' => 'nullable',
            'status' => 'nullable|max:255',
            'warehouse' => 'nullable|max:255',
        ];
    }
}
