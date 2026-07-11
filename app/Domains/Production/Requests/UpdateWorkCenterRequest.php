<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();
        $workCenterId = $this->route('work_center');

        return [
            'name'                  => 'required|string|max:255',
            'code'                  => [
                'required', 'string', 'max:50',
                Rule::unique('production_work_centers')
                    ->where('tenant_id', $tenantId)
                    ->ignore($workCenterId),
            ],
            'work_center_type'      => 'nullable|string|max:100',
            'description'           => 'nullable|string',
            'department_name'       => 'nullable|string|max:255',
            'location'              => 'nullable|string|max:255',
            'capacity_per_hour'     => 'nullable|numeric|min:0',
            'efficiency_percentage' => 'nullable|numeric|min:0|max:100',
            'cost_per_hour'         => 'nullable|numeric|min:0',
            'status'                => 'required|in:active,inactive',
            'parent_id'             => [
                'nullable', 'integer',
                Rule::exists('production_work_centers', 'id')->where('tenant_id', $tenantId)
            ],
            'type'                  => [
                'nullable', 'string',
                Rule::in(['department', 'section', 'work_center', 'machine_group'])
            ],
            'shifts'                => 'nullable|array',
            'shifts.*'              => [
                'integer',
                Rule::exists('production_shifts', 'id')->where('tenant_id', $tenantId)
            ],
        ];
    }
}
