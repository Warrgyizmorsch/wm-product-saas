<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * QualityInspectionResource
 *
 * Resource for Quality Control inspections and results.
 */
class QualityInspectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'inspection_number'   => $this->inspection_number,
            'stage'               => $this->stage,
            'status'              => $this->status,
            'result'              => $this->result,
            'sample_size'         => (int) $this->sample_size,
            'inspected_quantity'  => (float) $this->inspected_quantity,
            'passed_qty'          => (float) $this->passed_qty,
            'failed_qty'          => (float) $this->failed_qty,
            'production_order_id' => $this->production_order_id,
            'order_number'        => $this->order?->order_number,
            'remarks'             => $this->remarks,
            'inspected_at'        => $this->inspected_at?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
