<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\CodeService;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionSerialNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScannerController extends Controller
{
    public function __construct(
        private readonly CodeService $codeService
    ) {}

    public function index()
    {
        return view('modules.production.mes.operator.scanner');
    }

    public function scan(Request $request)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = require_tenant_id();
        $request->validate([
            'code'              => 'required|string|max:100',
            'device_identifier' => 'nullable|string|max:100',
        ]);

        $code = trim($request->input('code'));

        try {
            if (!$this->codeService->validate($code, $tenantId)) {
                return redirect()->back()->with('error', "Invalid or unknown scanned code: [{$code}].");
            }

            $entity = $this->codeService->resolveEntity(
                $code,
                $tenantId,
                auth()->id(),
                $request->input('device_identifier')
            );

            // Determine redirect target based on entity type
            if ($entity instanceof ProductionOrder) {
                // Find first ready operation
                $readyOp = $entity->operations()->where('status', 'ready')->first();
                if ($readyOp) {
                    return redirect()->route('production.mes.operator.execution', $readyOp->id)
                        ->with('success', "Order scanned successfully. Directing to Operation #{$readyOp->sequence}.");
                }
                return redirect()->route('production.orders.show', $entity->id)
                    ->with('success', "Order scanned. No ready operations found; directing to order details.");
            }

            if ($entity instanceof ProductionBatch) {
                return redirect()->route('production.orders.show', $entity->production_order_id)
                    ->with('success', "Batch [{$entity->batch_number}] scanned. Directing to associated Production Order.");
            }

            if ($entity instanceof ProductionSerialNumber) {
                return redirect()->route('production.orders.show', $entity->production_order_id)
                    ->with('success', "Serial number [{$entity->serial_number}] scanned. Directing to Production Order.");
            }

            return redirect()->back()->with('success', "Scan resolved: " . get_class($entity) . " #{$entity->id}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
