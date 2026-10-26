<?php

namespace App\Domains\Production\Requests\Api;

use App\Domains\Production\Models\Machine;
use Illuminate\Validation\Rule;

class StoreMachineApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $tenantId = require_tenant_id();

        return [
            'section_id'         => [
                'nullable',
                'integer',
                Rule::exists('production_work_center_sections', 'id')->where('tenant_id', $tenantId),
            ],
            'name'               => 'required|string|max:255',
            'code'               => [
                'required', 'string', 'max:50',
                Rule::unique('production_machines')->where('tenant_id', $tenantId),
            ],
            'machine_type'       => 'nullable|string|max:100',
            'manufacturer'       => 'nullable|string|max:255',
            'model_number'       => 'nullable|string|max:100',
            'capacity'           => 'required|numeric|gt:0|max:99999999.9999',
            'status'             => ['required', Rule::in(Machine::STATUSES)],
            'installation_date'  => 'nullable|date',
        ];
    }
}
