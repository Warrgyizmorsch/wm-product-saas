<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Services\MesExecutionService;
use Illuminate\Http\Request;
use App\Domains\Production\Requests\MesCompleteOperationRequest;
use InvalidArgumentException;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Models\ProductionShift;

use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionReworkOrder;
use Illuminate\Support\Facades\DB;

class MesController extends Controller
{
    public function __construct(
        private readonly MesExecutionService $mesService
    ) {
    }

    /**
     * Operator Dashboard — shows all operations assigned to or relevant for the current user.
     */
    public function dashboard(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $userId = auth()->id();
        $search = $request->input('search');
        $orderId = $request->input('order_id');
        $productId = $request->input('product_id');
        $workCenterId = $request->input('work_center_id');
        $status = $request->input('status');

        // Retrieve active schedules in this tenant with operations
        $activeSchedulesQuery = ProductionSchedule::with([
            'order.product',
            'operations.workCenter',
            'operations.machine',
            'operations.orderOperation.vendor',
            'operations.orderOperation.routingOperation',
            'operations.orderOperation.latestDeliveryChallan',
            'operations.orderOperation.deliveryChallans',
            'operations.orderOperation.operatorAssignments.user',
        ])
            ->where('tenant_id', $tenantId);

        if ($status) {
            $activeSchedulesQuery->where('status', $status);
        } else {
            $activeSchedulesQuery->whereIn('status', [ProductionSchedule::STATUS_RELEASED, ProductionSchedule::STATUS_IN_PROGRESS]);
        }

        if ($orderId) {
            $activeSchedulesQuery->where('production_order_id', $orderId);
        }

        if ($productId) {
            $activeSchedulesQuery->whereHas('order', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            });
        }

        if ($workCenterId) {
            $activeSchedulesQuery->whereHas('operations', function ($q) use ($workCenterId) {
                $q->where('work_center_id', $workCenterId);
            });
        }

        if ($search) {
            $activeSchedulesQuery->where(function ($q) use ($search) {
                $q->where('schedule_number', 'like', "%{$search}%")
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($pq) use ($search) {
                            $pq->where('name', 'like', "%{$search}%")
                              ->orWhere('sku', 'like', "%{$search}%");
                        });
                  });
            });
        }

        $activeSchedules = $activeSchedulesQuery
            ->orderBy('scheduled_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Auto-evaluate readiness & completion for active schedules so next operations show ready instantly
        $poService = app(\App\Domains\Production\Services\ProductionOrderService::class);
        foreach ($activeSchedules as $sched) {
            if ($sched->production_order_id) {
                $poService->reconcileOperationReadiness($sched->production_order_id);
                $poService->evaluateAndAutoCompleteOrder($sched->production_order_id, $userId);
            }
        }

        // Running operations (for stopwatch timer block countdowns)
        $running = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->with(['schedule.order.product', 'workCenter', 'machine'])
            ->whereHas('schedule', fn($q) => $q->whereIn('status', [ProductionSchedule::STATUS_RELEASED, ProductionSchedule::STATUS_IN_PROGRESS]))
            ->where('status', ProductionScheduleOperation::STATUS_RUNNING)
            ->orderBy('planned_start')
            ->get();

        // Paused operations (for completion modals)
        $paused = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->with(['schedule.order.product', 'workCenter', 'machine'])
            ->whereHas('schedule', fn($q) => $q->whereIn('status', [ProductionSchedule::STATUS_RELEASED, ProductionSchedule::STATUS_IN_PROGRESS]))
            ->where('status', ProductionScheduleOperation::STATUS_PAUSED)
            ->orderBy('planned_start')
            ->get();

        // Completed today count
        $completedToday = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->where('status', ProductionScheduleOperation::STATUS_COMPLETED)
            ->whereDate('actual_finish', today())
            ->count();

        // Active ready count (for performance sidebar tracker badge)
        $readyCount = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereHas('schedule', fn($q) => $q->whereIn('status', [ProductionSchedule::STATUS_RELEASED, ProductionSchedule::STATUS_IN_PROGRESS]))
            ->where('status', ProductionScheduleOperation::STATUS_READY)
            ->count();

        // Recently completed schedules
        $recentlyCompletedSchedules = ProductionSchedule::with([
            'order.product',
            'operations.workCenter',
            'operations.machine',
            'operations.orderOperation.vendor',
            'operations.orderOperation.latestDeliveryChallan',
            'operations.orderOperation.deliveryChallans',
        ])
            ->where('tenant_id', $tenantId)
            ->where('status', ProductionSchedule::STATUS_COMPLETED)
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        // Orders list for filtering active shopfloor orders
        $orders = \App\Domains\Production\Models\ProductionOrder::where('tenant_id', $tenantId)
            ->whereIn('status', [
                \App\Domains\Production\Models\ProductionOrder::STATUS_RELEASED,
                \App\Domains\Production\Models\ProductionOrder::STATUS_IN_PROGRESS
            ])
            ->with('product')
            ->orderBy('order_number')
            ->get();

        // Products list for filtering
        $products = \App\Domains\Inventory\Models\Product::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        // Shifts assigned/active
        $shifts = ProductionShift::where('tenant_id', $tenantId)->where('active', true)->get();

        // Operators list for shopfloor operator assignment, Quality Plans, Work Centers, & Machines
        $operators = \App\Models\User::where('tenant_id', $tenantId)->get();
        $qualityPlans = \App\Domains\Production\Models\ProductionQualityPlan::where('tenant_id', $tenantId)
            ->whereIn('status', ['approved', 'draft', 'active'])
            ->with('parameters')
            ->get();
        $workCenters = \App\Domains\Production\Models\WorkCenter::where('tenant_id', $tenantId)->where(function($q) { $q->where('status', 'active')->orWhereNull('status'); })->get();
        $machines = \App\Domains\Production\Models\Machine::where('tenant_id', $tenantId)->get();

        return view('modules.production.mes.dashboard', compact(
            'activeSchedules',
            'recentlyCompletedSchedules',
            'running',
            'paused',
            'completedToday',
            'readyCount',
            'shifts',
            'operators',
            'qualityPlans',
            'workCenters',
            'machines',
            'orders',
            'products'
        ));
    }

    public function start(Request $request, int $op)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        try {
            $machineId = $request->input('machine_id') ? (int) $request->input('machine_id') : null;
            $this->mesService->startOperation($op, $machineId, auth()->id());
            return redirect()->back()->with('success', 'Operation started successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function pause(Request $request, int $op)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        try {
            $this->mesService->pauseOperation($op, $request->input('remarks'), auth()->id());
            return redirect()->back()->with('success', 'Operation paused.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function resume(int $op)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        try {
            $this->mesService->resumeOperation($op, auth()->id());
            return redirect()->back()->with('success', 'Operation resumed.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function complete(MesCompleteOperationRequest $request, int $op)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $data = $request->validated();

        try {
            $this->mesService->completeOperation($op, $data, auth()->id());
            return redirect()->back()->with('success', 'Operation completed and progress logged.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function logProgress(MesCompleteOperationRequest $request, int $op)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $data = $request->validated();

        try {
            $this->mesService->logPartialProgress($op, $data, auth()->id());
            return redirect()->back()->with('success', 'Daily progress logged successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function hold(Request $request, int $op)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        try {
            $this->mesService->holdOperation($op, $request->input('remarks'));
            return redirect()->back()->with('success', 'Operation placed on hold.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, int $op)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        try {
            $this->mesService->cancelOperation($op);
            return redirect()->back()->with('success', 'Operation cancelled.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reportAndonAlert(Request $request, int $op)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $request->validate([
            'category' => 'required|string|max:100',
            'severity' => 'nullable|string|in:info,warning,critical',
            'reason' => 'required|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        try {
            $this->mesService->reportAndonAlert(
                $op,
                $request->input('category'),
                $request->input('severity', 'warning'),
                $request->input('reason'),
                $request->input('remarks'),
                auth()->id()
            );

            return redirect()->back()->with('success', 'Andon alert reported to supervisors successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function recordQualityInspection(Request $request, int $op)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $request->validate([
            'accepted_qty' => 'required|numeric|min:0',
            'rejected_qty' => 'required|numeric|min:0',
            'quality_plan_id' => 'nullable|integer',
            'audited_by' => 'nullable|integer',
            'defect_reason' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            $tenantId = require_tenant_id();
            app(\App\Domains\Production\Services\QualityInspectionService::class)->processShopfloorInspection($tenantId, [
                'production_order_operation_id' => $op,
                'accepted_qty' => (float) $request->input('accepted_qty'),
                'rejected_qty' => (float) $request->input('rejected_qty'),
                'quality_plan_id' => $request->input('quality_plan_id'),
                'audited_by' => $request->input('audited_by'),
                'defect_reason' => $request->input('defect_reason'),
                'batch_id' => $request->input('batch_id'),
                'remarks' => $request->input('remarks'),
            ], $request->input('audited_by') ?? auth()->id());

            return redirect()->back()->with('success', 'Shopfloor Quality Inspection recorded successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function recordOperationalScrap(Request $request, int $op)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'product_id' => 'nullable|integer',
            'batch_id' => 'nullable|integer',
        ]);

        try {
            $tenantId = require_tenant_id();
            $userId = auth()->id();
            $orderOp = ProductionOrderOperation::where('tenant_id', $tenantId)->findOrFail($op);
            $qty = (float) $request->input('quantity');
            $reason = $request->input('reason');
            $batchId = $request->input('batch_id');
            $outputPid = $orderOp->product_id ?? $orderOp->source_product_id ?? $orderOp->order?->product_id;
            $productId = $request->input('product_id') ?? $outputPid;
            $isOutputProduct = ((int) $productId === (int) $outputPid);

            DB::transaction(function () use ($tenantId, $orderOp, $qty, $reason, $batchId, $productId, $userId, $isOutputProduct) {
                ProductionOrderScrap::create([
                    'tenant_id' => $tenantId,
                    'production_order_id' => $orderOp->production_order_id,
                    'production_order_operation_id' => $orderOp->id,
                    'production_batch_id' => $batchId,
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'reason' => $reason,
                    'recorded_by' => $userId,
                    'recorded_at' => now(),
                ]);

                // ONLY increment operation output scrap when the scrapped item is the output component/product itself!
                if ($isOutputProduct) {
                    $orderOp->quantity_scrapped += $qty;
                    $orderOp->save();
                }

                app(\App\Domains\Production\Services\ProductionMaterialService::class)
                    ->evaluateAndIssueReplacementMaterial($tenantId, $orderOp->production_order_id, $productId, $qty, $reason, $userId);
            });

            return redirect()->back()->with('success', "Operational scrap of {$qty} units recorded successfully.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function recordDisposition(Request $request, int $op)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $request->validate([
            'disposition_type' => 'required|string|in:rework,scrap',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
            'work_center_id' => 'nullable|integer',
            'machine_id' => 'nullable|integer',
            'instructions' => 'nullable|string|max:500',
            'batch_id' => 'nullable|integer',
            'rework_location' => 'nullable|string|in:vendor_rework,internal_repair',
        ]);

        try {
            $tenantId = require_tenant_id();
            $userId = auth()->id();
            $orderOp = ProductionOrderOperation::where('tenant_id', $tenantId)->findOrFail($op);
            $dispType = $request->input('disposition_type');
            $qty = (float) $request->input('quantity');
            $reason = $request->input('reason', 'Quality Inspection Rejection');
            $batchId = $request->input('batch_id');
            $reworkLoc = $request->input('rework_location');
            $isVendorRework = ($dispType === 'rework') && ($reworkLoc === 'vendor_rework' || ($orderOp->is_external && $reworkLoc !== 'internal_repair'));

            DB::transaction(function () use ($tenantId, $orderOp, $dispType, $qty, $reason, $batchId, $userId, $request, $isVendorRework) {
                $ncr = ProductionNcr::where('tenant_id', $tenantId)
                    ->where('production_order_operation_id', $orderOp->id)
                    ->where('status', 'open')
                    ->latest()
                    ->first();

                if (!$ncr) {
                    $ncr = ProductionNcr::create([
                        'tenant_id' => $tenantId,
                        'ncr_number' => 'NCR-SF-' . strtoupper(uniqid()),
                        'category' => $orderOp->is_external ? 'subcontract' : 'process',
                        'status' => 'open',
                        'disposition_type' => 'pending',
                        'production_order_id' => $orderOp->production_order_id,
                        'production_order_operation_id' => $orderOp->id,
                        'batch_id' => $batchId,
                        'machine_id' => $orderOp->machine_used_id ?? $orderOp->machine_id,
                        'operator_id' => $userId,
                        'description' => "Shopfloor Quality Rejection for {$qty} units on operation #{$orderOp->operation_number}.",
                    ]);
                }

                if ($dispType === 'rework') {
                    app(\App\Domains\Production\Services\ReworkService::class)->createReworkOrder($tenantId, $ncr->id, [
                        'original_production_order_id' => $orderOp->production_order_id,
                        'production_order_operation_id' => $orderOp->id,
                        'batch_id' => $batchId,
                        'rework_type' => $request->input('rework_type', $isVendorRework ? 'vendor_rework' : 'reprocess'),
                        'quantity' => $qty,
                        'work_center_id' => $request->input('work_center_id') ?? $orderOp->work_center_id,
                        'machine_id' => $request->input('machine_id') ?? $orderOp->machine_id,
                        'instructions' => $request->input('instructions') ?? "Rework for {$qty} rejected units.",
                        'assigned_to' => $request->input('assigned_to') ?? $userId,
                        'cost_estimate' => (float) ($request->input('cost_estimate') ?? 150.00),
                    ]);

                    \App\Domains\Production\Models\ProductionOrderRework::create([
                        'tenant_id' => $tenantId,
                        'production_order_id' => $orderOp->production_order_id,
                        'production_order_operation_id' => $orderOp->id,
                        'production_batch_id' => $batchId,
                        'quantity' => $qty,
                        'reason' => $reason,
                        'status' => 'in_progress',
                        'recorded_by' => $userId,
                        'recorded_at' => now(),
                    ]);

                    if ($isVendorRework) {
                        app(\App\Domains\Production\Services\SubcontractMaterialBalanceService::class)
                            ->createReworkChallan($tenantId, $orderOp, $qty, [
                                'reason' => $reason,
                                'instructions' => $request->input('instructions'),
                                'batch_id' => $batchId,
                            ], $userId);
                    }

                    $orderOp->quantity_rejected = max(0, $orderOp->quantity_rejected - $qty);
                    $orderOp->save();

                    $ncr->update(['disposition_type' => $isVendorRework ? 'vendor_rework' : 'rework']);
                } else { // scrap
                    ProductionOrderScrap::create([
                        'tenant_id' => $tenantId,
                        'production_order_id' => $orderOp->production_order_id,
                        'production_order_operation_id' => $orderOp->id,
                        'production_batch_id' => $batchId,
                        'product_id' => $orderOp->source_product_id ?? $orderOp->order?->product_id,
                        'quantity' => $qty,
                        'reason' => $reason,
                        'recorded_by' => $userId,
                        'recorded_at' => now(),
                    ]);

                    $orderOp->quantity_scrapped += $qty;
                    $orderOp->quantity_rejected = max(0, $orderOp->quantity_rejected - $qty);
                    $orderOp->save();

                    if ($ncr) {
                        $ncr->update(['disposition_type' => 'scrap', 'status' => 'closed', 'closed_at' => now(), 'closed_by' => $userId]);
                    }

                    $scrapProductId = $orderOp->source_product_id ?? $orderOp->order?->product_id;
                    app(\App\Domains\Production\Services\ProductionMaterialService::class)
                        ->evaluateAndIssueReplacementMaterial($tenantId, $orderOp->production_order_id, $scrapProductId, $qty, $reason, $userId);
                }
            });

            $msg = ($dispType === 'rework')
                ? ($isVendorRework
                    ? "Subcontract Rework Gate Pass created. {$qty} rejected unit(s) ready for dispatch back to vendor."
                    : "Rework recorded for {$qty} units. Ready for Re-QC upon completion.")
                : "Scrap recorded and material replacement evaluated for {$qty} units.";

            return redirect()->back()->with('success', $msg);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Touch-Friendly Operator Dashboard.
     */
    public function operatorDashboard(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $userId = auth()->id();

        // Auto-reconcile operation readiness for active orders
        $poService = app(\App\Domains\Production\Services\ProductionOrderService::class);
        $activeOrderIds = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereHas('schedule', fn($q) => $q->whereIn('status', [ProductionSchedule::STATUS_RELEASED, ProductionSchedule::STATUS_IN_PROGRESS]))
            ->pluck('production_order_id')
            ->unique();
        foreach ($activeOrderIds as $orderId) {
            if ($orderId) {
                $poService->reconcileOperationReadiness($orderId);
            }
        }

        // My operator assignments
        $myAssignments = ProductionOperatorAssignment::with(['operation.order.product', 'operation.workCenter'])
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->get();

        // Running operations
        $running = ProductionScheduleOperation::with(['schedule.order.product', 'workCenter', 'machine'])
            ->whereHas('schedule', fn($q) => $q->whereIn('status', [ProductionSchedule::STATUS_RELEASED, ProductionSchedule::STATUS_IN_PROGRESS]))
            ->where('status', ProductionScheduleOperation::STATUS_RUNNING)
            ->get();

        // Ready queue
        $ready = ProductionScheduleOperation::with(['schedule.order.product', 'workCenter', 'machine'])
            ->whereHas('schedule', fn($q) => $q->whereIn('status', [ProductionSchedule::STATUS_RELEASED, ProductionSchedule::STATUS_IN_PROGRESS]))
            ->where('status', ProductionScheduleOperation::STATUS_READY)
            ->get();

        // Paused
        $paused = ProductionScheduleOperation::with(['schedule.order.product', 'workCenter', 'machine'])
            ->whereHas('schedule', fn($q) => $q->whereIn('status', [ProductionSchedule::STATUS_RELEASED, ProductionSchedule::STATUS_IN_PROGRESS]))
            ->where('status', ProductionScheduleOperation::STATUS_PAUSED)
            ->get();

        // Done today
        $completedToday = ProductionScheduleOperation::where('status', ProductionScheduleOperation::STATUS_COMPLETED)
            ->whereDate('actual_finish', today())
            ->count();

        return view('modules.production.mes.operator.dashboard', compact(
            'myAssignments',
            'running',
            'ready',
            'paused',
            'completedToday'
        ));
    }

    /**
     * My Assigned Operations list view.
     */
    public function myOperations(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $userId = auth()->id();

        $assignments = ProductionOperatorAssignment::with(['operation.order.product', 'operation.workCenter'])
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->get();

        return view('modules.production.mes.operator.my-operations', compact('assignments'));
    }

    /**
     * Touch-Friendly Operation Execution details view.
     */
    public function operationExecution(Request $request, int $opId)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $op = ProductionOrderOperation::with(['order.product', 'workCenter', 'machine'])->findOrFail($opId);

        $order = $op->order;
        $batchService = app(\App\Domains\Production\Services\BatchProductionService::class);
        $batchQueue = $batchService->getOperationBatchQueue($op, auth()->id());
        $batches = ProductionBatch::where('tenant_id', $tenantId)->where('production_order_id', $order->id)->get();
        $serials = ProductionSerialNumber::where('tenant_id', $tenantId)->where('production_order_id', $order->id)->get();

        // Determine list of active assignments
        $assignment = ProductionOperatorAssignment::where('tenant_id', $tenantId)
            ->where('production_order_operation_id', $opId)
            ->whereIn('status', ['assigned', 'accepted'])
            ->first();

        // Get list of all operators for quick reassignment list
        $operators = \App\Models\User::where('tenant_id', $tenantId)->get();

        // Try mapping the schedule operation if it exists
        $scheduleOp = ProductionScheduleOperation::where('production_order_operation_id', $opId)->first();

        return view('modules.production.mes.operator.operation-execution', compact(
            'op',
            'order',
            'batches',
            'batchQueue',
            'serials',
            'assignment',
            'operators',
            'scheduleOp'
        ));
    }
}
