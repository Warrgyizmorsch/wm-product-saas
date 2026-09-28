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
            'code'           => $this->code,
            'version'        => $this->version,
            'type'           => $this->type,
            'status'         => $this->status,
            'product_id'     => $this->product_id,
            'product'        => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
                'sku'  => $this->product->sku ?? null,
            ]),
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
                'target_value'    => $p->target_value !== null ? (float) $p->target_value : null,
                'unit_of_measure' => $p->unit_of_measure,
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
