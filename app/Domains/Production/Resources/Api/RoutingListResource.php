<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RoutingListResource
 *
 * Compact summary resource for manufacturing Routings list.
 */
class RoutingListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'routing_number' => $this->routing_number,
            'name'           => $this->name,
            'version'        => $this->version,
            'revision'       => (int) $this->revision,
            'is_default'     => (bool) $this->is_default,
            'status'         => $this->status,
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_to'   => $this->effective_to?->format('Y-m-d'),
            'product'        => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
                'sku'  => $this->product->sku ?? null,
            ]),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
