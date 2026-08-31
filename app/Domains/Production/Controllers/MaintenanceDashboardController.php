<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionPmSchedule;
use App\Domains\Production\Repositories\MaintenanceRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceDashboardController extends Controller
{
    public function __construct(
        private readonly MaintenanceRepositoryInterface $repository
    ) {}

    public function index(Request $request): View
    {
        $tenantId = require_tenant_id();

        $machinesUnderMaintenance = Machine::where('tenant_id', $tenantId)
            ->where('status', Machine::STATUS_UNDER_MAINTENANCE)
            ->count();

        $openBreakdownWos = ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)
            ->where('type', ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN)
            ->whereIn('status', [
                ProductionMaintenanceWorkOrder::STATUS_DRAFT,
                ProductionMaintenanceWorkOrder::STATUS_SCHEDULED,
                ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS,
            ])
            ->count();

        $dueSchedules = ProductionPmSchedule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('next_due_date', '<=', now()->toDateString())
            ->count();

        $overdueSchedules = ProductionPmSchedule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('next_due_date', '<', now()->toDateString())
            ->count();

        $monthlyExpense = (float) ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)
            ->where('status', ProductionMaintenanceWorkOrder::STATUS_COMPLETED)
            ->whereMonth('actual_end', now()->month)
            ->whereYear('actual_end', now()->year)
            ->sum('total_cost');

        $recentWorkOrders = $this->repository->getWorkOrders($tenantId, [])->take(10);
        $duePmList        = $this->repository->getDuePmSchedules($tenantId, now()->addDays(7)->toDateString())->take(10);

        return view('modules.production.maintenance.dashboard', compact(
            'machinesUnderMaintenance',
            'openBreakdownWos',
            'dueSchedules',
            'overdueSchedules',
            'monthlyExpense',
            'recentWorkOrders',
            'duePmList'
        ));
    }
}
