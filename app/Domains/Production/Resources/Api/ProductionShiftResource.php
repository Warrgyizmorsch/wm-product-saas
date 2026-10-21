<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductionShiftResource
 *
 * Resource representation for Production Shifts.
 */
class ProductionShiftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'code'             => $this->code,
            'start_time'       => $this->start_time,
            'end_time'         => $this->end_time,
            'break_minutes'    => (int) $this->break_minutes,
            'overtime_allowed' => (bool) $this->overtime_allowed,
            'active'           => (bool) $this->active,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
