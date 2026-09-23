<?php

namespace App\Domains\Production\Controllers\Api;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionAlertConfiguration;
use App\Domains\Production\Models\ProductionEventTimeline;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\DashboardRefreshService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProductionDashboardApiController
 *
 * REST API for executive and shopfloor dashboard metrics.
 */
class ProductionDashboardApiController extends ApiBaseController
{
    /**
     * Authorize access to production dashboard and analytical datasets.
     */
    protected function authorizeDashboardAccess(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->role === 'admin'
                || $user->role === 'super_admin'
                || $user->hasProductionPermission('production.intelligence.view')
                || $user->hasProductionPermission('production.order.view')
                || $user->hasProductionPermission('production.order.create')
                || $user->hasProductionPermission('production.order.update')
                || $user->hasProductionPermission('production.mes.execute')
            ),
            403,
            'Unauthorized access to Production Dashboard.'
        );
    }

    /**
     * GET /api/v1/production/dashboard
     * Returns high-level operational KPIs for the active tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $tenantId = $this->getTenantId();

        $orderCounts = ProductionOrder::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $activeOperations = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->whereIn('status', [
                ProductionOrderOperation::STATUS_READY,
                ProductionOrderOperation::STATUS_RUNNING,
                ProductionOrderOperation::STATUS_PAUSED,
            ])
            ->count();

        $totalPlans = ProductionPlan::where('tenant_id', $tenantId)->count();
        $totalWorkCenters = WorkCenter::where('tenant_id', $tenantId)->count();

        $machineStates = Machine::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        return $this->successResponse([
            'orders' => [
                'total'       => array_sum($orderCounts),
                'draft'       => (int) ($orderCounts[ProductionOrder::STATUS_DRAFT] ?? 0),
                'released'    => (int) ($orderCounts[ProductionOrder::STATUS_RELEASED] ?? 0),
                'in_progress' => (int) ($orderCounts[ProductionOrder::STATUS_IN_PROGRESS] ?? 0),
                'completed'   => (int) ($orderCounts[ProductionOrder::STATUS_COMPLETED] ?? 0),
                'closed'      => (int) ($orderCounts[ProductionOrder::STATUS_CLOSED] ?? 0),
                'cancelled'   => (int) ($orderCounts[ProductionOrder::STATUS_CANCELLED] ?? 0),
            ],
            'operations' => [
                'active_count' => $activeOperations,
            ],
            'masters' => [
                'total_plans'        => $totalPlans,
                'total_work_centers' => $totalWorkCenters,
                'machines_active'    => (int) ($machineStates[Machine::STATUS_ACTIVE] ?? 0),
                'machines_total'     => array_sum($machineStates),
            ],
        ], 'Production dashboard KPIs retrieved successfully.');
    }

    /**
     * GET /api/v1/production/dashboard
     * Alias for canonical dashboard endpoint.
     */
    public function dashboard(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * GET /api/v1/production/dashboard/metrics
     * Detailed time-series production throughput, OEE, downtime, and operational metrics.
     */
    public function metrics(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $tenantId = $this->getTenantId();
        $filters = [
            'date_start' => $request->query('from_date', $request->query('date_start', now()->startOfDay()->toDateTimeString())),
            'date_end'   => $request->query('to_date', $request->query('date_end', now()->endOfDay()->toDateTimeString())),
            'work_center_id' => $request->query('work_center_id'),
        ];

        /** @var DashboardRefreshService $refreshService */
        $refreshService = app(DashboardRefreshService::class);
        $data = $refreshService->refreshExecutiveDashboard($tenantId, $filters);

        return $this->successResponse($data, 'Production dashboard metrics retrieved successfully.');
    }

    /**
     * GET /api/v1/production/dashboard/alerts
     * Open shop floor blockers, alert configurations, timeline events, and quality holds.
     */
    public function alerts(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $tenantId = $this->getTenantId();

        $configurations = ProductionAlertConfiguration::where('tenant_id', $tenantId)->get();

        $recentEvents = ProductionEventTimeline::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_type', 'Alert Fired')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $openNcrs = ProductionNcr::where('tenant_id', $tenantId)
            ->whereIn('status', ['draft', 'open', 'under_investigation'])
            ->count();

        return $this->successResponse([
            'configurations'  => $configurations,
            'recent_events'   => $recentEvents,
            'open_ncrs_count' => $openNcrs,
        ], 'Production dashboard alerts retrieved successfully.');
    }
}
