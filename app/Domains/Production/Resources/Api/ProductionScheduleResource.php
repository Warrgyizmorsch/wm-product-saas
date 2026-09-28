<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductionScheduleResource
 *
 * Resource representation for Production Schedules.
 */
class ProductionScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'schedule_number'     => $this->schedule_number,
            'production_order_id' => $this->production_order_id,
            'order'               => $this->whenLoaded('order', fn () => [
                'id'           => $this->order->id,
                'order_number' => $this->order->order_number,
                'product_name' => $this->order->product?->name ?? null,
                'product_sku'  => $this->order->product?->sku ?? null,
            ]),
            'scheduling_type'     => $this->scheduling_type,
            'status'              => $this->status,
            'start_date'          => $this->start_date?->format('Y-m-d H:i:s'),
            'end_date'            => $this->end_date?->format('Y-m-d H:i:s'),
            'notes'               => $this->notes,
            'operations'          => $this->whenLoaded('operations', fn () => $this->operations->map(fn ($op) => [
                'id'                       => $op->id,
                'sequence'                 => $op->sequence,
                'operation_number'         => $op->operation_number,
                'name'                     => $op->name,
                'work_center_name'         => $op->workCenter?->name,
                'machine_name'             => $op->machine?->name,
                'status'                   => $op->status,
                'planned_start'            => $op->planned_start?->toIso8601String(),
                'planned_finish'           => $op->planned_finish?->toIso8601String(),
                'planned_duration_minutes' => (float) $op->planned_duration_minutes,
            ])),
            'released_at'         => $this->released_at?->toIso8601String(),
            'cancelled_at'        => $this->cancelled_at?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
