<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PlanningExceptionService
{
    // Exception Types
    public const TYPE_MATERIAL_SHORTAGE    = 'MATERIAL_SHORTAGE';
    public const TYPE_LATE_INCOMING_SUPPLY = 'LATE_INCOMING_SUPPLY';
    public const TYPE_CAPACITY_OVERLOAD    = 'CAPACITY_OVERLOAD';
    public const TYPE_MACHINE_UNAVAILABLE  = 'MACHINE_UNAVAILABLE';
    public const TYPE_OPERATION_DELAY      = 'OPERATION_DELAY';
    public const TYPE_DUE_DATE_RISK        = 'DUE_DATE_RISK';
    public const TYPE_PENDING_ECO_IMPACT   = 'PENDING_ECO_IMPACT';
    public const TYPE_QUALITY_REWORK_RISK  = 'QUALITY_REWORK_RISK';
    public const TYPE_SUBCONTRACT_DELAY    = 'SUBCONTRACT_DELAY';

    // Severities
    public const SEVERITY_CRITICAL = 'CRITICAL';
    public const SEVERITY_HIGH     = 'HIGH';
    public const SEVERITY_MEDIUM   = 'MEDIUM';
    public const SEVERITY_LOW      = 'LOW';
    public const SEVERITY_ON_TRACK = 'ON_TRACK';

    // Planner Recommendations
    public const REC_EXPEDITE_PO              = 'EXPEDITE_PO';
    public const REC_RESCHEDULE_OPERATION     = 'RESCHEDULE_OPERATION';
    public const REC_ASSIGN_ALTERNATE_MACHINE = 'ASSIGN_ALTERNATE_MACHINE';
    public const REC_REVIEW_SUBCONTRACT       = 'REVIEW_SUBCONTRACT';
    public const REC_REVIEW_PENDING_ECO       = 'REVIEW_PENDING_ECO';
    public const REC_REVIEW_MATERIAL_SHORTAGE = 'REVIEW_MATERIAL_SHORTAGE';
    public const REC_REVIEW_CAPACITY          = 'REVIEW_CAPACITY';
    public const REC_REVIEW_QUALITY           = 'REVIEW_QUALITY';

    // Domain Constants
    public const CAPACITY_ELEVATED_UTILIZATION_PCT = 120.0;
    public const NEAR_TERM_WINDOW_HOURS            = 48;

    public function __construct(
        private readonly ProductionReadinessService $readinessService,
        private readonly CapacityPlanningService $capacityService,
        private readonly ProductionEcoImpactAnalysisService $ecoImpactService,
        private readonly SubcontractPerformanceService $subcontractService,
        private readonly ProductionVarianceAnalysisService $varianceService
    ) {}

    /**
     * Bulk-evaluate active Production Orders for a tenant and return ranked planning exceptions.
     */
    public function evaluateAllOrders(int $tenantId, array $filters = []): array
    {
        $query = ProductionOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                ProductionOrder::STATUS_DRAFT,
                ProductionOrder::STATUS_RELEASED,
                ProductionOrder::STATUS_IN_PROGRESS,
            ])
            ->with([
                'product',
                'routing',
                'bom',
                'operations.workCenter',
                'operations.machine',
                'operations.reworks',
                'operations.predecessorDependencies',
                'operations.routingOperation',
                'scraps',
            ]);

        if (!empty($filters['production_order_id'])) {
            $query->where('id', $filters['production_order_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        $orders = $query->orderBy('id', 'desc')->get();

        // Batch pre-fetch supporting context data
        $openDowntimes = ProductionMachineDowntime::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->get();

        $openEcos = ProductionEco::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                ProductionEco::STATUS_DRAFT,
                ProductionEco::STATUS_UNDER_REVIEW,
                ProductionEco::STATUS_APPROVED,
            ])
            ->get();

        $openNcrs = ProductionNcr::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'investigating'])
            ->get();

        $openPurchaseOrders = PurchaseOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['issued', 'confirmed', 'partially_received'])
            ->with('items')
            ->get();

        $context = [
            'downtimes' => $openDowntimes,
            'ecos' => $openEcos,
            'ncrs' => $openNcrs,
            'purchase_orders' => $openPurchaseOrders,
        ];

        $orderAnalyses = [];

        foreach ($orders as $order) {
            $analysis = $this->evaluateOrderRisk($order, $context);

            if (!empty($filters['severity']) && $analysis['overall_risk'] !== strtoupper($filters['severity'])) {
                continue;
            }

            if (!empty($filters['exception_type'])) {
                $hasType = collect($analysis['exceptions'])->contains('type', strtoupper($filters['exception_type']));
                if (!$hasType) continue;
            }

            $orderAnalyses[] = $analysis;
        }

        // Rank orders deterministically: CRITICAL > HIGH > MEDIUM > LOW > ON_TRACK
        $severityRank = [
            self::SEVERITY_CRITICAL => 1,
            self::SEVERITY_HIGH => 2,
            self::SEVERITY_MEDIUM => 3,
            self::SEVERITY_LOW => 4,
            self::SEVERITY_ON_TRACK => 5,
        ];

        usort($orderAnalyses, function ($a, $b) use ($severityRank) {
            $rankA = $severityRank[$a['overall_risk']] ?? 5;
            $rankB = $severityRank[$b['overall_risk']] ?? 5;
            if ($rankA === $rankB) {
                return $b['order_id'] <=> $a['order_id'];
            }
            return $rankA <=> $rankB;
        });

        $summary = [
            'total_orders' => count($orderAnalyses),
            'critical' => count(array_filter($orderAnalyses, fn($a) => $a['overall_risk'] === self::SEVERITY_CRITICAL)),
            'high' => count(array_filter($orderAnalyses, fn($a) => $a['overall_risk'] === self::SEVERITY_HIGH)),
            'medium' => count(array_filter($orderAnalyses, fn($a) => $a['overall_risk'] === self::SEVERITY_MEDIUM)),
            'low' => count(array_filter($orderAnalyses, fn($a) => $a['overall_risk'] === self::SEVERITY_LOW)),
            'on_track' => count(array_filter($orderAnalyses, fn($a) => $a['overall_risk'] === self::SEVERITY_ON_TRACK)),
        ];

        return [
            'tenant_id' => $tenantId,
            'summary' => $summary,
            'orders' => $orderAnalyses,
        ];
    }

    /**
     * Evaluate the 9 Risk Vectors for a single Production Order using existing domain results.
     */
    public function evaluateOrderRisk(ProductionOrder $order, array $context = []): array
    {
        $readiness = $this->readinessService->evaluateOrderReadiness($order);
        $exceptions = [];

        // Vector 1: Material Shortage
        $this->evaluateMaterialShortageVector($order, $readiness, $exceptions);

        // Vector 2: Late Incoming Supply
        $this->evaluateLateSupplyVector($order, $context['purchase_orders'] ?? collect(), $exceptions);

        // Vector 3: Capacity Overload
        $this->evaluateCapacityOverloadVector($order, $exceptions);

        // Vector 4: Machine Unavailable
        $this->evaluateMachineUnavailableVector($order, $context['downtimes'] ?? collect(), $exceptions);

        // Vector 5: Operation Delay
        $this->evaluateOperationDelayVector($order, $exceptions);

        // Vector 6: Due-Date Completion Risk
        $this->evaluateDueDateRiskVector($order, $exceptions);

        // Vector 7: Pending ECO Impact
        $this->evaluatePendingEcoImpactVector($order, $context['ecos'] ?? collect(), $exceptions);

        // Vector 8: Quality / Rework Risk
        $this->evaluateQualityReworkVector($order, $readiness, $context['ncrs'] ?? collect(), $exceptions);

        // Vector 9: Subcontract Delay
        $this->evaluateSubcontractDelayVector($order, $context['purchase_orders'] ?? collect(), $exceptions);

        // Aggregate overall order severity & risk drivers
        $overallRisk = self::SEVERITY_ON_TRACK;
        $severityOrder = [
            self::SEVERITY_CRITICAL => 1,
            self::SEVERITY_HIGH => 2,
            self::SEVERITY_MEDIUM => 3,
            self::SEVERITY_LOW => 4,
            self::SEVERITY_ON_TRACK => 5,
        ];

        $drivers = [];
        $recommendations = [];

        foreach ($exceptions as $ex) {
            $sev = $ex['severity'];
            if (($severityOrder[$sev] ?? 5) < ($severityOrder[$overallRisk] ?? 5)) {
                $overallRisk = $sev;
            }
            $drivers[] = $ex['risk_driver'];
            if (!empty($ex['recommended_action'])) {
                $recommendations[] = $ex['recommended_action'];
            }
        }

        return [
            'order' => $order,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'tenant_id' => $order->tenant_id,
            'product_id' => $order->product_id,
            'product_name' => $order->product?->name ?? "Product #{$order->product_id}",
            'status' => $order->status,
            'quantity_ordered' => (float) $order->quantity_ordered,
            'quantity_produced' => (float) $order->quantity_produced,
            'start_date' => $order->start_date ? Carbon::parse($order->start_date)->format('Y-m-d') : null,
            'due_date' => $order->end_date ? Carbon::parse($order->end_date)->format('Y-m-d') : null,
            'overall_risk' => $overallRisk,
            'risk_drivers' => array_values(array_unique($drivers)),
            'recommended_actions' => array_values(array_unique($recommendations)),
            'exceptions_count' => count($exceptions),
            'exceptions' => $exceptions,
            'readiness_summary' => [
                'overall_status' => $readiness['overall_status'],
                'ready_operations_count' => $readiness['ready_operations_count'],
                'blocked_operations_count' => $readiness['blocked_operations_count'],
            ],
        ];
    }

    /**
     * Vector 1: Material Shortage Evaluation.
     */
    private function evaluateMaterialShortageVector(ProductionOrder $order, array $readiness, array &$exceptions): void
    {
        foreach ($readiness['operations'] as $opEval) {
            $mat = $opEval['material'] ?? [];
            $isBlockedOrPartial = in_array($mat['status'] ?? '', [ProductionReadinessService::STATUS_BLOCKED, ProductionReadinessService::STATUS_PARTIALLY_READY]);
            $hasShortage = ($mat['shortage_qty'] ?? 0) > 0 || (($mat['ready_qty'] ?? 0) < ($mat['required_qty'] ?? 0) && ($mat['required_qty'] ?? 0) > 0);

            if ($isBlockedOrPartial || $hasShortage) {
                $blockers = $mat['blockers'] ?? [];
                $reasonText = !empty($blockers)
                    ? implode('; ', array_map(fn($b) => is_array($b) ? ($b['message'] ?? $b['code'] ?? 'Blocker') : (string) $b, $blockers))
                    : "Material unreserved or insufficient stock";
                $severity = ($opEval['sequence'] <= 10) ? self::SEVERITY_CRITICAL : self::SEVERITY_HIGH;
                $exceptions[] = [
                    'type' => self::TYPE_MATERIAL_SHORTAGE,
                    'severity' => $severity,
                    'production_order_id' => $order->id,
                    'operation_id' => $opEval['operation_id'],
                    'operation_name' => $opEval['operation_name'],
                    'reason' => "Material shortage on operation '{$opEval['operation_name']}': {$reasonText}",
                    'risk_driver' => 'Material Shortage',
                    'recommended_action' => self::REC_REVIEW_MATERIAL_SHORTAGE,
                    'evidence' => "Target Qty: {$opEval['target_qty']}, Ready Qty: {$opEval['ready_qty']}. Blocker: {$reasonText}",
                ];
            }
        }
    }

    /**
     * Vector 2: Late Incoming Supply Evaluation.
     */
    private function evaluateLateSupplyVector(ProductionOrder $order, Collection $purchaseOrders, array &$exceptions): void
    {
        if ($purchaseOrders->isEmpty()) return;

        foreach ($order->operations as $op) {
            if ($op->status === ProductionOrderOperation::STATUS_COMPLETED) continue;

            $opPlannedStart = $op->actual_start_time ?? ($order->start_date ? Carbon::parse($order->start_date) : now());

            foreach ($purchaseOrders as $po) {
                $expectedDelivery = $po->expected_delivery_date ? Carbon::parse($po->expected_delivery_date) : null;
                if ($expectedDelivery && $expectedDelivery->isAfter($opPlannedStart)) {
                    $delayDays = $expectedDelivery->diffInDays($opPlannedStart);
                    if ($delayDays > 0) {
                        $exceptions[] = [
                            'type' => self::TYPE_LATE_INCOMING_SUPPLY,
                            'severity' => $delayDays >= 3 ? self::SEVERITY_HIGH : self::SEVERITY_MEDIUM,
                            'production_order_id' => $order->id,
                            'operation_id' => $op->id,
                            'operation_name' => $op->name,
                            'reason' => "Incoming PO {$po->po_number} expected date ({$expectedDelivery->format('Y-m-d')}) is after operation planned start ({$opPlannedStart->format('Y-m-d')}).",
                            'risk_driver' => 'Late Incoming Supply',
                            'recommended_action' => self::REC_EXPEDITE_PO,
                            'evidence' => "PO #{$po->po_number} delayed by {$delayDays} days relative to operation sequence {$op->sequence}.",
                        ];
                    }
                }
            }
        }
    }

    /**
     * Vector 3: Capacity Overload Evaluation.
     */
    private function evaluateCapacityOverloadVector(ProductionOrder $order, array &$exceptions): void
    {
        foreach ($order->operations as $op) {
            if ($op->status === ProductionOrderOperation::STATUS_COMPLETED || !$op->workCenter) continue;

            $capacity = $op->workCenter->capacity_per_day > 0 ? (float) $op->workCenter->capacity_per_day : 8.0;
            $plannedHours = ((float) $op->total_time_planned) / 60.0;
            $utilization = ($plannedHours / $capacity) * 100.0;

            if ($utilization > 100.0) {
                $severity = $utilization >= self::CAPACITY_ELEVATED_UTILIZATION_PCT ? self::SEVERITY_HIGH : self::SEVERITY_MEDIUM;
                $exceptions[] = [
                    'type' => self::TYPE_CAPACITY_OVERLOAD,
                    'severity' => $severity,
                    'production_order_id' => $order->id,
                    'operation_id' => $op->id,
                    'operation_name' => $op->name,
                    'reason' => "Work Center '{$op->workCenter->name}' utilization ({$utilization}%) exceeds available capacity for operation '{$op->name}'.",
                    'risk_driver' => 'Capacity Overload',
                    'recommended_action' => self::REC_RESCHEDULE_OPERATION,
                    'evidence' => "Work Center {$op->workCenter->name}: Planned workload {$plannedHours}h vs daily capacity {$capacity}h.",
                ];
            }
        }
    }

    /**
     * Vector 4: Machine Unavailable Evaluation.
     */
    private function evaluateMachineUnavailableVector(ProductionOrder $order, Collection $downtimes, array &$exceptions): void
    {
        foreach ($order->operations as $op) {
            if ($op->status === ProductionOrderOperation::STATUS_COMPLETED || !$op->machine_id) continue;

            $activeDowntime = $downtimes->firstWhere('machine_id', $op->machine_id);
            if ($activeDowntime) {
                // Check if valid alternate machine exists on routing operation
                $hasAlternate = false;
                if ($op->routing_operation_id) {
                    $hasAlternate = \App\Domains\Production\Models\RoutingOperationAlternateMachine::withoutGlobalScopes()
                        ->where('routing_operation_id', $op->routing_operation_id)
                        ->exists();
                }

                if ($hasAlternate) {
                    $exceptions[] = [
                        'type' => self::TYPE_MACHINE_UNAVAILABLE,
                        'severity' => self::SEVERITY_MEDIUM,
                        'production_order_id' => $order->id,
                        'operation_id' => $op->id,
                        'operation_name' => $op->name,
                        'reason' => "Primary Machine '{$op->machine?->name}' is down ({$activeDowntime->reason}), but alternate machine is available.",
                        'risk_driver' => 'Machine Downtime (Alternate Available)',
                        'recommended_action' => self::REC_ASSIGN_ALTERNATE_MACHINE,
                        'evidence' => "Machine #{$op->machine_id} has active downtime ID #{$activeDowntime->id}.",
                    ];
                } else {
                    $exceptions[] = [
                        'type' => self::TYPE_MACHINE_UNAVAILABLE,
                        'severity' => self::SEVERITY_CRITICAL,
                        'production_order_id' => $order->id,
                        'operation_id' => $op->id,
                        'operation_name' => $op->name,
                        'reason' => "Assigned Machine '{$op->machine?->name}' is down ({$activeDowntime->reason}) with NO alternate machine defined.",
                        'risk_driver' => 'Machine Breakdown (No Alternate)',
                        'recommended_action' => self::REC_ASSIGN_ALTERNATE_MACHINE,
                        'evidence' => "Machine #{$op->machine_id} open breakdown since {$activeDowntime->start_time}.",
                    ];
                }
            }
        }
    }

    /**
     * Vector 5: Operation Delay Evaluation.
     */
    private function evaluateOperationDelayVector(ProductionOrder $order, array &$exceptions): void
    {
        foreach ($order->operations as $op) {
            if ($op->status === ProductionOrderOperation::STATUS_COMPLETED) continue;

            $plannedTotal = (float) $op->total_time_planned;
            $actualTotal = (float) $op->setup_time_actual + (float) $op->processing_time_actual;

            if ($plannedTotal > 0 && $actualTotal > $plannedTotal) {
                $variancePct = (($actualTotal - $plannedTotal) / $plannedTotal) * 100.0;
                if ($variancePct > 15.0) {
                    $exceptions[] = [
                        'type' => self::TYPE_OPERATION_DELAY,
                        'severity' => $variancePct > 30.0 ? self::SEVERITY_HIGH : self::SEVERITY_MEDIUM,
                        'production_order_id' => $order->id,
                        'operation_id' => $op->id,
                        'operation_name' => $op->name,
                        'reason' => "Operation '{$op->name}' execution delayed by +{$variancePct}% over planned duration.",
                        'risk_driver' => 'Operation Execution Delay',
                        'recommended_action' => self::REC_RESCHEDULE_OPERATION,
                        'evidence' => "Planned: {$plannedTotal}m, Actual: {$actualTotal}m (+{$variancePct}%).",
                    ];
                }
            }
        }
    }

    /**
     * Vector 6: Due-Date Completion Risk Evaluation.
     */
    private function evaluateDueDateRiskVector(ProductionOrder $order, array &$exceptions): void
    {
        if (!$order->end_date) return;

        $dueDate = Carbon::parse($order->end_date)->endOfDay();
        $isCompleted = in_array($order->status, [ProductionOrder::STATUS_COMPLETED, ProductionOrder::STATUS_CLOSED]);

        if ($isCompleted) return;

        // Estimate completion based on current date & remaining processing
        $now = now();
        if ($now->isAfter($dueDate)) {
            $overdueDays = $now->diffInDays($dueDate);
            $exceptions[] = [
                'type' => self::TYPE_DUE_DATE_RISK,
                'severity' => self::SEVERITY_CRITICAL,
                'production_order_id' => $order->id,
                'operation_id' => null,
                'operation_name' => null,
                'reason' => "Production Order {$order->order_number} is overdue by {$overdueDays} days beyond target due date ({$dueDate->format('Y-m-d')}).",
                'risk_driver' => 'Due Date Breach',
                'recommended_action' => self::REC_RESCHEDULE_OPERATION,
                'evidence' => "Target Due Date: {$dueDate->format('Y-m-d')}. Current Date: {$now->format('Y-m-d')}.",
            ];
        }
    }

    /**
     * Vector 7: Pending ECO Impact Evaluation.
     */
    private function evaluatePendingEcoImpactVector(ProductionOrder $order, Collection $openEcos, array &$exceptions): void
    {
        if ($openEcos->isEmpty()) return;

        $matchingEco = $openEcos->firstWhere('product_id', $order->product_id);
        if ($matchingEco) {
            $exceptions[] = [
                'type' => self::TYPE_PENDING_ECO_IMPACT,
                'severity' => self::SEVERITY_MEDIUM,
                'production_order_id' => $order->id,
                'operation_id' => null,
                'operation_name' => null,
                'reason' => "Pending ECO '{$matchingEco->title}' (#{$matchingEco->eco_number}) affects product '{$order->product?->name}'.",
                'risk_driver' => 'Pending ECO Revision Impact',
                'recommended_action' => self::REC_REVIEW_PENDING_ECO,
                'evidence' => "ECO ID #{$matchingEco->id} status: {$matchingEco->status}.",
            ];
        }
    }

    /**
     * Vector 8: Quality / Rework Risk Evaluation.
     */
    private function evaluateQualityReworkVector(ProductionOrder $order, array $readiness, Collection $openNcrs, array &$exceptions): void
    {
        // 1. Readiness quality blockers
        foreach ($readiness['operations'] as $opEval) {
            $qual = $opEval['quality'] ?? [];
            if (($qual['status'] ?? '') === ProductionReadinessService::STATUS_BLOCKED) {
                $blockerStrs = array_map(fn($b) => is_array($b) ? ($b['message'] ?? $b['code'] ?? 'Quality Blocker') : (string) $b, $qual['blockers'] ?? []);
                $exceptions[] = [
                    'type' => self::TYPE_QUALITY_REWORK_RISK,
                    'severity' => self::SEVERITY_HIGH,
                    'production_order_id' => $order->id,
                    'operation_id' => $opEval['operation_id'],
                    'operation_name' => $opEval['operation_name'],
                    'reason' => "Quality blocker on operation '{$opEval['operation_name']}': " . implode(', ', $blockerStrs),
                    'risk_driver' => 'Quality / Inspection Blocker',
                    'recommended_action' => self::REC_REVIEW_QUALITY,
                    'evidence' => "Quality blockers: " . implode('; ', $blockerStrs),
                ];
            }
        }

        // 2. Explicit rework records
        foreach ($order->operations as $op) {
            $reworksCol = $op->relationLoaded('reworks') ? $op->reworks : $op->reworks()->get();
            if ($reworksCol->count() > 0) {
                $reworkQty = (float) $reworksCol->sum('quantity');
                $exceptions[] = [
                    'type' => self::TYPE_QUALITY_REWORK_RISK,
                    'severity' => self::SEVERITY_HIGH,
                    'production_order_id' => $order->id,
                    'operation_id' => $op->id,
                    'operation_name' => $op->name,
                    'reason' => "Explicit rework logged on operation '{$op->name}' (Qty: {$reworkQty}).",
                    'risk_driver' => 'Operation Rework',
                    'recommended_action' => self::REC_REVIEW_QUALITY,
                    'evidence' => "Explicit ProductionOrderRework entries total {$reworkQty} units.",
                ];
            }
        }
    }

    /**
     * Vector 9: Subcontract Delay Evaluation.
     */
    private function evaluateSubcontractDelayVector(ProductionOrder $order, Collection $purchaseOrders, array &$exceptions): void
    {
        foreach ($order->operations as $op) {
            if (!$op->is_external || $op->status === ProductionOrderOperation::STATUS_COMPLETED) continue;

            $leadTimeDays = (int) $op->subcontract_lead_time_days ?: 3;
            $startDate = $op->actual_start_time ?? ($order->start_date ? Carbon::parse($order->start_date) : now());
            $expectedReturn = $startDate->copy()->addDays($leadTimeDays);

            if (now()->isAfter($expectedReturn)) {
                $overdueDays = now()->diffInDays($expectedReturn);
                $exceptions[] = [
                    'type' => self::TYPE_SUBCONTRACT_DELAY,
                    'severity' => self::SEVERITY_HIGH,
                    'production_order_id' => $order->id,
                    'operation_id' => $op->id,
                    'operation_name' => $op->name,
                    'reason' => "External subcontract operation '{$op->name}' is overdue by {$overdueDays} days from vendor.",
                    'risk_driver' => 'Subcontract Vendor Delay',
                    'recommended_action' => self::REC_REVIEW_SUBCONTRACT,
                    'evidence' => "Expected return date was {$expectedReturn->format('Y-m-d')}. Lead time: {$leadTimeDays} days.",
                ];
            }
        }
    }
}
