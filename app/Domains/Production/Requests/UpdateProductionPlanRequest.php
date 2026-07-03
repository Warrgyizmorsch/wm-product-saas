<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'product_id'  => 'required|integer|exists:products,id',
            'quantity'    => 'required|numeric|gt:0',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'bom_id'      => 'nullable|integer|exists:production_boms,id',
            'routing_id'  => 'nullable|integer|exists:routings,id',
            'description' => 'nullable|string|max:1000',
        ];
    }
}
