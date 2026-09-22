<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MachineResource
 *
 * Resource for Machine master data, maintenance status, and state telemetry.
 */
class MachineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'work_center_id'            => $this->work_center_id,
            'name'                      => $this->name,
            'code'                      => $this->code,
            'machine_type'              => $this->machine_type,
            'manufacturer'              => $this->manufacturer,
            'model_number'              => $this->model_number,
            'capacity'                  => (float) $this->capacity,
            'status'                    => $this->status,
            'current_state'             => $this->current_state,
            'current_state_reason'      => $this->current_state_reason,
            'last_maintenance_date'     => $this->last_maintenance_date?->format('Y-m-d'),
            'next_maintenance_due_date' => $this->next_maintenance_due_date?->format('Y-m-d'),
            'work_center'               => $this->whenLoaded('workCenter', fn () => [
                'id'   => $this->workCenter->id,
                'name' => $this->workCenter->name,
                'code' => $this->workCenter->code,
            ]),
            'created_at'                => $this->created_at?->toIso8601String(),
            'updated_at'                => $this->updated_at?->toIso8601String(),
        ];
    }
}
