<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;

class ProductionReadinessService
{
    public const STATUS_READY = 'READY';
    public const STATUS_PARTIALLY_READY = 'PARTIALLY_READY';
    public const STATUS_WAITING_PREDECESSOR = 'WAITING_PREDECESSOR';
    public const STATUS_BLOCKED = 'BLOCKED';

    public const REASON_MATERIAL_SHORTAGE = 'MATERIAL_SHORTAGE';
    public const REASON_MATERIAL_NOT_RESERVED = 'MATERIAL_NOT_RESERVED';
    public const REASON_PREDECESSOR_INCOMPLETE = 'PREDECESSOR_INCOMPLETE';
    public const REASON_WIP_NOT_AVAILABLE = 'WIP_NOT_AVAILABLE';
    public const REASON_WIP_PARTIALLY_AVAILABLE = 'WIP_PARTIALLY_AVAILABLE';
    public const REASON_MACHINE_UNAVAILABLE = 'MACHINE_UNAVAILABLE';
    public const REASON_MACHINE_UNDER_MAINTENANCE = 'MACHINE_UNDER_MAINTENANCE';
    public const REASON_NO_VALID_RESOURCE = 'NO_VALID_RESOURCE';
    public const REASON_SUBCONTRACT_WIP_PENDING = 'SUBCONTRACT_WIP_PENDING';
    public const REASON_SUBCONTRACT_WIP_PARTIAL = 'SUBCONTRACT_WIP_PARTIAL';
    public const REASON_QC_PENDING = 'QC_PENDING';
    public const REASON_QC_FAILED = 'QC_FAILED';
    public const REASON_NCR_OPEN = 'NCR_OPEN';
    public const REASON_BATCH_NOT_ALLOCATED = 'BATCH_NOT_ALLOCATED';

    /**
     * Evaluate comprehensive readiness for a single Production Order Operation across 6 dimensions.
     */
    public function evaluateOperationReadiness(ProductionOrderOperation $operation): array
    {
        $operation->loadMissing([
            'order',
            'previousOperation',
            'predecessorDependencies',
            'routingOperation',
            'machine',
            'workCenter',
        ]);

        $order = $operation->order;
        $tenantId = (int) $operation->tenant_id;
        $orderQty = (float) ($opTargetQty = $operation->target_produced_qty > 0 ? $operation->target_produced_qty : ($order->quantity_ordered ?? 1.0));

        $blockingReasons = [];
        $warnings = [];

        // 1. Material Readiness Dimension
        $materialEval = $this->evaluateMaterialDimension($operation, $tenantId, $orderQty);

        // 2. Predecessor & WIP Dependency Dimension
        $dependencyEval = $this->evaluateDependencyDimension($operation, $tenantId, $orderQty);

        // 3. Machine / Work Center Dimension
        $machineEval = $this->evaluateMachineDimension($operation, $tenantId);

        // 4. Subcontract WIP Dimension
        $subcontractEval = $this->evaluateSubcontractDimension($operation, $tenantId, $orderQty);

        // 5. Quality Readiness Dimension
        $qualityEval = $this->evaluateQualityDimension($operation, $tenantId);

        // 6. Batch / Tracking Dimension
        $trackingEval = $this->evaluateTrackingDimension($operation, $tenantId);

        // Merge blockers and warnings from all dimensions
        foreach ([$materialEval, $dependencyEval, $machineEval, $subcontractEval, $qualityEval, $trackingEval] as $dim) {
            if (!empty($dim['blockers'])) {
                foreach ($dim['blockers'] as $b) {
                    $blockingReasons[] = $b;
                }
            }
            if (!empty($dim['warnings'])) {
                foreach ($dim['warnings'] as $w) {
                    $warnings[] = $w;
                }
            }
        }

        // Quantified Executable Quantity Calculation
        $dimQuantities = [
            $materialEval['ready_qty'],
            $dependencyEval['ready_qty'],
            $subcontractEval['ready_qty'],
        ];

        // Non-quantity hard blockers reset ready_qty to 0
        $hardBlock = ($machineEval['status'] === self::STATUS_BLOCKED)
            || ($qualityEval['status'] === self::STATUS_BLOCKED)
            || ($trackingEval['status'] === self::STATUS_BLOCKED)
            || ($dependencyEval['status'] === self::STATUS_BLOCKED && $dependencyEval['ready_qty'] <= 0);

        if ($hardBlock) {
            $readyQty = 0.0;
        } else {
            $readyQty = min($orderQty, min($dimQuantities));
            $readyQty = max(0.0, round($readyQty, 4));
        }

        $blockedQty = max(0.0, round($orderQty - $readyQty, 4));

        $actionableBlockers = array_values(array_filter($blockingReasons, fn($b) => empty($b['is_workflow_dependency'])));
        $workflowDependencies = array_values(array_filter($blockingReasons, fn($b) => !empty($b['is_workflow_dependency'])));

        // Determine Overall Dimension Status
        if (!empty($blockingReasons) && $readyQty <= 0.0) {
            $overallStatus = self::STATUS_BLOCKED;
        } elseif ($readyQty > 0.0 && $blockedQty > 0.0) {
            $overallStatus = self::STATUS_PARTIALLY_READY;
        } elseif ($readyQty >= $orderQty && empty($blockingReasons)) {
            $overallStatus = self::STATUS_READY;
        } else {
            $overallStatus = $readyQty > 0.0 ? self::STATUS_PARTIALLY_READY : self::STATUS_BLOCKED;
        }

        return [
            'operation_id' => $operation->id,
            'operation_number' => $operation->operation_number,
            'operation_name' => $operation->name,
            'sequence' => $operation->sequence,
            'overall_status' => $overallStatus,
            'target_qty' => $orderQty,
            'ready_qty' => $readyQty,
            'blocked_qty' => $blockedQty,
            'claimed_qty' => (float) ($operation->quantity_claimed ?? 0.0),
            'remaining_executable_qty' => max(0.0, round($readyQty - (float) ($operation->quantity_claimed ?? 0.0), 4)),
            'material' => $materialEval,
            'dependency' => $dependencyEval,
            'machine' => $machineEval,
            'subcontract' => $subcontractEval,
            'quality' => $qualityEval,
            'tracking' => $trackingEval,
            'blocking_reasons' => $blockingReasons,
            'actionable_blockers' => $actionableBlockers,
            'workflow_dependencies' => $workflowDependencies,
            'warnings' => $warnings,
        ];
    }

    /**
     * Evaluate overall readiness for an entire Production Order aggregating all operations in sequence.
     */
    public function evaluateOrderReadiness(ProductionOrder $order): array
    {
        $tenantId = (int) $order->tenant_id;
        $ops = $order->operations()
            ->with(['predecessorDependencies', 'routingOperation', 'machine', 'workCenter'])
            ->orderBy('sequence')
            ->get();

        if ($ops->isEmpty()) {
            return [
                'production_order_id' => $order->id,
                'order_number' => $order->order_number,
                'overall_status' => self::STATUS_BLOCKED,
                'ready_operations_count' => 0,
                'partial_operations_count' => 0,
                'waiting_operations_count' => 0,
                'blocked_operations_count' => 0,
                'actionable_blockers_count' => 1,
                'workflow_dependencies_count' => 0,
                'operations' => [],
                'blocking_reasons' => [[
                    'code' => 'NO_OPERATIONS',
                    'dimension' => 'sequence',
                    'message' => 'Production Order has no defined operations.',
                    'severity' => 'error',
                ]],
                'actionable_blockers' => [[
                    'code' => 'NO_OPERATIONS',
                    'dimension' => 'sequence',
                    'message' => 'Production Order has no defined operations.',
                    'severity' => 'error',
                ]],
                'workflow_dependencies' => [],
                'warnings' => [],
            ];
        }

        $opEvaluations = [];
        $readyCount = 0;
        $partialCount = 0;
        $waitingCount = 0;
        $blockedCount = 0;
        $orderBlockers = [];
        $actionableBlockers = [];
        $workflowDependencies = [];
        $orderWarnings = [];

        foreach ($ops as $op) {
            $eval = $this->evaluateOperationReadiness($op);
            $opEvaluations[] = $eval;

            if ($eval['overall_status'] === self::STATUS_READY) {
                $readyCount++;
            } elseif ($eval['overall_status'] === self::STATUS_PARTIALLY_READY) {
                $partialCount++;
            } elseif ($eval['overall_status'] === self::STATUS_WAITING_PREDECESSOR) {
                $waitingCount++;
            } else {
                $blockedCount++;
            }

            foreach ($eval['blocking_reasons'] as $b) {
                $item = array_merge($b, ['operation_id' => $op->id, 'sequence' => $op->sequence]);
                $orderBlockers[] = $item;
                if (!empty($b['is_workflow_dependency'])) {
                    $workflowDependencies[] = $item;
                } else {
                    $actionableBlockers[] = $item;
                }
            }
            foreach ($eval['warnings'] as $w) {
                $orderWarnings[] = array_merge($w, ['operation_id' => $op->id, 'sequence' => $op->sequence]);
            }
        }

        $firstOp = $opEvaluations[0] ?? null;

        if ($readyCount === count($ops)) {
            $overallStatus = self::STATUS_READY;
        } elseif (!empty($actionableBlockers)) {
            $overallStatus = self::STATUS_BLOCKED;
        } elseif ($firstOp && in_array($firstOp['overall_status'], [self::STATUS_READY, self::STATUS_PARTIALLY_READY], true)) {
            $overallStatus = self::STATUS_PARTIALLY_READY;
        } else {
            $overallStatus = self::STATUS_PARTIALLY_READY;
        }

        return [
            'production_order_id' => $order->id,
            'order_number' => $order->order_number,
            'overall_status' => $overallStatus,
            'total_operations' => count($ops),
            'ready_operations_count' => $readyCount,
            'partial_operations_count' => $partialCount,
            'waiting_operations_count' => $waitingCount,
            'blocked_operations_count' => $blockedCount,
            'actionable_blockers_count' => count($actionableBlockers),
            'workflow_dependencies_count' => count($workflowDependencies),
            'operations' => $opEvaluations,
            'blocking_reasons' => $orderBlockers,
            'actionable_blockers' => $actionableBlockers,
            'workflow_dependencies' => $workflowDependencies,
            'warnings' => $orderWarnings,
        ];
    }

    // ─── DIMENSION EVALUATORS ──────────────────────────────────────────────────

    private function evaluateMaterialDimension(ProductionOrderOperation $op, int $tenantId, float $orderQty): array
    {
        $order = $op->order;
        $reservations = ProductionOrderReservation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('production_order_id', $order->id)
            ->with(['product'])
            ->get();

        if ($reservations->isEmpty()) {
            return [
                'status' => self::STATUS_READY,
                'required_qty' => $orderQty,
                'issued_qty' => $orderQty,
                'reserved_qty' => 0.0,
                'available_stock' => 0.0,
                'shortage_qty' => 0.0,
                'ready_qty' => $orderQty,
                'blockers' => [],
                'warnings' => [],
            ];
        }

        $totalRequired = 0.0;
        $totalIssued = 0.0;
        $totalReserved = 0.0;
        $totalAvailable = 0.0;
        $blockers = [];
        $warnings = [];

        $minExecutableFromMaterial = $orderQty;

        foreach ($reservations as $res) {
            $req = (float) $res->quantity_planned;
            $iss = (float) $res->quantity_issued;
            $resv = (float) $res->quantity_reserved;

            $totalRequired += $req;
            $totalIssued += $iss;
            $totalReserved += $resv;

            if ($req <= 0.0) continue;

            $whStock = (float) ProductWarehouseStock::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $res->product_id)
                ->sum('quantity');

            $totalAvailable += $whStock;
            $usableForThisItem = $iss + $resv + $whStock;

            // Ratio calculation if BOM quantity > 0
            $bomRatio = ($req / max(1.0, $orderQty));
            $executableQtyForThisItem = $bomRatio > 0 ? ($usableForThisItem / $bomRatio) : $orderQty;

            $minExecutableFromMaterial = min($minExecutableFromMaterial, $executableQtyForThisItem);

            $pName = $res->product->name ?? "Product #{$res->product_id}";

            if ($usableForThisItem <= 0.0) {
                $blockers[] = [
                    'code' => self::REASON_MATERIAL_SHORTAGE,
                    'dimension' => 'material',
                    'message' => "Material shortage for [{$pName}] (Required: {$req}, Issued: {$iss}, Warehouse Stock: {$whStock}).",
                    'severity' => 'error',
                    'product_id' => $res->product_id,
                ];
            } elseif ($usableForThisItem < $req) {
                $warnings[] = [
                    'code' => self::REASON_MATERIAL_SHORTAGE,
                    'dimension' => 'material',
                    'message' => "Material [{$pName}] is partially available (Required: {$req}, Issued: {$iss}, Stock: {$whStock}).",
                    'severity' => 'warning',
                    'product_id' => $res->product_id,
                ];
            } elseif ($iss <= 0.0 && $resv > 0.0) {
                $warnings[] = [
                    'code' => self::REASON_MATERIAL_NOT_RESERVED,
                    'dimension' => 'material',
                    'message' => "Material [{$pName}] is reserved in warehouse but not yet issued to order.",
                    'severity' => 'info',
                    'product_id' => $res->product_id,
                ];
            }
        }

        $readyQty = max(0.0, round(min($orderQty, $minExecutableFromMaterial), 4));
        $shortageQty = max(0.0, round($totalRequired - ($totalIssued + $totalReserved + $totalAvailable), 4));

        if (!empty($blockers) && $readyQty <= 0.0) {
            $status = self::STATUS_BLOCKED;
        } elseif ($readyQty < $orderQty) {
            $status = self::STATUS_PARTIALLY_READY;
        } else {
            $status = self::STATUS_READY;
        }

        return [
            'status' => $status,
            'required_qty' => round($totalRequired, 4),
            'issued_qty' => round($totalIssued, 4),
            'reserved_qty' => round($totalReserved, 4),
            'available_stock' => round($totalAvailable, 4),
            'shortage_qty' => $shortageQty,
            'ready_qty' => $readyQty,
            'blockers' => $blockers,
            'warnings' => $warnings,
        ];
    }

    private function evaluateDependencyDimension(ProductionOrderOperation $op, int $tenantId, float $orderQty): array
    {
        $blockers = [];
        $warnings = [];

        // 1. Intra-routing predecessor check
        $intraReadyQty = $orderQty;
        if ($op->previousOperation) {
            $prevOp = $op->previousOperation;
            $produced = (float) $prevOp->quantity_produced;
            $transferred = (float) ($prevOp->quantity_transferred_out ?? 0.0);

            $availableWip = max($produced, $transferred);

            if ($prevOp->status !== ProductionOrderOperation::STATUS_COMPLETED && $availableWip <= 0.0) {
                $intraReadyQty = 0.0;
                $blockers[] = [
                    'code' => self::REASON_PREDECESSOR_INCOMPLETE,
                    'dimension' => 'dependency',
                    'message' => "Intra-routing predecessor operation {$prevOp->operation_number} is incomplete.",
                    'severity' => 'info',
                    'is_workflow_dependency' => true,
                ];
            } else {
                $intraReadyQty = min($orderQty, $availableWip);
                if ($intraReadyQty < $orderQty) {
                    $warnings[] = [
                        'code' => self::REASON_WIP_PARTIALLY_AVAILABLE,
                        'dimension' => 'dependency',
                        'message' => "Predecessor operation {$prevOp->operation_number} has partial WIP available ({$intraReadyQty}/{$orderQty} units).",
                        'severity' => 'warning',
                    ];
                }
            }
        }

        // 2. Cross-assembly predecessor check
        $crossReadyLimits = [];
        $crossPreds = $op->predecessorDependencies()
            ->wherePivot('dependency_type', 'cross_assembly')
            ->get();

        foreach ($crossPreds as $predOp) {
            $childProductId = $predOp->source_product_id;
            if (!$childProductId) continue;

            $bomItem = ProductionBomItem::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('bom_id', $op->source_bom_id ?? $op->order->bom_id)
                ->where('material_id', $childProductId)
                ->first();
            $bomRatio = ($bomItem && (float) $bomItem->quantity > 0) ? (float) $bomItem->quantity : 1.0;

            $issuedToOrder = (float) ProductionOrderReservation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('production_order_id', $op->production_order_id)
                ->where('product_id', $childProductId)
                ->sum('quantity_issued');

            $whStock = (float) ProductWarehouseStock::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $childProductId)
                ->sum('quantity');

            $predOpFresh = ProductionOrderOperation::find($predOp->id) ?? $predOp;
            $produced = (float) $predOpFresh->quantity_produced;
            $qcHold = (float) ($predOpFresh->active_qc_hold ?? 0.0);
            $rework = (float) ($predOpFresh->active_rework ?? 0.0);
            $consumed = (float) ($predOpFresh->quantity_consumed ?? 0.0);

            $usableIntermediateSfg = max(0.0, $produced - ($qcHold + $rework) - $consumed);
            $totalUsableSfg = $issuedToOrder + $whStock + $usableIntermediateSfg;

            $maxExecutable = $totalUsableSfg / $bomRatio;
            $crossReadyLimits[] = $maxExecutable;

            if ($totalUsableSfg <= 0.0 && $predOpFresh->status !== ProductionOrderOperation::STATUS_COMPLETED) {
                $blockers[] = [
                    'code' => self::REASON_WIP_NOT_AVAILABLE,
                    'dimension' => 'dependency',
                    'message' => "Cross-assembly predecessor operation {$predOpFresh->operation_number} has insufficient usable SFG.",
                    'severity' => 'error',
                    'is_workflow_dependency' => true,
                ];
            }
        }

        $minCrossReady = !empty($crossReadyLimits) ? min($crossReadyLimits) : $orderQty;
        $readyQty = max(0.0, round(min($intraReadyQty, $minCrossReady), 4));

        if (!empty($blockers) && $readyQty <= 0.0) {
            $status = self::STATUS_BLOCKED;
        } elseif ($readyQty < $orderQty) {
            $status = self::STATUS_PARTIALLY_READY;
        } else {
            $status = self::STATUS_READY;
        }

        return [
            'status' => $status,
            'ready_qty' => $readyQty,
            'blocked_qty' => max(0.0, round($orderQty - $readyQty, 4)),
            'blockers' => $blockers,
            'warnings' => $warnings,
        ];
    }

    private function evaluateMachineDimension(ProductionOrderOperation $op, int $tenantId): array
    {
        $blockers = [];
        $warnings = [];

        // External operations do not require internal machines
        if ((bool) ($op->is_external || $op->routingOperation?->is_external || $op->work_center_id === null)) {
            return [
                'status' => self::STATUS_READY,
                'assigned_machine' => null,
                'alternate_available' => false,
                'blockers' => [],
                'warnings' => [],
            ];
        }

        $machine = $op->machine;

        if (!$machine && $op->work_center_id) {
            // Check if active machines exist at Work Center
            $activeMachines = Machine::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('work_center_id', $op->work_center_id)
                ->where('status', 'active')
                ->get();

            if ($activeMachines->isEmpty()) {
                $blockers[] = [
                    'code' => self::REASON_NO_VALID_RESOURCE,
                    'dimension' => 'machine',
                    'message' => "No active machines available at assigned Work Center #{$op->work_center_id}.",
                    'severity' => 'error',
                ];
                return [
                    'status' => self::STATUS_BLOCKED,
                    'assigned_machine' => null,
                    'alternate_available' => false,
                    'blockers' => $blockers,
                    'warnings' => $warnings,
                ];
            }

            $machine = $activeMachines->first();
            $warnings[] = [
                'code' => 'PRIMARY_MACHINE_UNASSIGNED',
                'dimension' => 'machine',
                'message' => "Primary machine unassigned; defaulting to active machine [{$machine->name}].",
                'severity' => 'info',
            ];
        }

        if (!$machine) {
            return [
                'status' => self::STATUS_READY,
                'assigned_machine' => null,
                'alternate_available' => false,
                'blockers' => [],
                'warnings' => [],
            ];
        }

        // Machine status / Maintenance Check
        $isInactive = !$machine->isActive();
        $isMaintenance = in_array(strtolower((string)$machine->status), ['under_maintenance', 'maintenance', 'breakdown']);

        // Downtime collision check
        $downtimeConflict = ProductionMachineDowntime::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('machine_id', $machine->id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();

        $primaryUnavailable = $isInactive || $isMaintenance || $downtimeConflict;

        if ($primaryUnavailable) {
            // Alternate Machine Search
            $alternateAvailable = false;
            $routingOpId = $op->routing_operation_id;

            if ($routingOpId) {
                $altMachines = RoutingOperationAlternateMachine::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('routing_operation_id', $routingOpId)
                    ->pluck('machine_id');

                if ($altMachines->isNotEmpty()) {
                    $validAltExists = Machine::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->whereIn('id', $altMachines)
                        ->where('status', 'active')
                        ->exists();

                    if ($validAltExists) {
                        $alternateAvailable = true;
                    }
                }
            }

            if (!$alternateAvailable && $op->work_center_id) {
                $otherWorkCenterMachines = Machine::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('work_center_id', $op->work_center_id)
                    ->where('id', '!=', $machine->id)
                    ->where('status', 'active')
                    ->exists();

                if ($otherWorkCenterMachines) {
                    $alternateAvailable = true;
                }
            }

            if ($alternateAvailable) {
                $warnings[] = [
                    'code' => 'PRIMARY_MACHINE_UNAVAILABLE',
                    'dimension' => 'machine',
                    'message' => "Primary machine [{$machine->name}] is under maintenance/inactive, but an approved alternate resource is available.",
                    'severity' => 'warning',
                    'machine_id' => $machine->id,
                ];
                return [
                    'status' => self::STATUS_READY,
                    'assigned_machine' => $machine->name,
                    'alternate_available' => true,
                    'blockers' => [],
                    'warnings' => $warnings,
                ];
            } else {
                $code = $isMaintenance ? self::REASON_MACHINE_UNDER_MAINTENANCE : self::REASON_MACHINE_UNAVAILABLE;
                $blockers[] = [
                    'code' => $code,
                    'dimension' => 'machine',
                    'message' => "Primary machine [{$machine->name}] is unavailable and no valid alternate resource exists.",
                    'severity' => 'error',
                    'machine_id' => $machine->id,
                ];
                return [
                    'status' => self::STATUS_BLOCKED,
                    'assigned_machine' => $machine->name,
                    'alternate_available' => false,
                    'blockers' => $blockers,
                    'warnings' => $warnings,
                ];
            }
        }

        return [
            'status' => self::STATUS_READY,
            'assigned_machine' => $machine->name,
            'alternate_available' => false,
            'blockers' => [],
            'warnings' => [],
        ];
    }

    private function evaluateSubcontractDimension(ProductionOrderOperation $op, int $tenantId, float $orderQty): array
    {
        if (!$op->is_external) {
            return [
                'status' => self::STATUS_READY,
                'required_qty' => $orderQty,
                'returned_qty' => $orderQty,
                'ready_qty' => $orderQty,
                'blockers' => [],
                'warnings' => [],
            ];
        }

        $produced = (float) $op->quantity_produced;
        $statusStr = strtolower((string) $op->status);
        $blockers = [];
        $warnings = [];

        if (in_array($statusStr, ['completed', 'qc_passed'], true) || $produced >= $orderQty) {
            return [
                'status' => self::STATUS_READY,
                'required_qty' => $orderQty,
                'returned_qty' => $produced,
                'ready_qty' => $orderQty,
                'blockers' => [],
                'warnings' => [],
            ];
        }

        if ($produced > 0.0 && $produced < $orderQty) {
            $warnings[] = [
                'code' => self::REASON_SUBCONTRACT_WIP_PARTIAL,
                'dimension' => 'subcontract',
                'message' => "Subcontract WIP partially returned from vendor ({$produced}/{$orderQty} units).",
                'severity' => 'warning',
            ];
            return [
                'status' => self::STATUS_PARTIALLY_READY,
                'required_qty' => $orderQty,
                'returned_qty' => $produced,
                'ready_qty' => $produced,
                'blockers' => [],
                'warnings' => $warnings,
            ];
        }

        $blockers[] = [
            'code' => self::REASON_SUBCONTRACT_WIP_PENDING,
            'dimension' => 'subcontract',
            'message' => "Subcontract WIP is currently awaiting return from vendor (Status: {$op->status}).",
            'severity' => 'error',
        ];

        return [
            'status' => self::STATUS_BLOCKED,
            'required_qty' => $orderQty,
            'returned_qty' => 0.0,
            'ready_qty' => 0.0,
            'blockers' => $blockers,
            'warnings' => $warnings,
        ];
    }

    private function evaluateQualityDimension(ProductionOrderOperation $op, int $tenantId): array
    {
        $isQcRequired = (bool) ($op->routingOperation?->quality_required ?? false);

        if (!$isQcRequired) {
            return [
                'status' => self::STATUS_READY,
                'qc_required' => false,
                'inspection_status' => 'N/A',
                'blockers' => [],
                'warnings' => [],
            ];
        }

        $inspections = ProductionQualityInspection::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('production_order_id', $op->production_order_id)
            ->where(function ($q) use ($op) {
                $q->where('production_order_operation_id', $op->id)
                  ->orWhereNull('production_order_operation_id');
            })
            ->get();

        if ($inspections->isEmpty()) {
            return [
                'status' => self::STATUS_BLOCKED,
                'qc_required' => true,
                'inspection_status' => 'PENDING',
                'blockers' => [[
                    'code' => self::REASON_QC_PENDING,
                    'dimension' => 'quality',
                    'message' => "Mandatory quality gate requires an approved passed quality inspection.",
                    'severity' => 'error',
                ]],
                'warnings' => [],
            ];
        }

        $failedInspection = $inspections->first(fn($i) => strtolower((string)$i->result) === 'failed' || strtolower((string)$i->status) === 'rejected');
        if ($failedInspection) {
            return [
                'status' => self::STATUS_BLOCKED,
                'qc_required' => true,
                'inspection_status' => 'FAILED',
                'blockers' => [[
                    'code' => self::REASON_QC_FAILED,
                    'dimension' => 'quality',
                    'message' => "Quality inspection failed/rejected for operation {$op->operation_number}.",
                    'severity' => 'error',
                    'inspection_id' => $failedInspection->id,
                ]],
                'warnings' => [],
            ];
        }

        $passedInspection = $inspections->first(fn($i) => strtolower((string)$i->status) === 'approved' && strtolower((string)$i->result) === 'passed');
        if ($passedInspection) {
            return [
                'status' => self::STATUS_READY,
                'qc_required' => true,
                'inspection_status' => 'PASSED',
                'blockers' => [],
                'warnings' => [],
            ];
        }

        return [
            'status' => self::STATUS_BLOCKED,
            'qc_required' => true,
            'inspection_status' => 'PENDING',
            'blockers' => [[
                'code' => self::REASON_QC_PENDING,
                'dimension' => 'quality',
                'message' => "Quality inspection is currently pending approval.",
                'severity' => 'error',
            ]],
            'warnings' => [],
        ];
    }

    private function evaluateTrackingDimension(ProductionOrderOperation $op, int $tenantId): array
    {
        $order = $op->order;

        if ($order && ($order->track_batch || $order->track_serial_number)) {
            $hasWipBatch = ProductionWip::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->exists();

            if (!$hasWipBatch && $op->sequence > 1) {
                return [
                    'status' => self::STATUS_PARTIALLY_READY,
                    'tracking_type' => $order->track_batch ? 'batch' : 'serial',
                    'blockers' => [],
                    'warnings' => [[
                        'code' => self::REASON_BATCH_NOT_ALLOCATED,
                        'dimension' => 'tracking',
                        'message' => "Batch/Serial tracking enabled but no batch WIP record allocated yet.",
                        'severity' => 'info',
                    ]],
                ];
            }
        }

        return [
            'status' => self::STATUS_READY,
            'tracking_type' => 'standard',
            'blockers' => [],
            'warnings' => [],
        ];
    }
}
