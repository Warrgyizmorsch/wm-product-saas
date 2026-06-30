<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\RoutingOperation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoutingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $operationTypes = implode(',', RoutingOperation::TYPES);

        return [
            'name'               => 'required|string|max:255',
            'product_id'         => 'required|exists:products,id',
            'version'            => 'required|string|max:50',
            'is_default'         => 'boolean',
            'effective_from'     => 'required|date',
            'effective_to'       => 'nullable|date|after_or_equal:effective_from',
            'description'        => 'nullable|string',

            'operations'                                      => 'required|array|min:1',
            'operations.*.sequence'                           => 'required|integer|min:1',
            'operations.*.name'                               => 'required|string|max:255',
            'operations.*.operation_type'                     => "required|in:{$operationTypes}",
            'operations.*.work_center_id'                     => 'required|exists:production_work_centers,id',
            'operations.*.machine_id'                         => 'nullable|exists:production_machines,id',
            'operations.*.setup_time_minutes'                 => 'nullable|numeric|min:0',
            'operations.*.processing_time_minutes'            => 'nullable|numeric|min:0',
            'operations.*.wait_time_minutes'                  => 'nullable|numeric|min:0',
            'operations.*.expected_yield_percentage'          => 'nullable|numeric|min:0.01|max:100',
            'operations.*.labor_cost_rate'                    => 'nullable|numeric|min:0',
            'operations.*.machine_cost_rate'                  => 'nullable|numeric|min:0',
            'operations.*.description'                        => 'nullable|string',
            'operations.*.instructions'                       => 'nullable|string',
            'operations.*.quality_required'                   => 'nullable|boolean',
            'operations.*.is_external'                        => 'nullable|boolean',
            'operations.*.vendor_id'                          => 'nullable|integer',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $operations = $this->input('operations', []);

            $sequences = [];
            foreach ($operations as $index => $op) {
                $seq = (int) ($op['sequence'] ?? 0);
                if ($seq > 0) {
                    if (in_array($seq, $sequences, true)) {
                        $validator->errors()->add(
                            "operations.{$index}.sequence",
                            "Sequence {$seq} is duplicated. Each operation must have a unique sequence number."
                        );
                    } else {
                        $sequences[] = $seq;
                    }
                }
            }

            foreach ($operations as $index => $op) {
                $machineId    = !empty($op['machine_id']) ? (int) $op['machine_id'] : null;
                $workCenterId = !empty($op['work_center_id']) ? (int) $op['work_center_id'] : null;

                if ($machineId && $workCenterId) {
                    $machine = Machine::find($machineId);
                    if ($machine && $machine->work_center_id !== $workCenterId) {
                        $validator->errors()->add(
                            "operations.{$index}.machine_id",
                            "The selected machine does not belong to the chosen work center."
                        );
                    }
                }
            }
        });
    }
}
