<?php

namespace App\Domains\Production\Requests\Api;

class UpdatePlanApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name'        => 'sometimes|required|string|max:255',
            'quantity'    => 'sometimes|required|numeric|gt:0',
            'start_date'  => 'sometimes|required|date',
            'end_date'    => 'sometimes|required|date|after_or_equal:start_date',
            'bom_id'      => 'nullable|integer|exists:production_boms,id',
            'routing_id'  => 'nullable|integer|exists:routings,id',
            'description' => 'nullable|string|max:1000',
        ];
    }
}
