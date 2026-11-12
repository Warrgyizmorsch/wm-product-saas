<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\DTO\RoutingDTO;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Repositories\RoutingRepositoryInterface;
use App\Domains\Production\Repositories\WorkCenterRepositoryInterface;
use App\Domains\Production\Repositories\MachineRepositoryInterface;
use App\Domains\Production\Requests\StoreRoutingRequest;
use App\Domains\Production\Requests\UpdateRoutingRequest;
use App\Domains\Production\Services\RoutingCostService;
use App\Domains\Production\Services\RoutingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoutingController extends Controller
{
    public function __construct(
        private readonly RoutingRepositoryInterface $routingRepository,
        private readonly WorkCenterRepositoryInterface $workCenterRepository,
        private readonly MachineRepositoryInterface $machineRepository,
        private readonly RoutingService $routingService,
        private readonly RoutingCostService $costService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Routing::class);

        $filters  = $request->only(['status', 'search']);
        $routings = $this->routingRepository->paginateAll($filters, 15)->withQueryString();

        return view('modules.production.routing.index', compact('routings', 'filters'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Routing::class);

        $workCenters = \App\Domains\Production\Models\WorkCenter::with(['sections' => fn($q) => $q->where('status', 'active')])->where('status', 'active')->get();
        $vendors     = \App\Domains\Inventory\Models\Vendor::where('status', 'active')->get();
        $serviceProducts = Product::where('type', 'service')->get();
        $operationTypes = config('production.operation_types', []);

        // RT-IMPL-20: Duplicate Version opens this create form pre-filled from the source routing — nothing is saved
        // until the user clicks Save. The edit form's pre-fill is reused in "copy mode" (posts to store).
        if ($request->filled('source_routing_id')) {
            $routing = $this->routingRepository->getRoutingWithOperations((int) $request->input('source_routing_id'));
            abort_if(!$routing, 404, 'Source routing not found.');
            Gate::authorize('view', $routing);
            $routing->loadMissing([
                'operations.workCenter', 'operations.section', 'operations.machine', 'operations.manualProduction',
                'operations.primaryOperation', 'operations.alternativeOperations',
            ]);
            $copyMode = true;
            $newVersion = (string) $request->input('version', '');
            [$workCenters, $keptResources] = $this->keepUsedInactiveResources($workCenters, $routing);

            return view('modules.production.routing.edit', compact(
                'routing', 'workCenters', 'vendors', 'serviceProducts', 'operationTypes', 'copyMode', 'newVersion', 'keptResources'
            ));
        }

        return view('modules.production.routing.create', compact(
            'workCenters', 'vendors', 'serviceProducts', 'operationTypes'
        ));
    }

    public function store(StoreRoutingRequest $request): RedirectResponse
    {
        Gate::authorize('create', Routing::class);

        try {
            $tenantId = require_tenant_id();
            $data = $request->validated();
            if (!empty($data['operations']) && is_array($data['operations'])) {
                foreach ($data['operations'] as &$op) {
                    if (isset($op['labor_cost_rate'])) {
                        $op['labor_cost_rate'] = convert_to_base((float) $op['labor_cost_rate']);
                    }
                    if (isset($op['machine_cost_rate'])) {
                        $op['machine_cost_rate'] = convert_to_base((float) $op['machine_cost_rate']);
                    }
                    if (isset($op['subcontract_cost_per_unit'])) {
                        $op['subcontract_cost_per_unit'] = convert_to_base((float) $op['subcontract_cost_per_unit']);
                    }
                }
                unset($op);
            }
            $dto      = RoutingDTO::fromArray($data);
            $routing  = $this->routingService->create($dto, $tenantId, auth()->id() ?: 1);

            // RT-IMPL-20: saved from a Duplicate Version copy → record the source (revision + audit)
            if (!empty($data['source_routing_id'])) {
                $routing = $this->routingService->recordRevisionSource($routing, (int) $data['source_routing_id'], auth()->id() ?: 1);
            }

            $redirect = redirect()
                ->route('production.routing.show', $routing->id)
                ->with('success', "Routing '{$routing->routing_number}' created successfully in draft.");

            if (session()->has('warning')) {
                $redirect->with('warning', session('warning'));
            }

            return $redirect;
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View
    {
        $routing = $this->routingRepository->getRoutingWithOperations($id);
        abort_if(!$routing, 404, 'Routing not found.');

        Gate::authorize('view', $routing);

        $routing->loadMissing([
            'operations.workCenter',
            'operations.section',
            'operations.machine',
            'operations.manualProduction',
            'operations.primaryOperation',
            'operations.alternativeOperations',
        ]);

        // Cost preview (only if active or operations exist)
        $costSummary = null;
        if ($routing->operations->count() > 0) {
            try {
                $costSummary = $this->costService->calculateRoutingCost($routing->id, 1.0);
            } catch (\Exception) {
                $costSummary = null;
            }
        }

        // RT-IMPL-14 (RT-19 rule 7): outdated / not-calculated warnings — numbers compared, nothing recalculated
        $costStatus = app(\App\Domains\Production\Services\RoutingCostStatusService::class)->check($routing);

        // RT-IMPL-06: linked BOMs and the effect of Retire / Cancel, shown in the confirmations
        $bomImpact = app(\App\Domains\Production\Services\RoutingLifecycleService::class)->bomImpact($routing);

        return view('modules.production.routing.show', compact('routing', 'costSummary', 'costStatus', 'bomImpact'));
    }

    public function edit(int $id): View
    {
        $routing = $this->routingRepository->getRoutingWithOperations($id);
        abort_if(!$routing, 404, 'Routing not found.');

        Gate::authorize('update', $routing);

        abort_if($routing->isReadOnly(), 403, 'This routing is read-only. Only draft routings can be edited.');

        $routing->loadMissing([
            'operations.workCenter',
            'operations.section',
            'operations.machine',
            'operations.manualProduction',
            'operations.primaryOperation',
            'operations.alternativeOperations',
        ]);

        $workCenters    = \App\Domains\Production\Models\WorkCenter::with(['sections' => fn($q) => $q->where('status', 'active')])->where('status', 'active')->get();
        $vendors        = \App\Domains\Inventory\Models\Vendor::where('status', 'active')->get();
        $serviceProducts = Product::where('type', 'service')->get();

        $operationTypes = config('production.operation_types', []);

        [$workCenters, $keptResources] = $this->keepUsedInactiveResources($workCenters, $routing);

        // RT-IMPL-09: operations BOMs still use — the form warns before removing one
        $operationBomUsage = [];
        $alloc = \App\Domains\Production\Models\RoutingOperationMaterial::whereIn('routing_operation_id', $routing->operations->pluck('id'))
            ->get(['routing_operation_id', 'bom_id'])->groupBy('routing_operation_id');
        $bomNumbers = \App\Domains\Production\Models\ProductionBom::whereIn('id', $alloc->flatten()->pluck('bom_id')->filter()->unique())->pluck('bom_number', 'id');
        foreach ($alloc as $opId => $rows) {
            $operationBomUsage[$opId] = 'BOM ' . $rows->pluck('bom_id')->unique()->map(fn($id) => $bomNumbers[$id] ?? ('#' . $id))->implode(', ') . ' (' . $rows->count() . ' material(s))';
        }

        return view('modules.production.routing.edit', compact(
            'routing', 'workCenters', 'vendors', 'serviceProducts', 'operationTypes', 'operationBomUsage', 'keptResources'
        ));
    }

    public function update(UpdateRoutingRequest $request, int $id): RedirectResponse
    {
        $routing = $this->routingRepository->find($id);
        abort_if(!$routing, 404, 'Routing not found.');

        Gate::authorize('update', $routing);

        try {
            $data = $request->validated();
            if (!empty($data['operations']) && is_array($data['operations'])) {
                foreach ($data['operations'] as &$op) {
                    if (isset($op['labor_cost_rate'])) {
                        $op['labor_cost_rate'] = convert_to_base((float) $op['labor_cost_rate']);
                    }
                    if (isset($op['machine_cost_rate'])) {
                        $op['machine_cost_rate'] = convert_to_base((float) $op['machine_cost_rate']);
                    }
                    if (isset($op['subcontract_cost_per_unit'])) {
                        $op['subcontract_cost_per_unit'] = convert_to_base((float) $op['subcontract_cost_per_unit']);
                    }
                }
                unset($op);
            }
            $dto     = RoutingDTO::fromArray($data);
            $routing = $this->routingService->update($id, $dto);

            $redirect = redirect()
                ->route('production.routing.show', $routing->id)
                ->with('success', 'Routing updated successfully.');

            if (session()->has('warning')) {
                $redirect->with('warning', session('warning'));
            }

            return $redirect;
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $routing = $this->routingRepository->find($id);
        abort_if(!$routing, 404, 'Routing not found.');

        Gate::authorize('delete', $routing);

        abort_if(!$routing->isDraft(), 403, 'Only draft routings can be deleted.');

        $this->routingRepository->delete($id);

        return redirect()
            ->route('production.routing.index')
            ->with('success', 'Draft routing deleted successfully.');
    }

    public function submitApproval(int $id): RedirectResponse
    {
        $routing = $this->routingRepository->find($id);
        abort_if(!$routing, 404, 'Routing not found.');

        Gate::authorize('submit', $routing);

        try {
            $this->routingService->submitApproval($id, auth()->id() ?: 1);
            return redirect()->back()->with('success', 'Routing submitted for approval.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function approve(int $id): RedirectResponse
    {
        $routing = $this->routingRepository->find($id);
        abort_if(!$routing, 404, 'Routing not found.');

        Gate::authorize('approve', $routing);

        try {
            $this->routingService->approve($id, auth()->id() ?: 1);
            return redirect()->back()
                ->with('success', 'Routing approved. Older versions stay as they are — retire them with the Retire action when replaced.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $routing = $this->routingRepository->find($id);
        abort_if(!$routing, 404, 'Routing not found.');

        Gate::authorize('reject', $routing);

        $request->validate(['comments' => 'nullable|string|max:1000']);
        try {
            $this->routingService->reject($id, auth()->id() ?: 1, $request->input('comments'));
            return redirect()->back()->with('success', 'Routing rejected and returned to draft.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $routing = $this->routingRepository->find($id);
        abort_if(!$routing, 404, 'Routing not found.');

        Gate::authorize('cancel', $routing);

        $request->validate(['comments' => 'nullable|string|max:1000']);
        try {
            $this->routingService->cancel($id, auth()->id() ?: 1, $request->input('comments'));
            return redirect()->back()->with('success', 'Routing cancelled.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function duplicateVersion(Request $request, int $id): RedirectResponse
    {
        $routing = $this->routingRepository->find($id);
        abort_if(!$routing, 404, 'Routing not found.');

        Gate::authorize('duplicate', $routing);

        $request->validate(['new_version' => 'required|string|max:50']);
        try {
            // RT-IMPL-20: nothing is saved here — check the version, then open the pre-filled create form
            $this->routingService->checkRoutingConflicts($routing->name, $request->input('new_version'), $routing->tenant_id);

            return redirect()
                ->route('production.routing.create', ['source_routing_id' => $routing->id, 'version' => $request->input('new_version')])
                ->with('info', "Copy of {$routing->routing_number} v{$routing->version} — nothing is saved until you click Save.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * RT-IMPL-02: retire an active / scheduled routing → historical (manual; approval never retires older versions).
     */
    public function retire(Request $request, int $id): RedirectResponse
    {
        $routing = $this->routingRepository->find($id);
        abort_if(!$routing, 404, 'Routing not found.');

        Gate::authorize('retire', $routing);

        $request->validate(['comments' => 'nullable|string|max:1000']);
        try {
            $this->routingService->retire($id, auth()->id() ?: 1, $request->input('comments'));
            return redirect()->back()->with('success', "Routing '{$routing->routing_number}' retired — it is now historical.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * RT-IMPL-18 (RT-29 rule 3): the routing's own work center / section / machine / batch stays in its row's
     * dropdown even when it is inactive or deleted — tagged, never cleared silently. Names are changed in memory
     * only (never saved). The save is refused until another resource is chosen (shared validator).
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array<int, array{machine?: array, batch?: array}>}
     */
    private function keepUsedInactiveResources($workCenters, Routing $routing): array
    {
        $tag = fn($model) => (method_exists($model, 'trashed') && $model->trashed()) ? 'Deleted' : 'Inactive';
        $ops = $routing->operations->where('is_external', false);
        $kept = [];

        foreach ($ops->pluck('work_center_id')->filter()->unique() as $wcId) {
            $wc = $workCenters->firstWhere('id', $wcId);
            if (!$wc) {
                $wc = \App\Domains\Production\Models\WorkCenter::withTrashed()->find($wcId);
                if (!$wc) {
                    continue;
                }
                $wc->name = $wc->name . ' — ' . $tag($wc);
                $wc->setRelation('sections', collect());
                $workCenters->push($wc);
            }
            foreach ($ops->where('work_center_id', $wcId)->pluck('section_id')->filter()->unique() as $secId) {
                if (!$wc->sections->firstWhere('id', $secId)) {
                    $sec = \App\Domains\Production\Models\WorkCenterSection::withTrashed()->find($secId);
                    if ($sec) {
                        $sec->name = $sec->name . ' — ' . $tag($sec);
                        $wc->setRelation('sections', $wc->sections->push($sec));
                    }
                }
            }
        }

        foreach ($ops as $op) {
            if ($op->machine_id) {
                $m = \App\Domains\Production\Models\Machine::withTrashed()->find($op->machine_id);
                if ($m && ($m->trashed() || $m->status !== \App\Domains\Production\Models\Machine::STATUS_ACTIVE)) {
                    $kept[$op->id]['machine'] = ['id' => $m->id, 'label' => "{$m->name} ({$m->code}) — " . ($m->trashed() ? 'Deleted' : 'Inactive')];
                }
            }
            if ($op->section_manual_production_id) {
                $b = \App\Domains\Production\Models\SectionManualProduction::withTrashed()->find($op->section_manual_production_id);
                if ($b && ($b->trashed() || !$b->active)) {
                    $kept[$op->id]['batch'] = ['id' => $b->id, 'label' => "{$b->batch_name} — " . ($b->trashed() ? 'Deleted' : 'Inactive')];
                }
            }
        }

        return [$workCenters, $kept];
    }

    public function getOperationsForAjax(int $id): \Illuminate\Http\JsonResponse
    {
        $routing = \App\Domains\Production\Models\Routing::with([
            'operations' => function($q) {
                $q->orderBy('sequence', 'asc');
            },
            'operations.workCenter',
            'operations.machine'
        ])->find($id);

        if (!$routing) {
            return response()->json(['error' => 'Routing not found'], 404);
        }

        $ops = $routing->operations->map(function($op) {
            return [
                'sequence' => $op->sequence,
                'name' => $op->name,
                'operation_type' => $op->operation_type,
                'work_center_name' => $op->workCenter ? $op->workCenter->name : 'N/A',
                'machine_name' => $op->machine ? $op->machine->name : 'N/A',
                'setup_time_minutes' => $op->setup_time_minutes,
                'setup_time_per_piece_minutes' => $op->setup_time_per_piece_minutes,
                'processing_time_minutes' => $op->processing_time_minutes,
                'expected_yield_percentage' => $op->expected_yield_percentage,
            ];
        });

        return response()->json($ops);
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $action = $request->input('action');
        $ids = $request->input('ids');

        if (empty($ids) || !is_array($ids)) {
            return redirect()
                ->back()
                ->with('error', __('production.no_routings_selected'));
        }

        $tenantId = require_tenant_id();
        $routings = Routing::whereIn('id', $ids)
            ->where('tenant_id', $tenantId)
            ->get();

        $successCount = 0;
        $failedCount = 0;

        switch ($action) {
            case 'delete':
                foreach ($routings as $routing) {
                    if (Gate::allows('delete', $routing)) {
                        $this->routingRepository->delete($routing->id);
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "deleted";
                break;

            case 'submit_approval':
                foreach ($routings as $routing) {
                    if (Gate::allows('submit', $routing)) {
                        try {
                            $this->routingService->submitApproval($routing->id, auth()->id() ?: 1);
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "submitted for approval";
                break;

            case 'approve':
                foreach ($routings as $routing) {
                    if (Gate::allows('approve', $routing)) {
                        try {
                            $this->routingService->approve($routing->id, auth()->id() ?: 1);
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "approved";
                break;

            case 'cancel':
                foreach ($routings as $routing) {
                    if (Gate::allows('cancel', $routing)) {
                        try {
                            $this->routingService->cancel($routing->id, auth()->id() ?: 1, 'Bulk cancelled.');
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "cancelled";
                break;

            default:
                return redirect()
                    ->back()
                    ->with('error', 'Invalid action.');
        }

        if ($failedCount > 0) {
            if ($successCount > 0) {
                return redirect()
                    ->route('production.routing.index')
                    ->with('success', "Successfully {$messagePrefix} {$successCount} routing(s). {$failedCount} routing(s) could not be processed due to status or permission rules.");
            }
            return redirect()
                ->route('production.routing.index')
                ->with('error', "No routings could be {$messagePrefix}. Check status restrictions and your permissions.");
        }

        return redirect()
            ->route('production.routing.index')
            ->with('success', "Successfully {$messagePrefix} {$successCount} routing(s).");
    }
}
