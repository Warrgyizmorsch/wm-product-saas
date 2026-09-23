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
            'items'                             => 'nullable|array|min:1',
            'items.*.material_id'               => 'required_with:items|exists:products,id',
            'items.*.quantity'                  => 'required_with:items|numeric|gt:0',
            'items.*.uom_id'                    => 'required_with:items|exists:uoms,id',
            'items.*.material_scrap_percentage' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
