<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Repositories\MachineRepositoryInterface;

class MachineDashboardController extends Controller
{
    public function __construct(
        private readonly MachineRepositoryInterface $machineRepository
    ) {
    }

    public function index()
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $machines = $this->machineRepository->getDashboardMachines($tenantId);

        return view('modules.production.mes.machine-dashboard', compact('machines'));
    }

    public function show(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $details = $this->machineRepository->getMachineDashboardDetails($id);
        $machine = $details['machine'];

        $stateHistories = \App\Domains\Production\Models\ProductionMachineStateHistory::with('changer')
            ->where('machine_id', $machine->id)
            ->orderByDesc('started_at')
            ->take(10)
            ->get();

        $downtimes = \App\Domains\Production\Models\ProductionMachineDowntime::with(['creator', 'approver', 'order'])
            ->where('machine_id', $machine->id)
            ->orderByDesc('id')
            ->take(10)
            ->get();

        return view('modules.production.mes.machine-detail', array_merge($details, compact('stateHistories', 'downtimes')));
    }
}
