<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductionBomListResource
 *
 * Compact summary resource for BOM listing.
 */
class ProductionBomListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'bom_number'     => $this->bom_number,
            'bom_name'       => $this->bom_name,
            'bom_type'       => $this->bom_type,
            'usage_context'  => $this->usage_context,
            'base_quantity'  => (float) $this->base_quantity,
            'version'        => $this->version,
            'revision'       => (int) $this->revision,
            'status'         => $this->status,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'product'        => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
                'sku'  => $this->product->sku ?? null,
            ]),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
