<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalWorkerPayGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('production_external_worker_pay_grades')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'hourly_rate' => [
                'required',
                'numeric',
                'min:0',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'A pay grade with this code already exists for your organization.',
            'hourly_rate.min' => 'Hourly rate cannot be negative.',
        ];
    }
}
