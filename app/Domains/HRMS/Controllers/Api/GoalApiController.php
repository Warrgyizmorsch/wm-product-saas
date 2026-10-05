<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Goal;
use App\Domains\HRMS\Models\GoalCategory;
use App\Domains\HRMS\Models\GoalCycle;
use App\Domains\HRMS\Repositories\GoalRepositoryInterface;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GoalApiController extends Controller
{
    public function __construct(
        private readonly GoalRepositoryInterface $goalRepository,
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
        [$tenantId, $user] = $this->resolveContext();

        try {
            $data = $this->goalRepository->getIndexData($request->all(), $user, $tenantId);

            $summary = [
                'kpis' => [
                    'total_active_goals' => $data['totalActive'] ?? 0,
                    'avg_progress_pct'   => round((float) ($data['avgProgress'] ?? 0), 1),
                    'on_track_count'     => $data['onTrackCount'] ?? 0,
                    'behind_count'       => $data['behindCount'] ?? 0,
                ],
                'active_cycle' => !empty($data['activeCycle']) ? [
                    'id'         => $data['activeCycle']->id,
                    'name'       => $data['activeCycle']->name,
                    'code'       => $data['activeCycle']->code,
                    'start_date' => $data['activeCycle']->start_date?->format('Y-m-d'),
                    'end_date'   => $data['activeCycle']->end_date?->format('Y-m-d'),
                    'status'     => $data['activeCycle']->status,
                ] : null,
                'total_categories' => $data['categories']?->count() ?? 0,
                'is_hr_admin'      => $data['isHrAdmin'] ?? false,
            ];

            return $this->sendSuccess($summary, 'Goals dashboard summary loaded successfully.');
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
            $query = Goal::with([
                'category:id,name,code,color,icon',
                'cycle:id,name,code,status',
                'department:id,name',
                'employee:id,full_name,employee_id',
                'employees:id,full_name,employee_id',
                'keyResults:id,goal_id,title,progress_percentage,current_value,target_value,unit',
            ])->where('tenant_id', $tenantId);

            // Tab filtering
            $tab = $request->get('tab');
            if ($tab === 'company_goals') {
                $query->where(function ($q) {
                    $q->whereIn('owner_type', ['company', 'department'])
                      ->orWhereNotNull('goal_category_id')
                      ->orWhereNull('parent_goal_id');
                });
            } elseif ($tab === 'my_team_goals' || $tab === 'my_goals') {
                if (!$isHrAdmin && $currentEmployee) {
                    $query->where(function ($q) use ($currentEmployee) {
                        $q->where('employee_id', $currentEmployee->id)
                          ->orWhereHas('employees', fn($eq) => $eq->where('employees.id', $currentEmployee->id))
                          ->orWhere('department_id', $currentEmployee->department_id)
                          ->orWhere('owner_type', 'company');
                    });
                }
            }

            if ($request->filled('goal_cycle_id') || $request->filled('cycle_id')) {
                $query->where('goal_cycle_id', $request->get('goal_cycle_id') ?? $request->get('cycle_id'));
            }

            if ($request->filled('goal_category_id') || $request->filled('category_id')) {
                $query->where('goal_category_id', $request->get('goal_category_id') ?? $request->get('category_id'));
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
                    $q->where('goals.title', 'like', "%{$s}%")
                      ->orWhere('goals.code', 'like', "%{$s}%")
                      ->orWhere('goals.description', 'like', "%{$s}%")
                      ->orWhere('goals.health_status', 'like', "%{$s}%")
                      ->orWhere('goals.status', 'like', "%{$s}%")
                      ->orWhere('goals.owner_type', 'like', "%{$s}%")
                      ->orWhereHas('category', function ($cq) use ($s) {
                          $cq->where('goal_categories.name', 'like', "%{$s}%")
                            ->orWhere('goal_categories.code', 'like', "%{$s}%");
                      })
                      ->orWhereHas('cycle', function ($cyq) use ($s) {
                          $cyq->where('goal_cycles.name', 'like', "%{$s}%");
                      })
                      ->orWhereHas('department', function ($dq) use ($s) {
                          $dq->where('departments.name', 'like', "%{$s}%");
                      })
                      ->orWhereHas('employee', function ($eq) use ($s) {
                          $eq->where('employees.full_name', 'like', "%{$s}%")
                            ->orWhere('employees.employee_id', 'like', "%{$s}%")
                            ->orWhere('employees.job_title', 'like', "%{$s}%");
                      })
                      ->orWhereHas('employees', function ($eq) use ($s) {
                          $eq->where('employees.full_name', 'like', "%{$s}%")
                            ->orWhere('employees.employee_id', 'like', "%{$s}%")
                            ->orWhere('employees.job_title', 'like', "%{$s}%");
                      })
                      ->orWhereHas('keyResults', function ($kq) use ($s) {
                          $kq->where('goal_key_results.title', 'like', "%{$s}%")
                            ->orWhere('goal_key_results.unit', 'like', "%{$s}%");
                      });
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

            $items = $paginated->getCollection()->map(function ($g) use ($isHrAdmin, $currentEmployee) {
                $assignedEmps = $g->employees->isNotEmpty() ? $g->employees : ($g->employee ? collect([$g->employee]) : collect());

                return [
                    'id'                  => $g->id,
                    'code'                => $g->code,
                    'title'               => $g->title,
                    'owner_type'          => $g->owner_type,
                    'owners'              => match ($g->owner_type) {
                        'company'    => ['type' => 'company', 'name' => 'Organization-Wide'],
                        'department' => ['type' => 'department', 'name' => $g->department?->name ?? 'Department'],
                        default      => [
                            'type'      => 'employee',
                            'employees' => $assignedEmps->map(fn($e) => [
                                'id'          => $e->id,
                                'employee_id' => $e->employee_id,
                                'name'        => $e->full_name,
                            ])->values()->all(),
                        ],
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
                    'capabilities'        => [
                        'can_view'     => true,
                        'can_edit'     => $isHrAdmin || ($currentEmployee && ($g->employee_id === $currentEmployee->id || $assignedEmps->contains('id', $currentEmployee->id))),
                        'can_delete'   => $isHrAdmin,
                        'can_check_in' => $isHrAdmin || ($currentEmployee && ($g->employee_id === $currentEmployee->id || $assignedEmps->contains('id', $currentEmployee->id))),
                    ],
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
            $data = $this->goalRepository->getShowData($id, $user, $tenantId);
            $goal = $data['goal'];

            $assignedEmps = $goal->employees->isNotEmpty() ? $goal->employees : ($goal->employee ? collect([$goal->employee]) : collect());

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

            $subGoals = $goal->childGoals->map(function ($c) {
                $childEmps = $c->employees->isNotEmpty() ? $c->employees : ($c->employee ? collect([$c->employee]) : collect());
                return [
                    'id'                  => $c->id,
                    'code'                => $c->code,
                    'title'               => $c->title,
                    'progress_percentage' => (float) $c->progress_percentage,
                    'health_status'       => $c->health_status,
                    'category'            => $c->category?->name,
                    'assigned_to'         => $childEmps->pluck('full_name')->join(', ') ?: ($c->department?->name ?? 'Organization'),
                ];
            });

            $response = [
                'id'                  => $goal->id,
                'code'                => $goal->code,
                'title'               => $goal->title,
                'description'         => $goal->description,
                'owner_type'          => $goal->owner_type,
                'assigned_employees'  => $assignedEmps->map(fn($e) => [
                    'id'          => $e->id,
                    'employee_id' => $e->employee_id,
                    'name'        => $e->full_name,
                    'department'  => $e->department?->name,
                ])->values()->all(),
                'department'          => $goal->department ? ['id' => $goal->department->id, 'name' => $goal->department->name] : null,
                'category'            => $goal->category ? ['id' => $goal->category->id, 'name' => $goal->category->name, 'color' => $goal->category->color] : null,
                'cycle'               => $goal->cycle ? ['id' => $goal->cycle->id, 'name' => $goal->cycle->name] : null,
                'parent_goal'         => $goal->parentGoal ? [
                    'id'                  => $goal->parentGoal->id,
                    'code'                => $goal->parentGoal->code,
                    'title'               => $goal->parentGoal->title,
                    'progress_percentage' => (float) $goal->parentGoal->progress_percentage,
                ] : null,
                'progress_percentage' => (float) $goal->progress_percentage,
                'health_status'       => $goal->health_status,
                'priority'            => $goal->priority,
                'due_date'            => $goal->due_date?->format('Y-m-d'),
                'status'              => $goal->status,
                'key_results'         => $keyResults,
                'recent_check_ins'    => $checkIns,
                'cascaded_sub_goals'  => $subGoals,
                'capabilities'        => [
                    'can_view'     => true,
                    'can_edit'     => $isHrAdmin || ($currentEmployee && ($goal->employee_id === $currentEmployee->id || $assignedEmps->contains('id', $currentEmployee->id))),
                    'can_delete'   => $isHrAdmin,
                    'can_check_in' => $isHrAdmin || ($currentEmployee && ($goal->employee_id === $currentEmployee->id || $assignedEmps->contains('id', $currentEmployee->id))),
                ],
            ];

            return $this->sendSuccess($response, 'Goal details loaded successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 404);
        }
    }

    /**
     * POST /api/hrms/goals
     */
    public function store(Request $request): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $validator = Validator::make($request->all(), [
            'title'                      => 'required|string|max:255',
            'code'                       => 'nullable|string|max:50',
            'goal_cycle_id'              => 'nullable',
            'custom_goal_cycle'          => 'nullable|string|max:255',
            'goal_category_id'           => 'nullable',
            'custom_goal_category'       => 'nullable|string|max:255',
            'owner_type'                 => 'required|in:company,department,employee',
            'department_id'              => 'nullable|integer|exists:departments,id',
            'employee_id'                => 'nullable|integer|exists:employees,id',
            'employee_ids'               => 'nullable|array',
            'employee_ids.*'             => 'integer|exists:employees,id',
            'parent_goal_id'             => 'nullable|integer|exists:goals,id',
            'priority'                   => 'nullable|in:low,medium,high,critical',
            'due_date'                   => 'nullable|date',
            'description'                => 'nullable|string|max:2000',
            'key_results'                => 'nullable|array',
            'key_results.*.title'        => 'required_with:key_results|string|max:255',
            'key_results.*.metric_type'  => 'nullable|in:numeric,currency,percentage,boolean_milestone',
            'key_results.*.target_value' => 'nullable|numeric',
            'key_results.*.unit'         => 'nullable|string|max:50',
            'key_results.*.weightage'    => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $goal = $this->goalRepository->storeGoal($validator->validated(), $tenantId, $user);

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
        [$tenantId, $user] = $this->resolveContext();

        $validator = Validator::make($request->all(), [
            'title'                => 'sometimes|required|string|max:255',
            'goal_cycle_id'        => 'nullable',
            'custom_goal_cycle'    => 'nullable|string|max:255',
            'goal_category_id'     => 'nullable',
            'custom_goal_category' => 'nullable|string|max:255',
            'owner_type'           => 'nullable|in:company,department,employee',
            'department_id'        => 'nullable|integer|exists:departments,id',
            'employee_id'          => 'nullable|integer|exists:employees,id',
            'employee_ids'         => 'nullable|array',
            'employee_ids.*'       => 'integer|exists:employees,id',
            'parent_goal_id'       => 'nullable|integer|exists:goals,id',
            'health_status'        => 'nullable|in:on_track,at_risk,behind,completed,cancelled',
            'priority'             => 'nullable|in:low,medium,high,critical',
            'due_date'             => 'nullable|date',
            'description'          => 'nullable|string|max:2000',
            'status'               => 'nullable|in:draft,active,closed,cancelled',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $updated = $this->goalRepository->updateGoal($id, $validator->validated(), $tenantId, $user);

            return $this->sendSuccess([
                'id'                  => $updated->id,
                'code'                => $updated->code,
                'title'               => $updated->title,
                'progress_percentage' => (float) $updated->progress_percentage,
                'health_status'       => $updated->health_status,
                'status'              => $updated->status,
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
        [$tenantId] = $this->resolveContext();

        try {
            $this->goalRepository->deleteGoal($id, $tenantId);
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
        [$tenantId, $user] = $this->resolveContext();

        $validator = Validator::make($request->all(), [
            'goal_key_result_id' => 'nullable|integer|exists:goal_key_results,id',
            'new_value'          => 'nullable|numeric',
            'health_status'      => 'required|in:on_track,at_risk,behind',
            'comment'            => 'required|string|max:1000',
            'blockers'           => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $ci = $this->goalRepository->recordCheckIn($id, $validator->validated(), $tenantId, $user);

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
            $tree = $this->goalRepository->getCascadingTree($tenantId, $cycleId);
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
            $goals = Goal::with(['category:id,name,color', 'cycle:id,name', 'keyResults:id,goal_id,title,progress_percentage'])
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($currentEmployee) {
                    $q->where('employee_id', $currentEmployee->id)
                      ->orWhereHas('employees', fn($eq) => $eq->where('employees.id', $currentEmployee->id));
                })
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
        $cycles = GoalCycle::where('tenant_id', $tenantId)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'code', 'start_date', 'end_date', 'status']);

        return $this->sendSuccess($cycles, 'Goal cycles retrieved successfully.');
    }

    public function storeCycle(Request $request): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'code'        => 'nullable|string|max:50',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'status'      => 'nullable|in:upcoming,active,review_period,closed',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $cycle = $this->goalRepository->storeCycle($validator->validated(), $tenantId, $user);
            return $this->sendSuccess([
                'id'         => $cycle->id,
                'name'       => $cycle->name,
                'code'       => $cycle->code,
                'start_date' => $cycle->start_date?->format('Y-m-d'),
                'end_date'   => $cycle->end_date?->format('Y-m-d'),
                'status'     => $cycle->status,
            ], 'Goal cycle created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    public function destroyCycle(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->goalRepository->deleteCycle($id, $tenantId);
            return $this->sendSuccess(null, 'Goal cycle deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    public function indexCategories(): JsonResponse
    {
        [$tenantId] = $this->resolveContext();
        $categories = GoalCategory::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color', 'icon', 'status']);

        return $this->sendSuccess($categories, 'Goal strategic categories retrieved successfully.');
    }

    public function storeCategory(Request $request): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'code'        => 'nullable|string|max:50',
            'color'       => 'nullable|string|max:30',
            'icon'        => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $category = $this->goalRepository->storeCategory($validator->validated(), $tenantId, $user);
            return $this->sendSuccess([
                'id'    => $category->id,
                'name'  => $category->name,
                'code'  => $category->code,
                'color' => $category->color,
                'icon'  => $category->icon,
            ], 'Strategic category created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    public function destroyCategory(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->goalRepository->deleteCategory($id, $tenantId);
            return $this->sendSuccess(null, 'Strategic category deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
}
