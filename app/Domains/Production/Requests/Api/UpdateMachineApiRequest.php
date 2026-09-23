<?php

namespace App\Domains\Production\Requests\Api;

use App\Domains\Production\Models\Machine;
use Illuminate\Validation\Rule;

class UpdateMachineApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $tenantId = require_tenant_id();
        $machineId = $this->route('machine') ?? $this->route('id');

        return [
            'work_center_id'     => 'sometimes|required|exists:production_work_centers,id',
            'name'               => 'sometimes|required|string|max:255',
            'code'               => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('production_machines')->where('tenant_id', $tenantId)->ignore($machineId),
            ],
            'machine_type'       => 'nullable|string|max:100',
            'manufacturer'       => 'nullable|string|max:255',
            'model_number'       => 'nullable|string|max:100',
            'capacity'           => 'nullable|numeric|min:0',
            'status'             => ['sometimes', 'required', Rule::in(Machine::STATUSES)],
        ];
    }
}
