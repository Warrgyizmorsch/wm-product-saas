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
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;

class ProductionReadinessService
{
    /** @var array<int, array{ops: \Illuminate\Support\Collection, pool: array}> per-order context, set only while evaluateOrderReadiness runs */
    private array $orderCtx = [];

    public const STATUS_READY = 'READY';
    public const STATUS_PARTIALLY_READY = 'PARTIALLY_READY';
    public const STATUS_WAITING_PREDECESSOR = 'WAITING_PREDECESSOR';
    public const STATUS_BLOCKED = 'BLOCKED';

    public const REASON_MATERIAL_SHORTAGE = 'MATERIAL_SHORTAGE';
    public const REASON_MATERIAL_NOT_RESERVED = 'MATERIAL_NOT_RESERVED';
    public const REASON_MATERIAL_NOT_ISSUED = 'MATERIAL_NOT_ISSUED';
    /** UR-01: less than the quantity needed for 1 unit is issued — the operation cannot produce even 1 unit */
    public const REASON_MATERIAL_MINIMUM_NOT_ISSUED = 'MATERIAL_MINIMUM_NOT_ISSUED';
    /** UR-03: another operation is running on / booked on the same resource (information only, not a block) */
    public const REASON_RESOURCE_BUSY = 'RESOURCE_BUSY';
    public const REASON_RESOURCE_BOOKED = 'RESOURCE_BOOKED';
    public const REASON_PREDECESSOR_INCOMPLETE = 'PREDECESSOR_INCOMPLETE';
    /** UR-07: the operations that make a sub-assembly this operation needs are not all completed */
    public const REASON_SUB_ASSEMBLY_INCOMPLETE = 'SUB_ASSEMBLY_INCOMPLETE';
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
        // UR-04: the Subcontract column is information only for now (Subcontracted / Not subcontracted) — it does not limit the quantity.
        $dimQuantities = [
            $materialEval['ready_qty'],
            $dependencyEval['ready_qty'],
        ];

        // Non-quantity hard blockers reset ready_qty to 0
        $hardBlock = ($machineEval['status'] === self::STATUS_BLOCKED)
            || ($qualityEval['status'] === self::STATUS_BLOCKED)
            || ($trackingEval['status'] === self::STATUS_BLOCKED)
            || (in_array($dependencyEval['status'], [self::STATUS_BLOCKED, self::STATUS_WAITING_PREDECESSOR], true) && $dependencyEval['ready_qty'] <= 0);

        if ($hardBlock) {
            $readyQty = 0.0;
        } else {
            $readyQty = min($orderQty, min($dimQuantities));
            $readyQty = max(0.0, round($readyQty, 4));
        }

        $blockedQty = max(0.0, round($orderQty - $readyQty, 4));

        $actionableBlockers = array_values(array_filter($blockingReasons, fn($b) => empty($b['is_workflow_dependency'])));
        $workflowDependencies = array_values(array_filter($blockingReasons, fn($b) => !empty($b['is_workflow_dependency'])));

        // Determine Overall Dimension Status (PX-08: a pure workflow dependency is WAITING, not BLOCKED)
        if (!empty($blockingReasons) && $readyQty <= 0.0) {
            $overallStatus = empty($actionableBlockers) ? self::STATUS_WAITING_PREDECESSOR : self::STATUS_BLOCKED;
        } elseif ($readyQty > 0.0 && $blockedQty > 0.0) {
            $overallStatus = self::STATUS_PARTIALLY_READY;
        } elseif ($readyQty >= $orderQty && empty($blockingReasons)) {
            $overallStatus = self::STATUS_READY;
        } else {
            $overallStatus = $readyQty > 0.0 ? self::STATUS_PARTIALLY_READY : self::STATUS_BLOCKED;
        }

            $pendingQc = app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($operation->id);
            $totalProcessed = (float) ($operation->quantity_produced ?? 0.0) + $pendingQc + (float) ($operation->quantity_rejected ?? 0.0) + (float) ($operation->quantity_scrapped ?? 0.0);
            $effectiveClaimed = max((float) ($operation->quantity_claimed ?? 0.0), $totalProcessed);
            $remainingExecutable = ($operation->status === 'completed') ? 0.0 : max(0.0, round($readyQty - $effectiveClaimed, 4));

            return [
                'operation_id' => $operation->id,
                'operation_number' => $operation->operation_number,
                'operation_name' => $operation->name,
                'sequence' => $operation->sequence,
                'overall_status' => $overallStatus,
                'target_qty' => $orderQty,
                'ready_qty' => $readyQty,
                'blocked_qty' => $blockedQty,
                'claimed_qty' => $effectiveClaimed,
                'remaining_executable_qty' => $remainingExecutable,
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
        if ($order->relationLoaded('operations')) {
            $ops = $order->operations->sortBy('sequence');
        } else {
            $ops = $order->operations()
                ->with(['predecessorDependencies', 'routingOperation', 'machine', 'workCenter'])
                ->orderBy('sequence')
                ->get();
        }

        foreach ($ops as $op) {
            if (!$op->relationLoaded('order')) {
                $op->setRelation('order', $order);
            }
        }

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

        $this->beginOrderContext($order);

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

        $this->endOrderContext($order);

        // PX-08: all ready -> READY; any actionable blocker -> BLOCKED; some ready/partial -> PARTIALLY READY;
        // nothing ready and only workflow dependencies left -> WAITING.
        if ($readyCount === count($ops)) {
            $overallStatus = self::STATUS_READY;
        } elseif (!empty($actionableBlockers)) {
            $overallStatus = self::STATUS_BLOCKED;
        } elseif ($readyCount + $partialCount > 0) {
            $overallStatus = self::STATUS_PARTIALLY_READY;
        } else {
            $overallStatus = self::STATUS_WAITING_PREDECESSOR;
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

    /**
     * Resolve every operation of the order ONCE and share it (with the pooled issue) between all the operation evaluations that follow,
     * until endOrderContext(). evaluateOrderReadiness() does this itself; ProductionOrderService::reconcileOperationReadiness() does it around its loop.
     */
    public function beginOrderContext(ProductionOrder $order): void
    {
        $matSvc = app(OperationMaterialService::class);
        $ctxOps = $matSvc->orderedOperations($order);
        $ctxResolved = $matSvc->resolveAll($ctxOps);
        $this->orderCtx[$order->id] = ['ops' => $ctxOps, 'resolved' => $ctxResolved, 'pool' => $matSvc->poolAllocation($order, $ctxOps, $ctxResolved)];
    }

    public function endOrderContext(ProductionOrder $order): void
    {
        unset($this->orderCtx[$order->id]);
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

        if ($order && $order->relationLoaded('operations')) {
            $sfgProductIds = $order->operations->pluck('source_product_id')->filter()->all();
        } else {
            $sfgProductIds = ProductionOrderOperation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->whereNotNull('source_product_id')
                ->pluck('source_product_id')
                ->filter()
                ->all();
        }

        $bomItems = collect();
        if ($op->source_bom_id) {
            // PO-IMPL-02: production_bom_items.is_alternative was dropped (master rows = one per material)
            $bomItems = ProductionBomItem::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('bom_id', $op->source_bom_id)
                ->get();
        }

        $routingOpMaterials = collect();
        if ($op->routing_operation_id) {
            // PO-IMPL-02 (PO-12): this BOM's allocations only, limited to the materials chosen on the order
            $routingOpMaterials = \App\Domains\Production\Models\RoutingOperationMaterial::withoutGlobalScopes()
                ->where('routing_operation_id', $op->routing_operation_id)
                ->when($op->source_bom_id, fn ($q) => $q->where('bom_id', $op->source_bom_id))
                ->when(
                    is_array($op->selected_routing_material_ids),
                    fn ($q) => $q->whereIn('id', $op->selected_routing_material_ids ?: [0]),
                    fn ($q) => $q->where('is_alternative', false)
                )
                ->get();
        }

        // Check if the routing defines operation material assignments
        $hasRoutingAllocations = false;
        if ($op->source_routing_id) {
            $hasRoutingAllocations = \App\Domains\Production\Models\RoutingOperationMaterial::withoutGlobalScopes()
                ->when($op->source_bom_id, fn ($q) => $q->where('bom_id', $op->source_bom_id))
                ->whereHas('routingOperation', function ($q) use ($op) {
                    $q->where('routing_id', $op->source_routing_id);
                })->exists();
        }

        if ($hasRoutingAllocations) {
            // PX-01..06 / D-02: BOM-linked orders use the pooled, issued-material check (see evaluateMaterialDimensionPooled).
            return $this->evaluateMaterialDimensionPooled($op, $tenantId, $orderQty);
        } else {
            if ($op->source_bom_id) {
                $bomMaterialIds = $bomItems->pluck('material_id')->all();
                $reservations = $reservations->filter(fn($r) => in_array($r->product_id, $bomMaterialIds, true) && !in_array($r->product_id, $sfgProductIds, true));
            } else {
                $reservations = $reservations->filter(fn($r) => !in_array($r->product_id, $sfgProductIds, true));
            }
        }

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
            $rom = $routingOpMaterials->firstWhere('material_id', $res->product_id);
            if ($rom && (float) $rom->quantity > 0) {
                $bom = $op->source_bom_id ? \App\Domains\Production\Models\ProductionBom::find($op->source_bom_id) : null;
                $baseQty = ($bom && (float) $bom->base_quantity > 0) ? (float) $bom->base_quantity : 1.0;

                $grossRomQty = (float) ($rom->gross_quantity ?? $rom->calculateGrossQuantity());
                $bomRatio = $grossRomQty / $baseQty;

                $req = round($bomRatio * $orderQty, 4);
            } else {
                $bomItem = $bomItems->firstWhere('material_id', $res->product_id);
                if ($bomItem && (float) $bomItem->quantity > 0) {
                    $baseQty = 1.0;
                    if ($op->source_bom_id) {
                        $bom = \App\Domains\Production\Models\ProductionBom::find($op->source_bom_id);
                        if ($bom && (float) $bom->base_quantity > 0) {
                            $baseQty = (float) $bom->base_quantity;
                        }
                    }
                    $scrapFactor = 1.0 + ((float) ($bomItem->material_scrap_percentage ?? 0) / 100.0);
                    $bomRatio = ((float) $bomItem->quantity / $baseQty) * $scrapFactor;
                    $req = round($bomRatio * $orderQty, 4);
                } else {
                    $req = (float) $res->quantity_planned;
                    $bomRatio = ($req / max(1.0, $orderQty));
                }
            }

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

            // Include compatible unreserved remnants in available material stock
            $remnantAvail = (float) \App\Domains\Inventory\Models\InventoryRemnant::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $res->product_id)
                ->whereIn('status', [
                    \App\Domains\Inventory\Models\InventoryRemnant::STATUS_AVAILABLE,
                    \App\Domains\Inventory\Models\InventoryRemnant::STATUS_PARTIALLY_RESERVED,
                ])
                ->selectRaw('SUM(current_quantity - reserved_quantity) as total_avail')
                ->value('total_avail') ?? 0.0;

            $whStock += $remnantAvail;

            $totalAvailable += $whStock;
            $usableForThisItem = $iss + $resv + $whStock;

            // Ratio calculation based on BOM component consumption
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

    /**
     * PX-09 (decision D-03 A): output (good + rejected + scrapped) of an operation may not exceed the quantity its issued material
     * covers. Only enforced for BOM-linked orders (the legacy stock-based check stays advisory). A user holding
     * `production.mes.override_readiness` (production manager / admin) may go beyond it — the override is written to the order's event log.
     *
     * @throws \InvalidArgumentException
     */
    public function assertWithinReadyQty(ProductionOrderOperation $op, float $additionalUnits, ?\App\Models\User $user = null): void
    {
        if ($additionalUnits <= 0.0) {
            return;
        }
        $op->loadMissing('order');
        $order = $op->order;
        if (!$order || !$this->usesPooledMaterial($op)) {
            return;
        }

        $target = (float) ($op->target_produced_qty > 0 ? $op->target_produced_qty : $order->quantity_ordered);
        if ($target <= 0.0) {
            return;
        }

        $mat = $this->evaluateMaterialDimensionPooled($op, (int) $op->tenant_id, $target);
        $limit = (float) $mat['ready_qty'];
        $already = (float) $op->quantity_produced + (float) $op->quantity_rejected + (float) $op->quantity_scrapped;

        if ($already + $additionalUnits <= $limit + 0.0001) {
            return;
        }

        $limiting = collect($mat['materials'] ?? [])
            ->sortBy(fn ($l) => $l['required'] > 0 ? $l['issued'] / $l['required'] : 1.0)
            ->first();
        $name = $limiting['name'] ?? 'a material';

        if ($user && $user->hasProductionPermission('production.mes.override_readiness')) {
            app(ProductionEventService::class)->writeEvent((int) $op->tenant_id, [
                'production_order_id' => $op->production_order_id,
                'event_type' => 'Readiness Override',
                'title' => 'Output logged beyond the ready quantity',
                'description' => "Operation {$op->operation_number}: {$additionalUnits} units logged on top of {$already} while material covers only {$limit} (limiting material: {$name}).",
                'severity' => 'warning',
                'event_source' => 'ProductionReadinessService',
                'triggered_by' => $user->id,
            ]);

            return;
        }

        $room = max(0.0, round($limit - $already, 4));
        throw new \InvalidArgumentException(
            "Cannot log {$additionalUnits} units on operation {$op->operation_number}: the material issued to this order covers only {$limit} of {$target} units"
            . " ({$already} already logged, {$room} left). Limiting material: {$name}. Issue the missing material first."
        );
    }

    /** True when the operation's order is BOM-linked (routing allocation rows exist) — the pooled, issued-material path applies. */
    private function usesPooledMaterial(ProductionOrderOperation $op): bool
    {
        if (!$op->source_routing_id) {
            return false;
        }

        return \App\Domains\Production\Models\RoutingOperationMaterial::withoutGlobalScopes()
            ->when($op->source_bom_id, fn ($q) => $q->where('bom_id', $op->source_bom_id))
            ->whereHas('routingOperation', fn ($q) => $q->where('routing_id', $op->source_routing_id))
            ->exists();
    }

    /**
     * UR-01 (the user's rule, docs/production_execution_rules_draft.md section 11) — Material column:
     *   target -> BOM quantity needed for 1 unit -> compared with the quantity that is THERE.
     *   - everything there for the whole target                         -> READY
     *   - there for at least 1 unit, but not for the target             -> PARTIALLY READY ("partially issued")
     *   - there for less than 1 unit                                     -> BLOCKED ("cannot produce, minimum quantity not issued")
     * A direct material is "there" when it is ISSUED to the order. RV-03 (rule A, M-26 / M-27): a sub-assembly made inside this order is
     * judged the same way as ONE line — need for 1 unit = its BOM quantity, there = the part issued from the store + what its maker
     * operations have produced (counted once ALL of them are completed — the parent waits for them anyway). Store stock that is not
     * issued is not part of the verdict. The order's pooled issued quantity of a product is still handed out to the operations in
     * execution order (PX-06).
     */
    private function evaluateMaterialDimensionPooled(ProductionOrderOperation $op, int $tenantId, float $orderQty): array
    {
        $svc = app(OperationMaterialService::class);
        $order = $op->order;

        $ctx = $this->orderCtx[$order->id] ?? null;
        $ops = $ctx['ops'] ?? $svc->orderedOperations($order);
        $pool = $ctx['pool'] ?? $svc->poolAllocation($order, $ops);

        // Direct materials AND every sub-assembly this operation needs that is MADE inside this order (RV-03, rule A). A sub-assembly
        // that is completely from stock has no makers and stays a normal material line.
        $resolvedOp = $ctx['resolved'][$op->id] ?? $svc->resolve($op->setRelation('order', $order), $ops);
        $materials = $resolvedOp['materials'];

        $subLines = [];   // product id => the figures of the sub-assembly line
        foreach ($resolvedOp['sub_assemblies'] as $subEntry) {
            $subPid = (int) $subEntry['product_id'];
            $madeSet = $svc->madeFromSet($order, $op, $subEntry);
            $subLines[$subPid] ??= ['row' => $subEntry['row'], 'total' => 0.0, 'from_stock' => 0.0, 'to_produce' => 0.0, 'made' => 0.0, 'done' => 0, 'makers' => 0];
            $subLines[$subPid]['total'] += (float) $subEntry['required'];
            $subLines[$subPid]['from_stock'] += (float) $subEntry['from_stock'];
            $subLines[$subPid]['to_produce'] += (float) $subEntry['to_produce'];
            $subLines[$subPid]['made'] += $madeSet['made'];
            $subLines[$subPid]['done'] += $madeSet['done'];
            $subLines[$subPid]['makers'] += $madeSet['total'];
        }

        $lineSources = [];
        foreach ($materials as $m) {
            if (isset($subLines[(int) $m['product_id']])) {
                continue;   // the part of a sub-assembly taken from stock: it belongs to the sub-assembly line below
            }
            $lineSources[] = [
                'row' => $m['row'],
                'product_id' => (int) $m['product_id'],
                'required' => (float) $m['required'],
                'issued' => (float) ($pool[$op->id][$m['product_id']]['issued'] ?? 0.0),
                'sub' => null,
            ];
        }
        foreach ($subLines as $subPid => $subLine) {
            $stockIssued = (float) ($pool[$op->id][$subPid]['issued'] ?? 0.0);
            $lineSources[] = [
                'row' => $subLine['row'],
                'product_id' => (int) $subPid,
                'required' => $subLine['total'],
                'issued' => min($subLine['total'], $stockIssued + $subLine['made']),   // issued from the store + made
                'sub' => $subLine + ['stock_issued' => $stockIssued],
            ];
        }
        // BOM order like resolve(): the line listed first in the BOM comes first
        usort($lineSources, fn ($a, $b) => [(int) ($a['row']->bom_item_id ?? 0), (int) ($a['row']->sequence ?? 0), (int) $a['row']->id] <=> [(int) ($b['row']->bom_item_id ?? 0), (int) ($b['row']->sequence ?? 0), (int) $b['row']->id]);

        $minUnits = min(1.0, $orderQty);   // "the 1 QT" of the rule (a target below 1 is its own minimum)
        $eps = 0.00005;

        $blockers = [];
        $warnings = [];
        $lines = [];
        $minUnitsCovered = $orderQty;
        $anyBelowMin = false;
        $anyShort = false;
        $totRequired = $totIssued = $totShort = 0.0;

        foreach ($lineSources as $src) {
            $pid = $src['product_id'];
            $required = (float) $src['required'];
            if ($required <= 0.0) {
                continue;
            }
            $sub = $src['sub'];

            $issued = (float) $src['issued'];                                // a sub-assembly: issued from the store + made (RV-03)
            $perUnit = $orderQty > 0 ? $required / $orderQty : 0.0;          // BOM quantity needed for 1 unit
            $unitsCovered = $perUnit > 0 ? min($orderQty, $issued / $perUnit) : $orderQty;
            $minUnitsCovered = min($minUnitsCovered, $unitsCovered);

            $belowMin = $perUnit > 0 && ($issued + $eps) < ($perUnit * $minUnits);
            $isFull = ($issued + $eps) >= $required;
            $state = $belowMin ? 'blocked' : ($isFull ? 'ready' : 'partial');

            $totRequired += $required;
            $totIssued += $issued;
            $totShort += max(0.0, $required - $issued);

            $name = $src['row']->material?->name ?? "Product #{$pid}";
            $lines[] = [
                'product_id' => $pid,
                'name' => $name,
                'required' => round($required, 4),       // a sub-assembly: its whole need (stock part + made part)
                'issued' => round($issued, 4),           // a sub-assembly: issued from the store + made
                'per_unit' => round($perUnit, 4),
                'units_covered' => round($unitsCovered, 4),
                'state' => $state,
                'sub_assembly' => $sub !== null,         // RV-03: a sub-assembly made inside this order (what it is made of below)
            ] + ($sub === null ? [] : [
                'from_stock' => round($sub['from_stock'], 4),
                'stock_issued' => round($sub['stock_issued'], 4),
                'to_produce' => round($sub['to_produce'], 4),
                'made' => round($sub['made'], 4),
                'makers_done' => (int) $sub['done'],
                'makers_total' => (int) $sub['makers'],
            ]);

            $there = $sub === null ? 'issued' : 'available';
            if ($belowMin) {
                $anyBelowMin = true;
                // RV-03: while the operations that make a sub-assembly are still running, its shortfall is a pure WAIT for them (PX-08 / UR-02:
                // a wait is never an actionable "Blocked"); once they are all done, a shortfall is a real blocker again
                $pureWait = $sub !== null && (int) $sub['done'] < (int) $sub['makers'];
                $blockers[] = [
                    'code' => self::REASON_MATERIAL_MINIMUM_NOT_ISSUED,
                    'dimension' => 'material',
                    'message' => "Cannot produce: minimum quantity of [{$name}] is not {$there} (1 unit needs " . qty_format($perUnit) . ", {$there} " . qty_format($issued) . ').',
                    'severity' => $pureWait ? 'info' : 'error',
                    'product_id' => $pid,
                ] + ($pureWait ? ['is_workflow_dependency' => true] : []);
            } elseif (!$isFull) {
                $anyShort = true;
                $warnings[] = [
                    'code' => self::REASON_MATERIAL_SHORTAGE,
                    'dimension' => 'material',
                    'message' => ($sub === null ? 'Partially issued' : 'Partially available') . ": [{$name}] " . qty_format($issued) . ' of ' . qty_format($required) . " {$there} (enough for " . qty_format($unitsCovered) . ' of ' . qty_format($orderQty) . ' units).',
                    'severity' => 'warning',
                    'product_id' => $pid,
                ];
            }
        }

        if (empty($lines)) {
            return [
                'status' => self::STATUS_READY,
                'label' => __('production.no_direct_materials'),
                'tone' => 'secondary',
                'required_qty' => $orderQty,
                'issued_qty' => $orderQty,
                'shortage_qty' => 0.0,
                'ready_qty' => $orderQty,
                'materials' => [],
                'blockers' => [],
                'warnings' => [],
            ];
        }

        if ($anyBelowMin) {
            $status = self::STATUS_BLOCKED;
            $label = __('production.material_blocked_minimum');
            $tone = 'danger';
            $readyQty = 0.0;
        } elseif ($anyShort) {
            $status = self::STATUS_PARTIALLY_READY;
            $label = __('production.material_partially_issued');
            $tone = 'warning';
            $readyQty = max(0.0, round(min($orderQty, $minUnitsCovered), 4));
        } else {
            $status = self::STATUS_READY;
            $label = __('production.ready');
            $tone = 'success';
            $readyQty = $orderQty;
        }

        return [
            'status' => $status,
            'label' => $label,
            'tone' => $tone,
            'required_qty' => round($totRequired, 4),
            'issued_qty' => round($totIssued, 4),
            'shortage_qty' => round($totShort, 4),
            'ready_qty' => $readyQty,
            'materials' => $lines,
            'blockers' => $blockers,
            'warnings' => $warnings,
        ];
    }

    private function evaluateDependencyDimension(ProductionOrderOperation $op, int $tenantId, float $orderQty): array
    {
        $blockers = [];
        $warnings = [];
        $waitingFor = [];      // UR-02: the operations this one is waiting for (named on screen)
        $partialFrom = null;   // UR-02: predecessor that handed over only part of the quantity

        // 1. Intra-routing predecessor check
        $intraReadyQty = $orderQty;
        $prevOp = null;

        if ($op->previous_operation_id) {
            $prevOp = $op->previousOperation;
            if (!$prevOp) {
                $prevOp = ProductionOrderOperation::withoutGlobalScopes()->find($op->previous_operation_id);
            }
        } elseif ($op->is_parallel) {
            // Explicitly parallel operation - starts concurrently, no linear predecessor constraint
            $prevOp = null;
        } elseif ($op->routing_operation_id || $op->relationLoaded('routingOperation')) {
            $rOp = $op->routingOperation ?: \App\Domains\Production\Models\RoutingOperation::find($op->routing_operation_id);
            if ($rOp && ($rOp->is_parallel || $rOp->previous_operation_id === null)) {
                // Explicitly independent or parallel routing operation
                $prevOp = null;
            }
        } else {
            // Check if order operations have explicit dependency tracking configured
            $hasExplicitTracking = $op->order && $op->order->relationLoaded('operations')
                ? $op->order->operations->contains(fn($o) => $o->previous_operation_id !== null || $o->is_parallel)
                : ProductionOrderOperation::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('production_order_id', $op->production_order_id)
                    ->where(fn($q) => $q->whereNotNull('previous_operation_id')->orWhere('is_parallel', true))
                    ->exists();

            if (!$hasExplicitTracking) {
                if ($op->order && $op->order->relationLoaded('operations')) {
                    $prevOp = $op->order->operations
                        ->filter(function ($other) use ($op) {
                            if ($other->sequence >= $op->sequence) return false;
                            return $op->source_product_id
                                ? (int) $other->source_product_id === (int) $op->source_product_id
                                : empty($other->source_product_id);
                        })
                        ->sortByDesc('sequence')
                        ->first();
                }
                if (!$prevOp) {
                    $prevOp = ProductionOrderOperation::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('production_order_id', $op->production_order_id)
                        ->where('sequence', '<', $op->sequence)
                        ->where(function ($q) use ($op) {
                            if ($op->source_product_id) {
                                $q->where('source_product_id', $op->source_product_id);
                            } else {
                                $q->whereNull('source_product_id');
                            }
                        })
                        ->orderBy('sequence', 'desc')
                        ->first();
                }
            }
        }

        if ($prevOp) {
            $produced = (float) $prevOp->quantity_produced;
            $transferred = (float) ($op->quantity_transferred_in > 0 ? $op->quantity_transferred_in : ($prevOp->quantity_transferred_out ?? 0.0));
            $isOverlap = (bool) (
                $prevOp->overlap_enabled
                || $op->overlap_enabled
                || ((float) ($prevOp->transfer_batch_quantity ?? 0) > 0)
                || ((float) ($op->transfer_batch_quantity ?? 0) > 0)
                || (bool) ($prevOp->routingOperation?->overlap_enabled ?? false)
                || (bool) ($op->routingOperation?->overlap_enabled ?? false)
            );

            if ($prevOp->status === ProductionOrderOperation::STATUS_COMPLETED) {
                $availableWip = max($produced, $transferred);
            } elseif ($isOverlap || $transferred > 0) {
                $availableWip = $transferred;
            } else {
                $availableWip = 0.0;
            }

            if ($availableWip <= 0.0) {
                $intraReadyQty = 0.0;
                $waitingFor[] = $this->describePredecessor($prevOp, 'predecessor');
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
                    $partialFrom = ['predecessor' => $this->describePredecessor($prevOp, 'predecessor'), 'received' => $intraReadyQty, 'of' => $orderQty];
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

        // UR-07: sub-assemblies made INSIDE this order (the drawer of this operation): the operation waits — and cannot be executed —
        // until ALL the operations that make them are completed, whatever the stock is.
        $coveredOpIds = [];
        foreach ($this->pendingSubAssemblies($op) as $group) {
            $crossReadyLimits[] = 0.0;
            $waitingFor[] = $group;
            foreach ($group['pending'] as $pending) {
                $coveredOpIds[] = $pending['operation_id'];
            }
            $blockers[] = [
                'code' => self::REASON_SUB_ASSEMBLY_INCOMPLETE,
                'dimension' => 'dependency',
                'message' => 'Waiting for sub-assembly [' . ($group['product'] ?? 'sub-assembly') . "]: {$group['done']} of {$group['total']} of its operations are completed.",
                'severity' => 'info',
                'is_workflow_dependency' => true,
            ];
        }

        $crossPreds = $op->predecessorDependencies()
            ->wherePivot('dependency_type', 'cross_assembly')
            ->get();

        foreach ($crossPreds as $predOp) {
            if (in_array($predOp->id, $coveredOpIds, true)) {
                continue; // already waiting for it through its sub-assembly group above
            }
            $childProductId = $predOp->source_product_id;
            if (!$childProductId) continue;

            $targetBomId = $op->source_bom_id ?? $op->order->bom_id;
            // the operation's OWN row for this sub-assembly first (the same material can be allocated to several operations with different quantities)
            $bomAlloc = ($op->routing_operation_id
                ? \App\Domains\Production\Models\RoutingOperationMaterial::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('bom_id', $targetBomId)
                    ->where('routing_operation_id', $op->routing_operation_id)
                    ->where('material_id', $childProductId)
                    ->where('is_alternative', false)
                    ->first()
                : null)
                ?? \App\Domains\Production\Models\RoutingOperationMaterial::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('bom_id', $targetBomId)
                    ->where('material_id', $childProductId)
                    ->where('is_alternative', false)
                    ->first();
            $bomItem = $bomAlloc ?: ProductionBomItem::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('bom_id', $targetBomId)
                ->where('material_id', $childProductId)
                ->first(); // PO-IMPL-02: no is_alternative on master rows
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
            $claimed = (float) ($predOpFresh->quantity_claimed ?? 0.0);
            $qcHold = (float) ($predOpFresh->active_qc_hold ?? 0.0);
            $rework = (float) ($predOpFresh->active_rework ?? 0.0);
            $consumed = (float) ($predOpFresh->quantity_consumed ?? 0.0);

            $usableIntermediateSfg = max(0.0, $produced - ($qcHold + $rework) - $consumed);
            $totalUsableSfg = $issuedToOrder + $whStock + $usableIntermediateSfg;

            // UR-07: finish-to-start — the operation waits until the sub-assembly operation is completed, whatever the stock is
            // (no early start on a transfer batch / queue threshold any more).
            if (!in_array($predOpFresh->status, [ProductionOrderOperation::STATUS_COMPLETED, ProductionOrderOperation::STATUS_SKIPPED, ProductionOrderOperation::STATUS_CANCELLED], true)) {
                $crossReadyLimits[] = 0.0;
                $waitingFor[] = [
                    'kind' => 'sub_assembly',
                    'product_id' => (int) $childProductId,
                    'product' => Product::withoutGlobalScopes()->find($childProductId)?->name,
                    'done' => 0,
                    'total' => 1,
                    'pending' => [$this->describePredecessor($predOpFresh, 'sub_assembly')],
                ];
                $blockers[] = [
                    'code' => self::REASON_SUB_ASSEMBLY_INCOMPLETE,
                    'dimension' => 'dependency',
                    'message' => "Waiting for sub-assembly operation {$predOpFresh->operation_number}: it is not completed yet.",
                    'severity' => 'info',
                    'is_workflow_dependency' => true,
                ];
            } else {
                $crossReadyLimits[] = $totalUsableSfg / $bomRatio;
            }
        }

        $minCrossReady = !empty($crossReadyLimits) ? min($crossReadyLimits) : $orderQty;
        $readyQty = max(0.0, round(min($intraReadyQty, $minCrossReady), 4));

        if (!empty($blockers) && $readyQty <= 0.0) {
            // PX-08 / UR-02: a pure workflow wait (predecessor or sub-assembly not finished) is WAITING — never a fault / never "BLOCKED"
            $hasOwnBlocker = collect($blockers)->contains(fn ($b) => empty($b['is_workflow_dependency']));
            $status = $hasOwnBlocker ? self::STATUS_BLOCKED : self::STATUS_WAITING_PREDECESSOR;
        } elseif ($readyQty < $orderQty) {
            $status = self::STATUS_PARTIALLY_READY;
        } else {
            $status = self::STATUS_READY;
        }

        return array_merge([
            'status' => $status,
            'ready_qty' => $readyQty,
            'blocked_qty' => max(0.0, round($orderQty - $readyQty, 4)),
            'blockers' => $blockers,
            'warnings' => $warnings,
        ], $this->describeFlow($op, $waitingFor, $partialFrom, $prevOp));
    }

    /**
     * UR-07 — the sub-assemblies (child-BOM materials made INSIDE this order) this operation needs whose making operations are not
     * all completed yet. One entry per sub-assembly; empty when there is nothing to wait for.
     *
     * @return array<int, array{kind: string, product_id: int, product: ?string, done: int, total: int, pending: array}>
     */
    public function pendingSubAssemblies(ProductionOrderOperation $op): array
    {
        $op->loadMissing('order');
        $order = $op->order;
        if (!$order) {
            return [];
        }

        $svc = app(OperationMaterialService::class);
        $ops = ($this->orderCtx[$order->id]['ops'] ?? null) ?: $svc->orderedOperations($order);
        $finished = [
            ProductionOrderOperation::STATUS_COMPLETED,
            ProductionOrderOperation::STATUS_SKIPPED,
            ProductionOrderOperation::STATUS_CANCELLED,
        ];

        $groups = [];
        $resolvedOp = ($this->orderCtx[$order->id]['resolved'][$op->id] ?? null) ?: $svc->resolve($op->setRelation('order', $order), $ops);
        foreach ($resolvedOp['sub_assemblies'] as $sub) {
            $makers = $sub['producers'];
            $open = $makers->reject(fn ($m) => in_array($m->status, $finished, true));
            if ($open->isEmpty()) {
                continue;
            }

            $groups[] = [
                'kind' => 'sub_assembly',
                'product_id' => (int) $sub['product_id'],
                'product' => $sub['row']->material?->name,
                'done' => $makers->count() - $open->count(),
                'total' => $makers->count(),
                'pending' => $open->sortBy('sequence')->map(fn ($m) => $this->describePredecessor($m, 'sub_assembly'))->values()->all(),
            ];
        }

        return $groups;
    }

    /**
     * UR-07 — the rule behind the "parent waits" drawer: an operation that needs a sub-assembly made inside this order can never be
     * started, logged on or completed until ALL the operations that make it are completed. There is no manager override for this.
     *
     * @throws \InvalidArgumentException
     */
    public function assertSubAssembliesComplete(ProductionOrderOperation $op): void
    {
        $pending = $this->pendingSubAssemblies($op);
        if (empty($pending)) {
            return;
        }

        $what = collect($pending)
            ->map(fn ($g) => '[' . ($g['product'] ?? 'sub-assembly') . "] ({$g['done']} of {$g['total']} operations completed)")
            ->implode(', ');

        throw new \InvalidArgumentException(
            "Operation {$op->operation_number} cannot be executed yet: it waits for the operations that make the sub-assembly {$what}. Complete them first."
        );
    }

    /** One operation as it is named on screen: "Op 10" + its name (and the product it makes when it belongs to a sub-assembly). */
    private function describePredecessor(ProductionOrderOperation $p, string $kind): array
    {
        $product = null;
        if ($kind === 'sub_assembly' && $p->source_product_id) {
            $product = Product::withoutGlobalScopes()->find($p->source_product_id)?->name;
        }

        return [
            'operation_id' => $p->id,
            'sequence' => $p->sequence,
            'operation_number' => $p->operation_number,
            'name' => $p->name,
            'kind' => $kind,
            'product' => $product,
        ];
    }

    /**
     * UR-02 — "Operation Flow" column: where the operation stands in the sequence, in words (never red "Blocked" for a wait).
     *   waiting for Op N / can start with part of the quantity / ready to start (independent or predecessor finished) /
     *   in progress / paused / completed (other statuses are shown by their own name).
     *
     * @return array{state: string, label: string, tone: string, detail: ?string, waiting_for: array, partial_from: ?array}
     */
    private function describeFlow(ProductionOrderOperation $op, array $waitingFor, ?array $partialFrom, ?ProductionOrderOperation $prevOp): array
    {
        $opName = fn (array $p) => 'Op ' . $p['sequence'];
        $status = strtolower((string) $op->status);
        $base = ['waiting_for' => $waitingFor, 'partial_from' => $partialFrom, 'detail' => null];

        $own = [
            ProductionOrderOperation::STATUS_COMPLETED => ['completed', __('production.flow_completed'), 'success'],
            ProductionOrderOperation::STATUS_RUNNING => ['in_progress', __('production.flow_in_progress'), 'primary'],
            ProductionOrderOperation::STATUS_PAUSED => ['paused', __('production.flow_paused'), 'warning'],
        ];
        if (isset($own[$status])) {
            return $base + ['state' => $own[$status][0], 'label' => $own[$status][1], 'tone' => $own[$status][2]];
        }
        if (!in_array($status, ['', ProductionOrderOperation::STATUS_WAITING, ProductionOrderOperation::STATUS_READY, 'pending'], true)) {
            // skipped, cancelled, QC hold, rework, … — shown by their own name
            return $base + ['state' => 'other', 'label' => ucfirst(str_replace('_', ' ', $status)), 'tone' => 'secondary'];
        }

        if (!empty($waitingFor)) {
            // "Op 10" for the operations of its own routing, "sub-assembly semi-4" for the operations that make a sub-assembly (UR-07)
            $preds = collect($waitingFor)->where('kind', 'predecessor');
            $subs = collect($waitingFor)->where('kind', 'sub_assembly');

            $parts = [];
            $details = [];
            if ($preds->isNotEmpty()) {
                $parts[] = $preds->map($opName)->implode(', ');
                $details[] = $preds->pluck('name')->implode(', ');
            }
            if ($subs->isNotEmpty()) {
                $parts[] = __('production.flow_sub_assembly', ['products' => $subs->pluck('product')->filter()->unique()->implode(', ') ?: '…']);
                foreach ($subs as $group) {
                    $details[] = __('production.flow_sub_assembly_progress', ['product' => $group['product'] ?? '…', 'done' => $group['done'], 'total' => $group['total']]);
                }
            }

            return array_merge($base, [
                'state' => 'waiting',
                'label' => __('production.flow_waiting_for', ['ops' => implode(' + ', $parts)]),
                'tone' => 'info',
                'detail' => implode('; ', $details),
            ]);
        }

        if ($partialFrom) {
            return array_merge($base, [
                'state' => 'partial',
                'label' => __('production.flow_partial', [
                    'got' => qty_format($partialFrom['received']),
                    'of' => qty_format($partialFrom['of']),
                    'op' => $opName($partialFrom['predecessor']),
                ]),
                'tone' => 'warning',
                'detail' => $partialFrom['predecessor']['name'],
            ]);
        }

        if (!$prevOp) {
            $detail = __('production.flow_ready_independent');
        } elseif ($prevOp->status === ProductionOrderOperation::STATUS_COMPLETED) {
            $detail = __('production.flow_ready_after', ['op' => 'Op ' . $prevOp->sequence]);
        } else {
            $detail = __('production.flow_ready_received', ['op' => 'Op ' . $prevOp->sequence]);
        }

        return array_merge($base, [
            'state' => 'ready',
            'label' => __('production.flow_ready'),
            'tone' => 'success',
            'detail' => $detail,
        ]);
    }

    /**
     * UR-03 — "Resources" column: every resource the operation needs (a machine or a manual batch) — ready or not, and whether another
     * operation is already running on it or booked on it in the same time slot. The checks that existed before (machine status,
     * maintenance, open downtime, manual batch / section / shift) are kept unchanged in evaluateResourceCore().
     * A busy / booked resource is information (a warning), not a block.
     */
    private function evaluateMachineDimension(ProductionOrderOperation $op, int $tenantId): array
    {
        $core = $this->evaluateResourceCore($op, $tenantId);

        $notNeeded = (bool) ($op->is_external || $op->routingOperation?->is_external || $op->work_center_id === null);
        if ($notNeeded) {
            return $core + ['label' => __('production.resource_not_needed'), 'tone' => 'secondary', 'resources' => []];
        }

        $isBatch = !$op->machine_id && $op->section_manual_production_id;
        $type = $isBatch ? 'manual_batch' : 'machine';
        $resourceId = $isBatch ? (int) $op->section_manual_production_id : (int) $op->machine_id;
        $blocked = $core['status'] === self::STATUS_BLOCKED;
        $name = $isBatch ? ($core['assigned_resource'] ?? null) : ($core['assigned_machine'] ?? null);

        if (!$name && !$blocked) {
            return $core + ['label' => __('production.resource_not_assigned'), 'tone' => 'secondary', 'resources' => []];
        }
        $name = $name ?: '—';

        $busy = [];
        $booked = [];
        if (!$blocked && $resourceId > 0) {
            [$busy, $booked] = $this->resourceOccupancy($op, $type, $resourceId);
        }

        $extraWarnings = [];
        $detail = null;
        if ($blocked) {
            $state = 'unavailable';
            $tone = 'danger';
            $detail = $core['blockers'][0]['message'] ?? null;
        } elseif (!empty($busy)) {
            $state = 'busy';
            $tone = 'warning';
            $detail = 'Running now: ' . implode('; ', $busy);
            $extraWarnings[] = [
                'code' => self::REASON_RESOURCE_BUSY,
                'dimension' => 'machine',
                'message' => "{$name} is busy now — running " . implode('; ', $busy) . '.',
                'severity' => 'warning',
            ];
        } elseif (!empty($booked)) {
            $state = 'booked';
            $tone = 'warning';
            $detail = 'Also booked: ' . implode('; ', $booked);
            $extraWarnings[] = [
                'code' => self::REASON_RESOURCE_BOOKED,
                'dimension' => 'machine',
                'message' => "{$name} is also booked in this operation's time slot: " . implode('; ', $booked) . '.',
                'severity' => 'warning',
            ];
        } else {
            $state = 'ready';
            $tone = 'success';
        }

        $stateLabel = __('production.resource_' . ($state === 'unavailable' ? 'unavailable' : $state));

        return array_merge($core, [
            'warnings' => array_merge($core['warnings'] ?? [], $extraWarnings),
            'label' => $name . ' — ' . $stateLabel,
            'tone' => $tone,
            'resources' => [[
                'type' => $type,
                'name' => $name,
                'state' => $state,
                'state_label' => $stateLabel,
                'tone' => $tone,
                'detail' => $detail,
            ]],
        ]);
    }

    /**
     * Other operations (any order) on the same machine / manual batch: [running now, booked in this operation's time slot].
     * Each entry reads like "ORD-2026-000007 Op 20 (24 Nov 10:00–14:00)".
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function resourceOccupancy(ProductionOrderOperation $op, string $type, int $resourceId): array
    {
        $col = $type === 'machine' ? 'machine_id' : 'section_manual_production_id';
        $tenantId = (int) $op->tenant_id;

        $running = ProductionOrderOperation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where($col, $resourceId)
            ->where('id', '!=', $op->id)
            ->where('status', ProductionOrderOperation::STATUS_RUNNING)
            ->orderBy('id')
            ->limit(3)
            ->get(['id', 'production_order_id', 'sequence']);

        $sched = $op->scheduleOperation;
        $scheduled = collect();
        if ($sched && $sched->planned_start && $sched->planned_finish) {
            // same rule as the scheduler's own conflict check (SchedulingService::detectConflictsDetailed): live schedules, unfinished operations
            $scheduled = ProductionScheduleOperation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where($col, $resourceId)
                ->where('id', '!=', $sched->id)
                ->where('production_order_operation_id', '!=', $op->id)
                ->whereNotIn('production_order_operation_id', $running->pluck('id')->all())
                ->whereNotIn('status', [
                    ProductionScheduleOperation::STATUS_COMPLETED,
                    ProductionScheduleOperation::STATUS_CANCELLED,
                    ProductionScheduleOperation::STATUS_SKIPPED,
                ])
                ->whereHas('schedule', fn ($q) => $q->where('tenant_id', $tenantId)->whereIn('status', ['scheduled', 'released', 'in_progress']))
                ->where('planned_start', '<', $sched->planned_finish)
                ->where('planned_finish', '>', $sched->planned_start)
                ->orderBy('planned_start')
                ->limit(3)
                ->get(['id', 'production_order_id', 'sequence', 'planned_start', 'planned_finish']);
        }

        $orderIds = $running->pluck('production_order_id')->merge($scheduled->pluck('production_order_id'))->unique()->all();
        $numbers = $orderIds
            ? ProductionOrder::withoutGlobalScopes()->whereIn('id', $orderIds)->pluck('order_number', 'id')
            : collect();

        $busy = $running->map(fn ($r) => ($numbers[$r->production_order_id] ?? ('Order #' . $r->production_order_id)) . ' Op ' . $r->sequence)->all();
        $booked = $scheduled->map(function ($r) use ($numbers) {
            $from = \Carbon\Carbon::parse($r->planned_start);
            $to = \Carbon\Carbon::parse($r->planned_finish);

            return ($numbers[$r->production_order_id] ?? ('Order #' . $r->production_order_id)) . ' Op ' . $r->sequence
                . ' (' . $from->format('d M H:i') . '–' . ($from->isSameDay($to) ? $to->format('H:i') : $to->format('d M H:i')) . ')';
        })->all();

        return [$busy, $booked];
    }

    private function evaluateResourceCore(ProductionOrderOperation $op, int $tenantId): array
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

        // An operation runs on a machine OR a manual batch of a section (RT-06 / RT-07). A batch operation is checked on its batch.
        if (!$op->machine_id && $op->section_manual_production_id) {
            return $this->evaluateManualBatchDimension($op, $tenantId);
        }

        $machine = $op->machine;

        if (!$machine && $op->work_center_id) {
            $totalMachines = Machine::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereHas('section', fn ($q) => $q->where('work_center_id', $op->work_center_id))
                ->count();

            if ($totalMachines > 0) {
                // Check if active machines exist at Work Center
                $activeMachines = Machine::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereHas('section', fn ($q) => $q->where('work_center_id', $op->work_center_id))
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

        // PX-07: downtime uses the real statuses (open / closed). An OPEN downtime that has started and has not ended blocks the machine.
        $now = now();
        $downtimeConflict = ProductionMachineDowntime::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('machine_id', $machine->id)
            ->where('status', ProductionMachineDowntime::STATUS_OPEN)
            ->where(fn ($q) => $q->whereNull('start_time')->orWhere('start_time', '<=', $now))
            ->where(fn ($q) => $q->whereNull('end_time')->orWhere('end_time', '>', $now))
            ->exists();

        // A downtime that overlaps the operation's scheduled window is only a warning.
        $sched = $op->scheduleOperation;
        if (!$downtimeConflict && $sched && $sched->planned_start && $sched->planned_finish) {
            $overlap = ProductionMachineDowntime::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('machine_id', $machine->id)
                ->where('start_time', '<', $sched->planned_finish)
                ->where(fn ($q) => $q->whereNull('end_time')->orWhere('end_time', '>', $sched->planned_start))
                ->exists();
            if ($overlap) {
                $warnings[] = [
                    'code' => 'MACHINE_DOWNTIME_IN_WINDOW',
                    'dimension' => 'machine',
                    'message' => "Machine [{$machine->name}] has a downtime that overlaps this operation's scheduled window.",
                    'severity' => 'warning',
                    'machine_id' => $machine->id,
                ];
            }
        }

        // RT-06/07 + RT-11: one resource per operation — no invented alternate machines. An unavailable machine blocks.
        if ($isInactive || $isMaintenance || $downtimeConflict) {
            $code = $isMaintenance ? self::REASON_MACHINE_UNDER_MAINTENANCE : self::REASON_MACHINE_UNAVAILABLE;
            $reason = $downtimeConflict ? 'has an open downtime' : ($isMaintenance ? 'is under maintenance' : 'is inactive');
            $blockers[] = [
                'code' => $code,
                'dimension' => 'machine',
                'message' => "Machine [{$machine->name}] {$reason}.",
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

        return [
            'status' => self::STATUS_READY,
            'assigned_machine' => $machine->name,
            'alternate_available' => false,
            'blockers' => [],
            'warnings' => $warnings,
        ];
    }

    /**
     * Resource dimension of a MANUAL BATCH operation: the batch and its section must be active and the batch needs an active shift
     * (a batch works only its own shift). Same result shape as the machine dimension.
     */
    private function evaluateManualBatchDimension(ProductionOrderOperation $op, int $tenantId): array
    {
        $batch = \App\Domains\Production\Models\SectionManualProduction::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->find($op->section_manual_production_id);
        $section = $batch ? \App\Domains\Production\Models\WorkCenterSection::withoutGlobalScopes()->find($batch->section_id) : null;
        $shift = $batch?->shift_id ? \App\Domains\Production\Models\ProductionShift::withoutGlobalScopes()->find($batch->shift_id) : null;

        $problem = null;
        if (!$batch) {
            $problem = 'its manual batch no longer exists';
        } elseif (!$batch->active) {
            $problem = "manual batch [{$batch->batch_name}] is inactive";
        } elseif (!$section || !$section->isActive()) {
            $problem = "the section of manual batch [{$batch->batch_name}] is inactive";
        } elseif ($shift && !(bool) ($shift->active ?? true)) {
            $problem = "the shift [{$shift->name}] of manual batch [{$batch->batch_name}] is inactive";
        }

        if ($problem) {
            return [
                'status' => self::STATUS_BLOCKED,
                'assigned_machine' => null,
                'assigned_resource' => $batch?->batch_name,
                'alternate_available' => false,
                'blockers' => [[
                    'code' => self::REASON_MACHINE_UNAVAILABLE,
                    'dimension' => 'machine',
                    'message' => "Operation {$op->operation_number}: {$problem}.",
                    'severity' => 'error',
                ]],
                'warnings' => [],
            ];
        }

        return [
            'status' => self::STATUS_READY,
            'assigned_machine' => null,
            'assigned_resource' => $batch->batch_name,
            'alternate_available' => false,
            'blockers' => [],
            'warnings' => [],
        ];
    }

    /**
     * UR-04 — Subcontract column: only "Subcontracted" / "Not subcontracted" for now.
     * No ready / blocked verdict and no vendor-return check until the user gives the tests; the quantity is not limited by it.
     */
    private function evaluateSubcontractDimension(ProductionOrderOperation $op, int $tenantId, float $orderQty): array
    {
        $isSubcontracted = (bool) $op->is_external;

        return [
            'status' => self::STATUS_READY,
            'is_subcontracted' => $isSubcontracted,
            'label' => $isSubcontracted ? __('production.subcontracted') : __('production.not_subcontracted'),
            'tone' => $isSubcontracted ? 'info' : 'secondary',
            'required_qty' => $orderQty,
            'returned_qty' => (float) $op->quantity_produced,
            'ready_qty' => $orderQty,
            'blockers' => [],
            'warnings' => [],
        ];
    }

    private function evaluateQualityDimension(ProductionOrderOperation $op, int $tenantId): array
    {
        // PX-11: same answer as MES — the order-operation snapshot first, the routing only for orders created before the snapshot carried it.
        $isQcRequired = (bool) ($op->quality_required || ($op->routingOperation?->quality_required ?? false));

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
