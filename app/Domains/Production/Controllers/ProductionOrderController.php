<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRequest;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Requests\StoreProductionOrderRequest;
use App\Domains\Production\Requests\UpdateProductionOrderRequest;
use App\Domains\Production\Services\ProductionCostAdjustmentService;
use App\Domains\Production\Services\ProductionCostVarianceService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Sales\Models\SalesOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

use App\Domains\Production\Repositories\ProductionOrderRepositoryInterface;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $orderService,
        private readonly ProductionMaterialService $materialService,
        private readonly ProductionExecutionService $executionService,
        private readonly ProductionCostVarianceService $costService,
        private readonly ProductionCostAdjustmentService $adjustmentService,
        private readonly ProductionOrderRepositoryInterface $orderRepository
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', ProductionOrder::class);

        $filters = $request->only(['search', 'status', 'production_mode', 'start_date', 'end_date', 'sort_by', 'sort_order']);
        $orders = $this->orderRepository->paginateWithFilters($filters, 15)->withQueryString();
        $statusCounts = $this->orderRepository->getStatusCounts();

        return view('modules.production.orders.index', compact('orders', 'statusCounts'));
    }

    public function create(Request $request)
    {
        Gate::authorize('create', ProductionOrder::class);

        $salesOrderId = $request->query('sales_order_id');
        $productionOrderRequestId = $request->query('production_order_request_id');
        $productId = $request->query('product_id');

        $tenantId = require_tenant_id();

        $productionOrderRequests = $this->orderRepository->getPendingRequests(
            $tenantId,
            $productionOrderRequestId ? (int) $productionOrderRequestId : null
        );

        $selectedRequest = null;
        if ($productionOrderRequestId) {
            $selectedRequest = $productionOrderRequests->firstWhere('id', (int) $productionOrderRequestId);
        }

        $salesOrder = null;
        $salesOrderItems = collect();

        if ($salesOrderId) {
            $salesOrder = SalesOrder::with(['items.product'])->find($salesOrderId);
            if ($salesOrder) {
                $salesOrderItems = $salesOrder->items->filter(function ($item) {
                    return $item->product && $item->product->supplier_method === 'manufacture';
                });
                $products = $salesOrderItems->map(fn($item) => $item->product)->unique('id');
            } else {
                $products = Product::whereIn('type', ['finished_good', 'finished_goods', 'semi_finished', 'semi_finished_goods'])->get();
            }
        } else {
            $products = Product::whereIn('type', ['finished_good', 'finished_goods', 'semi_finished', 'semi_finished_goods'])->get();
        }

        $quantityParam = $request->query('quantity');

        $projectId = $request->query('project_id');
        $projectTaskId = $request->query('project_task_id');

        $project = null;
        $projectTask = null;
        $projectMilestone = null;

        if ($projectTaskId) {
            $projectTask = \App\Domains\Projects\Models\Task::where('tenant_id', $tenantId)
                ->with(['project', 'milestone.salesOrderItem.product'])
                ->find($projectTaskId);
            if ($projectTask) {
                $project = $projectTask->project;
                $projectMilestone = $projectTask->milestone;
                $projectId = $project?->id;

                if (!$productId && $projectMilestone?->salesOrderItem) {
                    $productId = $projectMilestone->salesOrderItem->product_id;
                }
                if (!$quantityParam && $projectMilestone?->salesOrderItem) {
                    $quantityParam = $projectMilestone->salesOrderItem->quantity;
                }
            }
        } elseif ($projectId) {
            $project = \App\Domains\Projects\Models\Project::where('tenant_id', $tenantId)->find($projectId);
        }

        $selectedProductId = old('product_id', $productId ?? $selectedRequest?->product_id);
        $selectedQty = old('quantity_ordered', $quantityParam ?? $selectedRequest?->quantity_requested);
        $selectedSoItemId = old('sales_order_item_id', $selectedRequest?->materialRequirementItem?->sales_order_item_id ?? $projectMilestone?->sales_order_item_id);

        if (!$selectedProductId && $salesOrderItems->isNotEmpty()) {
            $firstItem = $salesOrderItems->first();
            $selectedProductId = $firstItem->product_id;
            $selectedQty = $selectedQty ?? $firstItem->quantity;
            $selectedSoItemId = $selectedSoItemId ?? $firstItem->id;
        }

        if ($selectedProductId && !$products->contains('id', (int) $selectedProductId)) {
            $selProd = Product::find($selectedProductId);
            if ($selProd) {
                $products->push($selProd);
            }
        }

        return view('modules.production.orders.create', compact(
            'products',
            'salesOrder',
            'salesOrderItems',
            'productionOrderRequests',
            'productionOrderRequestId',
            'selectedRequest',
            'selectedProductId',
            'selectedQty',
            'selectedSoItemId',
            'project',
            'projectTask',
            'projectMilestone'
        ));
    }

    public function store(StoreProductionOrderRequest $request)
    {
        Gate::authorize('create', ProductionOrder::class);

        $tenantId = require_tenant_id();

        try {
            $order = $this->orderService->createDirect($request->validated(), $tenantId, Auth::id());

            return redirect()
                ->route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-reservations'])
                // UR-11 / UR-12 (M-23 / M-24): nothing is sent to the store automatically any more — the order stays a draft until the
                // user selects the materials and sends the request(s) by hand.
                ->with('success', "Production Order {$order->order_number} created as a draft. Check it, then select the materials to request and send them to the store.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * PO-IMPL-05 (PO-01): BOM / Routing resolution, blocking reasons and the Tab 1 / Tab 2 workspace for Create.
     */
    public function getEngineeringStatus(Request $request)
    {
        Gate::authorize('create', ProductionOrder::class);

        $request->validate([
            'product_id' => 'required|integer',
            'branch_id'  => 'nullable|integer',
            'quantity'   => 'nullable|numeric|min:0',
        ]);
        $tenantId = require_tenant_id();
        $product = Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($request->integer('product_id'));
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : (branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id());

        return response()->json(
            app(\App\Domains\Production\Services\ProductionOrderEngineeringService::class)
                ->buildInteractiveWorkspacePayload($product, $tenantId, $branchId, (float) ($request->input('quantity') ?: 1))
        );
    }

    public function createFromPlan(int $planId)
    {
        Gate::authorize('create', ProductionOrder::class);

        try {
            $order = $this->orderService->createFromPlan($planId, Auth::id());

            return redirect()
                ->route('production.orders.show', $order->id)
                ->with('success', 'Production Order generated from Plan successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function show(int $id)
    {
        $order = $this->orderRepository->findWithDetails($id);
        abort_if(!$order, 404, 'Production Order not found.');

        Gate::authorize('view', $order);

        // Auto-completion is a separate concern and stays.
        if (in_array($order->status, ['released', 'in_progress'])) {
            $this->orderService->evaluateAndAutoCompleteOrder($order, Auth::id());
        }

        if (in_array($order->status, ['released', 'in_progress']) && $order->wips->isEmpty()) {
            try {
                app(\App\Domains\Production\Services\ProductionWipService::class)->initializeWip($order->id);
                $order->load([
                    'wips.currentRoutingOperation',
                    'wips.currentWorkCenter',
                    'wips.transactions.fromOperation',
                    'wips.transactions.toOperation'
                ]);
            } catch (\Exception $e) {
                // Fail-safe to avoid blocking page load
            }
        }

        foreach ($order->batches as $batch) {
            try {
                app(\App\Domains\Production\Services\BatchProductionService::class)->reconcileBatchActualQuantity($batch->id);
            } catch (\Exception $e) {
                // Fail-safe to avoid blocking page load
            }
        }

        foreach ($order->operations as $op) {
            try {
                $this->executionService->reconcileOperationQuantities($op->id);
            } catch (\Exception $e) {
                // Fail-safe to avoid blocking page load
            }
        }

        // UR-05 (the user's rule): every time this screen loads, ALL readiness checks run again and the saved operation status is
        // refreshed at that moment (written only when it changed; draft orders are skipped inside). The 5-minute sweep and the
        // event refreshes (issue / return, start, log, machine, downtime, release) stay as a safety net.
        try {
            $this->orderService->reconcileOperationReadiness($order);
        } catch (\Throwable $e) {
            // Fail-safe to avoid blocking page load
        }

        // Get variance analysis calculations
        $costs = $this->costService->getCostAnalysis($order);

        // Cost Adjustments & Final Costing Summary
        $costAdjustments = $order->costAdjustments()
            ->with(['creator', 'updater'])
            ->latest('adjustment_date')
            ->paginate(10, ['*'], 'adjustments_page');

        $dailyManualAdjustments = $this->adjustmentService->getDailyAdjustments($order);
        $dailyHistory = $this->costService->getDailyCostHistory($order, $dailyManualAdjustments);
        $finalCostingSummary = $this->adjustmentService->getFinalCostingSummary($order, $costs);

        $costComponents = \App\Domains\Production\Models\ProductionCostAdjustment::getCostComponents();
        $categories = \App\Domains\Production\Models\ProductionCostAdjustment::getCategories();
        $warehouses = Warehouse::where('tenant_id', $order->tenant_id)->orderByDesc('is_default')->orderBy('name')->get();
        $operators = \App\Models\User::where('tenant_id', $order->tenant_id)->orderBy('name')->get();
        $wipWorkCenterSummaries = app(\App\Domains\Production\Services\ProductionWipService::class)
            ->getWorkCenterWipSummaries($order->tenant_id, $order->id);
        $readiness = app(\App\Domains\Production\Services\ProductionReadinessService::class)->evaluateOrderReadiness($order);
        // UR-07: the operations as a tree — the operations that make a sub-assembly sit in an arrow drawer ABOVE the operation that needs them
        // (executed first). Every operation is resolved once and shared by the tree and the per-operation details.
        $opMaterialService = app(\App\Domains\Production\Services\OperationMaterialService::class);
        $opList = $opMaterialService->orderedOperations($order);
        $opResolved = $opMaterialService->resolveAll($opList);
        $opTree = $opMaterialService->operationTree($order, $opList, $opResolved);
        $opDescribe = $opMaterialService->describeAll($order, $opList, $opResolved);
        $varianceAnalysis = app(\App\Domains\Production\Services\ProductionVarianceAnalysisService::class)->analyzeProductionOrder($order);
        $riskAnalysis = app(\App\Domains\Production\Services\PlanningExceptionService::class)->evaluateOrderRisk($order);
        // UR-11 / UR-12: what can still be requested from the store, and whether releasing needs the "materials not released" confirmation
        $materialRequestLines = $this->orderService->materialRequestLines($order);
        $materialsNotReleased = $order->isPreRelease() && $this->orderService->materialsNotReleased($order);

        return view('modules.production.orders.show', compact(
            'order',
            'readiness',
            'materialRequestLines',
            'materialsNotReleased',
            'opTree',
            'opDescribe',
            'varianceAnalysis',
            'riskAnalysis',
            'costs',
            'costAdjustments',
            'dailyHistory',
            'finalCostingSummary',
            'costComponents',
            'categories',
            'warehouses',
            'operators',
            'wipWorkCenterSummaries'
        ));
    }

    public function requestAdditionalMaterial(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('issue', $order);

        if ($order->isCompleted() || $order->isClosed() || $order->isCancelled()) {
            return redirect()->back()->with('error', 'Cannot request additional material for a completed, closed, or cancelled order.');
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.notes' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $slip = $this->orderService->createAdHocRequisitionSlip(
                $order,
                $validated['items'],
                auth()->id(),
                $validated['notes'] ?? null
            );

            $totalQty = (float) collect($validated['items'])->sum('quantity');
            $reqReason = $validated['notes'] ?? 'Ad-hoc additional material request';
            app(\App\Domains\Production\Services\ProductionNotificationService::class)
                ->notifyAdditionalMaterialRequested($order, $totalQty, $reqReason, (int) (auth()->id() ?? 1));

            return redirect()->route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-reservations'])
                ->with('success', "Request sent to store for additional materials. (Requisition #{$slip->requisition_number})");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * UR-11 (M-23 / M-24): the user selects the planned materials to request and sends them to the store — one request (one requisition
     * slip) per click, as many as needed per order. The first request locks a draft order (UR-12).
     */
    public function sendMaterialRequest(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('issue', $order);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.selected' => 'nullable|boolean',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $selected = collect($validated['items'])->filter(fn ($i) => !empty($i['selected']) && (float) ($i['quantity'] ?? 0) > 0)->values()->all();
        if ($selected === []) {
            return redirect()->back()->withInput()->with('error', 'Select at least one material to request.');
        }

        try {
            $wasDraft = $order->isDraft();
            $slip = $this->orderService->sendMaterialRequest($order, $selected, auth()->id(), $validated['notes'] ?? null);

            $totalQty = (float) collect($selected)->sum('quantity');
            try {
                app(\App\Domains\Production\Services\ProductionNotificationService::class)
                    ->notifyAdditionalMaterialRequested($order, $totalQty, $validated['notes'] ?? 'Material request sent to the store', (int) (auth()->id() ?? 1));
            } catch (\Throwable $e) {
                // a notification problem must not undo the request
            }

            return redirect()->route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-procurement'])
                ->with('success', "Request {$slip->requisition_number} sent to the store." . ($wasDraft ? ' The order is now locked: it can no longer be edited or deleted, only cancelled or released.' : ''));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /** UR-10: "Produce all here" — the set of this operation also makes what the later operations need. */
    public function mergeSubAssembly(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('restructure', $order);
        $data = $request->validate(['operation_id' => 'required|integer', 'child_bom_id' => 'required|integer']);

        try {
            app(\App\Domains\Production\Services\SubAssemblySetService::class)
                ->merge($order, (int) $data['operation_id'], (int) $data['child_bom_id'], auth()->id());

            return redirect()->back()->with('success', __('production.sa_merged'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /** UR-10: "Produce separately" — every operation gets its own set again. */
    public function splitSubAssembly(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('restructure', $order);
        $data = $request->validate(['operation_id' => 'required|integer', 'child_bom_id' => 'required|integer']);

        try {
            app(\App\Domains\Production\Services\SubAssemblySetService::class)
                ->split($order, (int) $data['operation_id'], (int) $data['child_bom_id'], auth()->id());

            return redirect()->back()->with('success', __('production.sa_split'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function edit(int $id)
    {
        $order = ProductionOrder::findOrFail($id);

        Gate::authorize('update', $order);

        if ($order->isFrozen()) {
            return redirect()
                ->route('production.orders.show', $order->id)
                ->with('error', $order->isLocked()
                    ? 'This order is locked (a material request was sent): it can no longer be edited — only cancelled or released.'
                    : 'Frozen Production Orders cannot be edited.');
        }

        $products = Product::whereIn('type', ['finished_good', 'finished_goods', 'semi_finished', 'semi_finished_goods'])->get();

        return view('modules.production.orders.edit', compact('order', 'products'));
    }

    public function update(UpdateProductionOrderRequest $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);

        Gate::authorize('update', $order);

        try {
            $this->orderService->update($id, $request->validated());

            return redirect()
                ->route('production.orders.show', $order->id)
                ->with('success', 'Production Order updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(int $id)
    {
        $order = ProductionOrder::findOrFail($id);

        Gate::authorize('delete', $order);

        try {
            $this->orderService->delete($id);

            return redirect()
                ->route('production.orders.index')
                ->with('success', 'Production Order deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ── Shop Floor Transitions ──

    public function release(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('release', $order);

        try {
            // UR-12 (M-24): not having the material yet is only a warning — releasing needs the user's confirmation
            $this->orderService->release($id, Auth::id(), $request->boolean('confirm_materials'));

            return redirect()
                ->route('production.orders.show', $order->id)
                ->with('success', "Order {$order->order_number} released. Next: generate a schedule.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function complete(int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('complete', $order);

        try {
            $this->orderService->complete($id, Auth::id());

            return redirect()
                ->route('production.orders.show', $order->id)
                ->with('success', "Order {$order->order_number} completed. Review cost analysis.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function close(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('close', $order);

        try {
            $this->orderService->close($id, Auth::id(), $request->input('no_fg_reason'));

            return redirect()->back()->with('success', 'Production Order closed and archived.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel(int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('cancel', $order);

        try {
            $this->orderService->cancel($id, Auth::id());

            return redirect()->back()->with('success', 'Production Order cancelled.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ── Material Operations ──

    public function issueMaterial(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('issue', $order);

        $request->validate([
            'reservation_id' => 'required|exists:production_order_reservations,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.0001',
            'remarks' => 'nullable|string|max:255',
        ]);

        ProductionOrderReservation::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->findOrFail($request->input('reservation_id'));

        try {
            $this->materialService->issueMaterial(
                $request->input('reservation_id'),
                (float) $request->input('quantity'),
                $request->input('remarks'),
                Auth::id(),
                $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null
            );

            return redirect()->back()->with('success', 'Material quantity issued successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function returnMaterial(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('return', $order);

        $request->validate([
            'reservation_id' => 'required|exists:production_order_reservations,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.0001',
            'remarks' => 'nullable|string|max:255',
        ]);

        ProductionOrderReservation::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->findOrFail($request->input('reservation_id'));

        try {
            $this->materialService->returnMaterial(
                $request->input('reservation_id'),
                (float) $request->input('quantity'),
                $request->input('remarks'),
                Auth::id(),
                $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null
            );

            return redirect()->back()->with('success', 'Material returned to warehouse successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function allocateRemnant(Request $request, int $id, \App\Domains\Production\Services\RemnantAllocationService $allocationService)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('issue', $order);

        $request->validate([
            'reservation_id' => 'required|exists:production_order_reservations,id',
            'remnant_id' => 'required|exists:inventory_remnants,id',
            'allocated_length' => 'nullable|numeric|min:0.0001',
            'allocated_quantity' => 'nullable|numeric|min:0.0001',
        ]);

        try {
            $allocations = [
                [
                    'remnant_id' => (int) $request->input('remnant_id'),
                    'allocated_length' => $request->filled('allocated_length') ? (float) $request->input('allocated_length') : null,
                    'allocated_quantity' => $request->filled('allocated_quantity') ? (float) $request->input('allocated_quantity') : null,
                ]
            ];

            $allocationService->allocateRemnants(
                $order->tenant_id,
                $order->id,
                (int) $request->input('reservation_id'),
                $allocations,
                Auth::id()
            );

            return redirect()->back()->with('success', 'Remnant allocated successfully to production requirement.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function releaseRemnantAllocation(Request $request, int $id, int $allocationId, \App\Domains\Production\Services\RemnantAllocationService $allocationService)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('issue', $order);

        try {
            $allocation = \App\Domains\Production\Models\ProductionOrderRemnantAllocation::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->findOrFail($allocationId);

            $allocationService->releaseAllocation($allocation->id, Auth::id());

            return redirect()->back()->with('success', 'Remnant allocation released successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function consumeRemnantAllocation(Request $request, int $id, int $allocationId, \App\Domains\Production\Services\RemnantAllocationService $allocationService)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('issue', $order);

        $request->validate([
            'consumed_length' => 'nullable|numeric|min:0.0001',
            'consumed_quantity' => 'nullable|numeric|min:0.0001',
        ]);

        try {
            $allocation = \App\Domains\Production\Models\ProductionOrderRemnantAllocation::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->findOrFail($allocationId);

            $allocationService->consumeAllocatedRemnant(
                $allocation->id,
                $request->filled('consumed_length') ? (float) $request->input('consumed_length') : null,
                $request->filled('consumed_quantity') ? (float) $request->input('consumed_quantity') : null,
                Auth::id()
            );

            return redirect()->back()->with('success', 'Allocated remnant consumed into production successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ── Progress Operations ──

    public function logProgress(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('logProgress', $order);

        $request->validate([
            'operation_id' => 'required|exists:production_order_operations,id',
            'batch_id' => 'nullable|exists:production_batches,id',
            'production_batch_id' => 'nullable|exists:production_batches,id',
            'quantity_produced' => 'required|numeric|min:0',
            'quantity_rejected' => 'required|numeric|min:0',
            'quantity_scrapped' => 'required|numeric|min:0',
            'setup_minutes_logged' => 'required|numeric|min:0',
            'run_minutes_logged' => 'required|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
            'machine_id' => 'nullable|exists:production_machines,id',
            'complete_operation' => 'nullable|boolean',
            'consumed_materials' => 'nullable|array',
            'consumed_materials.*.product_id' => 'nullable|exists:products,id',
            'consumed_materials.*.quantity' => 'nullable|numeric|min:0',
            'consumed_materials.*.warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        ProductionOrderOperation::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->findOrFail($request->input('operation_id'));

        $resolvedBatchId = $request->input('batch_id') ?? $request->input('production_batch_id');

        try {
            $this->executionService->logProgress(
                $request->input('operation_id'),
                (float) $request->input('quantity_produced'),
                (float) $request->input('quantity_rejected'),
                (float) $request->input('quantity_scrapped'),
                (float) $request->input('setup_minutes_logged'),
                (float) $request->input('run_minutes_logged'),
                $request->input('remarks'),
                $request->input('machine_id'),
                Auth::id(),
                (bool) $request->input('complete_operation', false),
                null,
                $resolvedBatchId ? (int) $resolvedBatchId : null,
                config('production.temp_flow') ? (array) $request->input('consumed_materials', []) : []
            );

            return redirect()->back()->with('success', 'Execution progress logged successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function logScrap(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('logProgress', $order);

        $request->validate([
            'operation_id' => 'nullable|exists:production_order_operations,id',
            'product_id' => 'nullable|exists:products,id',
            'quantity' => 'required|numeric|min:0.0001',
            'reason' => 'nullable|string|max:255',
            'create_ncr' => 'nullable|boolean',
            'ncr_category' => 'nullable|string|max:100',
        ]);

        if ($request->filled('operation_id')) {
            ProductionOrderOperation::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->findOrFail($request->input('operation_id'));
        }

        try {
            $this->executionService->logScrap(
                $id,
                $request->input('operation_id'),
                $request->input('product_id'),
                (float) $request->input('quantity'),
                $request->input('reason'),
                Auth::id(),
                null,
                (bool) $request->boolean('create_ncr'),
                $request->filled('ncr_category') ? ['category' => $request->input('ncr_category')] : []
            );

            return redirect()->back()->with('success', 'Production scrap logged successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function logRework(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('logProgress', $order);

        $request->validate([
            'operation_id' => 'nullable|exists:production_order_operations,id',
            'quantity' => 'required|numeric|min:0.0001',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($request->filled('operation_id')) {
            ProductionOrderOperation::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->findOrFail($request->input('operation_id'));
        }

        try {
            $this->executionService->logRework(
                $id,
                $request->input('operation_id'),
                (float) $request->input('quantity'),
                $request->input('reason'),
                Auth::id()
            );

            return redirect()->back()->with('success', 'Rework loop registered.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function receiveFg(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('receiveFg', $order);

        $request->validate([
            'quantity_received' => 'required|numeric|min:0.0001',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'quality_status' => 'required|string|in:passed,quarantine,failed',
            'remarks' => 'nullable|string|max:255',
        ]);

        try {
            $this->executionService->receiveFinishedGoods(
                $id,
                (float) $request->input('quantity_received'),
                $request->input('quality_status'),
                $request->input('remarks'),
                Auth::id(),
                $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null
            );

            return redirect()->back()->with('success', 'Finished goods received successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function bulkAction(Request $request)
    {
        $action = $request->input('action');
        $ids = $request->input('ids');

        if (empty($ids) || !is_array($ids)) {
            return redirect()
                ->back()
                ->with('error', 'No Production Orders selected.');
        }

        $tenantId = require_tenant_id();
        $orders = ProductionOrder::whereIn('id', $ids)
            ->where('tenant_id', $tenantId)
            ->get();

        $successCount = 0;
        $failedCount = 0;

        switch ($action) {
            case 'release':
                foreach ($orders as $order) {
                    if ($order->isPreRelease() && auth()->user()->can('release', $order)) {
                        try {
                            $this->orderService->releaseOrder($order->id, auth()->id() ?: 1);
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "released";
                break;

            case 'complete':
                foreach ($orders as $order) {
                    if (($order->isInProgress() || $order->isReleased()) && auth()->user()->can('update', $order)) {
                        try {
                            $this->orderService->completeOrder($order->id, auth()->id() ?: 1);
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "completed";
                break;

            case 'cancel':
                foreach ($orders as $order) {
                    if (($order->isPreRelease() || $order->isReleased()) && auth()->user()->can('cancel', $order)) {
                        try {
                            $this->orderService->cancelOrder($order->id, auth()->id() ?: 1);
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "cancelled";
                break;

            case 'delete':
                foreach ($orders as $order) {
                    if (($order->isDraft() || $order->isCancelled()) && auth()->user()->can('delete', $order)) {
                        try {
                            $order->delete();
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "deleted";
                break;

            default:
                return redirect()->back()->with('error', 'Invalid bulk action requested.');
        }

        $message = "Successfully {$messagePrefix} {$successCount} production order(s).";
        if ($failedCount > 0) {
            $message .= " ({$failedCount} order(s) skipped due to state or permissions).";
        }

        return redirect()->back()->with($successCount > 0 ? 'success' : 'error', $message);
    }

    public function generateSubcontractPr(ProductionOrder $order, ProductionOrderOperation $operation)
    {
        Gate::authorize('update', $order);
        $tenantId = require_tenant_id();

        if (!$operation->is_external) {
            return redirect()->back()->with('error', 'Operation is not an outsourced operation.');
        }

        $pr = app(\App\Domains\Production\Services\SubcontractProcurementOrchestrator::class)
            ->generateSubcontractRequisition($operation, $tenantId, Auth::id());

        return redirect()->back()->with('success', "Purchase Requisition {$pr->requisition_number} generated successfully for Subcontract Operation #{$operation->sequence}.");
    }
}
