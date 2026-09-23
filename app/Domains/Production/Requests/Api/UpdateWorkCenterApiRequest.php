<?php

namespace App\Domains\Production\Requests\Api;

use Illuminate\Validation\Rule;

class UpdateWorkCenterApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $tenantId = require_tenant_id();
        $workCenterId = $this->route('work_center') ?? $this->route('id');

        return [
            'name'                  => 'sometimes|required|string|max:255',
            'code'                  => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('production_work_centers')->where('tenant_id', $tenantId)->ignore($workCenterId),
            ],
            'work_center_type'      => 'nullable|string|max:100',
            'description'           => 'nullable|string',
            'department_name'       => 'nullable|string|max:255',
            'location'              => 'nullable|string|max:255',
            'capacity_per_hour'     => 'nullable|numeric|min:0',
            'efficiency_percentage' => 'nullable|numeric|min:0|max:100',
            'cost_per_hour'         => 'nullable|numeric|min:0',
            'overhead_rate'         => 'nullable|numeric|min:0',
            'status'                => 'sometimes|required|in:active,inactive',
        ];
    }
}
