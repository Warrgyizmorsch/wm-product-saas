<?php

namespace App\Domains\Production\Requests\Api;

use App\Domains\Production\Models\RoutingOperation;

class UpdateRoutingApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $operationTypes = implode(',', RoutingOperation::TYPES);

        return [
            'name'                                => 'sometimes|required|string|max:255',
            'version'                             => 'nullable|string|max:50',
            'is_default'                          => 'nullable|boolean',
            'effective_from'                      => 'nullable|date',
            'effective_to'                        => 'nullable|date|after_or_equal:effective_from',
            'description'                         => 'nullable|string',
            'operations'                          => 'nullable|array|min:1',
            'operations.*.sequence'               => 'required_with:operations|integer|min:1',
            'operations.*.name'                   => 'required_with:operations|string|max:255',
            'operations.*.operation_type'         => "required_with:operations|in:{$operationTypes}",
            'operations.*.work_center_id'         => 'nullable|exists:production_work_centers,id',
            'operations.*.machine_id'             => 'nullable|exists:production_machines,id',
        ];
    }
}
