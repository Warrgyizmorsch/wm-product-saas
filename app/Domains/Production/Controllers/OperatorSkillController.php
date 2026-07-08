<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionOperatorSkill;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use App\Models\User;
use App\Domains\Production\Requests\StoreOperatorSkillRequest;
use Illuminate\Http\Request;

class OperatorSkillController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $query = ProductionOperatorSkill::where('tenant_id', $tenantId)
            ->with(['user', 'workCenter', 'machine']);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', $search);
            })->orWhere('skill_code', 'like', $search);
        }

        $skills = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        return view('modules.production.skills.index', compact('skills'));
    }

    public function create()
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        // Get users in this tenant
        $users = User::where('tenant_id', $tenantId)->orderBy('name')->get();
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->orderBy('name')->get();
        $machines = Machine::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('modules.production.skills.create', compact('users', 'workCenters', 'machines'));
    }

    public function store(StoreOperatorSkillRequest $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $data = $request->validated();
        $data['tenant_id'] = $tenantId;
        $data['active'] = $request->boolean('active', true);

        ProductionOperatorSkill::create($data);

        return redirect()->route('production.operator-skills.index')
            ->with('success', 'Operator Skill mapping created successfully.');
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $skill = ProductionOperatorSkill::where('tenant_id', $tenantId)->findOrFail($id);

        $users = User::where('tenant_id', $tenantId)->orderBy('name')->get();
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->orderBy('name')->get();
        $machines = Machine::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('modules.production.skills.edit', compact('skill', 'users', 'workCenters', 'machines'));
    }

    public function update(StoreOperatorSkillRequest $request, int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $skill = ProductionOperatorSkill::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validated();
        $data['active'] = $request->boolean('active');

        $skill->update($data);

        return redirect()->route('production.operator-skills.index')
            ->with('success', 'Operator Skill mapping updated successfully.');
    }

    public function destroy(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $skill = ProductionOperatorSkill::where('tenant_id', $tenantId)->findOrFail($id);

        $skill->delete();

        return redirect()->route('production.operator-skills.index')
            ->with('success', 'Operator Skill mapping deleted.');
    }
}
