<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Services\WorkCenterEmployeeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class WorkCenterEmployeeController extends Controller
{
    public function __construct(
        private readonly WorkCenterEmployeeService $service
    ) {}

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'work_center_id' => 'required|integer',
            'section_id'     => 'nullable|integer',
            'employee_id'    => 'required|integer',
            'active'         => 'nullable|boolean',
        ]);

        $tenantId = require_tenant_id();
        $workCenterId = (int) $request->input('work_center_id');
        $sectionId = $request->filled('section_id') ? (int) $request->input('section_id') : null;
        $employeeId = (int) $request->input('employee_id');
        $active = $request->boolean('active', true);

        $workCenter = WorkCenter::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($workCenterId);

        abort_if(!$workCenter, 404, 'Work Center not found.');
        Gate::authorize('update', $workCenter);

        try {
            $assignment = $this->service->assign($workCenterId, $employeeId, $tenantId, $active, $sectionId);
            $this->triggerCalculationCountdown($assignment);

            $targetMsg = $sectionId ? 'Section' : 'Work Center';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Internal employee assigned to {$targetMsg} successfully.",
                    'assignment' => $assignment,
                ]);
            }
            return redirect()->back()->with('success', "Internal employee assigned to {$targetMsg} successfully.");
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while assigning employee: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->withInput()->with('error', 'An error occurred while assigning employee: ' . $e->getMessage());
        }
    }

    public function toggleStatus(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $tenantId = require_tenant_id();
        $assignment = WorkCenterEmployeeAssignment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('workCenter')
            ->find($id);

        abort_if(!$assignment, 404, 'Employee assignment not found.');
        Gate::authorize('update', $assignment->workCenter);

        try {
            $targetActive = $request->has('active') ? $request->boolean('active') : !$assignment->active;
            $this->service->setActive($id, $targetActive, $tenantId);
            $this->triggerCalculationCountdown($assignment);

            $statusText = $targetActive ? 'activated' : 'deactivated';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Employee assignment {$statusText} successfully.",
                    'active' => $targetActive,
                ]);
            }
            return redirect()->back()->with('success', "Employee assignment {$statusText} successfully.");
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'An error occurred while updating employee assignment: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'An error occurred while updating employee assignment: ' . $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse|JsonResponse
    {
        $tenantId = require_tenant_id();
        $assignment = WorkCenterEmployeeAssignment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('workCenter')
            ->find($id);

        abort_if(!$assignment, 404, 'Employee assignment not found.');
        Gate::authorize('update', $assignment->workCenter);

        try {
            $this->triggerCalculationCountdown($assignment);
            $this->service->remove($id, $tenantId);

            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Employee assignment removed successfully.',
                ]);
            }
            return redirect()->back()->with('success', 'Employee removed from Work Center successfully.');
        } catch (InvalidArgumentException $e) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'An error occurred while removing employee assignment: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'An error occurred while removing employee assignment: ' . $e->getMessage());
        }
    }

    private function triggerCalculationCountdown(?WorkCenterEmployeeAssignment $assignment): void
    {
        if (!$assignment) {
            return;
        }

        $workCenter = $assignment->workCenter;
        if ($workCenter) {
            app(\App\Domains\Production\Services\CountdownCalculationEngine::class)->triggerCountdown($workCenter, $assignment->section_id);
        }
    }
}
