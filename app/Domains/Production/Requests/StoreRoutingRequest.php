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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'version'        => $this->input('version') ?: '1.0.0',
            'effective_from' => $this->input('effective_from') ?: now()->toDateString(),
        ]);
    }

    public function rules(): array
    {
        $operationTypes = implode(',', RoutingOperation::TYPES);

        return [
            // Header
            'routing_number'     => 'nullable|string|max:50',
            'name'               => 'required|string|max:255',
            'product_id'         => 'required|exists:products,id',
            'version'            => 'nullable|string|max:50',
            'is_default'         => 'boolean',
            'effective_from'     => 'nullable|date',
            'effective_to'       => 'nullable|date|after_or_equal:effective_from',
            'description'        => 'nullable|string',

            // Operations grid
            'operations'                                      => 'required|array|min:1',
            'operations.*.sequence'                           => 'required|integer|min:1',
            'operations.*.name'                               => 'required|string|max:255',
            'operations.*.operation_type'                     => "required|in:{$operationTypes}",
            'operations.*.work_center_id'                     => 'nullable|exists:production_work_centers,id',
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
            'operations.*.subcontract_lead_time_days'         => 'nullable|numeric|min:0',
            'operations.*.subcontract_cost_per_unit'          => 'nullable|numeric|min:0',
            'operations.*.subcontract_service_product_id'     => 'nullable|exists:products,id',
            'operations.*.material_supply_type'               => 'nullable|in:company_supplied,vendor_supplied,none',
            'operations.*.subcontract_input_type'            => 'nullable|in:bom_raw_materials,previous_operation_wip',
            'operations.*.dispatch_buffer_days'               => 'nullable|numeric|min:0',
            'operations.*.return_buffer_days'                 => 'nullable|numeric|min:0',
            'operations.*.queue_threshold_enabled'            => 'nullable|boolean',
            'operations.*.overlap_enabled'                    => 'nullable|boolean',
            'operations.*.transfer_batch_quantity'            => 'nullable|numeric|min:0',
            'operations.*.transfer_lag_minutes'               => 'nullable|integer|min:0',
            'operations.*.material_id'                        => 'nullable|exists:products,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $operations = $this->input('operations', []);
            usort($operations, fn($a, $b) => ($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0));
            $totalOps = count($operations);

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

                // Rule 2: Queue Threshold validation
                $queueEnabled = filter_var($op['queue_threshold_enabled'] ?? $op['overlap_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $batchQty = (float) ($op['transfer_batch_quantity'] ?? 0);

                if ($queueEnabled) {
                    if ($batchQty <= 0) {
                        $validator->errors()->add(
                            "operations.{$index}.transfer_batch_quantity",
                            "Transfer batch quantity must be greater than zero when Queue Threshold is enabled."
                        );
                    }
                    if ($index === $totalOps - 1) {
                        $validator->errors()->add(
                            "operations.{$index}.queue_threshold_enabled",
                            "Queue Threshold cannot be enabled on the final operation because it has no successor operation."
                        );
                    }
                }
            }

            // Rule 3: Conditional validation based on is_external
            foreach ($operations as $index => $op) {
                $isExternal   = filter_var($op['is_external'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $machineId    = !empty($op['machine_id']) ? (int) $op['machine_id'] : null;
                $workCenterId = !empty($op['work_center_id']) ? (int) $op['work_center_id'] : null;
                $vendorId     = !empty($op['vendor_id']) ? (int) $op['vendor_id'] : null;

                if ($isExternal) {
                    if (empty($vendorId)) {
                        $validator->errors()->add(
                            "operations.{$index}.vendor_id",
                            "Vendor is required for outsourced operations."
                        );
                    }
                    if (isset($op['subcontract_lead_time_days']) && (float) $op['subcontract_lead_time_days'] < 0) {
                        $validator->errors()->add(
                            "operations.{$index}.subcontract_lead_time_days",
                            "Subcontract lead time days cannot be negative."
                        );
                    }
                    if (isset($op['dispatch_buffer_days']) && (float) $op['dispatch_buffer_days'] < 0) {
                        $validator->errors()->add(
                            "operations.{$index}.dispatch_buffer_days",
                            "Dispatch buffer days cannot be negative."
                        );
                    }
                    if (isset($op['return_buffer_days']) && (float) $op['return_buffer_days'] < 0) {
                        $validator->errors()->add(
                            "operations.{$index}.return_buffer_days",
                            "Return buffer days cannot be negative."
                        );
                    }
                } else {
                    if (empty($workCenterId)) {
                        $validator->errors()->add(
                            "operations.{$index}.work_center_id",
                            "Work center is required for internal operations."
                        );
                    }

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
