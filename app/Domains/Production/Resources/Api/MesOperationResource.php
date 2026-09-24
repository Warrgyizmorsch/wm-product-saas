<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MesOperationResource
 *
 * Resource for Shopfloor / MES operation dispatch, execution queue, and live telemetry.
 */
class MesOperationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'production_order_id' => $this->production_order_id,
            'order_number'        => $this->productionOrder?->order_number,
            'product_name'        => $this->productionOrder?->product?->name,
            'sequence'            => (int) ($this->sequence ?? $this->operation_sequence),
            'name'                => $this->name ?? $this->operation_name,
            'status'              => $this->status,
            'target_quantity'     => (float) ($this->target_produced_qty ?? $this->planned_quantity ?? 0),
            'quantity_produced'   => (float) ($this->quantity_produced ?? $this->completed_quantity ?? 0),
            'quantity_rejected'   => (float) $this->quantity_rejected,
            'quantity_scrapped'   => (float) $this->quantity_scrapped,
            'work_center'         => $this->workCenter ? [
                'id'   => $this->workCenter->id,
                'name' => $this->workCenter->name,
                'code' => $this->workCenter->code,
            ] : null,
            'machine'             => $this->machine ? [
                'id'            => $this->machine->id,
                'name'          => $this->machine->name,
                'code'          => $this->machine->code,
                'current_state' => $this->machine->current_state ?? null,
            ] : null,
            'actual_start_time'   => $this->actual_start_time?->toIso8601String(),
            'actual_end_time'     => $this->actual_end_time?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
