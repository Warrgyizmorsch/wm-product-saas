<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handled in controller
    }

    public function rules(): array
    {
        $tenantId = tenant_id() ?? 1;

        return [
            'name'                  => 'required|string|max:255',
            'code'                  => [
                'required', 'string', 'max:50',
                Rule::unique('production_work_centers')->where('tenant_id', $tenantId),
            ],
            'work_center_type'      => 'nullable|string|max:100',
            'description'           => 'nullable|string',
            'department_name'       => 'nullable|string|max:255',
            'location'              => 'nullable|string|max:255',
            'capacity_per_hour'     => 'nullable|numeric|min:0',
            'efficiency_percentage' => 'nullable|numeric|min:0|max:100',
            'cost_per_hour'         => 'nullable|numeric|min:0',
            'status'                => 'required|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'A work center with this code already exists in your organization.',
        ];
    }
}
