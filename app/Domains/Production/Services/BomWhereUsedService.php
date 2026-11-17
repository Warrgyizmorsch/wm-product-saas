<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\RoutingOperationMaterial;
use Illuminate\Support\Collection;

class BomWhereUsedService
{
    /**
     * Finds parent products consuming the product as a subassembly or component.
     * Checks approved/active parent BOMs across operation allocations and legacy items.
     */
    public function findParents(?Product $product): Collection
    {
        if (!$product) {
            return collect();
        }

        $tenantId = $product->tenant_id;

        $allocParents = RoutingOperationMaterial::where('material_id', $product->id)
            ->whereHas('bom', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                      ->whereIn('status', ['approved', 'active']);
            })
            ->with(['bom.product'])
            ->get()
            ->map(fn ($om) => $om->bom?->product)
            ->filter();

        $legacyParents = ProductionBomItem::where('material_id', $product->id)
            ->whereHas('bom', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                      ->whereIn('status', ['approved', 'active']);
            })
            ->with(['bom.product'])
            ->get()
            ->map(fn ($item) => $item->bom?->product)
            ->filter();

        return $allocParents->concat($legacyParents)->unique('id')->values();
    }

    /**
     * Finds full parent BOM records consuming the product as a subassembly or component.
     * Includes all statuses for full engineering traceability.
     */
    public function findParentBoms(?Product $product): Collection
    {
        if (!$product) {
            return collect();
        }

        $tenantId = $product->tenant_id;

        $allocBoms = RoutingOperationMaterial::where('material_id', $product->id)
            ->whereHas('bom', fn ($query) => $query->where('tenant_id', $tenantId))
            ->with(['bom.product'])
            ->get()
            ->map(fn ($om) => $om->bom)
            ->filter();

        $legacyBoms = ProductionBomItem::where('material_id', $product->id)
            ->whereHas('bom', fn ($query) => $query->where('tenant_id', $tenantId))
            ->with(['bom.product'])
            ->get()
            ->map(fn ($item) => $item->bom)
            ->filter();

        return $allocBoms->concat($legacyBoms)->unique('id')->values();
    }
}
