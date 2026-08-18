<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Requests\StoreShiftRequest;
use App\Domains\Production\Repositories\WorkCenterRepositoryInterface;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(
        private readonly WorkCenterRepositoryInterface $workCenterRepository
    ) {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $filters = $request->only(['search']);
        $shifts = $this->workCenterRepository->paginateShifts($filters, 15)->withQueryString();

        return view('modules.production.shifts.index', compact('shifts'));
    }

    public function create()
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        return view('modules.production.shifts.create');
    }

    public function store(StoreShiftRequest $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $data = $request->validated();
        $data['tenant_id'] = $tenantId;
        $data['overtime_allowed'] = $request->boolean('overtime_allowed');
        $data['active'] = $request->boolean('active', true);

        $this->workCenterRepository->createShift($data);

        return redirect()->route('production.shifts.index')
            ->with('success', 'Shift logged successfully.');
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $shift = $this->workCenterRepository->findShift($id);
        abort_if(!$shift, 404, 'Shift not found.');

        return view('modules.production.shifts.edit', compact('shift'));
    }

    public function update(StoreShiftRequest $request, int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $shift = $this->workCenterRepository->findShift($id);
        abort_if(!$shift, 404, 'Shift not found.');

        $data = $request->validated();
        $data['overtime_allowed'] = $request->boolean('overtime_allowed');
        $data['active'] = $request->boolean('active');

        $this->workCenterRepository->updateShift($id, $data);

        return redirect()->route('production.shifts.index')
            ->with('success', 'Shift updated successfully.');
    }

    public function destroy(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $shift = $this->workCenterRepository->findShift($id);
        abort_if(!$shift, 404, 'Shift not found.');

        $this->workCenterRepository->deleteShift($id);

        return redirect()->route('production.shifts.index')
            ->with('success', 'Shift deleted.');
    }
}
