<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Repositories\MachineRepositoryInterface;
use App\Domains\Production\Repositories\MaintenanceRepositoryInterface;
use App\Domains\Production\Services\PmScheduleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PmScheduleController extends Controller
{
    public function __construct(
        private readonly MaintenanceRepositoryInterface $repository,
        private readonly MachineRepositoryInterface $machineRepository,
        private readonly PmScheduleService $service
    ) {}

    public function index(Request $request): View
    {
        $tenantId = require_tenant_id();
        $filters  = $request->only(['machine_id', 'is_active', 'search']);
        $schedules = $this->repository->paginatePmSchedules($tenantId, $filters, 15)->withQueryString();
        $machines  = $this->machineRepository->getAll();

        return view('modules.production.maintenance.schedules.index', compact('schedules', 'machines', 'filters'));
    }

    public function create(): View
    {
        $machines = $this->machineRepository->getAll();
        return view('modules.production.maintenance.schedules.create', compact('machines'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'machine_id'               => ['required', 'integer'],
            'name'                     => ['required', 'string', 'max:255'],
            'maintenance_type'         => ['required', 'in:preventive,calibration,inspection'],
            'frequency_type'           => ['required', 'in:days,weeks,months'],
            'frequency_value'          => ['required', 'integer', 'min:1'],
            'estimated_duration_hours' => ['required', 'numeric', 'min:0.1'],
            'priority'                 => ['required', 'in:low,medium,high,critical'],
            'last_completed_date'      => ['nullable', 'date'],
            'checklist_text'           => ['nullable', 'string'],
        ]);

        try {
            if (!empty($validated['checklist_text'])) {
                $items = array_map('trim', explode("\n", str_replace("\r", '', $validated['checklist_text'])));
                $validated['checklist_json'] = array_values(array_filter($items));
            }

            $schedule = $this->service->createSchedule($tenantId, $validated);

            return redirect()
                ->route('production.maintenance.schedules.index')
                ->with('success', "PM Schedule '{$schedule->name}' created successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $tenantId = require_tenant_id();
        $schedule = $this->repository->findPmSchedule($id, $tenantId);
        abort_if(!$schedule, 404, 'PM Schedule not found.');

        $machines = $this->machineRepository->getAll();

        return view('modules.production.maintenance.schedules.edit', compact('schedule', 'machines'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'machine_id'               => ['required', 'integer'],
            'name'                     => ['required', 'string', 'max:255'],
            'maintenance_type'         => ['required', 'in:preventive,calibration,inspection'],
            'frequency_type'           => ['required', 'in:days,weeks,months'],
            'frequency_value'          => ['required', 'integer', 'min:1'],
            'estimated_duration_hours' => ['required', 'numeric', 'min:0.1'],
            'priority'                 => ['required', 'in:low,medium,high,critical'],
            'last_completed_date'      => ['nullable', 'date'],
            'is_active'                => ['nullable', 'boolean'],
            'checklist_text'           => ['nullable', 'string'],
        ]);

        try {
            if (isset($validated['checklist_text'])) {
                $items = array_map('trim', explode("\n", str_replace("\r", '', $validated['checklist_text'])));
                $validated['checklist_json'] = array_values(array_filter($items));
            }
            $validated['is_active'] = $request->has('is_active');

            $schedule = $this->service->updateSchedule($id, $tenantId, $validated);

            return redirect()
                ->route('production.maintenance.schedules.index')
                ->with('success', "PM Schedule '{$schedule->name}' updated successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        try {
            $this->repository->deletePmSchedule($id, $tenantId);
            return redirect()->route('production.maintenance.schedules.index')->with('success', 'PM Schedule deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function generateWorkOrders(Request $request): RedirectResponse
    {
        $tenantId = require_tenant_id();
        try {
            $generated = $this->service->generateDueWorkOrders($tenantId);
            $count     = count($generated);

            return redirect()
                ->route('production.maintenance.work-orders.index')
                ->with('success', "{$count} maintenance work order(s) generated successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
