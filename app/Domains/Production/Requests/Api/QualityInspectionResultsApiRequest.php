<?php

namespace App\Domains\Production\Requests\Api;

class QualityInspectionResultsApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'results'                 => 'required|array|min:1',
            'results.*.parameter_id'  => 'required|integer',
            'results.*.value_numeric' => 'nullable|numeric',
            'results.*.value_pass'    => 'nullable|boolean',
            'results.*.value_text'    => 'nullable|string',
            'passed'                  => 'nullable|boolean',
            'remarks'                 => 'nullable|string|max:1000',
        ];
    }
}
