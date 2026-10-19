<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Models\Machine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();
        $machineId = $this->route('machine');

        return [
            'section_id'         => [
                'required',
                'integer',
                Rule::exists('production_work_center_sections', 'id')->where('tenant_id', $tenantId),
            ],
            'name'               => 'required|string|max:255',
            'code'               => [
                'required', 'string', 'max:50',
                Rule::unique('production_machines')
                    ->where('tenant_id', $tenantId)
                    ->ignore($machineId),
            ],
            'machine_type'       => 'nullable|string|max:100',
            'manufacturer'       => 'nullable|string|max:255',
            'model_number'       => 'nullable|string|max:100',
            'capacity'           => 'nullable|numeric|min:0',
            'status'             => ['required', Rule::in(Machine::STATUSES)],
            'installation_date'  => 'nullable|date',
            'maintenance_status' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'        => 'A machine with this code already exists in your organization.',
            'section_id.required'=> 'A section must be selected for the machine.',
            'section_id.exists'  => 'The selected section does not exist.',
        ];
    }
}
