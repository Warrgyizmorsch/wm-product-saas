<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\RoutingOperation;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoutingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $operationTypes = implode(',', RoutingOperation::TYPES);

        return [
            // Header
            'routing_number'     => 'nullable|string|max:50',
            'name'               => 'required|string|max:255',
            'product_id'         => 'required|exists:products,id',
            'version'            => 'required|string|max:50',
            'is_default'         => 'boolean',
            'effective_from'     => 'required|date',
            'effective_to'       => 'nullable|date|after_or_equal:effective_from',
            'description'        => 'nullable|string',

            // Operations grid
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

            // Rule 1: Sequence numbers must be unique within the routing
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

            // Rule 2: Machine must belong to selected work center
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

            // Rule 3: External operation note (vendor_id warning — no vendor table yet)
            foreach ($operations as $index => $op) {
                $isExternal = !empty($op['is_external']);
                $vendorId   = !empty($op['vendor_id']) ? (int) $op['vendor_id'] : null;
                // Future: when vendor module exists, enforce: if is_external => vendor_id required
                // For now: just flag if is_external without vendor_id as a soft warning (no hard error)
            }
        });
    }

    public function messages(): array
    {
        return [
            'operations.required'           => 'At least one operation is required.',
            'operations.min'                => 'At least one routing operation must be defined.',
            'operations.*.work_center_id.required' => 'Work center is required for each operation.',
            'operations.*.name.required'    => 'Operation name is required.',
            'operations.*.operation_type.required' => 'Operation type is required.',
            'operations.*.operation_type.in' => 'Invalid operation type selected.',
            'operations.*.processing_time_minutes.min' => 'Processing time cannot be negative.',
            'operations.*.setup_time_minutes.min'      => 'Setup time cannot be negative.',
            'operations.*.expected_yield_percentage.min' => 'Yield percentage must be greater than 0.',
            'operations.*.expected_yield_percentage.max' => 'Yield percentage cannot exceed 100.',
        ];
    }
}
