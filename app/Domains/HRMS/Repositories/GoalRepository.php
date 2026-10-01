<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Goal;
use App\Domains\HRMS\Models\GoalCategory;
use App\Domains\HRMS\Models\GoalCheckIn;
use App\Domains\HRMS\Models\GoalCycle;
use App\Domains\HRMS\Services\GoalService;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;

class GoalRepository implements GoalRepositoryInterface
{
    public function __construct(
        private readonly GoalService $goalService,
        private readonly HrmsScopeService $scopeService
    ) {}

    /**
     * Get all data required for the Goals & OKRs dashboard / hub.
     */
    public function getIndexData(array $inputs, ?User $user, int $tenantId): array
    {
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrAdmin = $this->isHrAdmin($user);

        // 1. Top KPI Metrics
        $totalActive = Goal::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $avgProgress = Goal::where('tenant_id', $tenantId)->where('status', 'active')->avg('progress_percentage') ?? 0.0;
        $onTrackCount = Goal::where('tenant_id', $tenantId)->where('status', 'active')->where('health_status', 'on_track')->count();
        $behindCount = Goal::where('tenant_id', $tenantId)->where('status', 'active')->whereIn('health_status', ['at_risk', 'behind'])->count();

        // 2. Active Cycles & Categories for Filters & Modals
        $cycles = GoalCycle::where('tenant_id', $tenantId)->orderBy('start_date', 'desc')->get();
        $activeCycle = $cycles->firstWhere('status', 'active') ?? $cycles->first();
        $selectedCycleId = !empty($inputs['cycle_id']) ? (int) $inputs['cycle_id'] : $activeCycle?->id;

        $categories = GoalCategory::where('tenant_id', $tenantId)->orderBy('name')->get();
        $departments = Department::where('tenant_id', $tenantId)->orderBy('name')->get();
        $employees = Employee::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('status', true)
                  ->orWhere('status', 1)
                  ->orWhereNull('status');
            })
            ->orderBy('full_name')
            ->get();

        // 3. Tab 1: Company & Strategic Goals Query
        $companyQuery = Goal::with(['category', 'cycle', 'keyResults', 'childGoals', 'creator', 'employee', 'employees', 'department'])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereIn('owner_type', ['company', 'department'])
                  ->orWhereNotNull('goal_category_id')
                  ->orWhereNull('parent_goal_id');
            });

        // 4. Tab 2: My & Team Goals Query
        $myTeamQuery = Goal::with(['category', 'cycle', 'keyResults', 'parentGoal', 'employee', 'employees', 'department'])
            ->where('tenant_id', $tenantId);

        if (!$isHrAdmin && $currentEmployee) {
            $myTeamQuery->where(function ($q) use ($currentEmployee) {
                $q->where('employee_id', $currentEmployee->id)
                  ->orWhereHas('employees', function ($eq) use ($currentEmployee) {
                      $eq->where('employees.id', $currentEmployee->id);
                  })
                  ->orWhere('department_id', $currentEmployee->department_id)
                  ->orWhere('owner_type', 'company');
            });
        }

        // Apply filters to queries
        foreach ([$companyQuery, $myTeamQuery] as $query) {
            if ($selectedCycleId) {
                $query->where('goal_cycle_id', $selectedCycleId);
            }
            if (!empty($inputs['category_id'])) {
                $query->where('goal_category_id', $inputs['category_id']);
            }
            if (!empty($inputs['health_status'])) {
                $query->where('health_status', $inputs['health_status']);
            }
            if (!empty($inputs['status'])) {
                $query->where('status', $inputs['status']);
            }
            if (!empty($inputs['search'])) {
                $s = trim($inputs['search']);
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
                      ->orWhereHas('cycle', function ($cq) use ($s) {
                          $cq->where('goal_cycles.name', 'like', "%{$s}%");
                      })
                      ->orWhereHas('department', function ($dq) use ($s) {
                          $dq->where('departments.name', 'like', "%{$s}%")
                             ->orWhere('departments.code', 'like', "%{$s}%");
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
                      })
                      ->orWhereHas('parentGoal', function ($pq) use ($s) {
                          $pq->where('goals.title', 'like', "%{$s}%")
                             ->orWhere('goals.code', 'like', "%{$s}%");
                      });
                });
            }

            // Sorting
            $sort = $inputs['sort'] ?? 'newest';
            match ($sort) {
                'oldest'        => $query->orderBy('created_at', 'asc'),
                'progress_desc' => $query->orderBy('progress_percentage', 'desc'),
                'progress_asc'  => $query->orderBy('progress_percentage', 'asc'),
                'due_date'      => $query->orderBy('due_date', 'asc'),
                default         => $query->orderBy('created_at', 'desc'),
            };
        }

        $companyGoals = $companyQuery->get();
        $myTeamGoals = $myTeamQuery->get();

        // 5. Tab 3: Cascading Alignment Tree
        $alignmentTree = $this->goalService->getCascadingTree($tenantId, $selectedCycleId);

        // Potential Parent Goals for Modal Picker
        $parentGoalOptions = Goal::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('code')
            ->get();

        $defaultTab = $isHrAdmin ? 'company_goals' : 'my_team_goals';
        $activeTab = $inputs['active_tab'] ?? $defaultTab;

        return compact(
            'totalActive',
            'avgProgress',
            'onTrackCount',
            'behindCount',
            'cycles',
            'selectedCycleId',
            'categories',
            'departments',
            'employees',
            'companyGoals',
            'myTeamGoals',
            'alignmentTree',
            'parentGoalOptions',
            'isHrAdmin',
            'currentEmployee',
            'activeTab'
        );
    }

    /**
     * Get all data required for a single Goal / Objective detail page.
     */
    public function getShowData(int $id, ?User $user, int $tenantId): array
    {
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrAdmin = $this->isHrAdmin($user);

        $goal = Goal::with([
            'cycle',
            'category',
            'department',
            'employee',
            'employees',
            'parentGoal.category',
            'childGoals.category',
            'childGoals.employee',
            'childGoals.employees',
            'childGoals.department',
            'keyResults.owner',
            'checkIns.user',
            'checkIns.employee',
            'checkIns.keyResult',
        ])
        ->where('tenant_id', $tenantId)
        ->findOrFail($id);

        $parentGoalOptions = Goal::where('tenant_id', $tenantId)
            ->where('id', '!=', $goal->id)
            ->where('status', 'active')
            ->orderBy('title')
            ->get();

        $cycles = GoalCycle::where('tenant_id', $tenantId)->orderBy('start_date', 'desc')->get();
        $categories = GoalCategory::where('tenant_id', $tenantId)->orderBy('name')->get();
        $departments = Department::where('tenant_id', $tenantId)->orderBy('name')->get();
        $employees = Employee::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('status', true)
                  ->orWhere('status', 1)
                  ->orWhereNull('status');
            })
            ->orderBy('full_name')
            ->get();

        return compact(
            'goal',
            'parentGoalOptions',
            'cycles',
            'categories',
            'departments',
            'employees',
            'isHrAdmin',
            'currentEmployee'
        );
    }

    /**
     * Store a new Goal / Objective with its key results.
     */
    public function storeGoal(array $data, int $tenantId, ?User $user = null): Goal
    {
        return $this->goalService->createGoal($data, $tenantId, $user);
    }

    /**
     * Update an existing Goal / Objective with its key results.
     */
    public function updateGoal(int $id, array $data, int $tenantId, ?User $user = null): Goal
    {
        $goal = Goal::where('tenant_id', $tenantId)->findOrFail($id);
        return $this->goalService->updateGoal($goal, $data, $user);
    }

    /**
     * Delete an existing Goal / Objective.
     */
    public function deleteGoal(int $id, int $tenantId): bool
    {
        $goal = Goal::where('tenant_id', $tenantId)->findOrFail($id);
        return (bool) $goal->delete();
    }

    /**
     * Record a fast progress check-in on a Goal or Key Result.
     */
    public function recordCheckIn(int $goalId, array $data, int $tenantId, ?User $user = null): GoalCheckIn
    {
        $goal = Goal::where('tenant_id', $tenantId)->findOrFail($goalId);
        $employee = $this->scopeService->resolveEmployee($user, $tenantId);
        return $this->goalService->recordCheckIn($goal, $data, $user, $employee);
    }

    /**
     * Store a new Goal Cycle.
     */
    public function storeCycle(array $data, int $tenantId, ?User $user = null): GoalCycle
    {
        return GoalCycle::create([
            'tenant_id'   => $tenantId,
            'company_id'  => $data['company_id'] ?? $user?->company_id,
            'name'        => $data['name'],
            'code'        => !empty($data['code']) ? $data['code'] : ('CYCLE-' . date('Ym')),
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'status'      => $data['status'] ?? 'active',
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Delete a Goal Cycle.
     */
    public function deleteCycle(int $id, int $tenantId): bool
    {
        $cycle = GoalCycle::where('tenant_id', $tenantId)->findOrFail($id);
        return (bool) $cycle->delete();
    }

    /**
     * Store a new Strategic Pillar / Category.
     */
    public function storeCategory(array $data, int $tenantId, ?User $user = null): GoalCategory
    {
        return GoalCategory::create([
            'tenant_id'   => $tenantId,
            'company_id'  => $data['company_id'] ?? $user?->company_id,
            'name'        => $data['name'],
            'code'        => !empty($data['code']) ? $data['code'] : ('CAT-' . rand(100, 999)),
            'color'       => $data['color'] ?? '#3b82f6',
            'icon'        => $data['icon'] ?? 'feather-target',
            'description' => $data['description'] ?? null,
            'status'      => 'active',
        ]);
    }

    /**
     * Delete a Strategic Pillar / Category.
     */
    public function deleteCategory(int $id, int $tenantId): bool
    {
        $cat = GoalCategory::where('tenant_id', $tenantId)->findOrFail($id);
        return (bool) $cat->delete();
    }

    /**
     * Get cascading hierarchy tree of goals for a cycle.
     */
    public function getCascadingTree(int $tenantId, ?int $cycleId = null): array
    {
        return $this->goalService->getCascadingTree($tenantId, $cycleId);
    }

    /**
     * Determine whether user has HR administrative permissions.
     */
    private function isHrAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.goals.manage') ||
            $user->hasHrPermission('hrms.performance.manage') ||
            $user->hasHrPermission('hrms.employees.view');
    }
}
