<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Department;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoalController extends Controller
{
    public function __construct(
        private readonly GoalService $goalService,
        private readonly HrmsScopeService $scopeService
    ) {}

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

    /**
     * Display the Main Goals & OKRs Hub (Single Panel ERP Interface).
     */
    public function index(Request $request): View
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        // 1. Top KPI Metrics
        $totalActive = Goal::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $avgProgress = Goal::where('tenant_id', $tenantId)->where('status', 'active')->avg('progress_percentage') ?? 0.0;
        $onTrackCount = Goal::where('tenant_id', $tenantId)->where('status', 'active')->where('health_status', 'on_track')->count();
        $behindCount = Goal::where('tenant_id', $tenantId)->where('status', 'active')->whereIn('health_status', ['at_risk', 'behind'])->count();

        // 2. Active Cycles & Categories for Filters & Modals
        $cycles = GoalCycle::where('tenant_id', $tenantId)->orderBy('start_date', 'desc')->get();
        $activeCycle = $cycles->firstWhere('status', 'active') ?? $cycles->first();
        $selectedCycleId = $request->filled('cycle_id') ? (int) $request->cycle_id : $activeCycle?->id;

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
            if ($request->filled('category_id')) {
                $query->where('goal_category_id', $request->category_id);
            }
            if ($request->filled('health_status')) {
                $query->where('health_status', $request->health_status);
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
        }

        $companyGoals = $companyQuery->get();
        $myTeamGoals = $myTeamQuery->get();

        // 5. Tab 3: Cascading Alignment Tree
        $alignmentTree = $this->goalService->getCascadingTree($tenantId, $selectedCycleId);

        // Potential Parent Goals for Modal Picker
        $parentGoalOptions = Goal::where('tenant_id', $tenantId)
            ->whereIn('owner_type', ['company', 'department'])
            ->where('status', 'active')
            ->orderBy('title')
            ->get();

        $activeTab = $request->get('active_tab', 'company_goals');

        return view('modules.hrms.goals.index', compact(
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
        ));
    }

    /**
     * Display Goal / OKR Detail Reader & Check-in Timeline.
     */
    public function show(int $id): View
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        $goal = Goal::with([
            'cycle',
            'category',
            'department',
            'employee',
            'employees',
            'parentGoal.category',
            'childGoals.category',
            'childGoals.employee',
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

        return view('modules.hrms.goals.show', compact(
            'goal',
            'parentGoalOptions',
            'cycles',
            'categories',
            'departments',
            'employees',
            'isHrAdmin',
            'currentEmployee'
        ));
    }

    /**
     * Store new Goal / Objective with key results.
     */
    public function store(Request $request): RedirectResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        $request->validate([
            'title'            => 'required|string|max:255',
            'goal_cycle_id'    => 'nullable|exists:goal_cycles,id',
            'goal_category_id' => 'nullable|exists:goal_categories,id',
            'owner_type'       => 'required|in:company,department,employee',
            'department_id'    => 'nullable|exists:departments,id',
            'employee_id'      => 'nullable|exists:employees,id',
            'employee_ids'     => 'nullable|array',
            'employee_ids.*'   => 'exists:employees,id',
            'parent_goal_id'   => 'nullable|exists:goals,id',
            'priority'         => 'nullable|in:low,medium,high,critical',
            'due_date'         => 'nullable|date',
        ]);

        try {
            $data = $request->all();

            // Structure key results from form repeater if submitted
            if ($request->filled('kr_title') && is_array($request->kr_title)) {
                $krs = [];
                foreach ($request->kr_title as $idx => $title) {
                    if (empty(trim($title))) {
                        continue;
                    }
                    $krs[] = [
                        'title'         => trim($title),
                        'metric_type'   => $request->kr_metric_type[$idx] ?? 'percentage',
                        'unit'          => $request->kr_unit[$idx] ?? '%',
                        'start_value'   => (float) ($request->kr_start_value[$idx] ?? 0),
                        'target_value'  => (float) ($request->kr_target_value[$idx] ?? 100),
                        'current_value' => (float) ($request->kr_current_value[$idx] ?? 0),
                        'weightage'     => (float) ($request->kr_weightage[$idx] ?? 100),
                    ];
                }
                $data['key_results'] = $krs;
            }

            $goal = $this->goalService->createGoal($data, $tenantId, $user);

            return redirect()->route('hrms.goals.show', $goal->id)
                ->with('success', "Goal '{$goal->title}' created successfully.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to create goal: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update an existing Goal.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        $goal = Goal::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'title'            => 'required|string|max:255',
            'goal_cycle_id'    => 'nullable|exists:goal_cycles,id',
            'goal_category_id' => 'nullable|exists:goal_categories,id',
            'owner_type'       => 'required|in:company,department,employee',
            'department_id'    => 'nullable|exists:departments,id',
            'employee_id'      => 'nullable|exists:employees,id',
            'parent_goal_id'   => 'nullable|exists:goals,id',
            'health_status'    => 'nullable|in:on_track,at_risk,behind,completed,cancelled',
            'priority'         => 'nullable|in:low,medium,high,critical',
            'due_date'         => 'nullable|date',
        ]);

        try {
            $data = $request->all();

            if ($request->filled('kr_title') && is_array($request->kr_title)) {
                $krs = [];
                foreach ($request->kr_title as $idx => $title) {
                    if (empty(trim($title))) {
                        continue;
                    }
                    $krs[] = [
                        'id'            => $request->kr_id[$idx] ?? null,
                        'title'         => trim($title),
                        'metric_type'   => $request->kr_metric_type[$idx] ?? 'percentage',
                        'unit'          => $request->kr_unit[$idx] ?? '%',
                        'start_value'   => (float) ($request->kr_start_value[$idx] ?? 0),
                        'target_value'  => (float) ($request->kr_target_value[$idx] ?? 100),
                        'current_value' => (float) ($request->kr_current_value[$idx] ?? 0),
                        'weightage'     => (float) ($request->kr_weightage[$idx] ?? 100),
                    ];
                }
                $data['key_results'] = $krs;
            }

            $this->goalService->updateGoal($goal, $data, $user);

            return redirect()->back()->with('success', 'Goal updated successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to update goal: ' . $e->getMessage());
        }
    }

    /**
     * Delete an existing Goal.
     */
    public function destroy(int $id): RedirectResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        $goal = Goal::where('tenant_id', $tenantId)->findOrFail($id);

        try {
            $goal->delete();
            return redirect()->route('hrms.goals.index')->with('success', 'Goal deleted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete goal: ' . $e->getMessage());
        }
    }

    /**
     * Record a fast Progress Check-in on a Goal or Key Result.
     */
    public function checkIn(Request $request, int $id): RedirectResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrAdmin] = $this->resolveContext();

        $goal = Goal::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'goal_key_result_id' => 'nullable|exists:goal_key_results,id',
            'new_value'          => 'nullable|numeric',
            'health_status'      => 'required|in:on_track,at_risk,behind',
            'comment'            => 'required|string|max:1000',
            'blockers'           => 'nullable|string|max:1000',
        ]);

        try {
            $this->goalService->recordCheckIn($goal, $request->all(), $user, $currentEmployee);

            return redirect()->back()->with('success', 'Progress check-in recorded successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Check-in failed: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // CYCLES & CATEGORIES MASTER ACTIONS
    // =========================================================================

    public function storeCycle(Request $request): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'name'       => 'required|string|max:100',
            'code'       => 'nullable|string|max:50',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'status'     => 'nullable|in:planning,active,review,closed',
        ]);

        try {
            GoalCycle::create([
                'tenant_id'   => $tenantId,
                'company_id'  => $user?->company_id,
                'name'        => $request->name,
                'code'        => $request->code ?: ('CYCLE-' . date('Ym')),
                'start_date'  => $request->start_date,
                'end_date'    => $request->end_date,
                'status'      => $request->status ?? 'active',
                'description' => $request->description,
            ]);

            return redirect()->route('hrms.goals.index', ['active_tab' => 'cycles_pillars'])
                ->with('success', 'Goal Cycle created successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to create cycle: ' . $e->getMessage());
        }
    }

    public function destroyCycle(int $id): RedirectResponse
    {
        [$tenantId] = $this->resolveContext();
        $cycle = GoalCycle::where('tenant_id', $tenantId)->findOrFail($id);

        try {
            $cycle->delete();
            return redirect()->route('hrms.goals.index', ['active_tab' => 'cycles_pillars'])
                ->with('success', 'Goal Cycle deleted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete cycle: ' . $e->getMessage());
        }
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'name'  => 'required|string|max:100',
            'code'  => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'icon'  => 'nullable|string|max:50',
        ]);

        try {
            GoalCategory::create([
                'tenant_id'   => $tenantId,
                'company_id'  => $user?->company_id,
                'name'        => $request->name,
                'code'        => $request->code ?: ('CAT-' . rand(100, 999)),
                'color'       => $request->color ?? '#3b82f6',
                'icon'        => $request->icon ?? 'feather-target',
                'description' => $request->description,
                'status'      => 'active',
            ]);

            return redirect()->route('hrms.goals.index', ['active_tab' => 'cycles_pillars'])
                ->with('success', 'Strategic Pillar / Category created successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to create category: ' . $e->getMessage());
        }
    }

    public function destroyCategory(int $id): RedirectResponse
    {
        [$tenantId] = $this->resolveContext();
        $cat = GoalCategory::where('tenant_id', $tenantId)->findOrFail($id);

        try {
            $cat->delete();
            return redirect()->route('hrms.goals.index', ['active_tab' => 'cycles_pillars'])
                ->with('success', 'Category deleted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete category: ' . $e->getMessage());
        }
    }
}
