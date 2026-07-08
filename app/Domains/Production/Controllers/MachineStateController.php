<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\MachineStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MachineStateController extends Controller
{
    public function __construct(
        private readonly MachineStateService $stateService
    ) {}

    public function overrideState(Request $request)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = require_tenant_id();
        $request->validate([
            'machine_id' => 'required|integer',
            'state'      => 'required|string|in:Idle,Running,Setup,Waiting Material,Waiting Operator,Maintenance,Breakdown,Offline,Unknown',
            'reason'     => 'nullable|string|max:255',
            'remarks'    => 'nullable|string|max:1000',
        ]);

        try {
            $this->stateService->transitionState(
                $tenantId,
                (int)$request->input('machine_id'),
                $request->input('state'),
                $request->input('reason'),
                auth()->id(),
                $request->input('remarks')
            );

            return redirect()->back()->with('success', 'Machine state overridden successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
