<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'production_order_id' => 'required|integer',
            'product_id'          => 'required|integer',
            'planned_quantity'    => 'required|numeric|min:0.0001',
            'expiry_date'         => 'nullable|date',
            'remarks'             => 'nullable|string|max:1000',
        ];
    }
}
