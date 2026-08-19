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

        $filters = $request->only(['search', 'status', 'production_mode', 'start_date', 'end_date']);
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
                $products = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();
            }
        } else {
            $products = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();
        }

        $selectedProductId = old('product_id', $productId ?? $selectedRequest?->product_id);
        $selectedQty = old('quantity_ordered', $selectedRequest?->quantity_requested);
        $selectedSoItemId = old('sales_order_item_id', $selectedRequest?->materialRequirementItem?->sales_order_item_id);

        if (!$selectedProductId && $salesOrderItems->isNotEmpty()) {
            $firstItem = $salesOrderItems->first();
            $selectedProductId = $firstItem->product_id;
            $selectedQty = $selectedQty ?? $firstItem->quantity;
            $selectedSoItemId = $selectedSoItemId ?? $firstItem->id;
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
            'selectedSoItemId'
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
                ->with('success', "Requisition request sent to Store. Once items are issued, you can release the order.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
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

        return view('modules.production.orders.show', compact(
            'order',
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

            return redirect()->route('sales.material-requests.show', $slip->id)
                ->with('success', "Ad-hoc Material Requisition {$slip->requisition_number} successfully created for Order {$order->order_number}.");
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
                ->with('error', 'Frozen Production Orders cannot be edited.');
        }

        $products = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();

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

    public function release(int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('release', $order);

        try {
            $this->orderService->release($id, Auth::id());

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

    public function close(int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        Gate::authorize('close', $order);

        try {
            $this->orderService->close($id, Auth::id());

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
                $resolvedBatchId ? (int) $resolvedBatchId : null
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
                    if ($order->isDraft() && auth()->user()->can('update', $order)) {
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
                    if (($order->isDraft() || $order->isReleased()) && auth()->user()->can('update', $order)) {
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
