<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionScheduleCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();

        return [
            'start' => 'nullable|date',
            'view' => 'nullable|in:day,week,month',
            'layout' => 'nullable|in:gantt,list',
            'work_center_id' => [
                'nullable',
                Rule::exists('production_work_centers', 'id')->where('tenant_id', $tenantId),
            ],
            'machine_id' => [
                'nullable',
                Rule::exists('production_machines', 'id')->where('tenant_id', $tenantId),
                function ($attribute, $value, $fail) {
                    if ($this->filled('work_center_id')) {
                        $wcId = $this->input('work_center_id');
                        $machine = \App\Domains\Production\Models\Machine::withoutGlobalScopes()->find($value);
                        if ($machine && $machine->work_center_id != $wcId) {
                            $fail('The selected machine does not belong to the selected work center.');
                        }
                    }
                }
            ],
            'production_order_id' => [
                'nullable',
                Rule::exists('production_orders', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => [
                'nullable',
                Rule::in([
                    ProductionSchedule::STATUS_DRAFT,
                    ProductionSchedule::STATUS_SCHEDULED,
                    ProductionSchedule::STATUS_RELEASED,
                    ProductionSchedule::STATUS_COMPLETED,
                    ProductionSchedule::STATUS_CANCELLED,
                ]),
            ],
            'operation_status' => [
                'nullable',
                Rule::in(ProductionScheduleOperation::STATUSES),
            ],
            'shift_id' => [
                'nullable',
                Rule::exists('production_shifts', 'id')->where('tenant_id', $tenantId),
            ],
            'conflict_type' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'work_center_id.exists' => 'The selected work center is invalid or does not belong to this tenant.',
            'machine_id.exists' => 'The selected machine is invalid or does not belong to this tenant.',
            'production_order_id.exists' => 'The selected production order is invalid or does not belong to this tenant.',
            'shift_id.exists' => 'The selected shift is invalid or does not belong to this tenant.',
        ];
    }
}
