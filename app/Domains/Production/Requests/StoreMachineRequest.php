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
        $tenantId = tenant_id() ?? 1;

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
            'maintenance_status' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'A machine with this code already exists in your organization.',
            'work_center_id.exists' => 'The selected work center does not exist.',
        ];
    }
}
