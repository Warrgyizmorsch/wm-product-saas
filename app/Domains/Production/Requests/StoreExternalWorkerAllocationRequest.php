<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalWorkerAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();

        return [
            'section_id' => [
                'required',
                'integer',
                Rule::exists('production_work_center_sections', 'id')->where('tenant_id', $tenantId),
            ],
            'shift_id' => [
                'required',
                'integer',
                Rule::exists('production_shifts', 'id')->where('tenant_id', $tenantId),
            ],
            'pay_grade_id' => [
                'required',
                'integer',
                Rule::exists('production_external_worker_pay_grades', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'number_of_external_workers' => [
                'required',
                'integer',
                'min:1',
            ],
            'active' => [
                'nullable',
                'boolean',
            ],
            'update_if_exists' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'number_of_external_workers.required' => 'Number of external workers is required.',
            'number_of_external_workers.min'      => 'Number of external workers must be at least 1.',
        ];
    }
}
