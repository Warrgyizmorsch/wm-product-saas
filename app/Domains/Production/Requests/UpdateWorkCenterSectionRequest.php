<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Models\WorkCenterSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkCenterSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $supportsManual = filter_var($this->input('supports_manual_production'), FILTER_VALIDATE_BOOLEAN);
        if (!$supportsManual) {
            $this->merge([
                'supports_manual_production' => false,
                'manual_capacity_per_hour'   => null,
            ]);
        } else {
            $this->merge([
                'supports_manual_production' => true,
            ]);
        }

        $shifts = $this->input('shifts');
        if (is_array($shifts)) {
            $normalizedShifts = [];
            foreach ($shifts as $key => $val) {
                if (is_array($val)) {
                    $sId = (int) ($val['id'] ?? $val['shift_id'] ?? $key);
                    $normalizedShifts[] = $sId;
                } else {
                    $normalizedShifts[] = (int) $val;
                }
            }
            $this->merge([
                'shifts' => array_values(array_unique(array_filter($normalizedShifts))),
            ]);
        }
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();
        $sectionId = $this->route('work_center_section') ?? $this->route('section');
        $workCenterId = $this->input('work_center_id');

        return [
            'work_center_id'             => [
                'required',
                'integer',
                Rule::exists('production_work_centers', 'id')->where('tenant_id', $tenantId),
            ],
            'name'                       => 'required|string|max:255',
            'code'                       => [
                'required',
                'string',
                'max:50',
                Rule::unique('production_work_center_sections')
                    ->where('tenant_id', $tenantId)
                    ->where('work_center_id', $workCenterId)
                    ->ignore($sectionId),
            ],
            'workers_required'           => 'nullable|integer|min:1',
            'supports_manual_production' => 'sometimes|boolean',
            'manual_capacity_per_hour'   => [
                Rule::requiredIf(fn () => (bool) $this->input('supports_manual_production')),
                'nullable',
                'numeric',
                'gt:0',
                'max:99999999.9999',
            ],
            'description'                => 'nullable|string',
            'status'                     => ['required', Rule::in(WorkCenterSection::STATUSES)],
            'shifts'                     => 'required|array|min:1',
            'shifts.*'                   => [
                'integer',
                Rule::exists('production_shifts', 'id')->where('tenant_id', $tenantId),
            ],
            'machine_ids'                => 'nullable|array',
            'machine_ids.*'              => [
                'integer',
                Rule::exists('production_machines', 'id')->where('tenant_id', $tenantId),
            ],
            'sync_machines'              => 'nullable',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $machineIds = $this->input('machine_ids');
            $sectionId = (int) ($this->route('work_center_section') ?? $this->route('section'));
            if (!empty($machineIds) && is_array($machineIds)) {
                $tenantId = require_tenant_id();
                $machines = \App\Domains\Production\Models\Machine::where('tenant_id', $tenantId)
                    ->whereIn('id', $machineIds)
                    ->get();

                foreach ($machines as $machine) {
                    if ($machine->isDecommissioned()) {
                        $validator->errors()->add('machine_ids', "Machine '{$machine->name}' is decommissioned and cannot be assigned.");
                    }
                    if ($machine->section_id !== null && $machine->section_id !== $sectionId) {
                        $validator->errors()->add('machine_ids', "Machine '{$machine->name}' is already assigned to another section.");
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.unique'                        => 'A section with this code already exists in this work center.',
            'shifts.required'                    => 'Every section must have at least one assigned shift.',
            'shifts.min'                         => 'Every section must have at least one assigned shift.',
            'shifts.*.exists'                    => 'One or more selected shifts do not exist.',
            'machine_ids.*.exists'               => 'One or more selected machines do not exist or belong to another organization.',
            'manual_capacity_per_hour.required'  => 'Manual capacity per hour is required when manual production is enabled.',
            'manual_capacity_per_hour.numeric'   => 'Manual capacity per hour must be a valid number.',
            'manual_capacity_per_hour.gt'        => 'Manual capacity per hour must be greater than zero.',
        ];
    }
}
