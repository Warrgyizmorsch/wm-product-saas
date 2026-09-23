<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductionPlanDetailResource
 *
 * Detailed resource for Production Plan inspection.
 */
class ProductionPlanDetailResource extends JsonResource
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
            'description' => $this->description,
            'product'     => $this->whenLoaded('product', fn () => [
                'id'       => $this->product->id,
                'name'     => $this->product->name,
                'sku'      => $this->product->sku ?? null,
                'uom_name' => $this->product->uom?->name ?? null,
            ]),
            'bom'         => $this->whenLoaded('bom', fn () => [
                'id'         => $this->bom->id,
                'bom_number' => $this->bom->bom_number,
                'version'    => $this->bom->version,
            ]),
            'routing'     => $this->whenLoaded('routing', fn () => [
                'id'             => $this->routing->id,
                'routing_number' => $this->routing->routing_number,
                'name'           => $this->routing->name,
            ]),
            'orders'      => $this->whenLoaded('productionOrders', fn () => $this->productionOrders->map(fn ($order) => [
                'id'                => $order->id,
                'order_number'      => $order->order_number,
                'status'            => $order->status,
                'quantity_ordered'  => (float) $order->quantity_ordered,
                'quantity_produced' => (float) $order->quantity_produced,
            ])),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
