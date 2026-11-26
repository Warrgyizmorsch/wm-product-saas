<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\RoutingOperationQualityPlan;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Requests\StoreQualityPlanRequest;
use App\Domains\Production\Services\QualityPlanService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Quality Plans (docs/production_quality_rules.md QR-01..QR-13): thin controller over QualityPlanService.
 */
class QualityPlanController extends Controller
{
    public function __construct(private readonly QualityPlanService $service)
    {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', ProductionQualityPlan::class);
        $tenantId = require_tenant_id();

        $query = ProductionQualityPlan::where('tenant_id', $tenantId)
            ->with(['product', 'section.workCenter', 'creator'])
            ->withCount('parameters');

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)->orWhere('plan_number', 'like', $search);
            });
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $plans = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('modules.production.quality.plans.index', compact('plans'));
    }

    public function create()
    {
        Gate::authorize('create', ProductionQualityPlan::class);

        return view('modules.production.quality.plans.create', $this->formData() + ['plan' => null]);
    }

    public function store(StoreQualityPlanRequest $request): RedirectResponse
    {
        Gate::authorize('create', ProductionQualityPlan::class);

        try {
            $plan = $this->service->create(require_tenant_id(), $request->validated(), (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('production.quality-plans.show', $plan->id)
            ->with('success', 'Quality plan saved as draft (version 1).');
    }

    public function show(int $id)
    {
        $plan = $this->findPlan($id, true);
        Gate::authorize('view', $plan);

        $plan->load(['product', 'section.workCenter', 'parameters', 'creator', 'approver', 'submitter', 'approvals.user']);

        $versions = $plan->versions()->with('approver')->get();
        $approvedVersion = $plan->latestApprovedVersion();
        $openVersion = $this->service->openNewerVersion($plan);

        // Where used: routing operations linked to ANY version of this plan (flag the ones on an older version)
        $familyIds = $versions->pluck('id')->all();
        $routingLinks = RoutingOperationQualityPlan::whereIn('quality_plan_id', $familyIds ?: [$plan->id])
            ->with(['routingOperation.routing', 'plan'])
            ->get()
            ->filter(fn ($link) => $link->routingOperation && $link->routingOperation->routing);

        $inspectionCount = ProductionQualityInspection::where('tenant_id', $plan->tenant_id)
            ->where(function ($q) use ($plan) {
                $q->where('quality_plan_id', $plan->id)
                    ->orWhereHas('planLinks', fn ($l) => $l->where('quality_plan_id', $plan->id));
            })
            ->count();

        return view('modules.production.quality.plans.show', compact(
            'plan', 'versions', 'approvedVersion', 'openVersion', 'routingLinks', 'inspectionCount'
        ));
    }

    public function edit(int $id)
    {
        $plan = $this->findPlan($id);
        Gate::authorize('update', $plan);

        if ($plan->isApproved() && ($open = $this->service->openNewerVersion($plan))) {
            return redirect()->route('production.quality-plans.edit', $open->id)
                ->with('info', "Version {$open->version} of this plan is already open — continue there.");
        }

        $plan->load('parameters');

        return view('modules.production.quality.plans.edit', $this->formData() + ['plan' => $plan]);
    }

    public function update(StoreQualityPlanRequest $request, int $id): RedirectResponse
    {
        $plan = $this->findPlan($id);
        Gate::authorize('update', $plan);

        try {
            $saved = $this->service->update($plan, $request->validated(), (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = $saved->id === $plan->id
            ? 'Draft updated.'
            : "Version {$saved->version} created as a draft. Version {$plan->version} stays approved until the new version is approved.";

        return redirect()->route('production.quality-plans.show', $saved->id)->with('success', $message);
    }

    public function destroy(int $id): RedirectResponse
    {
        $plan = $this->findPlan($id);
        Gate::authorize('delete', $plan);

        try {
            $this->service->delete($plan, (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('production.quality-plans.index')->with('success', 'Quality plan permanently deleted.');
    }

    public function submit(int $id): RedirectResponse
    {
        $plan = $this->findPlan($id);
        Gate::authorize('submit', $plan);

        return $this->runAction(fn () => $this->service->submit($plan, (int) auth()->id()), 'Quality plan submitted for approval.');
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $plan = $this->findPlan($id);
        Gate::authorize('approve', $plan);

        return $this->runAction(
            fn () => $this->service->approve($plan, (int) auth()->id(), $request->input('comments')),
            "Version {$plan->version} approved. Older approved versions of this plan are now historical."
        );
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $plan = $this->findPlan($id);
        Gate::authorize('reject', $plan);

        $request->validate(['comments' => 'required|string|max:1000']);

        return $this->runAction(fn () => $this->service->reject($plan, (int) auth()->id(), $request->input('comments')), 'Quality plan rejected and returned to draft.');
    }

    public function retire(Request $request, int $id): RedirectResponse
    {
        $plan = $this->findPlan($id);
        Gate::authorize('retire', $plan);

        return $this->runAction(fn () => $this->service->markHistorical($plan, (int) auth()->id(), $request->input('comments')), 'Quality plan marked historical.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function findPlan(int $id, bool $withTrashed = false): ProductionQualityPlan
    {
        return ProductionQualityPlan::query()
            ->when($withTrashed, fn ($q) => $q->withTrashed())
            ->where('tenant_id', require_tenant_id())
            ->findOrFail($id);
    }

    private function runAction(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }

    private function formData(): array
    {
        $tenantId = require_tenant_id();

        // QR-03: traded and produced goods, never services
        $products = Product::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('item_type')->orWhereRaw('LOWER(item_type) != ?', ['service']);
            })
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'type']);

        $sections = WorkCenterSection::where('tenant_id', $tenantId)
            ->with('workCenter:id,name')
            ->orderBy('name')
            ->get();

        // Unit of numeric checks is picked from the UOM master
        $uoms = \App\Domains\Inventory\Models\Uom::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        // QM-14: products that already have a plan in use (draft / pending / approved) can't get a second one
        $productPlanHolders = $this->service->productPlanHolders($tenantId);

        return compact('products', 'sections', 'uoms', 'productPlanHolders');
    }
}
