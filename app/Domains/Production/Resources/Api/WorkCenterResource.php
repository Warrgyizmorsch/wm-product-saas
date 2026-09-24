<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * WorkCenterResource
 *
 * Resource for Work Center master data and capacity details.
 */
class WorkCenterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'code'                  => $this->code,
            'work_center_type'      => $this->work_center_type,
            'description'           => $this->description,
            'department_name'       => $this->department_name,
            'location'              => $this->location,
            'capacity_per_hour'     => (float) $this->capacity_per_hour,
            'efficiency_percentage' => (float) $this->efficiency_percentage,
            'cost_per_hour'         => (float) $this->cost_per_hour,
            'overhead_rate'         => (float) $this->overhead_rate,
            'status'                => $this->status,
            'type'                  => $this->type,
            'machines_count'        => $this->whenCounted('machines'),
            'machines'              => $this->whenLoaded('machines', fn () => $this->machines->map(fn ($m) => [
                'id'            => $m->id,
                'name'          => $m->name,
                'code'          => $m->code,
                'status'        => $m->status,
                'current_state' => $m->current_state ?? null,
            ])),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
