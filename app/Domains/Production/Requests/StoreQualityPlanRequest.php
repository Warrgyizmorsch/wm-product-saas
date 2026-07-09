<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQualityPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                       => 'required|string|max:255',
            'version'                    => 'required|string|max:50',
            'type'                       => 'required|string|in:product,product_category,process,work_center',
            'product_id'                 => 'nullable|exists:products,id',
            'work_center_id'             => 'nullable|exists:production_work_centers,id',
            'status'                     => 'nullable|string|in:draft,submitted,approved,archived',
            'parameters'                 => 'required|array|min:1',
            'parameters.*.name'          => 'required|string|max:255',
            'parameters.*.type'          => 'required|string|in:numeric,pass_fail,text',
            'parameters.*.min_value'     => 'nullable|numeric',
            'parameters.*.max_value'     => 'nullable|numeric',
            'parameters.*.unit_of_measure' => 'nullable|string|max:50',
            'parameters.*.sampling_type' => 'nullable|string|max:50',
            'parameters.*.sampling_value'=> 'nullable|numeric|min:0',
            'parameters.*.is_mandatory'  => 'nullable|boolean',
        ];
    }
}
