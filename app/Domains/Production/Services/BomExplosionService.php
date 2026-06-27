<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBom;
use InvalidArgumentException;

class BomExplosionService
{
    /**
     * Explode a product BOM to compute multi-level material requirements.
     */
    public function explode(int $productId, float $quantity, int $tenantId): array
    {
        $product = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($productId);

        if (!$product) {
            throw new InvalidArgumentException("Target finished product not found.");
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException("Explosion quantity must be greater than zero.");
        }

        $visited = [];
        $tree = $this->buildExplosionTree($product, $quantity, $tenantId, 1, $visited);
        
        $flat = [];
        $this->consolidateRequirements($tree, $flat);

        return [
            'tree' => $tree,
            'flat' => array_values($flat),
        ];
    }

    /**
     * Recursively build the BOM tree hierarchy.
     */
    private function buildExplosionTree(Product $product, float $quantity, int $tenantId, int $level, array $visited): array
    {
        if (isset($visited[$product->id])) {
            throw new InvalidArgumentException("Circular dependency loop detected: product '{$product->name}' (SKU: {$product->sku}) references itself.");
        }

        $visited[$product->id] = true;

        $node = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => $quantity,
            'uom_code' => 'PCS', // fallback
            'level' => $level,
            'children' => [],
            'has_sub_bom' => false,
        ];

        // Find the active approved BOM for this product
        $bom = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('status', 'approved')
            ->with(['items.material', 'items.uom'])
            ->first();

        if ($bom) {
            $node['has_sub_bom'] = true;
            $node['bom_number'] = $bom->bom_number;
            $node['bom_name'] = $bom->bom_name;
            $node['bom_version'] = $bom->version;
            $node['uom_code'] = $bom->baseUom ? $bom->baseUom->code : 'PCS';

            $baseQty = $bom->base_quantity > 0 ? $bom->base_quantity : 1.0;
            $multiplier = $quantity / $baseQty;

            foreach ($bom->items as $item) {
                // Calculate Net Component Quantity
                $netQty = $item->quantity * $multiplier;
                // Calculate Gross Quantity including scrap
                $scrapFactor = 1 + ($item->material_scrap_percentage / 100);
                $grossQty = $netQty * $scrapFactor;

                $childProduct = $item->material;

                // Recurse for the component
                $childTree = $this->buildExplosionTree($childProduct, $grossQty, $tenantId, $level + 1, $visited);
                
                // Set component properties
                $childTree['net_quantity'] = $netQty;
                $childTree['gross_quantity'] = $grossQty;
                $childTree['material_scrap_percentage'] = $item->material_scrap_percentage;
                $childTree['is_alternative'] = $item->is_alternative;
                $childTree['alternative_group'] = $item->alternative_group;
                $childTree['priority'] = $item->priority;
                $childTree['uom_code'] = $item->uom ? $item->uom->code : 'PCS';

                $node['children'][] = $childTree;
            }
        }

        return $node;
    }

    /**
     * Consolidate nested requirements into a flat raw material lists.
     */
    private function consolidateRequirements(array $node, array &$flat): void
    {
        // If a node has no children, it's a leaf node (Raw Material or external component)
        if (empty($node['children'])) {
            // Do not consolidate the root node if it's the target product itself
            if ($node['level'] === 1) {
                return;
            }

            $id = $node['product_id'];
            if (!isset($flat[$id])) {
                $flat[$id] = [
                    'product_id' => $node['product_id'],
                    'product_name' => $node['product_name'],
                    'product_sku' => $node['product_sku'],
                    'net_quantity' => 0.0,
                    'gross_quantity' => 0.0,
                    'uom_code' => $node['uom_code'],
                ];
            }
            $flat[$id]['net_quantity'] += $node['net_quantity'] ?? $node['quantity'];
            $flat[$id]['gross_quantity'] += $node['gross_quantity'] ?? $node['quantity'];
        } else {
            foreach ($node['children'] as $child) {
                $this->consolidateRequirements($child, $flat);
            }
        }
    }
}
