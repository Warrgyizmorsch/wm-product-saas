<?php

namespace App\Domains\Production\Requests\Api;

use App\Domains\Production\Requests\Concerns\ValidatesRoutingOperations;

/**
 * RT-IMPL-07: the API uses the SAME operation rules and checks as the web form
 * (section, manual batch, alternatives, machine XOR batch, tenant-scoped exists).
 * RT-IMPL-01: routings have no product any more — product_id is no longer required.
 */
class UpdateRoutingApiRequest extends ApiFormRequest
{
    use ValidatesRoutingOperations;

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
        return array_merge([
            'name'            => 'sometimes|required|string|max:255',
            'version'         => 'nullable|string|max:50',
            'is_default'      => 'nullable|boolean',
            'effective_from'  => 'nullable|date',
            'effective_to'    => 'nullable|date|after_or_equal:effective_from',
            'description'     => 'nullable|string',
            // API-only aliases (normalised in prepareForValidation)
            'operations.*.operation_sequence'        => 'nullable|integer|min:1',
            'operations.*.operation_name'            => 'nullable|string|max:255',
            'operations.*.run_time_per_unit_minutes' => 'nullable|numeric|min:0',
            'operations.*.run_time_minutes'          => 'nullable|numeric|min:0',
        ], $this->routingOperationRules(false));
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (is_array($this->input('operations'))) {
                $this->validateRoutingOperations($validator, $this->input('operations'));
            }
        });
    }

    public function messages(): array
    {
        return $this->routingOperationMessages();
    }
}
