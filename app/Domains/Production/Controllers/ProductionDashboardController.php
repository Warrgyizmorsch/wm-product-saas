<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRequest;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Repositories\ProductionOrderRepositoryInterface;
use App\Domains\Production\Repositories\ProductionQualityRepositoryInterface;
use App\Domains\Production\Repositories\ProductionWipRepositoryInterface;
use App\Domains\Production\Services\DashboardRefreshService;
use App\Domains\Production\Services\KpiCalculationService;
use App\Domains\Production\Services\SchedulingService;
use App\Domains\Production\Services\SubcontractPerformanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductionDashboardController extends Controller
{
    public function __construct(
        private readonly DashboardRefreshService $refreshService,
        private readonly KpiCalculationService $kpiService,
        private readonly ProductionQualityRepositoryInterface $qualityRepository,
        private readonly ProductionWipRepositoryInterface $wipRepository,
        private readonly ProductionOrderRepositoryInterface $orderRepository,
        private readonly SchedulingService $schedulingService,
        private readonly SubcontractPerformanceService $subcontractService
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->role === 'admin'
                || $user->role === 'super_admin'
                || $user->hasProductionPermission('production.intelligence.view')
                || $user->hasProductionPermission('production.order.create')
                || $user->hasProductionPermission('production.order.update')
                || $user->hasProductionPermission('production.mes.execute')
            ),
            403,
            'Unauthorized access to Production Dashboard.'
        );

        $tenantId = require_tenant_id();

        // Timeframe filter for executive metrics (today, week, month)
        $timeframe = $request->query('timeframe', 'today');
        $now = now();
        [$startDate, $endDate] = match ($timeframe) {
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
        $dateFilters = [
            'date_start' => $startDate->toDateTimeString(),
            'date_end'   => $endDate->toDateTimeString(),
        ];

        // 1. Executive Intelligence via existing domain service
        $executiveData = $this->refreshService->refreshExecutiveDashboard($tenantId, $dateFilters);
        $oeeKpi = $executiveData['today_oee'] ?? ['current_value' => 0.0, 'target_value' => 85.0, 'variance' => 0.0, 'status' => 'On Target'];
        $productionSummary = $executiveData['production_summary'] ?? ['planned_quantity' => 0, 'actual_quantity' => 0, 'schedule_adherence' => 100.0];
        $scrapStats = $executiveData['scrap_stats'] ?? ['scrap_rate' => 0.0, 'reject_rate' => 0.0, 'yield' => 100.0];

        // 2. Quality Metrics via existing repository
        $qualityKpis = $this->qualityRepository->getQualityDashboardKpis($tenantId);

        // 3. Operational WIP via existing repository (strictly operational quantities, no monetary valuation)
        $wipSummary = $this->wipRepository->getWipKpiSummary($tenantId);

        // 4. Subcontracting Metrics via existing repository
        $subcontractMetrics = $this->orderRepository->getSubcontractDashboardMetrics($tenantId);

        // 5. Critical Action Center Metrics (Fast, indexed database-level queries)
        $overdueOrdersQuery = ProductionOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', [ProductionOrder::STATUS_COMPLETED, 'closed', ProductionOrder::STATUS_CANCELLED])
            ->whereNotNull('end_date')
            ->where('end_date', '<', $now->toDateString());

        $overdueOrdersCount = (clone $overdueOrdersQuery)->count();
        $overdueOrdersList = (clone $overdueOrdersQuery)
            ->with(['product', 'operations', 'schedules'])
            ->orderBy('end_date', 'asc')
            ->take(20)
            ->get();

        $materialBlockersCount = ProductionOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', [ProductionOrder::STATUS_COMPLETED, 'closed', ProductionOrder::STATUS_CANCELLED])
            ->whereHas('requisitionSlips', function ($q) {
                $q->whereIn(DB::raw('LOWER(status)'), ['pending', 'pending store release']);
            })
            ->count();

        $breakdownCount = Machine::where('tenant_id', $tenantId)
            ->where('current_state', 'Breakdown')
            ->count();

        $pendingQcCount = $qualityKpis['pendingInspections'] ?? 0;
        $openNcrCount = $qualityKpis['ncrOpen'] ?? 0;
        $openCapaCount = $qualityKpis['capaOpen'] ?? 0;
        $vendorDelayedCount = $subcontractMetrics['vendor_delayed'] ?? 0;

        $totalCriticalExceptions = $overdueOrdersCount + $breakdownCount + $openNcrCount + $vendorDelayedCount;

        // 6. Production Orders Pipeline Status Breakdown
        $rawOrderCounts = ProductionOrder::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $orderStatusCounts = [
            'draft'       => $rawOrderCounts[ProductionOrder::STATUS_DRAFT] ?? 0,
            'released'    => $rawOrderCounts[ProductionOrder::STATUS_RELEASED] ?? 0,
            'in_progress' => $rawOrderCounts[ProductionOrder::STATUS_IN_PROGRESS] ?? 0,
            'completed'   => $rawOrderCounts[ProductionOrder::STATUS_COMPLETED] ?? 0,
            'closed'      => $rawOrderCounts['closed'] ?? 0,
            'cancelled'   => $rawOrderCounts[ProductionOrder::STATUS_CANCELLED] ?? 0,
            'total'       => array_sum($rawOrderCounts),
        ];
        $totalActiveOrders = $orderStatusCounts['draft'] + $orderStatusCounts['released'] + $orderStatusCounts['in_progress'];

        // 7. Requisition Slips Metrics (Store Material Issue Track)
        $rawReqCounts = ProductionRequisitionSlip::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $requisitionSummary = [
            'total'            => array_sum($rawReqCounts),
            'fully_issued'     => ($rawReqCounts['Fully Issued'] ?? 0) + ($rawReqCounts['completed'] ?? 0) + ($rawReqCounts['issued'] ?? 0),
            'partially_issued' => ($rawReqCounts['Partially Issued'] ?? 0) + ($rawReqCounts['partial'] ?? 0),
            'pending'          => ($rawReqCounts['Pending'] ?? 0) + ($rawReqCounts['pending'] ?? 0) + ($rawReqCounts['Pending Store Release'] ?? 0),
            'approved'         => ($rawReqCounts['Approved'] ?? 0) + ($rawReqCounts['reserved'] ?? 0),
        ];

        // 8. Ready to Start Orders (Optimized Database-Level whereHas, eliminating in-memory PHP array filtering)
        $readyToStartQuery = ProductionOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', [ProductionOrder::STATUS_IN_PROGRESS, ProductionOrder::STATUS_COMPLETED, 'closed', ProductionOrder::STATUS_CANCELLED])
            ->whereHas('requisitionSlips', function ($q) {
                $q->whereIn(DB::raw('LOWER(status)'), ['fully issued', 'completed', 'issued', 'partially issued', 'partial']);
            });

        $readyToStartCount = (clone $readyToStartQuery)->count();
        $readyToStartOrders = (clone $readyToStartQuery)
            ->with(['product', 'requisitionSlips', 'schedules', 'operations'])
            ->orderByDesc('id')
            ->take(20)
            ->get();

        $fullyIssuedOrders = ProductionOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', [ProductionOrder::STATUS_IN_PROGRESS, ProductionOrder::STATUS_COMPLETED, 'closed', ProductionOrder::STATUS_CANCELLED])
            ->whereHas('requisitionSlips', function ($q) {
                $q->whereIn(DB::raw('LOWER(status)'), ['fully issued', 'completed', 'issued']);
            })
            ->with(['product', 'requisitionSlips', 'schedules', 'operations'])
            ->orderByDesc('id')
            ->take(20)
            ->get();
        $fullyIssuedCount = $fullyIssuedOrders->count();

        $partiallyIssuedOrders = ProductionOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', [ProductionOrder::STATUS_IN_PROGRESS, ProductionOrder::STATUS_COMPLETED, 'closed', ProductionOrder::STATUS_CANCELLED])
            ->whereHas('requisitionSlips', function ($q) {
                $q->whereIn(DB::raw('LOWER(status)'), ['partially issued', 'partial']);
            })
            ->with(['product', 'requisitionSlips', 'schedules', 'operations'])
            ->orderByDesc('id')
            ->take(20)
            ->get();
        $partiallyIssuedCount = $partiallyIssuedOrders->count();

        $pendingStoreOrders = ProductionOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', [ProductionOrder::STATUS_COMPLETED, 'closed', ProductionOrder::STATUS_CANCELLED])
            ->whereHas('requisitionSlips', function ($q) {
                $q->whereIn(DB::raw('LOWER(status)'), ['pending', 'pending store release']);
            })
            ->with(['product', 'requisitionSlips', 'schedules', 'operations'])
            ->orderByDesc('id')
            ->take(20)
            ->get();
        $pendingStoreCount = $pendingStoreOrders->count();

        // 9. Operational Worklists:
        // TAB 1: Pending Sales Orders Demands
        $pendingRequests = ProductionOrderRequest::where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->whereNull('production_order_id')
            ->with([
                'product',
                'materialRequirementItem.materialRequirement.salesOrder.customer',
                'materialRequirementItem.salesOrderItem.salesOrder.customer',
            ])
            ->orderByDesc('id')
            ->take(20)
            ->get();
        $pendingSalesOrderCount = $pendingRequests->count();

        // TAB 3: Active In-Progress Execution
        $inProgressOrders = ProductionOrder::where('tenant_id', $tenantId)
            ->whereIn('status', [ProductionOrder::STATUS_RELEASED, ProductionOrder::STATUS_IN_PROGRESS])
            ->with([
                'product',
                'operations' => fn($q) => $q->orderBy('sequence'),
                'schedules',
                'requisitionSlips',
            ])
            ->orderByDesc('id')
            ->take(20)
            ->get();

        // TAB 4: Overdue Orders (Already resolved as $overdueOrdersList)

        // 10. Operator Assigned Operations & Exceptions
        $operatorAssignedCount = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNotNull('operator_id')
                  ->orWhereHas('operatorAssignments');
            })
            ->whereHas('order', function ($q) {
                $q->whereIn('status', [ProductionOrder::STATUS_RELEASED, ProductionOrder::STATUS_IN_PROGRESS]);
            })
            ->count();

        $pendingReworkCount = ProductionOrderRework::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();

        $scrapLoggedCount = ProductionOrderScrap::where('tenant_id', $tenantId)->count();

        // Recent Orders List (Preserved for backward compatibility)
        $recentOrders = ProductionOrder::where('tenant_id', $tenantId)
            ->with(['product', 'requisitionSlips', 'schedules', 'operations'])
            ->orderByDesc('id')
            ->take(10)
            ->get();

        // ── Phase 2: Live Manufacturing Intelligence ──────────────────────────
        // 1. Live Machine-State Pulse & Andon Integration (Authoritative existing refreshAndonBoard)
        $andonData = $this->refreshService->refreshAndonBoard($tenantId);
        $machineStateCounts = [
            'total'       => $andonData['total_machines'] ?? 0,
            'running'     => $andonData['running_count'] ?? 0,
            'idle'        => $andonData['idle_count'] ?? 0,
            'setup'       => $andonData['setup_count'] ?? 0,
            'breakdown'   => $andonData['breakdown_count'] ?? 0,
            'maintenance' => $andonData['maintenance_count'] ?? 0,
            'offline'     => $andonData['offline_count'] ?? 0,
        ];

        $attentionMachines = collect($andonData['machines'] ?? [])
            ->filter(function ($m) {
                $state = strtolower($m['current_state'] ?? '');
                return in_array($state, ['breakdown', 'maintenance'])
                    || (!empty($m['current_state_reason']) && $m['current_state_reason'] !== '—');
            })
            ->take(6)
            ->values();

        // 2. Work-Center Capacity, Planned Load & Bottleneck Indicators
        $workCenters = WorkCenter::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->with(['machines'])
            ->orderBy('name')
            ->get();

        $today = today();
        $workCenterLoads = [];

        foreach ($workCenters as $wc) {
            $availCapMinutes = (float) $this->schedulingService->calculateCapacity($wc->id, $today);

            $scheduledMinutes = 0.0;
            $todayOps = ProductionScheduleOperation::where('tenant_id', $tenantId)
                ->where('work_center_id', $wc->id)
                ->whereNotIn('status', [
                    ProductionScheduleOperation::STATUS_CANCELLED,
                    ProductionScheduleOperation::STATUS_SKIPPED,
                ])
                ->whereDate('planned_start', '<=', $today)
                ->whereDate('planned_finish', '>=', $today)
                ->get();

            foreach ($todayOps as $op) {
                $scheduledMinutes += $this->schedulingService->calculateOperationScheduledMinutesOnDate($op, $today);
            }

            $utilization = $availCapMinutes > 0
                ? round(($scheduledMinutes / $availCapMinutes) * 100, 1)
                : ($scheduledMinutes > 0 ? 100.0 : 0.0);

            // Presentation-only bottleneck classification
            $bottleneckStatus = match (true) {
                $utilization > 100.0 => 'Critical',
                $utilization >= 85.0 => 'Warning',
                default              => 'Normal',
            };

            $runningOpsCount = ProductionScheduleOperation::where('tenant_id', $tenantId)
                ->where('work_center_id', $wc->id)
                ->where('status', ProductionScheduleOperation::STATUS_RUNNING)
                ->count();

            $waitingOpsCount = ProductionScheduleOperation::where('tenant_id', $tenantId)
                ->where('work_center_id', $wc->id)
                ->whereIn('status', [
                    ProductionScheduleOperation::STATUS_READY,
                    ProductionScheduleOperation::STATUS_WAITING,
                ])
                ->count();

            $workCenterLoads[] = [
                'id'                => $wc->id,
                'name'              => $wc->name,
                'code'              => $wc->code,
                'capacity_minutes'  => $availCapMinutes,
                'capacity_hours'    => round($availCapMinutes / 60, 1),
                'scheduled_minutes' => $scheduledMinutes,
                'scheduled_hours'   => round($scheduledMinutes / 60, 1),
                'utilization'       => $utilization,
                'status'            => $bottleneckStatus,
                'running_ops'       => $runningOpsCount,
                'waiting_ops'       => $waitingOpsCount,
                'machine_count'     => $wc->machines->count(),
            ];
        }

        // Highlight bottlenecks first
        usort($workCenterLoads, fn($a, $b) => $b['utilization'] <=> $a['utilization']);
        $bottleneckWorkCenters = collect($workCenterLoads)
            ->filter(fn($wc) => in_array($wc['status'], ['Warning', 'Critical']))
            ->values();

        // 3. Live MES / Shop-Floor Execution Pulse
        $mesRunningQuery = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->where('status', ProductionScheduleOperation::STATUS_RUNNING);
        $mesRunningCount = (clone $mesRunningQuery)->count();
        $mesRunningOperations = (clone $mesRunningQuery)
            ->with(['schedule.order.product', 'workCenter', 'machine'])
            ->orderBy('actual_start', 'desc')
            ->take(5)
            ->get();

        $mesReadyCount = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->where('status', ProductionScheduleOperation::STATUS_READY)
            ->count();

        $mesPausedCount = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->where('status', ProductionScheduleOperation::STATUS_PAUSED)
            ->count();

        $mesCompletedTodayCount = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->where('status', ProductionScheduleOperation::STATUS_COMPLETED)
            ->whereDate('actual_finish', $today)
            ->count();

        // ── Phase 3: Quality, Six Big Losses, Cycle Time, Maintenance & Subcontract SLA ──
        // 1. Quality Intelligence Top Defect Categories
        $topDefectCategories = \App\Domains\Production\Models\ProductionNcr::where('tenant_id', $tenantId)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->take(3)
            ->get();

        // 2. Six Big Losses & Cycle Time Intelligence
        $sixBigLosses = $executiveData['six_big_losses'] ?? [
            'equipment_failure_minutes' => 0.0,
            'setup_adjustment_minutes'  => 0.0,
            'minor_stops_minutes'       => 0.0,
            'reduced_speed_minutes'     => 0.0,
            'startup_rejects_count'     => 0.0,
            'production_rejects_count'  => 0.0,
        ];
        $downtimeRate = (float) ($executiveData['downtime_rate'] ?? 0.0);
        $assetUtilizations = $executiveData['utilizations'] ?? [
            'machine_utilization'     => 0.0,
            'operator_utilization'    => 0.0,
            'work_center_utilization' => 0.0,
        ];
        $cycleTimes = $this->kpiService->getCycleTimes($tenantId, $dateFilters);

        // 3. Plant Maintenance Intelligence
        $overduePmCount = \App\Domains\Production\Models\ProductionPmSchedule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('next_due_date', '<', $today->toDateString())
            ->count();
        $duePmCount = \App\Domains\Production\Models\ProductionPmSchedule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereBetween('next_due_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
            ->count();
        $openBreakdownWosCount = \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)
            ->where('type', \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN)
            ->whereIn('status', [
                \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::STATUS_DRAFT,
                \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::STATUS_SCHEDULED,
                \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS,
            ])
            ->count();
        $machinesUnderMaintenanceCount = Machine::where('tenant_id', $tenantId)
            ->where('status', Machine::STATUS_UNDER_MAINTENANCE)
            ->count();

        // 4. Subcontracting SLA & Delivery Metrics
        $subcontractDelivery = $this->subcontractService->getDeliveryMetrics($tenantId, [
            'date_from' => $startDate->toDateString(),
            'date_to'   => $endDate->toDateString(),
        ]);

        // ── Phase 4: Execution Variance, Order Completion Risk & Planning Pulse ──
        // 4A. Operational Execution Variance (Timeframe-aware)
        $outputVariance = ($productionSummary['actual_quantity'] ?? 0) - ($productionSummary['planned_quantity'] ?? 0);

        $durationAggregation = DB::table('production_order_operations')
            ->where('tenant_id', $tenantId)
            ->where('status', ProductionOrderOperation::STATUS_COMPLETED)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('actual_end_time', [$startDate, $endDate])
                  ->orWhere(function ($sub) use ($startDate, $endDate) {
                      $sub->whereNull('actual_end_time')
                          ->whereBetween('updated_at', [$startDate, $endDate]);
                  });
            })
            ->selectRaw('
                COALESCE(SUM(total_time_planned), 0) as planned_minutes,
                COALESCE(SUM(setup_time_actual + processing_time_actual), 0) as actual_minutes
            ')
            ->first();

        $plannedDurationHours = round(((float) ($durationAggregation->planned_minutes ?? 0)) / 60.0, 1);
        $actualDurationHours = round(((float) ($durationAggregation->actual_minutes ?? 0)) / 60.0, 1);
        $durationVarianceHours = round($actualDurationHours - $plannedDurationHours, 1);
        $durationEfficiency = $actualDurationHours > 0
            ? round(($plannedDurationHours / $actualDurationHours) * 100, 1)
            : ($plannedDurationHours > 0 ? 100.0 : 100.0);

        // 4B. Near-Term Order Completion Risk (Current-State, Due in 72h & Progress < 50%)
        $atRiskOrders = ProductionOrder::where('tenant_id', $tenantId)
            ->whereIn('status', [ProductionOrder::STATUS_RELEASED, ProductionOrder::STATUS_IN_PROGRESS])
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$now->toDateString(), $now->copy()->addHours(72)->toDateString()])
            ->where('quantity_ordered', '>', 0)
            ->whereRaw('quantity_produced < (quantity_ordered * 0.5)')
            ->with([
                'product',
                'operations' => function ($q) {
                    $q->whereIn('status', [
                        ProductionOrderOperation::STATUS_RUNNING,
                        ProductionOrderOperation::STATUS_READY,
                        ProductionOrderOperation::STATUS_WAITING,
                    ])
                    ->orderBy('sequence', 'asc')
                    ->with('workCenter');
                }
            ])
            ->orderBy('end_date', 'asc')
            ->take(5)
            ->get();

        // Quality-constrained active orders count (Orders with active/open NCRs)
        $qualityConstrainedOrderIds = ProductionNcr::where('tenant_id', $tenantId)
            ->whereIn('status', [
                'open',
                'under_investigation',
            ])
            ->whereNotNull('production_order_id')
            ->distinct()
            ->pluck('production_order_id');

        $qualityConstrainedOrdersCount = ProductionOrder::where('tenant_id', $tenantId)
            ->whereIn('status', [ProductionOrder::STATUS_RELEASED, ProductionOrder::STATUS_IN_PROGRESS])
            ->whereIn('id', $qualityConstrainedOrderIds)
            ->count();

        // 4C. Production Planning & ECO Pulse (Current-State)
        $planStatusCounts = ProductionPlan::where('tenant_id', $tenantId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $ecoStatusCounts = ProductionEco::where('tenant_id', $tenantId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('modules.production.dashboard', compact(
            'timeframe',
            'oeeKpi',
            'productionSummary',
            'scrapStats',
            'qualityKpis',
            'wipSummary',
            'subcontractMetrics',
            'overdueOrdersCount',
            'overdueOrdersList',
            'materialBlockersCount',
            'breakdownCount',
            'pendingQcCount',
            'openNcrCount',
            'openCapaCount',
            'vendorDelayedCount',
            'totalCriticalExceptions',
            'totalActiveOrders',
            'orderStatusCounts',
            'requisitionSummary',
            'readyToStartCount',
            'readyToStartOrders',
            'fullyIssuedCount',
            'fullyIssuedOrders',
            'partiallyIssuedCount',
            'partiallyIssuedOrders',
            'pendingStoreCount',
            'pendingStoreOrders',
            'pendingRequests',
            'pendingSalesOrderCount',
            'inProgressOrders',
            'operatorAssignedCount',
            'pendingReworkCount',
            'scrapLoggedCount',
            'recentOrders',
            // Phase 2 Live Manufacturing Intelligence variables
            'andonData',
            'machineStateCounts',
            'attentionMachines',
            'workCenterLoads',
            'bottleneckWorkCenters',
            'mesRunningCount',
            'mesRunningOperations',
            'mesReadyCount',
            'mesPausedCount',
            'mesCompletedTodayCount',
            // Phase 3 Variables
            'topDefectCategories',
            'sixBigLosses',
            'downtimeRate',
            'assetUtilizations',
            'cycleTimes',
            'overduePmCount',
            'duePmCount',
            'openBreakdownWosCount',
            'machinesUnderMaintenanceCount',
            'subcontractDelivery',
            // Phase 4 Variables
            'outputVariance',
            'plannedDurationHours',
            'actualDurationHours',
            'durationVarianceHours',
            'durationEfficiency',
            'atRiskOrders',
            'qualityConstrainedOrdersCount',
            'planStatusCounts',
            'ecoStatusCounts'
        ));
    }
}
