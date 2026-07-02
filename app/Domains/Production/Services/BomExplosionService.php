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
        $tree = $this->calculate($product, $quantity, $tenantId, 1, $visited);
        
        $flat = $this->generateRequirements($tree);

        return [
            'tree' => $tree,
            'flat' => $flat,
        ];
    }

    /**
     * Calculate explosion node for a given product at a specific level.
     */
    protected function calculate(Product $product, float $quantity, int $tenantId, int $level, array $visited, ?int $forcedBomId = null): array
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

        // Find the BOM: either the forced child_bom_id or the default active approved BOM
        if ($forcedBomId) {
            $bom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', $forcedBomId)
                ->with(['items.material', 'items.uom'])
                ->first();
        } else {
            $bom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $product->id)
                ->where('status', 'approved')
                ->with(['items.material', 'items.uom'])
                ->first();
        }

        if ($bom) {
            $node['has_sub_bom'] = true;
            $node['bom_number'] = $bom->bom_number;
            $node['bom_name'] = $bom->bom_name;
            $node['bom_version'] = $bom->version;
            $node['uom_code'] = $bom->baseUom ? $bom->baseUom->code : 'PCS';

            $node['children'] = $this->resolveChildren($bom, $quantity, $tenantId, $level, $visited);
        }

        return $node;
    }

    /**
     * Resolve child items for a given BOM.
     */
    protected function resolveChildren(ProductionBom $bom, float $parentQty, int $tenantId, int $level, array $visited): array
    {
        $children = [];
        $baseQty = $bom->base_quantity > 0 ? $bom->base_quantity : 1.0;
        $multiplier = $parentQty / $baseQty;

        foreach ($bom->items as $item) {
            $netQty = $item->quantity * $multiplier;
            $grossQty = $this->applyScrap($netQty, $item->material_scrap_percentage);

            $childProduct = $item->material;

            // Recurse using calculate
            $childTree = $this->calculate($childProduct, $grossQty, $tenantId, $level + 1, $visited, $item->child_bom_id);
            
            $childTree['net_quantity'] = $netQty;
            $childTree['gross_quantity'] = $grossQty;
            $childTree['material_scrap_percentage'] = $item->material_scrap_percentage;
            $childTree['is_alternative'] = $item->is_alternative;
            $childTree['alternative_group'] = $item->alternative_group;
            $childTree['priority'] = $item->priority;
            $childTree['uom_code'] = $item->uom ? $item->uom->code : 'PCS';

            $children[] = $childTree;
        }

        return $children;
    }

    /**
     * Apply scrap loss calculation.
     */
    protected function applyScrap(float $netQty, float $scrapPct): float
    {
        $scrapFactor = 1 + ($scrapPct / 100);
        return $netQty * $scrapFactor;
    }

    /**
     * Generate consolidated flat list of requirements from the tree.
     */
    protected function generateRequirements(array $tree): array
    {
        $flat = [];
        $this->consolidateRequirements($tree, $flat);
        return array_values($flat);
    }

    /**
     * Consolidate nested requirements into a flat list.
     */
    private function consolidateRequirements(array $node, array &$flat): void
    {
        if (empty($node['children'])) {
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
