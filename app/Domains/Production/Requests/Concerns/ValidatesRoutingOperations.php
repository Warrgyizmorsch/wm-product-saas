<?php

namespace App\Domains\Production\Requests\Concerns;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenterSection;
use Illuminate\Validation\Rule;

/**
 * RT-IMPL-07 (RT-06 rule 3): ONE set of routing-operation rules and checks, shared by the web
 * Store/Update requests and the API Store/Update requests — so the API can no longer save operations
 * without a section / batch or skip the internal / external, section, machine-XOR-batch and alternative checks.
 *
 * `exists` rules are tenant-scoped.
 */
trait ValidatesRoutingOperations
{
    /** Tenant-scoped exists rule. */
    protected function tenantExists(string $table): \Illuminate\Validation\Rules\Exists
    {
        $tenantId = tenant_id() ?? 0;

        return Rule::exists($table, 'id')->where(fn($q) => $q->where('tenant_id', $tenantId));
    }

    /**
     * Operation rules. $required = false for partial API updates (operations optional; when present, the
     * same rules apply).
     */
    protected function routingOperationRules(bool $required = true): array
    {
        $operationTypes = implode(',', RoutingOperation::TYPES);
        $req = $required ? 'required' : 'required_with:operations';

        return [
            'operations'                                  => $required ? 'required|array|min:1' : 'nullable|array|min:1',
            'operations.*.sequence'                       => "{$req}|integer|min:1",
            'operations.*.id'                             => 'nullable|integer', // RT-IMPL-09: existing operation (matched within this routing only)
            'operations.*.operation_number'               => 'nullable|string|max:50',
            'operations.*.name'                           => "{$req}|string|max:255",
            'operations.*.operation_type'                 => "{$req}|in:{$operationTypes}",
            'operations.*.work_center_id'                 => ['nullable', $this->tenantExists('production_work_centers')],
            'operations.*.section_id'                     => ['nullable', $this->tenantExists('production_work_center_sections')],
            'operations.*.machine_id'                     => ['nullable', $this->tenantExists('production_machines')],
            'operations.*.section_manual_production_id'   => ['nullable', $this->tenantExists('production_section_manual_productions')],
            'operations.*.is_alternative'                 => 'nullable|boolean',
            'operations.*.row_uid'                        => 'nullable|string',
            'operations.*.replaces_operation_id'          => 'nullable',
            'operations.*.replaces_row_uid'               => 'nullable|string',
            'operations.*.replaces_sequence'              => 'nullable',
            'operations.*.priority'                       => 'nullable|integer|min:1',
            'operations.*.cost_per_unit'                  => 'nullable|numeric|min:0',
            // RT-IMPL-14: every figure is sent (form or API), checked, then saved as sent
            'operations.*.labor_cost_per_unit'            => 'nullable|numeric|min:0',
            'operations.*.machine_cost_per_unit'          => 'nullable|numeric|min:0',
            'operations.*.setup_cost_per_run'             => 'nullable|numeric|min:0',
            'operations.*.section_calculation_id'         => ['nullable', 'integer', $this->tenantExists('production_work_center_section_calculations')],
            'operations.*.section_snapshot_number'        => 'nullable|integer|min:1',
            'operations.*.work_center_calculation_id'     => ['nullable', 'integer', $this->tenantExists('production_work_center_calculations')],
            'operations.*.resource_snapshot'              => 'nullable',
            'operations.*.setup_time_minutes'             => 'nullable|numeric|min:0',
            'operations.*.setup_time_per_piece_minutes'   => 'nullable|numeric|min:0',
            'operations.*.processing_time_minutes'        => 'nullable|numeric|min:0',
            'operations.*.wait_time_minutes'              => 'nullable|numeric|min:0',
            'operations.*.expected_yield_percentage'      => 'nullable|numeric|min:0.01|max:100',
            'operations.*.labor_cost_rate'                => 'nullable|numeric|min:0',
            'operations.*.machine_cost_rate'              => 'nullable|numeric|min:0',
            'operations.*.description'                    => 'nullable|string',
            'operations.*.instructions'                   => 'nullable|string',
            'operations.*.quality_required'               => 'nullable|boolean',
            'operations.*.is_external'                    => 'nullable|boolean',
            'operations.*.vendor_id'                      => ['nullable', 'integer', $this->tenantExists('vendors')],
            'operations.*.subcontract_lead_time_days'     => 'nullable|numeric|min:0',
            'operations.*.subcontract_cost_per_unit'      => 'nullable|numeric|min:0',
            'operations.*.subcontract_service_product_id' => ['nullable', $this->tenantExists('products')],
            'operations.*.material_supply_type'           => 'nullable|in:company_supplied,vendor_supplied,none',
            'operations.*.subcontract_input_type'         => 'nullable|in:bom_raw_materials,previous_operation_wip',
            'operations.*.dispatch_buffer_days'           => 'nullable|numeric|min:0',
            'operations.*.return_buffer_days'             => 'nullable|numeric|min:0',
            'operations.*.queue_threshold_enabled'        => 'nullable|boolean',
            'operations.*.overlap_enabled'                => 'nullable|boolean',
            'operations.*.transfer_batch_quantity'        => 'nullable|numeric|min:0',
            'operations.*.transfer_lag_minutes'           => 'nullable|numeric|min:0',
            'operations.*.is_parallel'                    => 'nullable|boolean',
            'operations.*.parallel_group'                 => 'nullable|string|max:50',
            'operations.*.predecessor_sequence'           => 'nullable',
            'operations.*.previous_operation_id'          => 'nullable|integer',
        ];
    }

    protected function routingOperationMessages(): array
    {
        return [
            'operations.required'                        => 'At least one operation is required.',
            'operations.min'                             => 'At least one routing operation must be defined.',
            'operations.*.work_center_id.required'       => 'Work center is required for each operation.',
            'operations.*.section_id.required'           => 'Section is required for internal operations.',
            'operations.*.name.required'                 => 'Operation name is required.',
            'operations.*.operation_type.required'       => 'Operation type is required.',
            'operations.*.operation_type.in'             => 'Invalid operation type selected.',
            'operations.*.priority.min'                  => 'Alternative operation priority must be at least 1.',
            'operations.*.processing_time_minutes.min'   => 'Processing time cannot be negative.',
            'operations.*.setup_time_minutes.min'        => 'Setup time cannot be negative.',
            'operations.*.expected_yield_percentage.min' => 'Yield percentage must be greater than 0.',
            'operations.*.expected_yield_percentage.max' => 'Yield percentage cannot exceed 100.',
        ];
    }

    /**
     * The operation checks (were inline in StoreRoutingRequest / UpdateRoutingRequest):
     * unique sequences, queue threshold, internal vs external, work center ↔ section, machine XOR batch,
     * machine / batch ↔ section, alternatives.
     */
    protected function validateRoutingOperations($validator, array $operations): void
    {
        usort($operations, fn($a, $b) => ($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0));

        // Rule 1: unique sequences; Rule 2: queue threshold
        $sequences = [];
        foreach ($operations as $index => $op) {
            $seq = (int) ($op['sequence'] ?? 0);
            if ($seq > 0) {
                if (in_array($seq, $sequences, true)) {
                    $validator->errors()->add("operations.{$index}.sequence", "Sequence {$seq} is duplicated. Each operation must have a unique sequence number.");
                } else {
                    $sequences[] = $seq;
                }
            }

            $queueEnabled = filter_var($op['queue_threshold_enabled'] ?? $op['overlap_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($queueEnabled && (float) ($op['transfer_batch_quantity'] ?? 0) <= 0) {
                $validator->errors()->add("operations.{$index}.transfer_batch_quantity", 'Transfer batch quantity must be greater than zero when Queue Threshold is enabled.');
            }
        }

        // Rule 3: internal / external and resource assignment
        foreach ($operations as $index => $op) {
            $isExternal         = filter_var($op['is_external'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $isAlternative      = filter_var($op['is_alternative'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $machineId          = !empty($op['machine_id']) ? (int) $op['machine_id'] : null;
            $workCenterId       = !empty($op['work_center_id']) ? (int) $op['work_center_id'] : null;
            $sectionId          = !empty($op['section_id']) ? (int) $op['section_id'] : null;
            $manualProductionId = !empty($op['section_manual_production_id']) ? (int) $op['section_manual_production_id'] : null;
            $vendorId           = !empty($op['vendor_id']) ? (int) $op['vendor_id'] : null;

            if ($isExternal) {
                if (empty($vendorId)) {
                    $validator->errors()->add("operations.{$index}.vendor_id", 'Vendor is required for outsourced operations.');
                }
                foreach (['subcontract_lead_time_days' => 'Subcontract lead time days', 'dispatch_buffer_days' => 'Dispatch buffer days', 'return_buffer_days' => 'Return buffer days'] as $key => $label) {
                    if (isset($op[$key]) && (float) $op[$key] < 0) {
                        $validator->errors()->add("operations.{$index}.{$key}", "{$label} cannot be negative.");
                    }
                }
            } else {
                if (empty($workCenterId)) {
                    $validator->errors()->add("operations.{$index}.work_center_id", 'Work center is required for internal operations.');
                }

                // RT-IMPL-18 (RT-29): inactive / deleted resources are refused on every path
                if ($workCenterId) {
                    $wcModel = \App\Domains\Production\Models\WorkCenter::find($workCenterId);
                    if (!$wcModel) {
                        $validator->errors()->add("operations.{$index}.work_center_id", 'The selected work center no longer exists. Choose another work center.');
                    } elseif ($wcModel->status !== 'active') {
                        $validator->errors()->add("operations.{$index}.work_center_id", "Work center {$wcModel->code} is inactive. Choose another work center.");
                    }
                }
                if (empty($sectionId)) {
                    $validator->errors()->add("operations.{$index}.section_id", 'Section is required for internal operations.');
                } elseif ($workCenterId) {
                    $section = WorkCenterSection::find($sectionId);
                    if (!$section || (int) $section->work_center_id !== (int) $workCenterId) {
                        $validator->errors()->add("operations.{$index}.section_id", 'The selected section does not belong to the chosen work center.');
                    } elseif ($section->status !== 'active') {
                        $validator->errors()->add("operations.{$index}.section_id", "Section {$section->code} is inactive. Choose another section.");
                    }
                }

                // Machine XOR manual batch
                $hasMachine = !empty($machineId);
                $hasManual  = !empty($manualProductionId);
                if (!$hasMachine && !$hasManual) {
                    $validator->errors()->add("operations.{$index}.machine_id", 'Each internal operation must have either a Machine OR a Manual Production Batch selected.');
                } elseif ($hasMachine && $hasManual) {
                    $validator->errors()->add("operations.{$index}.machine_id", 'An operation cannot have both a Machine and a Manual Production Batch. Select only one.');
                } else {
                    if ($hasMachine && $sectionId) {
                        $machine = Machine::find($machineId);
                        if (!$machine || (int) $machine->section_id !== (int) $sectionId) {
                            $validator->errors()->add("operations.{$index}.machine_id", 'The selected machine does not belong to the chosen section.');
                        } elseif ($machine->status !== Machine::STATUS_ACTIVE) {
                            $validator->errors()->add("operations.{$index}.machine_id", "Machine {$machine->code} is inactive. Choose another machine.");
                        }
                    }
                    if ($hasManual && $sectionId) {
                        $manualBatch = SectionManualProduction::find($manualProductionId);
                        if (!$manualBatch || (int) $manualBatch->section_id !== (int) $sectionId) {
                            $validator->errors()->add("operations.{$index}.section_manual_production_id", 'The selected manual production batch does not belong to the chosen section.');
                        } elseif (!$manualBatch->active) {
                            $validator->errors()->add("operations.{$index}.section_manual_production_id", "Manual batch {$manualBatch->batch_name} is inactive. Choose another batch.");
                        }
                    }
                }
            }

            // RT-IMPL-14 (RT-19 rule 2): re-run the cost formula on the submitted inputs + the latest section snapshot.
            // Mismatch → refuse; match → the submitted values are saved as they are.
            $this->checkOperationCosts($validator, $op, $index, $isExternal, $sectionId, $workCenterId);

            // Alternatives
            if ($isAlternative) {
                if (empty($op['replaces_operation_id']) && empty($op['replaces_row_uid']) && empty($op['replaces_sequence'])) {
                    $validator->errors()->add("operations.{$index}.replaces_operation_id", 'An alternative operation must specify which primary operation it replaces.');
                }
                if (empty($op['priority']) || (int) $op['priority'] < 1) {
                    $validator->errors()->add("operations.{$index}.priority", 'Priority is required for alternative operations and must be at least 1.');
                }
            }
        }

        $this->validateAlternativeTargets($validator, $operations);
    }

    /**
     * RT-IMPL-08 (RT-10 rules 1–2): an alternative replaces a PRIMARY operation of THIS routing, resolved
     * unambiguously (replaces_sequence → replaces_row_uid → replaces_operation_id as an id of a submitted row),
     * and two alternatives of the same primary can't share a priority.
     */
    protected function validateAlternativeTargets($validator, array $operations): void
    {
        $bySeq = [];
        $byUid = [];
        $byId = [];
        foreach ($operations as $i => $op) {
            if (!empty($op['sequence'])) { $bySeq[(int) $op['sequence']] = $i; }
            if (!empty($op['row_uid'])) { $byUid[(string) $op['row_uid']] = $i; }
            if (!empty($op['id'])) { $byId[(int) $op['id']] = $i; }
        }

        $priorities = [];
        foreach ($operations as $index => $op) {
            if (!filter_var($op['is_alternative'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            $target = null;
            if (!empty($op['replaces_sequence'])) {
                $target = $bySeq[(int) $op['replaces_sequence']] ?? null;
            } elseif (!empty($op['replaces_row_uid'])) {
                $target = $byUid[(string) $op['replaces_row_uid']] ?? null;
            } elseif (!empty($op['replaces_operation_id'])) {
                $target = $byId[(int) $op['replaces_operation_id']] ?? null;
            } else {
                continue; // "must specify which primary" already reported
            }

            if ($target === null) {
                $validator->errors()->add("operations.{$index}.replaces_operation_id", 'An alternative operation must replace an operation of this routing.');
                continue;
            }
            if ($target === $index) {
                $validator->errors()->add("operations.{$index}.replaces_operation_id", 'An operation cannot replace itself.');
                continue;
            }
            if (filter_var($operations[$target]['is_alternative'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $validator->errors()->add("operations.{$index}.replaces_operation_id", 'An alternative must replace a primary operation, not another alternative.');
                continue;
            }
            $priority = (int) ($op['priority'] ?? 1);
            if (isset($priorities[$target][$priority])) {
                $primaryLabel = $operations[$target]['operation_number'] ?? ('sequence ' . ($operations[$target]['sequence'] ?? ''));
                $validator->errors()->add("operations.{$index}.priority", "Two alternatives of {$primaryLabel} have priority {$priority}. Each alternative of an operation needs its own priority.");
            }
            $priorities[$target][$priority] = true;
        }
    }

    /**
     * Short Full Review Point 2: currency of the money figures the caller sends.
     * 'base'   — API / imports: DB base currency (default).
     * 'active' — web Routing forms: the user's active currency (the form works on the converted resources feed).
     * The check then runs on resources in the same currency, so it matches the form calculator exactly.
     */
    protected function routingFiguresCurrency(): string
    {
        return 'base';
    }

    /** @var array<int, array> snapshot resource figures per section (one lookup per request) */
    private array $routingSnapshotResourceCache = [];

    private function snapshotResourcesFor(int $sectionId): ?array
    {
        if (!array_key_exists($sectionId, $this->routingSnapshotResourceCache)) {
            $section = WorkCenterSection::find($sectionId);
            $this->routingSnapshotResourceCache[$sectionId] = $section
                ? app(\App\Domains\Production\Services\SectionCalculationService::class)->getSnapshotResourceCosts($section)
                : null;
        }

        return $this->routingSnapshotResourceCache[$sectionId];
    }

    private function checkOperationCosts($validator, array $op, int|string $index, bool $isExternal, ?int $sectionId, ?int $workCenterId): void
    {
        $label = $op['operation_number'] ?? ('sequence ' . ($op['sequence'] ?? $index));
        $calculator = app(\App\Domains\Production\Services\RoutingOperationCostCalculator::class);

        if ($isExternal) {
            $result = $calculator->calculate($op, null);
            if ($calculator->mismatches($op, $result['figures']) !== []) {
                $validator->errors()->add("operations.{$index}.cost_per_unit", "Operation {$label}: cost does not match the subcontract cost and yield. Reload the operation and save again.");
            }
            return;
        }

        // Structural errors are reported by the checks above
        if (!$sectionId || !$workCenterId || (empty($op['machine_id']) === empty($op['section_manual_production_id']))) {
            return;
        }

        $resources = $this->snapshotResourcesFor($sectionId);
        if (!$resources || !empty($resources['not_calculated'])) {
            $code = $resources['section_code'] ?? "#{$sectionId}";
            $validator->errors()->add("operations.{$index}.section_id", "Section {$code} has not been calculated yet. Recalculate the Work Center first.");
            return;
        }

        if (!empty($op['section_calculation_id'])) {
            $ok = \App\Domains\Production\Models\WorkCenterSectionCalculation::where('id', (int) $op['section_calculation_id'])
                ->where('section_id', $sectionId)->exists();
            if (!$ok) {
                $validator->errors()->add("operations.{$index}.section_calculation_id", "Operation {$label}: the section snapshot does not belong to the selected section.");
                return;
            }
        }

        if ($this->routingFiguresCurrency() === 'active') {
            $resources = \App\Domains\Production\Services\RoutingOperationCostCalculator::resourcesToActiveCurrency($resources);
        }

        $result = $calculator->calculate($op, $resources);
        if ($result['error'] === 'not_in_snapshot') {
            $validator->errors()->add("operations.{$index}.machine_id", "Operation {$label}: the selected machine / batch is not in the latest calculation of section {$resources['section_code']} (snapshot #{$resources['snapshot_number']}). Recalculate the Work Center first.");
            return;
        }

        if ($calculator->mismatches($op, $result['figures']) !== []) {
            $validator->errors()->add("operations.{$index}.cost_per_unit", "Operation {$label}: cost does not match the selected machine / batch. Reload the resource and save again.");
        }
    }
}
