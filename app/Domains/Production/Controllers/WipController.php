<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Services\ProductionWipService;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WipController extends Controller
{
    public function __construct(
        private readonly ProductionWipService $wipService
    ) {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);
        $tenantId = require_tenant_id();

        // Self-heal: initialize WIP for any existing released or in-progress orders that do not have a WIP card yet
        $uninitializedOrders = \App\Domains\Production\Models\ProductionOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['released', 'in_progress'])
            ->whereNotExists(function ($query) {
                $query->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('production_wips')
                    ->whereColumn('production_wips.production_order_id', 'production_orders.id');
            })
            ->get();

        foreach ($uninitializedOrders as $order) {
            try {
                $this->wipService->initializeWip($order->id);
            } catch (\Exception $e) {
                // Fail-safe to avoid blocking page load
            }
        }

        $viewMode = $request->input('view', 'order');
        $search = $request->input('search');
        $status = $request->input('status');
        $workCenterIdFilter = $request->filled('work_center_id') ? (int) $request->input('work_center_id') : null;

        // Validate work center filter ownership if provided
        if ($workCenterIdFilter) {
            \App\Domains\Production\Models\WorkCenter::where('tenant_id', $tenantId)->findOrFail($workCenterIdFilter);
        }

        // Server-side paginated orders query
        $perPage = min(max((int) $request->input('per_page', 10), 1), 50);
        $ordersPaginator = $this->wipService->getConsolidatedOrderWipSummaries($tenantId, $search, $status, $perPage);

        // Pre-aggregate Work-Center summaries & Batch Pipeline data for orders on the current page
        $orderSummariesMap = [];
        $orderBatchPipelinesMap = [];
        foreach ($ordersPaginator->items() as $order) {
            $summaries = $this->wipService->getWorkCenterWipSummaries($tenantId, $order->id, $workCenterIdFilter);
            $orderSummariesMap[$order->id] = $summaries;
            $orderBatchPipelinesMap[$order->id] = $this->wipService->getBatchPipelineData($order->id);
        }

        // Flat card fallback view query
        $wipQuery = ProductionWip::where('tenant_id', $tenantId)
            ->with(['order', 'product', 'currentRoutingOperation', 'currentWorkCenter', 'currentMachine', 'batch']);

        if ($search) {
            $searchTerm = '%' . $search . '%';
            $wipQuery->where(function ($q) use ($searchTerm) {
                $q->whereHas('product', fn($p) => $p->where('name', 'like', $searchTerm)->orWhere('sku', 'like', $searchTerm))
                    ->orWhereHas('order', fn($o) => $o->where('order_number', 'like', $searchTerm));
            });
        }

        if ($status) {
            $wipQuery->where('status', $status);
        }

        if ($workCenterIdFilter) {
            $wipQuery->where('current_work_center_id', $workCenterIdFilter);
        }

        $wips = $wipQuery->orderBy('id', 'desc')->paginate($perPage)->withQueryString();

        // Calculate tenant-scoped summary KPI metrics using aggregate DB selectRaw
        $summary = ProductionWip::where('tenant_id', $tenantId)
            ->selectRaw('
                count(*) as total_count,
                sum(available_quantity) as total_available,
                sum(case when status = "active" then 1 else 0 end) as active_count,
                sum(case when status = "quality_hold" then 1 else 0 end) as hold_count,
                sum(case when status = "rework" then 1 else 0 end) as rework_count,
                sum(case when status = "completed" then 1 else 0 end) as completed_count
            ')
            ->first();

        $wipSummary = [
            'total_count' => (int) ($summary->total_count ?? 0),
            'total_available' => (float) ($summary->total_available ?? 0),
            'active_count' => (int) ($summary->active_count ?? 0),
            'hold_count' => (int) ($summary->hold_count ?? 0),
            'rework_count' => (int) ($summary->rework_count ?? 0),
            'completed_count' => (int) ($summary->completed_count ?? 0),
        ];

        $workCenters = \App\Domains\Production\Models\WorkCenter::where('tenant_id', $tenantId)->orderBy('name')->get();
        $warehouses = \App\Domains\Inventory\Models\Warehouse::where('tenant_id', $tenantId)->orderByDesc('is_default')->get();

        return view('modules.production.wip.index', compact(
            'wips',
            'wipSummary',
            'viewMode',
            'ordersPaginator',
            'orderSummariesMap',
            'orderBatchPipelinesMap',
            'workCenters',
            'workCenterIdFilter',
            'warehouses'
        ));
    }

    /**
     * AJAX endpoint for server-side paginated Work Center batch rows.
     */
    public function getWorkCenterBatches(Request $request, int $orderId, int $workCenterId)
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);
        $tenantId = require_tenant_id();

        $order = \App\Domains\Production\Models\ProductionOrder::where('tenant_id', $tenantId)->findOrFail($orderId);
        $workCenter = \App\Domains\Production\Models\WorkCenter::where('tenant_id', $tenantId)->findOrFail($workCenterId);

        $perPage = min(max((int) $request->input('per_page', 5), 1), 50);
        $status = $request->input('status');
        $search = $request->input('search');

        $paginatedWips = $this->wipService->getPaginatedWorkCenterWips(
            $tenantId,
            $order->id,
            $workCenter->id,
            $status,
            $search,
            $perPage
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'work_center_id' => $workCenter->id,
                'current_page' => $paginatedWips->currentPage(),
                'last_page' => $paginatedWips->lastPage(),
                'per_page' => $paginatedWips->perPage(),
                'total' => $paginatedWips->total(),
                'has_more' => $paginatedWips->hasMorePages(),
                'html' => view('modules.production.wip.partials.work-center-batch-rows', [
                    'wips' => $paginatedWips->items(),
                ])->render(),
            ]);
        }

        return redirect()->route('production.wip.index', ['view' => 'order']);
    }

    public function show(int $id)
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);
        $tenantId = require_tenant_id();

        $wip = ProductionWip::where('tenant_id', $tenantId)
            ->with(['order.operations.workCenter', 'product', 'currentRoutingOperation', 'currentWorkCenter', 'currentMachine', 'transactions.fromOperation', 'transactions.toOperation', 'transactions.operator'])
            ->findOrFail($id);

        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderByDesc('is_default')->get();

        return view('modules.production.wip.show', compact('wip', 'warehouses'));
    }

    public function transfer(Request $request, int $id)
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);

        $request->validate([
            'from_operation_id' => 'required|exists:production_routing_operations,id',
            'to_operation_id' => 'required|exists:production_routing_operations,id',
            'quantity' => 'required|numeric|min:0.0001',
            'remarks' => 'nullable|string|max:255',
        ]);

        try {
            $this->wipService->transferWip(
                $id,
                (int) $request->input('from_operation_id'),
                (int) $request->input('to_operation_id'),
                (float) $request->input('quantity'),
                $request->input('remarks'),
                auth()->id()
            );

            return redirect()->back()->with('success', 'WIP quantity transferred to next stage successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function adjust(Request $request, int $id)
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);

        $request->validate([
            'quantity' => 'required|numeric|min:0',
            'scrap_quantity' => 'nullable|numeric|min:0',
            'rejected_quantity' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
        ]);

        try {
            $this->wipService->adjustWip(
                $id,
                (float) $request->input('quantity'),
                $request->input('reason'),
                auth()->id(),
                $request->filled('scrap_quantity') ? (float) $request->input('scrap_quantity') : null,
                $request->filled('rejected_quantity') ? (float) $request->input('rejected_quantity') : null
            );

            return redirect()->back()->with('success', 'WIP quantity adjusted successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function convertToFg(Request $request, int $id)
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);

        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'quality_status' => 'nullable|string|in:passed,quarantine,failed',
            'remarks' => 'nullable|string|max:255',
        ]);

        try {
            $this->wipService->convertWipToFinishedGoods(
                $id,
                (int) $request->input('warehouse_id'),
                $request->input('remarks'),
                auth()->id(),
                $request->input('quality_status', 'passed')
            );

            return redirect()->route('production.wip.show', $id)->with('success', 'WIP converted and Finished Goods stock received.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function convertOrderToFg(Request $request, int $orderId)
    {
        abort_unless(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->hasProductionPermission('production.mes.execute')), 403);

        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'quality_status' => 'nullable|string|in:passed,quarantine,failed',
            'remarks' => 'nullable|string|max:255',
        ]);

        try {
            $totalConverted = $this->wipService->convertOrderWipToFinishedGoods(
                $orderId,
                (int) $request->input('warehouse_id'),
                $request->input('remarks'),
                auth()->id(),
                $request->input('quality_status', 'passed')
            );

            return redirect()->back()
                ->with('success', "Successfully received {$totalConverted} units into Finished Goods stock across all order WIP cards.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
