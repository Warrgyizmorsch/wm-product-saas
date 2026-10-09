<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MaintenanceWorkOrderResource
 *
 * Resource representation for Plant Maintenance Work Orders.
 */
class MaintenanceWorkOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'work_order_number'      => $this->work_order_number,
            'machine_id'             => $this->machine_id,
            'machine'                => $this->whenLoaded('machine', fn () => [
                'id'            => $this->machine->id,
                'name'          => $this->machine->name,
                'code'          => $this->machine->code,
                'current_state' => $this->machine->current_state ?? null,
            ]),
            'type'                   => $this->type,
            'priority'               => $this->priority,
            'status'                 => $this->status,
            'assigned_technician_id' => $this->assigned_technician_id,
            'technician'             => $this->whenLoaded('technician', fn () => [
                'id'   => $this->technician->id,
                'name' => $this->technician->name,
            ]),
            'problem_description'    => $this->problem_description,
            'work_performed'         => $this->work_performed,
            'planned_start'          => $this->planned_start?->toIso8601String(),
            'planned_end'            => $this->planned_end?->toIso8601String(),
            'actual_start'           => $this->actual_start?->toIso8601String(),
            'actual_end'             => $this->actual_end?->toIso8601String(),
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
        ];
    }
}
