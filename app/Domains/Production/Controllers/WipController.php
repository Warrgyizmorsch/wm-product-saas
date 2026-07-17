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
    ) {}

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

        $query = ProductionWip::where('tenant_id', $tenantId)
            ->with(['order', 'product', 'currentRoutingOperation', 'currentWorkCenter', 'currentMachine', 'batch']);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($p) use ($search) {
                    $p->where('name', 'like', $search)->orWhere('sku', 'like', $search);
                })->orWhereHas('order', function ($o) use ($search) {
                    $o->where('order_number', 'like', $search);
                });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $wips = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        return view('modules.production.wip.index', compact('wips'));
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
            'reason' => 'required|string|max:255',
        ]);

        try {
            $this->wipService->adjustWip(
                $id,
                (float) $request->input('quantity'),
                $request->input('reason'),
                auth()->id()
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
            'remarks' => 'nullable|string|max:255',
        ]);

        try {
            $this->wipService->convertWipToFinishedGoods(
                $id,
                (int) $request->input('warehouse_id'),
                $request->input('remarks'),
                auth()->id()
            );

            return redirect()->route('production.wip.show', $id)->with('success', 'WIP converted and Finished Goods stock received.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
