<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSerialNumberRequest extends FormRequest
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
            'quantity'            => 'required|integer|min:1',
            'prefix'              => 'required|string|max:50',
            'start_num'           => 'required|integer|min:1',
            'batch_id'            => 'nullable|integer',
        ];
    }
}
