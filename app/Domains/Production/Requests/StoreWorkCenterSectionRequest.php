<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Models\WorkCenterSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkCenterSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();
        $workCenterId = $this->input('work_center_id');

        return [
            'work_center_id'   => [
                'required',
                'integer',
                Rule::exists('production_work_centers', 'id')->where('tenant_id', $tenantId),
            ],
            'name'             => 'required|string|max:255',
            'code'             => [
                'required',
                'string',
                'max:50',
                Rule::unique('production_work_center_sections')
                    ->where('tenant_id', $tenantId)
                    ->where('work_center_id', $workCenterId),
            ],
            'workers_required' => 'required|integer|min:1',
            'description'      => 'nullable|string',
            'status'           => ['required', Rule::in(WorkCenterSection::STATUSES)],
            'shifts'           => 'required|array|min:1',
            'shifts.*'         => [
                'integer',
                Rule::exists('production_shifts', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'        => 'A section with this code already exists in this work center.',
            'shifts.required'    => 'Every section must have at least one assigned shift.',
            'shifts.min'         => 'Every section must have at least one assigned shift.',
            'shifts.*.exists'    => 'One or more selected shifts do not exist.',
        ];
    }
}
