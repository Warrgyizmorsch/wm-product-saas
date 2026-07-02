<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBomItem;
use Illuminate\Support\Collection;

class BomWhereUsedService
{
    /**
     * Finds parent products consuming the product as a subassembly or component.
     * Checks approved/active parent BOMs.
     */
    public function findParents(Product $product): Collection
    {
        $tenantId = $product->tenant_id;

        $items = ProductionBomItem::where('material_id', $product->id)
            ->whereHas('bom', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                      ->where('status', 'approved');
            })
            ->with(['bom.product'])
            ->get();

        return $items->map(function ($item) {
            return $item->bom->product;
        })->unique('id')->values();
    }

    /**
     * Finds full parent BOM records consuming the product as a subassembly or component.
     * Includes all statuses for full engineering traceability.
     */
    public function findParentBoms(Product $product): Collection
    {
        $tenantId = $product->tenant_id;

        $items = ProductionBomItem::where('material_id', $product->id)
            ->whereHas('bom', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->with(['bom.product'])
            ->get();

        return $items->map(function ($item) {
            return $item->bom;
        })->unique('id')->values();
    }
}
