<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Models\PipCategory;
use App\Domains\HRMS\Models\PipCheckin;
use App\Domains\HRMS\Models\PipObjective;
use App\Domains\HRMS\Models\PipPolicyTemplate;
use App\Domains\HRMS\Services\PipService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipApiController extends Controller
{
    public function __construct(
        private readonly PipService $pipService
    ) {}

    /**
     * Standardized JSON success response envelope.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Standardized JSON error response envelope.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $statusCode);
    }

    /**
     * Helper to check if current user has HR Admin permissions for PIP.
     */
    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->hasHrPermission('hrms.pip.manage')
            || $user->hasHrPermission('hrms.performance.manage')
            || $user->hasHrPermission('hr.settings.manage');
    }

    /**
     * Helper to get authenticated employee context.
     */
    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return Employee::where('user_id', $user->id)
            ->where('tenant_id', tenant_id() ?? $user->tenant_id ?? 1)
            ->first();
    }

    /**
     * GET /api/hrms/pip
     * PIP Master list, summary stats, search, filter, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        $search = $request->input('search');
        $status = $request->input('status');
        $departmentId = $request->input('department_id');
        $categoryId = $request->input('category_id');

        // Query Plans
        $plansQuery = PerformanceImprovementPlan::query()
            ->where('tenant_id', $tenantId)
            ->with(['employee.department', 'employee.designation', 'manager', 'hrRepresentative', 'category', 'objectives', 'checkins']);

        if (!$isHrAdmin) {
            if (!$employee) {
                return $this->sendError('Employee profile not found.', 404);
            }
            $plansQuery->where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id)
                  ->orWhere('manager_id', $employee->id);
            });
        }

        if ($search) {
            $plansQuery->where(function ($q) use ($search) {
                $q->where('pip_number', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }

        if ($status) {
            $plansQuery->where('status', $status);
        }

        if ($departmentId) {
            $plansQuery->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        if ($categoryId) {
            $plansQuery->where('pip_category_id', $categoryId);
        }

        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'oldest'   => $plansQuery->oldest('id'),
            'pip_asc'  => $plansQuery->orderBy('pip_number', 'asc'),
            'pip_desc' => $plansQuery->orderBy('pip_number', 'desc'),
            default    => $plansQuery->latest('id'),
        };

        $perPage = $request->integer('per_page', 15);
        $plans = $plansQuery->paginate($perPage);

        // Stats Computation
        $baseStats = PerformanceImprovementPlan::where('tenant_id', $tenantId);
        if (!$isHrAdmin && $employee) {
            $baseStats->where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id)
                  ->orWhere('manager_id', $employee->id);
            });
        }

        $stats = [
            'total_active'     => (clone $baseStats)->whereIn('status', ['active', 'under_review', 'extended'])->count(),
            'on_track'         => (clone $baseStats)->where('status', 'active')->count(),
            'at_risk'          => (clone $baseStats)->where('status', 'under_review')->count(),
            'completed'        => (clone $baseStats)->where('status', 'completed_success')->count(),
            'terminated'       => (clone $baseStats)->where('status', 'failed_terminated')->count(),
        ];

        return $this->sendSuccess([
            'stats'     => $stats,
            'plans'     => $plans,
        ], 'PIP dashboard data retrieved successfully.');
    }

    /**
     * POST /api/hrms/pip
     * Initiate a new PIP plan.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to initiate PIP.', 403);
        }

        $validated = $request->validate([
            'employee_id'               => 'required|exists:employees,id',
            'manager_id'                => 'nullable|exists:employees,id',
            'pip_category_id'           => 'nullable|exists:pip_categories,id',
            'reason_category'          => 'nullable|string|max:100',
            'reason_details'           => 'required|string',
            'start_date'                => 'required|date',
            'end_date'                  => 'required|date|after_or_equal:start_date',
            'checkin_frequency'         => 'required|in:weekly,biweekly,monthly',
            'objectives'                => 'nullable|array',
            'objectives.*.title'        => 'required_with:objectives|string|max:255',
            'objectives.*.description'  => 'nullable|string',
            'objectives.*.target_criteria' => 'nullable|string',
            'objectives.*.support_provided' => 'nullable|string',
        ]);

        $validated['hr_representative_id'] = auth()->id() ?? 1;
        $pip = $this->pipService->createPip($validated);

        return $this->sendSuccess($pip->load(['employee.department', 'employee.designation', 'manager', 'category', 'objectives']), 'Performance Improvement Plan initiated successfully.', 201);
    }

    /**
     * GET /api/hrms/pip/{id}
     * PIP Detailed Workspace.
     */
    public function show(mixed $id): JsonResponse
    {
        $pip = PerformanceImprovementPlan::with([
            'employee.department',
            'employee.designation',
            'employee.reportingManager',
            'manager',
            'hrRepresentative',
            'category',
            'objectives',
            'checkins.reviewer'
        ])->find($id);

        if (!$pip) {
            return $this->sendError("Performance Improvement Plan with ID '{$id}' not found.", 404);
        }

        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();
        if (!$isHrAdmin && $employee) {
            if ($pip->employee_id !== $employee->id && $pip->manager_id !== $employee->id && $pip->hr_representative_id !== auth()->id()) {
                return $this->sendError('Unauthorized action. You can only view your own PIP record.', 403);
            }
        }

        return $this->sendSuccess($pip, 'PIP plan workspace details retrieved successfully.');
    }

    /**
     * PUT /api/hrms/pip/{id}
     * Update main PIP plan metadata.
     */
    public function update(Request $request, mixed $id): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to update PIP plan.', 403);
        }

        $pip = PerformanceImprovementPlan::find($id);
        if (!$pip) {
            return $this->sendError("PIP plan with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'pip_category_id'   => 'nullable|exists:pip_categories,id',
            'reason_details'    => 'required|string',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date|after_or_equal:start_date',
            'checkin_frequency' => 'required|in:weekly,biweekly,monthly',
            'status'            => 'required|string',
        ]);

        $pip->update($validated);

        return $this->sendSuccess($pip->fresh(['category', 'employee']), 'PIP plan updated successfully.');
    }

    /**
     * DELETE /api/hrms/pip/{id}
     * Delete PIP plan and associated objectives and check-ins.
     */
    public function destroy(mixed $id): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to delete PIP plan.', 403);
        }

        $pip = PerformanceImprovementPlan::find($id);
        if (!$pip) {
            return $this->sendError("PIP plan with ID '{$id}' not found.", 404);
        }

        $pip->objectives()->delete();
        $pip->checkins()->delete();
        $pip->delete();

        return $this->sendSuccess(null, 'PIP plan and associated records deleted successfully.');
    }

    /**
     * POST /api/hrms/pip/{id}/objectives
     * Add SMART Objective to PIP.
     */
    public function storeObjective(Request $request, mixed $pipId): JsonResponse
    {
        $pip = PerformanceImprovementPlan::find($pipId);
        if (!$pip) {
            return $this->sendError("PIP plan with ID '{$pipId}' not found.", 404);
        }

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'target_criteria'  => 'nullable|string',
            'support_provided' => 'nullable|string',
        ]);

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $validated['tenant_id'] = $tenantId;
        $validated['pip_id'] = $pip->id;
        $validated['status'] = 'pending';

        $objective = PipObjective::create($validated);

        return $this->sendSuccess($objective, 'SMART Objective added successfully.', 201);
    }

    /**
     * PUT /api/hrms/pip/{pipId}/objectives/{objectiveId}/status
     * Quick status update for a SMART Objective.
     */
    public function updateObjectiveStatus(Request $request, mixed $pipId, mixed $objectiveId): JsonResponse
    {
        $objective = PipObjective::where('pip_id', $pipId)->find($objectiveId);
        if (!$objective) {
            return $this->sendError("SMART Objective with ID '{$objectiveId}' not found on PIP '{$pipId}'.", 404);
        }

        $validated = $request->validate([
            'status'          => 'required|in:pending,in_progress,achieved,partially_achieved,not_achieved',
            'manager_remarks' => 'nullable|string',
        ]);

        $objective->update($validated);

        return $this->sendSuccess($objective, 'SMART Objective status updated successfully.');
    }

    /**
     * PUT /api/hrms/pip/{pipId}/objectives/{objectiveId}
     * Full update for a SMART Objective.
     */
    public function updateObjective(Request $request, mixed $pipId, mixed $objectiveId): JsonResponse
    {
        $objective = PipObjective::where('pip_id', $pipId)->find($objectiveId);
        if (!$objective) {
            return $this->sendError("SMART Objective with ID '{$objectiveId}' not found on PIP '{$pipId}'.", 404);
        }

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'target_criteria'  => 'nullable|string',
            'support_provided' => 'nullable|string',
            'status'           => 'required|in:pending,in_progress,achieved,partially_achieved,not_achieved',
        ]);

        $objective->update($validated);

        return $this->sendSuccess($objective, 'SMART Objective updated successfully.');
    }

    /**
     * DELETE /api/hrms/pip/{pipId}/objectives/{objectiveId}
     * Remove a SMART Objective.
     */
    public function destroyObjective(mixed $pipId, mixed $objectiveId): JsonResponse
    {
        $objective = PipObjective::where('pip_id', $pipId)->find($objectiveId);
        if (!$objective) {
            return $this->sendError("SMART Objective with ID '{$objectiveId}' not found on PIP '{$pipId}'.", 404);
        }

        $objective->delete();

        return $this->sendSuccess(null, 'SMART Objective deleted successfully.');
    }

    /**
     * POST /api/hrms/pip/{id}/checkins
     * Record a 1-on-1 Milestone Check-in log.
     */
    public function storeCheckin(Request $request, mixed $pipId): JsonResponse
    {
        $pip = PerformanceImprovementPlan::find($pipId);
        if (!$pip) {
            return $this->sendError("PIP plan with ID '{$pipId}' not found.", 404);
        }

        $validated = $request->validate([
            'checkin_date'     => 'required|date',
            'rating_status'    => 'required|in:on_track,off_track,at_risk,exceeding',
            'manager_comments' => 'required|string',
        ]);

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        $checkin = PipCheckin::create([
            'tenant_id'        => $tenantId,
            'pip_id'           => $pip->id,
            'review_date'      => $validated['checkin_date'],
            'rating_status'    => $validated['rating_status'],
            'manager_comments' => $validated['manager_comments'],
            'reviewer_id'      => auth()->id(),
        ]);

        return $this->sendSuccess($checkin->load('reviewer'), 'Milestone check-in recorded successfully.', 201);
    }

    /**
     * PUT /api/hrms/pip/{pipId}/checkins/{checkinId}
     * Update 1-on-1 Milestone Check-in log.
     */
    public function updateCheckin(Request $request, mixed $pipId, mixed $checkinId): JsonResponse
    {
        $checkin = PipCheckin::where('pip_id', $pipId)->find($checkinId);
        if (!$checkin) {
            return $this->sendError("Milestone check-in with ID '{$checkinId}' not found on PIP '{$pipId}'.", 404);
        }

        $validated = $request->validate([
            'checkin_date'     => 'required|date',
            'rating_status'    => 'required|in:on_track,off_track,at_risk,exceeding',
            'manager_comments' => 'required|string',
        ]);

        $checkin->update([
            'review_date'      => $validated['checkin_date'],
            'rating_status'    => $validated['rating_status'],
            'manager_comments' => $validated['manager_comments'],
        ]);

        return $this->sendSuccess($checkin->fresh('reviewer'), 'Milestone check-in updated successfully.');
    }

    /**
     * DELETE /api/hrms/pip/{pipId}/checkins/{checkinId}
     * Delete a 1-on-1 Milestone Check-in log.
     */
    public function destroyCheckin(mixed $pipId, mixed $checkinId): JsonResponse
    {
        $checkin = PipCheckin::where('pip_id', $pipId)->find($checkinId);
        if (!$checkin) {
            return $this->sendError("Milestone check-in with ID '{$checkinId}' not found on PIP '{$pipId}'.", 404);
        }

        $checkin->delete();

        return $this->sendSuccess(null, 'Milestone check-in log deleted successfully.');
    }

    /**
     * POST /api/hrms/pip/{id}/evaluate
     * Submit final evaluation & close/extend PIP.
     */
    public function evaluate(Request $request, mixed $id): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to evaluate PIP.', 403);
        }

        $pip = PerformanceImprovementPlan::find($id);
        if (!$pip) {
            return $this->sendError("PIP plan with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'evaluation_outcome' => 'required|in:successful_completion,pip_extension,role_reassignment,termination',
            'final_remarks'      => 'required|string',
            'extension_days'     => 'nullable|required_if:evaluation_outcome,pip_extension|integer|min:7|max:90',
        ]);

        $this->pipService->evaluateFinalOutcome($pip, $validated);

        return $this->sendSuccess($pip->fresh(['employee', 'objectives', 'checkins']), 'PIP final evaluation submitted successfully.');
    }

    /**
     * GET /api/hrms/pip/categories
     * PIP Categories Master listing.
     */
    public function indexCategories(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $categories = PipCategory::where('tenant_id', $tenantId)
            ->latest('id')
            ->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($categories, 'PIP Categories retrieved successfully.');
    }

    /**
     * POST /api/hrms/pip/categories
     * Add PIP Category Master.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required.', 403);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $validated['tenant_id'] = $tenantId;

        $category = PipCategory::create($validated);

        return $this->sendSuccess($category, 'PIP Category created successfully.', 201);
    }

    /**
     * DELETE /api/hrms/pip/categories/{id}
     * Delete PIP Category Master.
     */
    public function destroyCategory(mixed $id): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required.', 403);
        }

        $category = PipCategory::find($id);
        if (!$category) {
            return $this->sendError("PIP Category with ID '{$id}' not found.", 404);
        }

        $category->delete();

        return $this->sendSuccess(null, 'PIP Category deleted successfully.');
    }

    /**
     * GET /api/hrms/pip/templates
     * Policy Templates Master listing.
     */
    public function indexTemplates(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $templates = PipPolicyTemplate::where('tenant_id', $tenantId)
            ->latest('id')
            ->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($templates, 'PIP Policy Templates retrieved successfully.');
    }

    /**
     * POST /api/hrms/pip/templates
     * Add Policy Template Master.
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required.', 403);
        }

        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'duration_days'     => 'required|integer|min:7|max:180',
            'checkin_frequency' => 'required|in:weekly,biweekly,monthly',
            'description'       => 'nullable|string',
        ]);

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $validated['tenant_id'] = $tenantId;

        $template = PipPolicyTemplate::create($validated);

        return $this->sendSuccess($template, 'PIP Policy Template created successfully.', 201);
    }

    /**
     * DELETE /api/hrms/pip/templates/{id}
     * Delete Policy Template Master.
     */
    public function destroyTemplate(mixed $id): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required.', 403);
        }

        $template = PipPolicyTemplate::find($id);
        if (!$template) {
            return $this->sendError("PIP Policy Template with ID '{$id}' not found.", 404);
        }

        $template->delete();

        return $this->sendSuccess(null, 'PIP Policy Template deleted successfully.');
    }
}
