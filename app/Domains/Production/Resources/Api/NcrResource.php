<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * NcrResource
 *
 * Resource representation for Non-Conformance Reports (NCR).
 */
class NcrResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'ncr_number'          => $this->ncr_number,
            'category'            => $this->category,
            'status'              => $this->status,
            'description'         => $this->description,
            'production_order_id' => $this->production_order_id,
            'order_number'        => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'inspection_id'       => $this->quality_inspection_id,
            'disposition_type'    => $this->disposition_type,
            'disposition_notes'   => $this->disposition_notes,
            'cost_impact'         => $this->cost_impact !== null ? (float) $this->cost_impact : null,
            'closed_at'           => $this->closed_at?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
