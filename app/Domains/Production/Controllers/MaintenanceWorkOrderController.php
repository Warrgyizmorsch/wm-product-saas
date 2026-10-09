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
        foreach ($technicians as $tech) {
            $tech->calculated_hourly_rate = $this->service->calculateTechnicianHourlyRate($tech);
        }

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
            'assignments'            => ['nullable', 'array'],
            'assignments.*.assignment_type' => ['nullable', 'in:internal,external'],
            'assignments.*.technician_id' => ['nullable', 'integer'],
            'assignments.*.technician_name' => ['nullable', 'string'],
            'assignments.*.expected_work_hours' => ['nullable', 'numeric', 'min:0'],
            'assignments.*.hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'assignments.*.notes' => ['nullable', 'string'],
        ]);

        try {
            if (!empty($validated['assignments'])) {
                foreach ($validated['assignments'] as &$assignment) {
                    if (isset($assignment['hourly_rate'])) {
                        $assignment['hourly_rate'] = convert_to_base((float) $assignment['hourly_rate']);
                    }
                }
                unset($assignment);
            }

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
        foreach ($technicians as $tech) {
            $tech->calculated_hourly_rate = $this->service->calculateTechnicianHourlyRate($tech);
        }
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
            'work_performed'           => ['required', 'string'],
            'completion_action'        => ['nullable', 'string', 'in:restore,scrap'],
            'was_machine_scraped'      => ['nullable'],
            'completed_at'             => ['nullable', 'date'],
            'additional_cost'          => ['nullable', 'numeric', 'min:0'],
            'additional_expense'       => ['nullable', 'numeric', 'min:0'],
            'external_parts_purchased' => ['nullable'],
            'mechanic_cost'            => ['nullable', 'numeric', 'min:0'],
            'assignments'              => ['nullable', 'array'],
            'assignments.*.id'         => ['required_with:assignments', 'integer'],
            'assignments.*.worked_hours'=> ['nullable', 'numeric', 'min:0'],
            'assignments.*.hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'decision_note'            => ['nullable', 'string'],
        ]);

        try {
            $isScraped = false;
            if (isset($validated['completion_action'])) {
                $isScraped = ($validated['completion_action'] === 'scrap');
            } elseif (isset($validated['was_machine_scraped'])) {
                $isScraped = filter_var($validated['was_machine_scraped'], FILTER_VALIDATE_BOOLEAN);
            }

            $externalPartsPurchased = filter_var($request->input('external_parts_purchased', false), FILTER_VALIDATE_BOOLEAN);
            $additionalCost = isset($validated['additional_cost'])
                ? convert_to_base((float) $validated['additional_cost'])
                : (isset($validated['additional_expense']) ? convert_to_base((float) $validated['additional_expense']) : 0.00);

            $mechanicCost = isset($validated['mechanic_cost']) ? convert_to_base((float) $validated['mechanic_cost']) : 0.00;

            if (!empty($validated['assignments'])) {
                foreach ($validated['assignments'] as &$assignment) {
                    if (isset($assignment['hourly_rate'])) {
                        $assignment['hourly_rate'] = convert_to_base((float) $assignment['hourly_rate']);
                    }
                }
                unset($assignment);
            }

            $wo = $this->service->completeWorkOrder(
                $id,
                $tenantId,
                auth()->id(),
                $validated['work_performed'],
                0.0,
                null,
                0.0,
                $mechanicCost,
                null,
                $isScraped,
                0.0,
                $validated['decision_note'] ?? null,
                $validated['completed_at'] ?? null,
                $additionalCost,
                $externalPartsPurchased,
                $validated['assignments'] ?? null
            );

            $message = $isScraped
                ? "Work Order completed. Machine decommissioned and scrap recorded."
                : "Work Order completed successfully. Downtime closed and machine restored to active.";

            return redirect()
                ->route('production.maintenance.work-orders.show', $wo->id)
                ->with('success', $message);
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
                ->with('success', "Breakdown reported for machine. Work Order '{$wo->work_order_number}' was created in draft with open downtime.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function addSpare(Request $request, int $id): RedirectResponse
    {
        $tenantId  = require_tenant_id();
        $validated = $request->validate([
            'product_id'    => ['required', 'integer'],
            'warehouse_id'  => ['nullable', 'integer'],
            'requested_qty' => ['required', 'numeric', 'min:0.0001'],
        ]);

        try {
            $this->spareService->addSpareRequest(
                $id,
                $tenantId,
                $validated['product_id'],
                $validated['warehouse_id'] ?? null,
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

    public function addAssignment(Request $request, int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $validated = $request->validate([
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.assignment_type' => ['required', 'in:internal,external'],
            'assignments.*.technician_id' => ['nullable', 'integer'],
            'assignments.*.technician_name' => ['nullable', 'string'],
            'assignments.*.expected_work_hours' => ['required', 'numeric', 'min:0'],
            'assignments.*.hourly_rate' => ['required', 'numeric', 'min:0'],
            'assignments.*.notes' => ['nullable', 'string'],
        ]);

        try {
            foreach ($validated['assignments'] as $assignment) {
                if (isset($assignment['hourly_rate'])) {
                    $assignment['hourly_rate'] = convert_to_base((float) $assignment['hourly_rate']);
                }
                $this->service->addAssignment($id, $tenantId, $assignment, auth()->id());
            }

            return redirect()
                ->route('production.maintenance.work-orders.show', $id)
                ->with('success', 'Assignments added successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function storeDowntimeLog(Request $request, int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $validated = $request->validate([
            'action_type' => ['required', 'string', 'in:inspection,repair,adjustment,cleaning,tool_change,other'],
            'other_action' => ['nullable', 'string', 'required_if:action_type,other'],
            'details' => ['required', 'string'],
        ]);

        try {
            $workOrder = $this->repository->findWorkOrder($id, $tenantId);
            abort_if(!$workOrder, 404, 'Work Order not found.');

            if (!$workOrder->downtime_id) {
                return redirect()->back()->with('error', 'No downtime record exists for this work order yet.');
            }

            $action = $validated['action_type'] === 'other'
                ? trim((string) $validated['other_action'])
                : ucfirst(str_replace('_', ' ', $validated['action_type']));

            app(\App\Domains\Production\Services\MaintenanceWorkOrderLogService::class)->recordManualLog(
                $workOrder,
                auth()->id(),
                $action,
                trim((string) $validated['details'])
            );

            return redirect()
                ->route('production.maintenance.work-orders.show', $workOrder->id)
                ->with('success', 'Downtime log added successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
