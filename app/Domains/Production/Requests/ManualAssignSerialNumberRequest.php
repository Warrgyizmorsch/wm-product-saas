<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManualAssignSerialNumberRequest extends FormRequest
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
            'serial_number'       => 'required|string|max:100',
            'batch_id'            => 'nullable|integer',
        ];
    }
}
