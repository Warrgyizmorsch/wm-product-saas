<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlanParameter;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Requests\StoreQualityPlanRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QualityPlanController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.quality.manage'), 403);
        $tenantId = require_tenant_id();

        $query = ProductionQualityPlan::where('tenant_id', $tenantId)
            ->with(['product', 'workCenter', 'creator']);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where('name', 'like', $search);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $plans = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        return view('modules.production.quality.plans.index', compact('plans'));
    }

    public function create()
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.quality.manage'), 403);
        $tenantId = require_tenant_id();

        $products = Product::where('tenant_id', $tenantId)->orderBy('name')->get();
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('modules.production.quality.plans.create', compact('products', 'workCenters'));
    }

    public function store(StoreQualityPlanRequest $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.quality.manage'), 403);
        $tenantId = require_tenant_id();

        $data = $request->validated();
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = auth()->id();
        $data['status'] = $request->input('status', 'draft');

        if ($data['status'] === 'approved') {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        DB::transaction(function () use ($tenantId, $data) {
            $plan = ProductionQualityPlan::create($data);

            foreach ($data['parameters'] as $param) {
                $param['tenant_id'] = $tenantId;
                $param['quality_plan_id'] = $plan->id;
                $param['is_mandatory'] = filter_var($param['is_mandatory'] ?? false, FILTER_VALIDATE_BOOLEAN);
                
                ProductionQualityPlanParameter::create($param);
            }
        });

        return redirect()->route('production.quality-plans.index')
            ->with('success', 'Quality Plan created successfully.');
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.quality.manage'), 403);
        $tenantId = require_tenant_id();
        $plan = ProductionQualityPlan::where('tenant_id', $tenantId)
            ->with('parameters')
            ->findOrFail($id);

        $products = Product::where('tenant_id', $tenantId)->orderBy('name')->get();
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('modules.production.quality.plans.edit', compact('plan', 'products', 'workCenters'));
    }

    public function update(StoreQualityPlanRequest $request, int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.quality.manage'), 403);
        $tenantId = require_tenant_id();
        $plan = ProductionQualityPlan::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validated();

        if ($request->input('status') === 'approved' && $plan->status !== 'approved') {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        DB::transaction(function () use ($tenantId, $plan, $data) {
            $plan->update($data);

            // Rebuild parameters
            ProductionQualityPlanParameter::where('quality_plan_id', $plan->id)->delete();

            foreach ($data['parameters'] as $param) {
                $param['tenant_id'] = $tenantId;
                $param['quality_plan_id'] = $plan->id;
                $param['is_mandatory'] = filter_var($param['is_mandatory'] ?? false, FILTER_VALIDATE_BOOLEAN);

                ProductionQualityPlanParameter::create($param);
            }
        });

        return redirect()->route('production.quality-plans.index')
            ->with('success', 'Quality Plan updated successfully.');
    }

    public function destroy(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.quality.manage'), 403);
        $tenantId = require_tenant_id();
        $plan = ProductionQualityPlan::where('tenant_id', $tenantId)->findOrFail($id);

        DB::transaction(function () use ($plan) {
            ProductionQualityPlanParameter::where('quality_plan_id', $plan->id)->delete();
            $plan->delete();
        });

        return redirect()->route('production.quality-plans.index')
            ->with('success', 'Quality Plan deleted.');
    }
}
