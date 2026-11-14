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
            'location'              => 'nullable|string|max:255',
            // Short Full Review Point 2: API money is DB base currency (config('currency.base')). overhead_cost = flat amount per day; overhead_rate = deprecated alias.
            'overhead_cost'         => 'nullable|numeric|min:0',
            'overhead_rate'         => 'nullable|numeric|min:0',
            'status'                => 'sometimes|required|in:active,inactive',
        ];
    }
}
