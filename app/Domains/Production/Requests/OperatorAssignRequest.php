<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OperatorAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'production_order_operation_id' => 'required|integer',
            'user_id'                       => 'required|integer',
            'remarks'                       => 'nullable|string|max:1000',
        ];
    }
}
