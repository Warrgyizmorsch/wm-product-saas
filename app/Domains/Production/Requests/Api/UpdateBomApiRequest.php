<?php

namespace App\Domains\Production\Requests\Api;

class UpdateBomApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'bom_name'                          => 'nullable|string|max:255',
            'bom_type'                          => 'sometimes|required|in:manufacturing,engineering,sales,phantom,subcontracting',
            'usage_context'                     => 'nullable|string|in:manufacturing,engineering,prototype,costing',
            'base_quantity'                     => 'sometimes|required|numeric|gt:0',
            'base_uom_id'                       => 'sometimes|required|exists:uoms,id',
            'routing_id'                        => 'nullable|exists:routings,id',
            'effective_date'                    => 'sometimes|required|date',
            'expiry_date'                       => 'nullable|date|after_or_equal:effective_date',
            'notes'                             => 'nullable|string',
            'revision_reason'                   => 'nullable|string|max:1000',
            'items'                             => 'nullable|array|min:1',
            'items.*.material_id'               => 'required_with:items|exists:products,id',
            'items.*.child_bom_id'              => 'nullable|integer|exists:production_boms,id',
            'items.*.quantity'                  => 'required_with:items|numeric|gt:0',
            'items.*.quantity_type'             => 'nullable|string|in:fixed,formula',
            'items.*.formula'                   => 'nullable|string|max:500',
            'items.*.uom_id'                    => 'required_with:items|exists:uoms,id',
            'items.*.material_scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.scrap_factor'              => 'nullable|numeric|min:0|max:100',
            'items.*.is_alternative'            => 'nullable|boolean',
            'items.*.alternative_group'         => 'nullable|string|max:255',
            'items.*.priority'                  => 'nullable|integer|min:1',
            'items.*.sequence'                  => 'nullable|integer|min:1',
            'items.*.effective_from'            => 'nullable|date',
            'items.*.effective_to'              => 'nullable|date|after_or_equal:items.*.effective_from',
            'items.*.notes'                     => 'nullable|string',
        ];
    }
}
