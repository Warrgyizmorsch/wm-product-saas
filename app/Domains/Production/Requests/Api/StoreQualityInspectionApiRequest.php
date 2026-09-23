<?php

namespace App\Domains\Production\Requests\Api;

class StoreQualityInspectionApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'quality_plan_id'               => 'required|integer|exists:production_quality_plans,id',
            'stage'                         => 'required|string|in:incoming,in_process,final',
            'production_order_id'           => 'nullable|integer|exists:production_orders,id',
            'production_order_operation_id' => 'nullable|integer|exists:production_order_operations,id',
            'machine_id'                    => 'nullable|integer|exists:production_machines,id',
            'operator_id'                   => 'nullable|integer|exists:users,id',
            'batch_id'                      => 'nullable|integer',
            'serial_number_id'              => 'nullable|integer',
        ];
    }
}
