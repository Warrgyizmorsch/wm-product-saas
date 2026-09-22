<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductionOrderListResource
 *
 * Compact summary resource optimized for high-performance listing and mobile queues.
 */
class ProductionOrderListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'order_number'      => $this->order_number,
            'status'            => $this->status,
            'production_model'  => $this->production_model,
            'quantity_ordered'  => (float) $this->quantity_ordered,
            'quantity_produced' => (float) $this->quantity_produced,
            'quantity_rejected' => (float) $this->quantity_rejected,
            'quantity_scrapped' => (float) $this->quantity_scrapped,
            'start_date'        => $this->start_date?->format('Y-m-d'),
            'end_date'          => $this->end_date?->format('Y-m-d'),
            'product'           => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
                'sku'  => $this->product->sku ?? null,
            ]),
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
