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
            'is_default'     => $this->has('is_default') ? (bool) $this->input('is_default') : true,
        ]);
    }

    public function rules(): array
    {
        $operationTypes = implode(',', RoutingOperation::TYPES);

        return [
            // Header
            'routing_number'         => 'nullable|string|max:50',
            'name'                   => 'required|string|max:255',
            'product_id'             => 'required|exists:products,id',
            'bom_id'                 => 'nullable|exists:production_boms,id',
            'material_quantity_mode' => 'nullable|in:per_unit,per_bom_base_quantity',
            'version'                => 'nullable|string|max:50',
            'is_default'             => 'boolean',
            'effective_from'         => 'nullable|date',
            'effective_to'           => 'nullable|date|after_or_equal:effective_from',
            'description'            => 'nullable|string',

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
            'operations.*.transfer_lag_minutes'               => 'nullable|numeric|min:0',
            'operations.*.material_id'                        => 'nullable|exists:products,id',
            'operations.*.materials'                          => 'nullable|array',
            'operations.*.materials.*.material_id'            => 'required|exists:products,id',
            'operations.*.materials.*.quantity'               => 'required|numeric|gt:0',
            'operations.*.materials.*.uom_id'                 => 'nullable|exists:uoms,id',
            'operations.*.is_parallel'                        => 'nullable|boolean',
            'operations.*.parallel_group'                     => 'nullable|string|max:50',
            'operations.*.predecessor_sequence'               => 'nullable',
            'operations.*.previous_operation_id'              => 'nullable|integer',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // Date lifecycle validation
            try {
                app(\App\Domains\Production\Services\RoutingLifecycleService::class)->validateEffectiveDates(
                    $this->input('effective_from'),
                    $this->input('effective_to'),
                    (int) $this->input('product_id'),
                    null,
                    tenant_id() ?? 1
                );
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('effective_from', $e->getMessage());
            }

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

            // Rule 4: BOM Component Assignment & Over-Allocation Validation
            $mode = $this->input('material_quantity_mode', \App\Domains\Production\Models\Routing::MODE_PER_UNIT);
            $allocatedByMaterial = [];
            $allAssignedMaterialIds = [];

            foreach ($operations as $opIndex => $op) {
                $matRows = [];
                if (!empty($op['materials']) && is_array($op['materials'])) {
                    foreach ($op['materials'] as $mIdx => $mat) {
                        if (!empty($mat['material_id'])) {
                            $qty = isset($mat['quantity']) ? (float) $mat['quantity'] : 0.0;
                            if ($qty <= 0) {
                                $validator->errors()->add(
                                    "operations.{$opIndex}.materials.{$mIdx}.quantity",
                                    "Material quantity must be greater than zero."
                                );
                            }
                            $matRows[] = [
                                'material_id' => (int) $mat['material_id'],
                                'quantity'    => $qty,
                            ];
                        }
                    }
                } elseif (!empty($op['material_id'])) {
                    $qty = isset($op['material_quantity']) ? (float) $op['material_quantity'] : 1.0;
                    $matRows[] = [
                        'material_id' => (int) $op['material_id'],
                        'quantity'    => $qty,
                    ];
                }

                foreach ($matRows as $m) {
                    $mId = $m['material_id'];
                    $q = $m['quantity'];
                    $allAssignedMaterialIds[] = $mId;
                    $allocatedByMaterial[$mId] = ($allocatedByMaterial[$mId] ?? 0.0) + $q;
                }
            }

            $bomId = $this->input('bom_id');
            $bom = null;
            if ($bomId) {
                $bom = \App\Domains\Production\Models\ProductionBom::with(['items.material', 'items.uom'])->find($bomId);
            } elseif ($this->input('product_id')) {
                $bom = \App\Domains\Production\Models\ProductionBom::with(['items.material', 'items.uom'])
                    ->where('product_id', $this->input('product_id'))
                    ->whereIn('status', ['approved', 'active', 'draft'])
                    ->latest('id')
                    ->first();
            }

            if ($bom) {
                $baseQuantity = (float) $bom->base_quantity;
                if ($baseQuantity <= 0) {
                    $validator->errors()->add('bom_id', "The selected BOM '{$bom->bom_number}' has an invalid base quantity ({$baseQuantity}). Base quantity must be greater than zero.");
                    return;
                }

                $bomItems = $bom->items->where('is_alternative', false);

                if ($bomItems->isNotEmpty()) {
                    $bomMaterialIds = $bomItems->pluck('material_id')->all();

                    // Check foreign components not belonging to the selected BOM
                    foreach (array_unique($allAssignedMaterialIds) as $assignedMatId) {
                        if (!in_array($assignedMatId, $bomMaterialIds, true)) {
                            $matProd = \App\Domains\Inventory\Models\Product::find($assignedMatId);
                            $matName = $matProd?->name ?? "Material #{$assignedMatId}";
                            $validator->errors()->add(
                                'operations',
                                "Component '{$matName}' does not belong to the selected Bill of Materials '{$bom->bom_number}'."
                            );
                        }
                    }

                    // Rule A: Every BOM component must be assigned to at least one operation
                    $unassignedComponents = [];
                    foreach ($bomItems as $item) {
                        if (!in_array($item->material_id, $allAssignedMaterialIds, true)) {
                            $compName = $item->material?->name ?? "Product #{$item->material_id}";
                            $compSku = $item->material?->sku ?? '';
                            $unassignedComponents[] = $compSku ? "{$compName} ({$compSku})" : $compName;
                        }
                    }

                    if (!empty($unassignedComponents)) {
                        $missingList = implode(', ', $unassignedComponents);
                        $validator->errors()->add(
                            'operations',
                            "Every BOM component must be assigned to at least one routing operation. Unassigned component(s): {$missingList}."
                        );
                    }

                    // Rule C: Over-allocation is prohibited (normalized comparison)
                    $partialWarnings = [];

                    foreach ($bomItems as $item) {
                        $matId = $item->material_id;
                        $enteredTotalAlloc = $allocatedByMaterial[$matId] ?? 0.0;

                        if ($mode === \App\Domains\Production\Models\Routing::MODE_PER_BOM_BASE_QUANTITY) {
                            $normalizedAlloc = $enteredTotalAlloc / $baseQuantity;
                            $bomReqInMode = (float) $item->quantity;
                            $enteredBasisLabel = "per BOM base quantity ({$baseQuantity})";
                        } else {
                            $normalizedAlloc = $enteredTotalAlloc;
                            $bomReqInMode = (float) $item->quantity / $baseQuantity;
                            $enteredBasisLabel = "per 1 unit";
                        }

                        $normalizedBomReq = (float) $item->quantity / $baseQuantity;
                        $compName = $item->material?->name ?? "Product #{$matId}";
                        $compSku = $item->material?->sku ?? '';
                        $uomName = $item->uom?->name ?? $item->uom?->code ?? 'units';
                        $displayName = $compSku ? "{$compName} ({$compSku})" : $compName;

                        if ($normalizedAlloc > ($normalizedBomReq + 0.00001)) {
                            $excessNormalized = $normalizedAlloc - $normalizedBomReq;
                            $excessInMode = $mode === \App\Domains\Production\Models\Routing::MODE_PER_BOM_BASE_QUANTITY 
                                ? ($excessNormalized * $baseQuantity) 
                                : $excessNormalized;

                            $validator->errors()->add(
                                'operations',
                                "Component '{$displayName}' is over-allocated. BOM requirement is " . round($bomReqInMode, 4) . " {$uomName} {$enteredBasisLabel}, but total allocated across operations is " . round($enteredTotalAlloc, 4) . " {$uomName} (exceeds by " . round($excessInMode, 4) . " {$uomName})."
                            );
                        } elseif ($normalizedAlloc < ($normalizedBomReq - 0.00001) && in_array($matId, $allAssignedMaterialIds, true)) {
                            $remainingInMode = $bomReqInMode - $enteredTotalAlloc;
                            $partialWarnings[] = "Component '{$displayName}' has " . round($remainingInMode, 4) . " {$uomName} unallocated ({$enteredBasisLabel}).";
                        }
                    }

                    if (!empty($partialWarnings)) {
                        session()->flash('routing_allocation_warnings', $partialWarnings);
                        session()->flash('warning', 'Partially Allocated Components: ' . implode('; ', $partialWarnings));
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
