<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Models\Machine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();

        return [
            'section_id'         => [
                'nullable',
                'integer',
                Rule::exists('production_work_center_sections', 'id')->where('tenant_id', $tenantId),
            ],
            'asset_id'           => 'nullable|integer|exists:assets,id',
            'name'               => 'required|string|max:255',
            'code'               => [
                'required', 'string', 'max:50',
                Rule::unique('production_machines')->where('tenant_id', $tenantId),
            ],
            'machine_type'       => 'nullable|string|max:100',
            'manufacturer'       => 'nullable|string|max:255',
            'capacity'                => 'required|numeric|gt:0|max:99999999.9999',
            'energy_source'           => ['nullable', 'string', Rule::in(['electricity', 'petrol', 'diesel', 'others'])],
            'consumption_per_hour'    => 'nullable|numeric|min:0',
            'energy_rate_per_unit'    => 'nullable|numeric|min:0',
            'operating_cost_per_hour' => 'nullable|numeric|min:0',
            'status'                  => ['required', Rule::in(Machine::STATUSES)],
            'installation_date'       => 'nullable|date',
            'maintenance_status'      => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'        => 'A machine with this code already exists in your organization.',
            'section_id.exists'  => 'The selected section does not exist.',
            'capacity.required'  => 'The machine hourly capacity is required.',
            'capacity.numeric'   => 'The machine hourly capacity must be a valid number.',
            'capacity.gt'        => 'The machine hourly capacity must be greater than zero.',
        ];
    }
}
