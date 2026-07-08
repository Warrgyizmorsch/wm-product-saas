<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\DowntimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DowntimeController extends Controller
{
    public function __construct(
        private readonly DowntimeService $downtimeService
    ) {}

    public function start(Request $request)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = require_tenant_id();
        $request->validate([
            'machine_id'                     => 'required|integer',
            'category'                       => 'required|string|in:Breakdown,Preventive Maintenance,Corrective Maintenance,Setup,Tool Change,Power Failure,Material Shortage,Operator Shortage,Quality Hold,Engineering Hold,Cleaning,Calibration,Other',
            'reason'                         => 'required|string|max:255',
            'production_order_id'            => 'nullable|integer',
            'production_order_operation_id'  => 'nullable|integer',
            'remarks'                        => 'nullable|string|max:1000',
        ]);

        try {
            $this->downtimeService->startDowntime(
                $tenantId,
                (int)$request->input('machine_id'),
                $request->input('category'),
                $request->input('reason'),
                auth()->id(),
                $request->only(['production_order_id', 'production_order_operation_id', 'remarks'])
            );

            return redirect()->back()->with('success', 'Downtime tracking started.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function end(Request $request, int $id)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = require_tenant_id();
        $request->validate([
            'remarks' => 'nullable|string|max:1000',
        ]);

        try {
            $this->downtimeService->endDowntime(
                $tenantId,
                $id,
                auth()->id(),
                $request->input('remarks')
            );

            return redirect()->back()->with('success', 'Downtime event closed.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
