<?php

namespace App\Domains\Production\Requests\Api;

use App\Domains\Production\Models\RoutingOperation;

class UpdateRoutingApiRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('operations') && is_array($this->operations)) {
            $normalized = [];
            foreach ($this->operations as $op) {
                if (!is_array($op)) {
                    continue;
                }
                if (!isset($op['sequence']) && isset($op['operation_sequence'])) {
                    $op['sequence'] = $op['operation_sequence'];
                }
                if (!isset($op['name']) && isset($op['operation_name'])) {
                    $op['name'] = $op['operation_name'];
                }
                if (!isset($op['processing_time_minutes'])) {
                    if (isset($op['run_time_per_unit_minutes'])) {
                        $op['processing_time_minutes'] = $op['run_time_per_unit_minutes'];
                    } elseif (isset($op['run_time_minutes'])) {
                        $op['processing_time_minutes'] = $op['run_time_minutes'];
                    }
                }
                $normalized[] = $op;
            }
            $this->merge(['operations' => $normalized]);
        }
    }

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
            'operations.*.operation_sequence'     => 'nullable|integer|min:1',
            'operations.*.operation_number'       => 'nullable|string|max:50',
            'operations.*.name'                   => 'required_with:operations|string|max:255',
            'operations.*.operation_name'         => 'nullable|string|max:255',
            'operations.*.operation_type'         => "required_with:operations|in:{$operationTypes}",
            'operations.*.work_center_id'         => 'nullable|exists:production_work_centers,id',
            'operations.*.machine_id'             => 'nullable|exists:production_machines,id',
            'operations.*.setup_time_minutes'     => 'nullable|numeric|min:0',
            'operations.*.processing_time_minutes'=> 'nullable|numeric|min:0',
            'operations.*.run_time_per_unit_minutes' => 'nullable|numeric|min:0',
            'operations.*.run_time_minutes'       => 'nullable|numeric|min:0',
            'operations.*.wait_time_minutes'      => 'nullable|numeric|min:0',
            'operations.*.expected_yield_percentage' => 'nullable|numeric|min:0|max:100',
            'operations.*.labor_cost_rate'        => 'nullable|numeric|min:0',
            'operations.*.machine_cost_rate'      => 'nullable|numeric|min:0',
            'operations.*.description'            => 'nullable|string',
            'operations.*.instructions'           => 'nullable|string',
            'operations.*.quality_required'       => 'nullable|boolean',
            'operations.*.is_external'            => 'nullable|boolean',
            'operations.*.vendor_id'              => 'nullable|exists:vendors,id',
            'operations.*.transfer_batch_quantity'=> 'nullable|numeric|min:0',
            'operations.*.transfer_lag_minutes'   => 'nullable|integer|min:0',
        ];
    }
}
