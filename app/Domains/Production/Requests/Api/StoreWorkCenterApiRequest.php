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
            'location'              => 'nullable|string|max:255',
            'overhead_rate'         => 'nullable|numeric|min:0',
            'status'                => 'required|in:active,inactive',
        ];
    }
}
