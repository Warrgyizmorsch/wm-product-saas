<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handled in controller
    }

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
            'overhead_cost'         => 'nullable|numeric|min:0',
            'status'                => (config('production.temp_flow') ? 'nullable' : 'required') . '|in:active,inactive',
            'production_calendar_id' => [
                'nullable', 'integer',
                Rule::exists('production_calendars', 'id')->where('tenant_id', $tenantId)
            ],
            'calculation_countdown_minutes' => (config('production.temp_flow') ? 'nullable' : 'required') . '|integer|min:1|max:1440',
        ];
    }

    /** Temp no-BOM flow: status and countdown are optional and default when left empty. */
    protected function prepareForValidation(): void
    {
        if (config('production.temp_flow')) {
            $this->merge([
                'status' => $this->input('status') ?: 'active',
                'calculation_countdown_minutes' => $this->input('calculation_countdown_minutes') ?: 60,
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'A work center with this code already exists in your organization.',
            'calculation_countdown_minutes.required' => 'The debounce countdown minutes field is required.',
            'calculation_countdown_minutes.min'      => 'The debounce countdown minutes must be at least 1.',
            'calculation_countdown_minutes.max'      => 'The debounce countdown minutes may not be greater than 1440.',
        ];
    }
}
