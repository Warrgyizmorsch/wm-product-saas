<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductionPlanListResource
 *
 * Compact summary resource for Production Plans list.
 */
class ProductionPlanListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'plan_number' => $this->plan_number,
            'name'        => $this->name,
            'quantity'    => (float) $this->quantity,
            'status'      => $this->status,
            'start_date'  => $this->start_date?->format('Y-m-d'),
            'end_date'    => $this->end_date?->format('Y-m-d'),
            'product'     => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
                'sku'  => $this->product->sku ?? null,
            ]),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
