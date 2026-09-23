<?php

namespace App\Domains\Production\Requests\Api;

use Illuminate\Validation\Rule;

class StoreWorkCenterApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $tenantId = require_tenant_id();

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
            'overhead_rate'         => 'nullable|numeric|min:0',
            'status'                => 'required|in:active,inactive',
            'type'                  => ['nullable', 'string', Rule::in(['department', 'section', 'work_center', 'machine_group'])],
        ];
    }
}
