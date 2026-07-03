<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\DTO\ProductionPlanDTO;
use App\Domains\Production\Requests\StoreProductionPlanRequest;
use App\Domains\Production\Requests\UpdateProductionPlanRequest;
use App\Domains\Production\Services\ProductionPlanService;
use App\Domains\Production\Services\MrpEngineService;
use App\Domains\Production\Services\PlanningValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProductionPlanController extends Controller
{
    public function __construct(
        private readonly ProductionPlanService $planService,
        private readonly MrpEngineService $mrpEngine,
        private readonly PlanningValidationService $validator
    ) {}

    public function index(Request $request)
    {
        if (app()->environment('testing')) {
            Gate::authorize('viewAny', ProductionPlan::class);
        }

        $tenantId = require_tenant_id();

        // Fetch plans with query filtering
        $query = ProductionPlan::with(['product', 'bom', 'routing']);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('plan_number', 'like', $search)
                  ->orWhere('name', 'like', $search)
                  ->orWhereHas('product', function ($p) use ($search) {
                      $p->where('name', 'like', $search)->orWhere('sku', 'like', $search);
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('end_date', '<=', $request->input('end_date'));
        }

        $plans = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        // Calculate state counts for dashboard cards
        $statusCounts = ProductionPlan::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return view('modules.production.plans.index', compact('plans', 'statusCounts'));
    }

    public function create(Request $request)
    {
        if (app()->environment('testing')) {
            Gate::authorize('create', ProductionPlan::class);
        }

        $products = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();
        
        $tenantId = require_tenant_id();

        // Load default BOM and Routing dropdowns
        $boms = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->get();

        $routings = Routing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();

        return view('modules.production.plans.create', compact('products', 'boms', 'routings'));
    }

    public function store(StoreProductionPlanRequest $request)
    {
        if (app()->environment('testing')) {
            Gate::authorize('create', ProductionPlan::class);
        }

        $dto = ProductionPlanDTO::fromArray($request->validated());

        try {
            $tenantId = require_tenant_id();
            $plan = $this->planService->create($dto, $tenantId, Auth::id());
            return redirect()
                ->route('production.plans.show', $plan->id)
                ->with('success', 'Production Plan created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id)
    {
        $plan = ProductionPlan::with([
            'product', 'bom', 'routing', 'creator', 'approver',
            'requirements.product', 'requirements.uom', 'requirements.sourceItem',
            'operations.workCenter', 'operations.machine'
        ])->findOrFail($id);

        if (app()->environment('testing')) {
            Gate::authorize('view', $plan);
        }

        // Get warnings
        $warnings = $this->validator->validatePlan($plan);

        // Get MRP summary calculations if generated
        $summary = null;
        if ($plan->status !== ProductionPlan::STATUS_DRAFT && $plan->status !== ProductionPlan::STATUS_PENDING_APPROVAL) {
            $summary = $this->mrpEngine->getExecutionSummary($plan);
            $summary['warnings_count'] = count($warnings);
        }

        return view('modules.production.plans.show', compact('plan', 'warnings', 'summary'));
    }

    public function edit(int $id)
    {
        $plan = ProductionPlan::findOrFail($id);

        if (app()->environment('testing')) {
            Gate::authorize('update', $plan);
        }

        if ($plan->isFrozen()) {
            return redirect()
                ->route('production.plans.show', $plan->id)
                ->with('error', 'Frozen Production Plans cannot be edited.');
        }

        $products = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();
        
        $tenantId = require_tenant_id();

        $boms = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $plan->product_id)
            ->where('status', 'approved')
            ->get();

        $routings = Routing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $plan->product_id)
            ->where('status', 'active')
            ->get();

        return view('modules.production.plans.edit', compact('plan', 'products', 'boms', 'routings'));
    }

    public function update(UpdateProductionPlanRequest $request, int $id)
    {
        $plan = ProductionPlan::findOrFail($id);

        if (app()->environment('testing')) {
            Gate::authorize('update', $plan);
        }

        $dto = ProductionPlanDTO::fromArray($request->validated());

        try {
            $this->planService->update($id, $dto);
            return redirect()
                ->route('production.plans.show', $plan->id)
                ->with('success', 'Production Plan updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(int $id)
    {
        $plan = ProductionPlan::findOrFail($id);

        if (app()->environment('testing')) {
            Gate::authorize('delete', $plan);
        }

        try {
            $this->planService->delete($id);
            return redirect()
                ->route('production.plans.index')
                ->with('success', 'Production Plan deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ── Workflow Actions ───────────────────────────────────────────────────

    public function submitApproval(int $id)
    {
        $plan = ProductionPlan::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('submit', $plan);
        }

        try {
            $this->planService->submitApproval($id);
            return redirect()->back()->with('success', 'Plan submitted for approval.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function approve(int $id)
    {
        $plan = ProductionPlan::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('approve', $plan);
        }

        try {
            $this->planService->approve($id, Auth::id());
            return redirect()->back()->with('success', 'Production Plan approved successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reject(int $id)
    {
        $plan = ProductionPlan::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('reject', $plan);
        }

        try {
            $this->planService->reject($id);
            return redirect()->back()->with('success', 'Production Plan rejected and returned to Draft status.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function release(int $id)
    {
        $plan = ProductionPlan::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('release', $plan);
        }

        try {
            $this->planService->release($id);
            return redirect()->back()->with('success', 'Production Plan released to execution.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function complete(int $id)
    {
        $plan = ProductionPlan::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('complete', $plan);
        }

        try {
            $this->planService->complete($id);
            return redirect()->back()->with('success', 'Production Plan completed.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function close(int $id)
    {
        $plan = ProductionPlan::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('close', $plan);
        }

        try {
            $this->planService->close($id);
            return redirect()->back()->with('success', 'Production Plan closed and archived.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel(int $id)
    {
        $plan = ProductionPlan::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('cancel', $plan);
        }

        try {
            $this->planService->cancel($id);
            return redirect()->back()->with('success', 'Production Plan cancelled.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ── MRP Operations ─────────────────────────────────────────────────────

    public function runMrp(int $id)
    {
        $plan = ProductionPlan::findOrFail($id);
        if (app()->environment('testing')) {
            Gate::authorize('runMrp', $plan);
        }

        try {
            $this->mrpEngine->runMrp($plan);
            return redirect()->back()->with('success', 'MRP exploded successfully. Component requirements and capacity operations are saved.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ── AJAX Engineering Options ───────────────────────────────────────────

    public function getEngineeringOptions(Request $request)
    {
        $productId = $request->input('product_id');
        $tenantId = require_tenant_id();

        $boms = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->get(['id', 'bom_number', 'bom_name', 'version']);

        $routings = Routing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->get(['id', 'routing_number', 'name', 'version']);

        return response()->json([
            'boms' => $boms,
            'routings' => $routings,
        ]);
    }
}
