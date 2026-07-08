<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\MachineStateService;
use App\Domains\Production\Requests\OverrideMachineStateRequest;

class MachineStateController extends Controller
{
    public function __construct(
        private readonly MachineStateService $stateService
    ) {}

    public function overrideState(OverrideMachineStateRequest $request)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = require_tenant_id();
        $data = $request->validated();

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
