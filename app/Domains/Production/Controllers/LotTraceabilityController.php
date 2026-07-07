<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Services\LotTraceabilityService;
use Illuminate\Http\Request;

class LotTraceabilityController extends Controller
{
    public function __construct(
        private readonly LotTraceabilityService $traceService
    ) {}

    public function index(Request $request)
    {
        return view('modules.production.mes.operator.traceability');
    }

    public function search(Request $request)
    {
        $tenantId = require_tenant_id();
        $request->validate([
            'type' => 'required|string|in:batch,serial,order',
            'code' => 'required|string|max:100',
        ]);

        $type = $request->input('type');
        $code = trim($request->input('code'));
        $id = null;

        if ($type === 'batch') {
            $batch = ProductionBatch::where('tenant_id', $tenantId)->where('batch_number', $code)->first();
            if ($batch) $id = $batch->id;
        } elseif ($type === 'order') {
            $order = ProductionOrder::where('tenant_id', $tenantId)->where('order_number', $code)->first();
            if ($order) $id = $order->id;
        } elseif ($type === 'serial') {
            $serial = ProductionSerialNumber::where('tenant_id', $tenantId)->where('serial_number', $code)->first();
            if ($serial) $id = $serial->id;
        }

        if (!$id) {
            return redirect()->route('production.mes.traceability.index')
                ->with('error', "Could not find any {$type} with code [{$code}].");
        }

        $genealogy = $this->traceService->buildGenealogy($tenantId, $type, $id);

        return view('modules.production.mes.operator.traceability', [
            'nodes' => $genealogy['nodes'],
            'edges' => $genealogy['edges'],
            'searchedType' => $type,
            'searchedCode' => $code,
        ]);
    }
}
