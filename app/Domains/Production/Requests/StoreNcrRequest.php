<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNcrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category'            => 'required|string|in:material,process,machine,human_error',
            'description'         => 'required|string',
            'production_order_id' => 'nullable|integer',
        ];
    }
}
