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
        $calc = $this->resource->latestCalculation;

        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'code'                  => $this->code,
            'work_center_type'      => $this->work_center_type,
            'description'           => $this->description,
            'location'              => $this->location,
            // Short Full Review Point 2: all money below is DB base currency; figures come from the latest WC calculation snapshot.
            'currency'              => config('currency.base', 'USD'),
            'overhead_cost'         => (float) ($this->overhead_cost ?? 0),
            'capacity_per_hour'     => (float) ($calc?->capacity_per_hour ?? 0),
            'cost_per_hour'         => (float) ($calc?->total_cost_per_hour ?? 0),
            'overhead_rate'         => $this->resource->calculatedOverheadCostPerHour(),
            'latest_calculation'    => $calc ? [
                'id'                    => $calc->id,
                'snapshot_number'       => $calc->snapshot_number,
                'labor_cost_per_hour'   => (float) $calc->labor_cost_per_hour,
                'machine_cost_per_hour' => (float) $calc->machine_cost_per_hour,
                'overhead_cost'         => (float) $calc->overhead_cost,
                'total_cost_per_hour'   => (float) $calc->total_cost_per_hour,
                'total_daily_cost'      => (float) $calc->total_daily_cost,
                'capacity_per_hour'     => (float) $calc->capacity_per_hour,
                'cost_per_unit'         => $calc->cost_per_unit !== null ? (float) $calc->cost_per_unit : null,
                'calculated_at'         => $calc->created_at?->toIso8601String(),
            ] : null,
            'status'                => $this->status,
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
