<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductionWipResource
 *
 * Resource representation for Work-In-Progress (WIP) tracking.
 * Adheres strictly to quantity and operational stage tracking without monetary valuation.
 */
class ProductionWipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                           => $this->id,
            'production_order_id'          => $this->production_order_id,
            'order_number'                 => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'production_batch_id'          => $this->production_batch_id,
            'product_id'                   => $this->product_id,
            'product'                      => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
                'sku'  => $this->product->sku ?? null,
            ]),
            'current_work_center_id'       => $this->current_work_center_id,
            'current_work_center'          => $this->whenLoaded('currentWorkCenter', fn () => [
                'id'   => $this->currentWorkCenter->id,
                'name' => $this->currentWorkCenter->name,
                'code' => $this->currentWorkCenter->code,
            ]),
            'current_machine_id'           => $this->current_machine_id,
            'current_machine'              => $this->whenLoaded('currentMachine', fn () => [
                'id'   => $this->currentMachine->id,
                'name' => $this->currentMachine->name,
                'code' => $this->currentMachine->code,
            ]),
            'current_routing_operation_id' => $this->current_routing_operation_id,
            'quantity'                     => (float) $this->quantity,
            'available_quantity'           => (float) $this->available_quantity,
            'completed_quantity'           => (float) $this->completed_quantity,
            'rejected_quantity'            => (float) $this->rejected_quantity,
            'scrap_quantity'               => (float) $this->scrap_quantity,
            'rework_quantity'              => (float) $this->rework_quantity,
            'status'                       => $this->status,
            'started_at'                   => $this->started_at?->toIso8601String(),
            'last_moved_at'                => $this->last_moved_at?->toIso8601String(),
            'completed_at'                 => $this->completed_at?->toIso8601String(),
            'created_at'                   => $this->created_at?->toIso8601String(),
            'updated_at'                   => $this->updated_at?->toIso8601String(),
        ];
    }
}
