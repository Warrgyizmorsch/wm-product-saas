<?php

namespace App\Domains\Production\Requests\Api;

use App\Domains\Production\Models\RoutingOperation;

class StoreRoutingApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $operationTypes = implode(',', RoutingOperation::TYPES);

        return [
            'routing_number'                      => 'nullable|string|max:50',
            'name'                                => 'required|string|max:255',
            'product_id'                          => 'required|exists:products,id',
            'version'                             => 'nullable|string|max:50',
            'is_default'                          => 'nullable|boolean',
            'effective_from'                      => 'nullable|date',
            'effective_to'                        => 'nullable|date|after_or_equal:effective_from',
            'description'                         => 'nullable|string',
            'operations'                          => 'required|array|min:1',
            'operations.*.sequence'               => 'required|integer|min:1',
            'operations.*.name'                   => 'required|string|max:255',
            'operations.*.operation_type'         => "required|in:{$operationTypes}",
            'operations.*.work_center_id'         => 'nullable|exists:production_work_centers,id',
            'operations.*.machine_id'             => 'nullable|exists:production_machines,id',
            'operations.*.setup_time_minutes'     => 'nullable|numeric|min:0',
            'operations.*.processing_time_minutes'=> 'nullable|numeric|min:0',
        ];
    }
}
