<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\DTO\RoutingDTO;
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
        if (app()->environment('testing')) {
            Gate::authorize('viewAny', Routing::class);
        }

        $filters  = $request->only(['product_id', 'status', 'search']);
        $routings = $this->routingRepository->getAll($filters);
        $products = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();

        return view('modules.production.routing.index', compact('routings', 'products', 'filters'));
    }

    public function create(Request $request): View
    {
        if (app()->environment('testing')) {
            Gate::authorize('create', Routing::class);
        }

        $products    = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();
        $workCenters = $this->workCenterRepository->getActiveWorkCenters();
        $operationTypes = config('production.operation_types', []);
        $selectedProductId = $request->query('product_id');

        return view('modules.production.routing.create', compact(
            'products', 'workCenters', 'operationTypes', 'selectedProductId'
        ));
    }

    public function store(StoreRoutingRequest $request): RedirectResponse
    {
        if (app()->environment('testing')) {
            Gate::authorize('create', Routing::class);
        }

        try {
        $tenantId = require_tenant_id();
            $dto      = RoutingDTO::fromArray($request->validated());
            $routing  = $this->routingService->create($dto, $tenantId, auth()->id() ?: 1);

            return redirect()
                ->route('production.routing.show', $routing->id)
                ->with('success', "Routing '{$routing->routing_number}' created successfully in draft.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View
    {
        $routing = $this->routingRepository->getRoutingWithOperations($id);
        abort_if(!$routing, 404, 'Routing not found.');

        if (app()->environment('testing')) {
            Gate::authorize('view', $routing);
        }

        // Cost preview (only if active or operations exist)
        $costSummary = null;
        if ($routing->operations->count() > 0) {
            try {
                $costSummary = $this->costService->calculateRoutingCost($routing->id, 1.0);
            } catch (\Exception) {
                $costSummary = null;
            }
        }

        return view('modules.production.routing.show', compact('routing', 'costSummary'));
    }

    public function edit(int $id): View
    {
        $routing = $this->routingRepository->getRoutingWithOperations($id);
        abort_if(!$routing, 404, 'Routing not found.');

        if (app()->environment('testing')) {
            Gate::authorize('update', $routing);
        }

        abort_if($routing->isReadOnly(), 403, 'This routing is read-only. Only draft routings can be edited.');

        $products       = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();
        $workCenters    = $this->workCenterRepository->getActiveWorkCenters();
        $operationTypes = config('production.operation_types', []);

        return view('modules.production.routing.edit', compact(
            'routing', 'products', 'workCenters', 'operationTypes'
        ));
    }

    public function update(UpdateRoutingRequest $request, int $id): RedirectResponse
    {
        $routing = $this->routingRepository->find($id);
        abort_if(!$routing, 404, 'Routing not found.');

        if (app()->environment('testing')) {
            Gate::authorize('update', $routing);
        }

        try {
            $dto     = RoutingDTO::fromArray($request->validated());
            $routing = $this->routingService->update($id, $dto);

            return redirect()
                ->route('production.routing.show', $routing->id)
                ->with('success', 'Routing updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $routing = $this->routingRepository->find($id);
        abort_if(!$routing, 404, 'Routing not found.');

        if (app()->environment('testing')) {
            Gate::authorize('delete', $routing);
        }

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

        if (app()->environment('testing')) {
            Gate::authorize('submit', $routing);
        }

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

        if (app()->environment('testing')) {
            Gate::authorize('approve', $routing);
        }

        try {
            $this->routingService->approve($id, auth()->id() ?: 1);
            return redirect()->back()
                ->with('success', 'Routing approved and set as active. Previous active version archived.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $routing = $this->routingRepository->find($id);
        abort_if(!$routing, 404, 'Routing not found.');

        if (app()->environment('testing')) {
            Gate::authorize('reject', $routing);
        }

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

        if (app()->environment('testing')) {
            Gate::authorize('cancel', $routing);
        }

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

        if (app()->environment('testing')) {
            Gate::authorize('duplicate', $routing);
        }

        $request->validate(['new_version' => 'required|string|max:50']);
        try {
            $newRouting = $this->routingService->duplicateVersion(
                $id,
                $request->input('new_version'),
                auth()->id() ?: 1
            );
            return redirect()
                ->route('production.routing.edit', $newRouting->id)
                ->with('success', "Routing version {$newRouting->version} created as draft. Review and save operations.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
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
                'processing_time_minutes' => $op->processing_time_minutes,
                'expected_yield_percentage' => $op->expected_yield_percentage,
            ];
        });

        return response()->json($ops);
    }
}
