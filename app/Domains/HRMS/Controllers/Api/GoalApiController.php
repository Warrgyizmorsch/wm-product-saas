<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Goal;
use App\Domains\HRMS\Models\GoalCategory;
use App\Domains\HRMS\Models\GoalCheckIn;
use App\Domains\HRMS\Models\GoalCycle;
use App\Domains\HRMS\Models\GoalKeyResult;
use App\Domains\HRMS\Services\GoalService;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GoalApiController extends Controller
{
    public function __construct(
        private readonly GoalService $goalService,
        private readonly HrmsScopeService $scopeService
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
     * Resolve current user context, tenant ID, and HR/Admin permissions.
     */
    private function resolveContext(): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 1;
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrAdmin = $user && (
            $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.goals.manage') ||
            $user->hasHrPermission('hrms.performance.manage') ||
            $user->hasHrPermission('hrms.employees.view')
        );

        return [$tenantId, $user, $currentEmployee, (bool) $isHrAdmin];
    }

    // =========================================================================
    // 1. DASHBOARD & SUMMARY KPI API
    // =========================================================================

    /**
     * GET /api/hrms/goals/summary
     */
    public function summary(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        try {
            $totalActive = Goal::where('tenant_id', $tenantId)->where('status', 'active')->count();
            $avgProgress = Goal::where('tenant_id', $tenantId)->where('status', 'active')->avg('progress_percentage') ?? 0.0;
            $onTrackCount = Goal::where('tenant_id', $tenantId)->where('status', 'active')->where('health_status', 'on_track')->count();
            $behindCount = Goal::where('tenant_id', $tenantId)->where('status', 'active')->whereIn('health_status', ['at_risk', 'behind'])->count();

            $myGoalsCount = 0;
            $myCompletedCount = 0;
            if ($currentEmployee) {
                $myGoalsCount = Goal::where('tenant_id', $tenantId)->where('employee_id', $currentEmployee->id)->where('status', 'active')->count();
                $myCompletedCount = Goal::where('tenant_id', $tenantId)->where('employee_id', $currentEmployee->id)->where('health_status', 'completed')->count();
            }

            return $this->sendSuccess([
                'total_active_goals' => $totalActive,
                'avg_progress_pct'   => round((float) $avgProgress, 1),
                'on_track_count'     => $onTrackCount,
                'behind_count'       => $behindCount,
                'my_active_goals'    => $myGoalsCount,
                'my_completed_goals' => $myCompletedCount,
                'is_hr_admin'        => $isHrAdmin,
            ], 'Goals dashboard summary loaded successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // 2. GOALS / OBJECTIVES CRUD APIS
    // =========================================================================

    /**
     * GET /api/hrms/goals
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        try {
            $query = Goal::with(['category:id,name,code,color,icon', 'cycle:id,name,code,status', 'department:id,name', 'employee:id,full_name,employee_id', 'keyResults'])
                ->where('tenant_id', $tenantId);

            if ($request->filled('goal_cycle_id')) {
                $query->where('goal_cycle_id', $request->goal_cycle_id);
            }

            if ($request->filled('goal_category_id')) {
                $query->where('goal_category_id', $request->goal_category_id);
            }

            if ($request->filled('health_status')) {
                $query->where('health_status', $request->health_status);
            }

            if ($request->filled('owner_type')) {
                $query->where('owner_type', $request->owner_type);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $s = trim($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('title', 'like', "%{$s}%")
                      ->orWhere('code', 'like', "%{$s}%")
                      ->orWhere('description', 'like', "%{$s}%");
                });
            }

            // Sorting
            $sort = $request->get('sort', 'newest');
            match ($sort) {
                'oldest'        => $query->orderBy('created_at', 'asc'),
                'progress_desc' => $query->orderBy('progress_percentage', 'desc'),
                'progress_asc'  => $query->orderBy('progress_percentage', 'asc'),
                'due_date'      => $query->orderBy('due_date', 'asc'),
                default         => $query->orderBy('created_at', 'desc'),
            };

            $perPage = min((int) ($request->get('per_page', 15)), 100);
            $paginated = $query->paginate($perPage);

            $items = $paginated->getCollection()->map(function ($g) {
                return [
                    'id'                  => $g->id,
                    'code'                => $g->code,
                    'title'               => $g->title,
                    'owner_type'          => $g->owner_type,
                    'owner_name'          => match ($g->owner_type) {
                        'company'    => 'Organization-Wide',
                        'department' => $g->department?->name ?? 'Department',
                        default      => $g->employee?->full_name ?? 'Individual',
                    },
                    'category'            => $g->category ? [
                        'id'    => $g->category->id,
                        'name'  => $g->category->name,
                        'color' => $g->category->color,
                    ] : null,
                    'cycle'               => $g->cycle ? [
                        'id'   => $g->cycle->id,
                        'name' => $g->cycle->name,
                    ] : null,
                    'progress_percentage' => (float) $g->progress_percentage,
                    'health_status'       => $g->health_status,
                    'priority'            => $g->priority,
                    'key_results_count'   => $g->keyResults->count(),
                    'due_date'            => $g->due_date?->format('Y-m-d'),
                    'status'              => $g->status,
                    'created_at'          => $g->created_at?->format('Y-m-d H:i:s'),
                ];
            });

            return $this->sendSuccess([
                'items'      => $items,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ], 'Goals retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/hrms/goals/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        try {
            $goal = Goal::with([
                'category:id,name,code,color,icon',
                'cycle:id,name,code,status',
                'department:id,name',
                'employee:id,full_name,employee_id',
                'parentGoal:id,code,title,progress_percentage',
                'childGoals:id,code,title,progress_percentage,owner_type',
                'keyResults',
                'checkIns.user:id,name',
                'checkIns.employee:id,full_name',
            ])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

            $keyResults = $goal->keyResults->map(fn($kr) => [
                'id'                  => $kr->id,
                'title'               => $kr->title,
                'metric_type'         => $kr->metric_type,
                'unit'                => $kr->unit,
                'start_value'         => (float) $kr->start_value,
                'target_value'        => (float) $kr->target_value,
                'current_value'       => (float) $kr->current_value,
                'weightage'           => (float) $kr->weightage,
                'progress_percentage' => (float) $kr->progress_percentage,
                'health_status'       => $kr->health_status,
                'due_date'            => $kr->due_date?->format('Y-m-d'),
            ]);

            $checkIns = $goal->checkIns->map(fn($ci) => [
                'id'            => $ci->id,
                'author'        => $ci->employee?->full_name ?? ($ci->user?->name ?? 'User'),
                'new_value'     => $ci->new_value,
                'new_progress'  => (float) $ci->new_progress,
                'health_status' => $ci->health_status,
                'comment'       => $ci->comment,
                'blockers'      => $ci->blockers,
                'check_in_date' => $ci->check_in_date?->format('Y-m-d H:i:s'),
            ]);

            return $this->sendSuccess([
                'id'                  => $goal->id,
                'code'                => $goal->code,
                'title'               => $goal->title,
                'description'         => $goal->description,
                'owner_type'          => $goal->owner_type,
                'owner_name'          => match ($goal->owner_type) {
                    'company'    => 'Organization-Wide',
                    'department' => $goal->department?->name ?? 'Department',
                    default      => $goal->employee?->full_name ?? 'Individual',
                },
                'category'            => $goal->category,
                'cycle'               => $goal->cycle,
                'parent_goal'         => $goal->parentGoal,
                'child_goals'         => $goal->childGoals,
                'progress_percentage' => (float) $goal->progress_percentage,
                'health_status'       => $goal->health_status,
                'priority'            => $goal->priority,
                'due_date'            => $goal->due_date?->format('Y-m-d'),
                'status'              => $goal->status,
                'key_results'         => $keyResults,
                'check_ins'           => $checkIns,
            ], 'Goal details loaded successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 404);
        }
    }

    /**
     * POST /api/hrms/goals
     */
    public function store(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        $validator = Validator::make($request->all(), [
            'title'            => 'required|string|max:255',
            'code'             => 'nullable|string|max:50',
            'goal_cycle_id'    => 'nullable|integer|exists:goal_cycles,id',
            'goal_category_id' => 'nullable|integer|exists:goal_categories,id',
            'owner_type'       => 'required|in:company,department,employee',
            'department_id'    => 'nullable|integer|exists:departments,id',
            'employee_id'      => 'nullable|integer|exists:employees,id',
            'parent_goal_id'   => 'nullable|integer|exists:goals,id',
            'priority'         => 'nullable|in:low,medium,high,critical',
            'due_date'         => 'nullable|date',
            'key_results'      => 'nullable|array',
            'key_results.*.title'        => 'required_with:key_results|string|max:255',
            'key_results.*.metric_type'  => 'nullable|in:numeric,currency,percentage,boolean_milestone',
            'key_results.*.target_value' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $goal = $this->goalService->createGoal($validator->validated(), $tenantId, $user);

            return $this->sendSuccess([
                'id'                  => $goal->id,
                'code'                => $goal->code,
                'title'               => $goal->title,
                'progress_percentage' => (float) $goal->progress_percentage,
                'status'              => $goal->status,
            ], 'Objective / Goal created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/hrms/goals/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        $goal = Goal::where('tenant_id', $tenantId)->find($id);
        if (!$goal) {
            return $this->sendError('Goal not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'title'            => 'sometimes|required|string|max:255',
            'goal_cycle_id'    => 'nullable|integer|exists:goal_cycles,id',
            'goal_category_id' => 'nullable|integer|exists:goal_categories,id',
            'owner_type'       => 'nullable|in:company,department,employee',
            'department_id'    => 'nullable|integer|exists:departments,id',
            'employee_id'      => 'nullable|integer|exists:employees,id',
            'parent_goal_id'   => 'nullable|integer|exists:goals,id',
            'health_status'    => 'nullable|in:on_track,at_risk,behind,completed,cancelled',
            'priority'         => 'nullable|in:low,medium,high,critical',
            'due_date'         => 'nullable|date',
            'status'           => 'nullable|in:draft,active,closed,cancelled',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $updated = $this->goalService->updateGoal($goal, $validator->validated(), $user);

            return $this->sendSuccess([
                'id'                  => $updated->id,
                'code'                => $updated->code,
                'title'               => $updated->title,
                'progress_percentage' => (float) $updated->progress_percentage,
                'health_status'       => $updated->health_status,
            ], 'Goal updated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/hrms/goals/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        $goal = Goal::where('tenant_id', $tenantId)->find($id);
        if (!$goal) {
            return $this->sendError('Goal not found.', 404);
        }

        try {
            $goal->delete();
            return $this->sendSuccess(null, 'Goal deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/hrms/goals/{id}/check-in
     */
    public function checkIn(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        $goal = Goal::where('tenant_id', $tenantId)->find($id);
        if (!$goal) {
            return $this->sendError('Goal not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'goal_key_result_id' => 'nullable|integer|exists:goal_key_results,id',
            'new_value'          => 'nullable|numeric',
            'new_progress'       => 'nullable|numeric|min:0|max:100',
            'health_status'      => 'required|in:on_track,at_risk,behind',
            'comment'            => 'required|string|max:1000',
            'blockers'           => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $ci = $this->goalService->recordCheckIn($goal, $validator->validated(), $user, $currentEmployee);

            return $this->sendSuccess([
                'check_in_id'   => $ci->id,
                'new_progress'  => (float) $ci->new_progress,
                'health_status' => $ci->health_status,
                'check_in_date' => $ci->check_in_date?->format('Y-m-d H:i:s'),
            ], 'Progress check-in recorded successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/hrms/goals/alignment-tree
     */
    public function alignmentTree(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveContext();
        $cycleId = $request->filled('goal_cycle_id') ? (int) $request->goal_cycle_id : null;

        try {
            $tree = $this->goalService->getCascadingTree($tenantId, $cycleId);
            return $this->sendSuccess($tree, 'Cascading alignment tree loaded successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/hrms/goals/my-goals
     */
    public function myGoals(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee] = $this->resolveContext();

        if (!$currentEmployee) {
            return $this->sendError('Employee profile not found.', 404);
        }

        try {
            $goals = Goal::with(['category:id,name,color', 'cycle:id,name', 'keyResults'])
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $currentEmployee->id)
                ->latest()
                ->get()
                ->map(fn($g) => [
                    'id'                  => $g->id,
                    'code'                => $g->code,
                    'title'               => $g->title,
                    'category'            => $g->category?->name,
                    'cycle'               => $g->cycle?->name,
                    'progress_percentage' => (float) $g->progress_percentage,
                    'health_status'       => $g->health_status,
                    'due_date'            => $g->due_date?->format('Y-m-d'),
                    'key_results_count'   => $g->keyResults->count(),
                ]);

            return $this->sendSuccess($goals, 'My goals retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // 3. CYCLES & CATEGORIES MASTERS APIS
    // =========================================================================

    public function indexCycles(): JsonResponse
    {
        [$tenantId] = $this->resolveContext();
        $cycles = GoalCycle::where('tenant_id', $tenantId)->orderBy('start_date', 'desc')->get();
        return $this->sendSuccess($cycles, 'Goal cycles retrieved successfully.');
    }

    public function indexCategories(): JsonResponse
    {
        [$tenantId] = $this->resolveContext();
        $categories = GoalCategory::where('tenant_id', $tenantId)->orderBy('name')->get();
        return $this->sendSuccess($categories, 'Goal strategic categories retrieved successfully.');
    }
}
