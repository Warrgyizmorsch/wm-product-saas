<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExternalWorkerAllocationQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number_of_external_workers' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'number_of_external_workers.required' => 'Number of external workers is required.',
            'number_of_external_workers.integer'  => 'Number of external workers must be an integer.',
            'number_of_external_workers.min'      => 'Number of external workers must be at least 1.',
        ];
    }
}
