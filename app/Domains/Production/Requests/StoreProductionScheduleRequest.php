<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Models\ProductionSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'production_order_id' => 'required|integer|exists:production_orders,id',
            'scheduling_type'     => ['required', 'string', Rule::in(ProductionSchedule::SCHEDULING_TYPES)],
            'start_date'          => 'required|date',
            'notes'               => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'production_order_id.required' => 'A Production Order must be selected.',
            'production_order_id.exists'   => 'The selected Production Order does not exist.',
            'scheduling_type.required'     => 'Scheduling type is required.',
            'scheduling_type.in'           => 'Invalid scheduling type. Allowed: ' . implode(', ', ProductionSchedule::SCHEDULING_TYPES),
            'start_date.required'          => 'Schedule start date is required.',
            'start_date.date'              => 'Schedule start date must be a valid date.',
        ];
    }
}
