<?php

namespace App\Domains\Production\Requests\Api;

class QualityInspectionResultsApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'results'   => 'required|array|min:1',
            'passed'    => 'nullable|boolean',
            'remarks'   => 'nullable|string|max:1000',
        ];
    }
}
