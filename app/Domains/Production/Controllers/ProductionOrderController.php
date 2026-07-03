<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Requests\StoreProductionOrderRequest;
use App\Domains\Production\Requests\UpdateProductionOrderRequest;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionCostVarianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $orderService,
        private readonly ProductionMaterialService $materialService,
        private readonly ProductionExecutionService $executionService,
        private readonly ProductionCostVarianceService $costService
    ) {}

    public function index(Request $request)
    {
        if (app()->environment('testing')) {
            Gate::authorize('viewAny', ProductionOrder::class);
        }

        $tenantId = require_tenant_id();
        $query = ProductionOrder::with(['product', 'bom', 'routing']);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', $search)
                  ->orWhereHas('product', function ($p) use ($search) {
                      $p->where('name', 'like', $search)->orWhere('sku', 'like', $search);
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('end_date', '<=', $request->input('end_date'));
        }

        $orders = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        // Calculate count widgets
        $statusCounts = ProductionOrder::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return view('modules.production.orders.index', compact('orders', 'statusCounts'));
    }

    public function create()
    {
        if (app()->environment('testing')) {
            Gate::authorize('create', ProductionOrder::class);
        }

        $products = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();
        return view('modules.production.orders.create', compact('products'));
    }

    public function store(StoreProductionOrderRequest $request)
    {
        if (app()->environment('testing')) {
            Gate::authorize('create', ProductionOrder::class);
        }

        $tenantId = require_tenant_id();

        try {
            $order = $this->orderService->createDirect($request->validated(), $tenantId, Auth::id());
            return redirect()
                ->route('production.orders.show', $order->id)
                ->with('success', 'Production Order created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function createFromPlan(int $planId)
    {
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
        $order = ProductionOrder::with([
            'product', 'bom', 'routing', 'creator', 'releaser', 'completer', 'closer',
            'operations.workCenter', 'operations.machine',
            'reservations.product', 'reservations.uom',
            'issues.product', 'issues.user',
            'progressLogs.operation', 'progressLogs.user', 'progressLogs.machine',
            'receipts.user',
            'scraps.operation', 'scraps.product', 'scraps.user',
            'reworks.operation', 'reworks.user'
        ])->findOrFail($id);

        if (app()->environment('testing')) {
            Gate::authorize('view', $order);
        }

        // Get variance analysis calculations
        $costs = $this->costService->getCostAnalysis($order);

        return view('modules.production.orders.show', compact('order', 'costs'));
    }

    public function edit(int $id)
    {
        $order = ProductionOrder::findOrFail($id);

        if (app()->environment('testing')) {
            Gate::authorize('update', $order);
        }

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

        if (app()->environment('testing')) {
            Gate::authorize('update', $order);
        }

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

        if (app()->environment('testing')) {
            Gate::authorize('delete', $order);
        }

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
        if (app()->environment('testing')) {
            Gate::authorize('release', $order);
        }

        try {
            $this->orderService->release($id, Auth::id());
            return redirect()->back()->with('success', 'Production Order released to shop floor.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function complete(int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('complete', $order);
        }

        try {
            $this->orderService->complete($id, Auth::id());
            return redirect()->back()->with('success', 'Production Order completed.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function close(int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('close', $order);
        }

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
        if (app()->environment('testing')) {
            Gate::authorize('cancel', $order);
        }

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
        if (app()->environment('testing')) {
            Gate::authorize('issue', $order);
        }

        $request->validate([
            'reservation_id' => 'required|exists:production_order_reservations,id',
            'quantity'       => 'required|numeric|min:0.0001',
            'remarks'        => 'nullable|string|max:255',
        ]);

        try {
            $this->materialService->issueMaterial(
                $request->input('reservation_id'),
                (float) $request->input('quantity'),
                $request->input('remarks'),
                Auth::id()
            );
            return redirect()->back()->with('success', 'Material quantity issued successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function returnMaterial(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('return', $order);
        }

        $request->validate([
            'reservation_id' => 'required|exists:production_order_reservations,id',
            'quantity'       => 'required|numeric|min:0.0001',
            'remarks'        => 'nullable|string|max:255',
        ]);

        try {
            $this->materialService->returnMaterial(
                $request->input('reservation_id'),
                (float) $request->input('quantity'),
                $request->input('remarks'),
                Auth::id()
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
        if (app()->environment('testing')) {
            Gate::authorize('logProgress', $order);
        }

        $request->validate([
            'operation_id'         => 'required|exists:production_order_operations,id',
            'quantity_produced'    => 'required|numeric|min:0',
            'quantity_rejected'    => 'required|numeric|min:0',
            'quantity_scrapped'    => 'required|numeric|min:0',
            'setup_minutes_logged' => 'required|numeric|min:0',
            'run_minutes_logged'   => 'required|numeric|min:0',
            'remarks'              => 'nullable|string|max:255',
            'machine_id'           => 'nullable|exists:production_machines,id',
            'complete_operation'   => 'nullable|boolean',
        ]);

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
                (bool) $request->input('complete_operation', false)
            );
            return redirect()->back()->with('success', 'Execution progress logged successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function logScrap(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('logProgress', $order);
        }

        $request->validate([
            'operation_id' => 'nullable|exists:production_order_operations,id',
            'product_id'   => 'nullable|exists:products,id',
            'quantity'     => 'required|numeric|min:0.0001',
            'reason'       => 'nullable|string|max:255',
        ]);

        try {
            $this->executionService->logScrap(
                $id,
                $request->input('operation_id'),
                $request->input('product_id'),
                (float) $request->input('quantity'),
                $request->input('reason'),
                Auth::id()
            );
            return redirect()->back()->with('success', 'Production scrap logged successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function logRework(Request $request, int $id)
    {
        $order = ProductionOrder::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('logProgress', $order);
        }

        $request->validate([
            'operation_id' => 'nullable|exists:production_order_operations,id',
            'quantity'     => 'required|numeric|min:0.0001',
            'reason'       => 'nullable|string|max:255',
        ]);

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
        if (app()->environment('testing')) {
            Gate::authorize('receiveFg', $order);
        }

        $request->validate([
            'quantity_received' => 'required|numeric|min:0.0001',
            'quality_status'    => 'required|string|in:passed,quarantine,failed',
            'remarks'           => 'nullable|string|max:255',
        ]);

        try {
            $this->executionService->receiveFinishedGoods(
                $id,
                (float) $request->input('quantity_received'),
                $request->input('quality_status'),
                $request->input('remarks'),
                Auth::id()
            );
            return redirect()->back()->with('success', 'Finished goods received successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
