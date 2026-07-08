<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NcrDispositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'disposition_type'             => 'required|string|in:rework,scrap,use_as_is',
            'original_production_order_id' => 'nullable|integer',
            'cost_estimate'                => 'nullable|numeric|min:0',
            'work_center_id'               => 'nullable|integer',
            'category'                     => 'nullable|string|max:255',
            'reason_code'                  => 'nullable|string|max:255',
            'quantity'                     => 'nullable|numeric|min:0',
            'cost'                         => 'nullable|numeric|min:0',
        ];
    }
}
