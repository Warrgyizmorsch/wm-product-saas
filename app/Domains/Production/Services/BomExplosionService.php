<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBom;
use InvalidArgumentException;

/**
 * Multi-level BOM explosion.
 *
 * BM-IMPL-16 (BM-20):
 *  - explodeBom() starts from a GIVEN BOM (the show page explodes the version being viewed — drafts included);
 *    explode() keeps the product-based entry (product's active BOM) for MRP-style callers.
 *  - Demand uses the allocation gross quantity (qty × (1 + scrap %)), primary rows only.
 *  - A sub-assembly is exploded only through the sub-BOM chosen on its allocation (BM-IMPL-08 resolver);
 *    no chosen sub-BOM → leaf (no "newest approved BOM" guessing).
 *  - Alternatives (alternative rows and materials on alternative operations) are listed as info rows:
 *    not recursed, not summed.
 * BM-IMPL-09: formula parameters removed.
 */
class BomExplosionService
{
    private const RELATIONS = ['operationMaterials.material.uom', 'operationMaterials.uom', 'operationMaterials.routingOperation', 'items.material.uom', 'items.uom'];

    /**
     * Explode a product's active BOM.
     */
    public function explode(int $productId, float $quantity, int $tenantId): array
    {
        $product = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($productId);

        if (!$product) {
            throw new InvalidArgumentException("Target finished product not found.");
        }

        return $this->run($product, $quantity, $tenantId, null);
    }

    /**
     * BM-IMPL-16 #1: explode the given BOM (any status) — used by the BOM show page.
     */
    public function explodeBom(int $bomId, float $quantity, int $tenantId): array
    {
        $bom = ProductionBom::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($bomId);
        if (!$bom) {
            throw new InvalidArgumentException("BOM not found.");
        }
        $product = Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($bom->product_id);
        if (!$product) {
            throw new InvalidArgumentException("Target finished product not found.");
        }

        return $this->run($product, $quantity, $tenantId, $bom->id);
    }

    private function run(Product $product, float $quantity, int $tenantId, ?int $bomId): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Explosion quantity must be greater than zero.");
        }

        $visited = [];
        $tree = $this->calculate($product, $quantity, $tenantId, 1, $visited, $bomId);

        return [
            'tree' => $tree,
            'flat' => $this->generateRequirements($tree),
        ];
    }

    /**
     * Calculate explosion node for a given product at a specific level.
     * Level 1 without a forced BOM → the product's active BOM; below level 1 only the chosen sub-BOM is followed.
     */
    protected function calculate(Product $product, float $quantity, int $tenantId, int $level, array $visited, ?int $forcedBomId = null, bool $legacyActivePick = false): array
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
            'uom_code' => $product->uom?->code ?? 'PCS',
            'level' => $level,
            'children' => [],
            'has_sub_bom' => false,
        ];

        $bom = null;
        if ($forcedBomId) {
            $bom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', $forcedBomId)
                ->with(self::RELATIONS)
                ->first();
        } elseif ($level === 1 || $legacyActivePick) {
            $bom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $product->id)
                ->whereIn('status', ['approved', 'active'])
                ->whereIn('bom_type', ['manufacturing', 'subcontracting', 'phantom'])
                ->orderByRaw("CASE WHEN bom_type = 'manufacturing' THEN 1 WHEN bom_type = 'subcontracting' THEN 2 WHEN bom_type = 'phantom' THEN 3 ELSE 4 END")
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->with(self::RELATIONS)
                ->first();
        }

        if ($bom) {
            $node['has_sub_bom'] = true;
            $node['bom_id'] = $bom->id;
            $node['bom_number'] = $bom->bom_number;
            $node['bom_name'] = $bom->bom_name;
            $node['bom_version'] = $bom->version;
            $node['bom_status'] = $bom->status;
            $node['bom_type'] = $bom->bom_type;
            $node['bom_base_quantity'] = (float) $bom->base_quantity;
            $node['bom_effective_date'] = $bom->effective_date ? $bom->effective_date->toDateString() : null;
            $node['is_phantom'] = ($bom->bom_type === 'phantom');
            $node['uom_code'] = $bom->baseUom ? $bom->baseUom->code : ($product->uom ? $product->uom->code : 'PCS');

            $node['children'] = $this->resolveChildren($bom, $quantity, $tenantId, $level, $visited);
        }

        return $node;
    }

    /**
     * Resolve child rows for a BOM from its allocations.
     */
    protected function resolveChildren(ProductionBom $bom, float $parentQty, int $tenantId, int $level, array $visited): array
    {
        $children = [];
        $baseQty = $bom->base_quantity > 0 ? $bom->base_quantity : 1.0;
        $multiplier = $parentQty / $baseQty;

        // Legacy BOMs saved before the two-tier model have master rows only (no allocations, no stored sub-BOM
        // choice) — read those rows and keep the old "active BOM of the sub-assembly" lookup for them only.
        $legacy = $bom->operationMaterials->isEmpty();
        $sourceRows = $legacy ? $bom->items : $bom->operationMaterials;

        foreach ($sourceRows as $item) {
            $childProduct = $item->material;
            if (!$childProduct) {
                continue;
            }

            $unitQty = (float) $item->quantity;
            $scrapPct = (float) ($item->material_scrap_percentage ?? 0);
            $netQty = $unitQty * $multiplier;
            $grossUnitQty = ($item->gross_quantity !== null && (float) $item->gross_quantity > 0) ? (float) $item->gross_quantity : $unitQty * (1 + $scrapPct / 100);
            $grossQty = $grossUnitQty * $multiplier;

            // Alternative row, or a material on an alternative operation → info only
            $isAlternative = !$legacy && ((bool) $item->is_alternative || (bool) ($item->routingOperation?->is_alternative));

            if ($isAlternative) {
                $childTree = [
                    'product_id' => $childProduct->id,
                    'product_name' => $childProduct->name,
                    'product_sku' => $childProduct->sku,
                    'quantity' => $grossQty,
                    'level' => $level + 1,
                    'children' => [],
                    'has_sub_bom' => false,
                ];
            } else {
                $childTree = $this->calculate($childProduct, $grossQty, $tenantId, $level + 1, $visited, $legacy ? null : ($item->child_bom_id ?: null), $legacy);
            }

            $childTree['net_quantity'] = $netQty;
            $childTree['gross_quantity'] = $grossQty;
            $childTree['material_scrap_percentage'] = $scrapPct;
            $childTree['is_alternative'] = $isAlternative;
            $childTree['replaces_item_id'] = $legacy ? null : $item->replaces_routing_material_id;
            $childTree['priority'] = (!$legacy && $item->is_alternative) ? $item->priority : null;
            $childTree['operation_sequence'] = $legacy ? null : $item->routingOperation?->sequence;
            $childTree['uom_code'] = $item->uom?->code ?? $item->material?->uom?->code ?? 'PCS';

            $children[] = $childTree;
        }

        return $children;
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
     * Consolidate nested requirements into a flat list (leaves only; alternatives skipped).
     * Blows through Phantom BOMs (is_phantom = true at level > 1).
     */
    private function consolidateRequirements(array $node, array &$flat): void
    {
        if (!empty($node['is_alternative'])) {
            return;
        }

        $isPhantom = !empty($node['is_phantom']) && $node['level'] > 1;

        if (empty($node['children']) || $isPhantom) {
            if ($node['level'] === 1 && empty($node['children'])) {
                return;
            }

            if (!$isPhantom) {
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
            }
        }

        if (!empty($node['children'])) {
            foreach ($node['children'] as $child) {
                $this->consolidateRequirements($child, $flat);
            }
        }
    }
}
