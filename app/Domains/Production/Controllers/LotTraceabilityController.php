<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Batch as InventoryBatch;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Services\LotTraceabilityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Production\Requests\SearchLotTraceabilityRequest;
use Illuminate\Support\Facades\Gate;

class LotTraceabilityController extends Controller
{
    public function __construct(
        private readonly LotTraceabilityService $traceService
    ) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        Gate::authorize('viewAny', ProductionOrder::class);
        $tenantId = require_tenant_id();
        $available = $this->getAvailableEntities($tenantId);

        return view('modules.production.mes.operator.traceability', $available);
    }

    public function search(SearchLotTraceabilityRequest $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        Gate::authorize('viewAny', ProductionOrder::class);
        $tenantId = require_tenant_id();
        $data     = $request->validated();

        $type      = $request->input('type');
        $code      = trim($request->input('code'));
        $direction = $request->input('direction', 'both');
        $id        = null;

        if ($type === 'batch') {
            $batch = ProductionBatch::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('batch_number', $code)->first();
            if ($batch) $id = $batch->id;
        } elseif ($type === 'order') {
            $order = ProductionOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('order_number', $code)->first();
            if ($order) $id = $order->id;
        } elseif ($type === 'serial') {
            $serial = ProductionSerialNumber::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('serial_number', $code)->first();
            if ($serial) $id = $serial->id;
        } elseif ($type === 'lot') {
            // Search by inventory batch number (raw material lot)
            $invBatch = InventoryBatch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('batch_number', $code)
                ->first();
            if ($invBatch) $id = $invBatch->id;
        }

        if (! $id) {
            return redirect()->route('production.mes.traceability.index')
                ->with('error', "Could not find any {$type} with code [{$code}].");
        }

        $genealogy = match ($direction) {
            'forward'  => $this->traceService->forwardTrace($tenantId, $type, $id),
            'backward' => $this->traceService->backwardTrace($tenantId, $type, $id),
            default    => $this->traceService->buildGenealogy($tenantId, $type, $id),
        };

        $available = $this->getAvailableEntities($tenantId);

        return view('modules.production.mes.operator.traceability', array_merge($available, [
            'nodes'             => $genealogy['nodes'],
            'edges'             => $genealogy['edges'],
            'searchedType'      => $type,
            'searchedCode'      => $code,
            'searchedDirection' => $direction,
        ]));
    }

    private function getAvailableEntities(int $tenantId): array
    {
        $orders = ProductionOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('product:id,name,sku')
            ->latest('id')
            ->take(100)
            ->get(['id', 'order_number', 'status', 'product_id', 'quantity_ordered', 'created_at']);

        $batches = ProductionBatch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('product:id,name,sku')
            ->latest('id')
            ->take(100)
            ->get(['id', 'batch_number', 'status', 'product_id', 'planned_quantity', 'actual_quantity', 'created_at']);

        $serials = ProductionSerialNumber::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('product:id,name,sku')
            ->latest('id')
            ->take(100)
            ->get(['id', 'serial_number', 'status', 'product_id', 'created_at']);

        $lots = InventoryBatch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('product:id,name,sku')
            ->latest('id')
            ->take(100)
            ->get(['id', 'batch_number', 'product_id', 'quantity', 'created_at']);

        return compact('orders', 'batches', 'serials', 'lots');
    }

    /**
     * Export genealogy trace as CSV download.
     */
    public function exportCsv(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        Gate::authorize('viewAny', ProductionOrder::class);
        $tenantId = require_tenant_id();

        $request->validate([
            'type' => 'required|string|in:batch,order,serial,lot',
            'code' => 'required|string|max:255',
        ]);

        $type = $request->input('type');
        $code = trim($request->input('code'));
        $id   = null;

        if ($type === 'batch') {
            $id = ProductionBatch::where('tenant_id', $tenantId)->where('batch_number', $code)->value('id');
        } elseif ($type === 'order') {
            $id = ProductionOrder::where('tenant_id', $tenantId)->where('order_number', $code)->value('id');
        } elseif ($type === 'serial') {
            $id = ProductionSerialNumber::where('tenant_id', $tenantId)->where('serial_number', $code)->value('id');
        } elseif ($type === 'lot') {
            $id = InventoryBatch::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('batch_number', $code)->value('id');
        }

        if (! $id) {
            return back()->with('error', "Could not find {$type} [{$code}] for export.");
        }

        $csvContent = $this->traceService->exportCsv($tenantId, $type, $id);
        $filename   = "trace_{$type}_{$code}_" . now()->format('Ymd_His') . '.csv';

        return response($csvContent, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
