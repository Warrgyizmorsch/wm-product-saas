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
            'work_center_id'     => 'required|exists:production_work_centers,id',
            'name'               => 'required|string|max:255',
            'code'               => [
                'required', 'string', 'max:50',
                Rule::unique('production_machines')->where('tenant_id', $tenantId),
            ],
            'machine_type'       => 'nullable|string|max:100',
            'manufacturer'       => 'nullable|string|max:255',
            'model_number'       => 'nullable|string|max:100',
            'capacity'           => 'nullable|numeric|min:0',
            'status'             => ['required', Rule::in(Machine::STATUSES)],
            'installation_date'  => 'nullable|date',
        ];
    }
}
