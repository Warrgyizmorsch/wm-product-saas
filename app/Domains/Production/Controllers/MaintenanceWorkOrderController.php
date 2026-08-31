<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Repositories\MachineRepositoryInterface;
use App\Domains\Production\Repositories\MaintenanceRepositoryInterface;
use App\Domains\Production\Services\MaintenanceSpareService;
use App\Domains\Production\Services\MaintenanceWorkOrderService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceWorkOrderController extends Controller
{
    public function __construct(
        private readonly MaintenanceRepositoryInterface $repository,
        private readonly MachineRepositoryInterface $machineRepository,
        private readonly MaintenanceWorkOrderService $service,
        private readonly MaintenanceSpareService $spareService
    ) {}

    public function index(Request $request): View
    {
        $tenantId   = require_tenant_id();
        \Illuminate\Support\Facades\Gate::authorize('viewAny', \App\Domains\Production\Models\Machine::class);
        $filters    = $request->only(['machine_id', 'status', 'type', 'search']);
        $workOrders = $this->repository->paginateWorkOrders($tenantId, $filters, 15)->withQueryString();
        $machines   = $this->machineRepository->getAll();

        return view('modules.production.maintenance.work-orders.index', compact('workOrders', 'machines', 'filters'));
    }

    public function create(): View
    {
        \Illuminate\Support\Facades\Gate::authorize('create', \App\Domains\Production\Models\Machine::class);
        $tenantId    = require_tenant_id();
        $machines    = $this->machineRepository->getAll();
        $technicians = User::where('tenant_id', $tenantId)->get();

        return view('modules.production.maintenance.work-orders.create', compact('machines', 'technicians'));
    }

    public function store(Request $request): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('create', \App\Domains\Production\Models\Machine::class);
        $tenantId  = require_tenant_id();
        $validated = $request->validate([
            'machine_id'             => ['required', 'integer'],
            'type'                   => ['required', 'in:preventive,breakdown,calibration'],
            'priority'               => ['required', 'in:low,medium,high,critical'],
            'assigned_technician_id' => ['nullable', 'integer'],
            'planned_start'          => ['nullable', 'date'],
            'planned_end'            => ['nullable', 'date'],
            'problem_description'    => ['required', 'string'],
        ]);

        try {
            $wo = $this->service->createWorkOrder($tenantId, $validated, auth()->id());

            return redirect()
                ->route('production.maintenance.work-orders.show', $wo->id)
                ->with('success', "Work Order '{$wo->work_order_number}' created successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View
    {
        \Illuminate\Support\Facades\Gate::authorize('viewAny', \App\Domains\Production\Models\Machine::class);
        $tenantId = require_tenant_id();
        $workOrder = $this->repository->findWorkOrder($id, $tenantId);
        abort_if(!$workOrder, 404, 'Work Order not found.');

        $technicians = User::where('tenant_id', $tenantId)->get();
        $products    = Product::where('tenant_id', $tenantId)->get();
        $warehouses  = Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.production.maintenance.work-orders.show', compact('workOrder', 'technicians', 'products', 'warehouses'));
    }

    public function edit(int $id): View
    {
        return $this->show($id);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        return $this->schedule($request, $id);
    }

    public function destroy(int $id): RedirectResponse
    {
        return $this->cancel(request(), $id);
    }

    public function schedule(Request $request, int $id): RedirectResponse
    {
        $tenantId  = require_tenant_id();
        $validated = $request->validate([
            'planned_start'          => ['required', 'date'],
            'planned_end'            => ['required', 'date', 'after:planned_start'],
            'assigned_technician_id' => ['nullable', 'integer'],
        ]);

        try {
            $wo = $this->service->scheduleWorkOrder(
                $id,
                $tenantId,
                $validated['planned_start'],
                $validated['planned_end'],
                $validated['assigned_technician_id'] ?? null,
                auth()->id()
            );

            return redirect()
                ->route('production.maintenance.work-orders.show', $wo->id)
                ->with('success', "Work Order scheduled successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function start(int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        try {
            $wo = $this->service->startWorkOrder($id, $tenantId, auth()->id());

            return redirect()
                ->route('production.maintenance.work-orders.show', $wo->id)
                ->with('success', "Work Order started. Machine is now under maintenance and MES is blocked.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function complete(Request $request, int $id): RedirectResponse
    {
        $tenantId  = require_tenant_id();
        $validated = $request->validate([
            'work_performed' => ['required', 'string'],
            'labor_hours'    => ['required', 'numeric', 'min:0.1'],
        ]);

        try {
            $wo = $this->service->completeWorkOrder(
                $id,
                $tenantId,
                auth()->id(),
                $validated['work_performed'],
                (float) $validated['labor_hours']
            );

            return redirect()
                ->route('production.maintenance.work-orders.show', $wo->id)
                ->with('success', "Work Order completed successfully. Downtime closed and machine restored to active.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $reason   = $request->input('reason', 'Cancelled by user');

        try {
            $wo = $this->service->cancelWorkOrder($id, $tenantId, auth()->id(), $reason);

            return redirect()
                ->route('production.maintenance.work-orders.show', $wo->id)
                ->with('success', "Work Order cancelled.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reportBreakdown(Request $request): RedirectResponse
    {
        $tenantId  = require_tenant_id();
        $validated = $request->validate([
            'machine_id' => ['required', 'integer'],
            'reason'     => ['required', 'string'],
            'priority'   => ['required', 'in:low,medium,high,critical'],
        ]);

        try {
            $wo = $this->service->reportBreakdown(
                $tenantId,
                $validated['machine_id'],
                $validated['reason'],
                auth()->id(),
                $validated['priority']
            );

            return redirect()
                ->route('production.maintenance.work-orders.show', $wo->id)
                ->with('success', "Breakdown reported for machine. Machine is now under maintenance and Breakdown Work Order '{$wo->work_order_number}' was created.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function addSpare(Request $request, int $id): RedirectResponse
    {
        $tenantId  = require_tenant_id();
        $validated = $request->validate([
            'product_id'    => ['required', 'integer'],
            'warehouse_id'  => ['required', 'integer'],
            'requested_qty' => ['required', 'numeric', 'min:0.0001'],
        ]);

        try {
            $this->spareService->addSpareRequest(
                $id,
                $tenantId,
                $validated['product_id'],
                $validated['warehouse_id'],
                (float) $validated['requested_qty']
            );

            return redirect()
                ->route('production.maintenance.work-orders.show', $id)
                ->with('success', "Spare part request added to Work Order.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function issueSpare(Request $request, int $spareId): RedirectResponse
    {
        $tenantId  = require_tenant_id();
        $validated = $request->validate([
            'issue_qty' => ['required', 'numeric', 'min:0.0001'],
        ]);

        try {
            $spare = $this->spareService->issueSparePart(
                $spareId,
                $tenantId,
                (float) $validated['issue_qty'],
                auth()->id()
            );

            return redirect()
                ->route('production.maintenance.work-orders.show', $spare->maintenance_work_order_id)
                ->with('success', "Spare part issued from warehouse. Stock deducted and costs updated.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
