<?php

namespace App\Domains\Production\Requests\Api;

class StoreBomApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'bom_number'                        => 'nullable|string|max:255',
            'bom_name'                          => 'nullable|string|max:255',
            'bom_type'                          => 'required|in:manufacturing,engineering,sales,phantom,subcontracting',
            'usage_context'                     => 'nullable|string|in:manufacturing,engineering,prototype,costing',
            'product_id'                        => 'required|exists:products,id',
            'base_quantity'                     => 'required|numeric|gt:0',
            'base_uom_id'                       => 'required|exists:uoms,id',
            'version'                           => 'required|string|max:50',
            'routing_id'                        => 'nullable|exists:routings,id',
            'effective_date'                    => 'required|date',
            'expiry_date'                       => 'nullable|date|after_or_equal:effective_date',
            'notes'                             => 'nullable|string',
            'items'                             => 'required|array|min:1',
            'items.*.material_id'               => 'required|exists:products,id',
            'items.*.quantity'                  => 'required|numeric|gt:0',
            'items.*.uom_id'                    => 'required|exists:uoms,id',
            'items.*.material_scrap_percentage' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
