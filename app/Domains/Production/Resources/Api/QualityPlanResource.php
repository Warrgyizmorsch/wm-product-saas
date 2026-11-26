<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * QualityPlanResource
 *
 * Resource representation for Quality Assurance Plans.
 */
class QualityPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'plan_number'    => $this->plan_number,
            'root_plan_id'   => $this->root_plan_id,
            'version'        => $this->version,
            'type'           => $this->type,
            'status'         => $this->status,
            'product_id'     => $this->product_id,
            'product'        => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
                'sku'  => $this->product->sku ?? null,
            ]),
            'section_id'     => $this->section_id,
            'work_center_id' => $this->work_center_id,
            'work_center'    => $this->whenLoaded('workCenter', fn () => [
                'id'   => $this->workCenter->id,
                'name' => $this->workCenter->name,
                'code' => $this->workCenter->code,
            ]),
            'parameters'     => $this->whenLoaded('parameters', fn () => $this->parameters->map(fn ($p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'type'            => $p->type,
                'min_value'       => $p->min_value !== null ? (float) $p->min_value : null,
                'max_value'       => $p->max_value !== null ? (float) $p->max_value : null,
                'uom_id'          => $p->uom_id,
                'unit_of_measure' => $p->unit_of_measure,
                'basis'           => $p->basis,
                'sampling_type'   => $p->sampling_type,
                'sampling_value'  => $p->sampling_value !== null ? (float) $p->sampling_value : null,
                'is_mandatory'    => (bool) $p->is_mandatory,
            ])),
            'approved_at'    => $this->approved_at?->toIso8601String(),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
