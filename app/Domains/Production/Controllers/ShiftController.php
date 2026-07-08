<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Requests\StoreShiftRequest;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $query = ProductionShift::where('tenant_id', $tenantId);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search);
            });
        }

        $shifts = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

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

        ProductionShift::create($data);

        return redirect()->route('production.shifts.index')
            ->with('success', 'Shift logged successfully.');
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $shift = ProductionShift::where('tenant_id', $tenantId)->findOrFail($id);

        return view('modules.production.shifts.edit', compact('shift'));
    }

    public function update(StoreShiftRequest $request, int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $shift = ProductionShift::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validated();
        $data['overtime_allowed'] = $request->boolean('overtime_allowed');
        $data['active'] = $request->boolean('active');

        $shift->update($data);

        return redirect()->route('production.shifts.index')
            ->with('success', 'Shift updated successfully.');
    }

    public function destroy(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $shift = ProductionShift::where('tenant_id', $tenantId)->findOrFail($id);

        $shift->delete();

        return redirect()->route('production.shifts.index')
            ->with('success', 'Shift deleted.');
    }
}
