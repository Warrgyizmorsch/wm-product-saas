<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductionBomDetailResource
 *
 * Full-fidelity detail resource for Bill of Materials including component lines.
 */
class ProductionBomDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'bom_number'      => $this->bom_number,
            'bom_name'        => $this->bom_name,
            'bom_type'        => $this->bom_type,
            'usage_context'   => $this->usage_context,
            'base_quantity'   => (float) $this->base_quantity,
            'version'         => $this->version,
            'revision'        => (int) $this->revision,
            'revision_reason' => $this->revision_reason,
            'status'          => $this->status,
            'effective_date'  => $this->effective_date?->format('Y-m-d'),
            'expiry_date'     => $this->expiry_date?->format('Y-m-d'),
            'notes'           => $this->notes,
            'product'         => $this->whenLoaded('product', fn () => [
                'id'       => $this->product->id,
                'name'     => $this->product->name,
                'sku'      => $this->product->sku ?? null,
                'uom_name' => $this->product->uom?->name ?? null,
            ]),
            'base_uom'        => $this->whenLoaded('baseUom', fn () => [
                'id'   => $this->baseUom->id,
                'name' => $this->baseUom->name,
                'code' => $this->baseUom->code ?? null,
            ]),
            'items'           => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id'                => $item->id,
                'item_product_id'   => $item->product_id,
                'item_product_name' => $item->product?->name ?? null,
                'item_sku'          => $item->product?->sku ?? null,
                'quantity'          => (float) $item->quantity,
                'scrap_percentage'  => (float) $item->scrap_percentage,
                'net_quantity'      => (float) $item->net_quantity,
                'gross_quantity'    => (float) $item->gross_quantity,
                'is_critical'       => (bool) $item->is_critical,
                'item_type'         => $item->item_type,
            ])),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
