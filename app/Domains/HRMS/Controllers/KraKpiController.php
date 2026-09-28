<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\AppraisalCycle;
use App\Domains\HRMS\Models\AppraisalReview;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeGoalItem;
use App\Domains\HRMS\Models\EmployeeGoalPlan;
use App\Domains\HRMS\Models\GoalProgressLog;
use App\Domains\HRMS\Models\KpiMaster;
use App\Domains\HRMS\Models\KpiTemplate;
use App\Domains\HRMS\Models\KpiTemplateItem;
use App\Domains\HRMS\Models\KraCategory;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Domains\HRMS\Services\KraKpiService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KraKpiController extends Controller
{
    public function __construct(
        private readonly KraKpiService $kraKpiService,
        private readonly HrmsScopeService $scopeService
    ) {}

    /**
     * Display the Enterprise KRA & KPI Performance Hub.
     */
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiService->ensureTablesExist($tenantId);
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $user && (
            $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.employees.view')
        );

        // 1. Appraisal Cycles
        $cycles = AppraisalCycle::where('tenant_id', $tenantId)->orderBy('created_at', 'desc')->get();
        $activeCycle = $cycles->firstWhere('status', 'in_progress')
            ?: $cycles->firstWhere('status', 'goal_setting')
            ?: $cycles->first();

        // 2. My Active Scorecard / Plan
        $myPlan = null;
        if ($currentEmployee) {
            $myPlan = EmployeeGoalPlan::with(['items.kraCategory', 'appraisalCycle', 'manager'])
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $currentEmployee->id)
                ->when($activeCycle, fn($q) => $q->where('appraisal_cycle_id', $activeCycle->id))
                ->latest()
                ->first();
        }

        // 3. Team Scorecards (For Managers)
        $teamPlans = collect();
        if ($currentEmployee) {
            $teamPlans = EmployeeGoalPlan::with(['employee.department', 'employee.designation', 'appraisalCycle', 'items'])
                ->where('tenant_id', $tenantId)
                ->where('manager_id', $currentEmployee->id)
                ->when($activeCycle, fn($q) => $q->where('appraisal_cycle_id', $activeCycle->id))
                ->latest()
                ->get();
        }

        // 4. All Scorecards (For HR/Admin with Search & Filters)
        $plansQuery = EmployeeGoalPlan::with(['employee.department', 'employee.designation', 'manager', 'appraisalCycle', 'items'])
            ->where('tenant_id', $tenantId);

        if (!$isHrOrAdmin && $currentEmployee) {
            $plansQuery->where(function ($q) use ($currentEmployee) {
                $q->where('employee_id', $currentEmployee->id)
                  ->orWhere('manager_id', $currentEmployee->id);
            });
        }

        // Filter: Cycle
        if ($request->filled('filter_cycle')) {
            $plansQuery->where('appraisal_cycle_id', $request->filter_cycle);
        }

        // Filter: Status
        if ($request->filled('filter_status')) {
            if ($request->filter_status === 'overdue') {
                $today = Carbon::today()->toDateString();
                $plansQuery->where(function ($q) use ($today) {
                    $q->where(function ($sq) use ($today) {
                        $sq->whereIn('status', ['draft', 'submitted'])
                           ->whereHas('appraisalCycle', function ($cq) use ($today) {
                               $cq->whereNotNull('goal_setting_deadline')
                                  ->where('goal_setting_deadline', '<', $today);
                           });
                    })->orWhere(function ($sq) use ($today) {
                        $sq->whereIn('status', ['approved', 'in_progress'])
                           ->whereHas('appraisalCycle', function ($cq) use ($today) {
                               $cq->whereNotNull('self_review_deadline')
                                  ->where('self_review_deadline', '<', $today);
                           });
                    });
                });
            } else {
                $plansQuery->where('status', $request->filter_status);
            }
        }

        // Filter: Department
        if ($request->filled('filter_department')) {
            $plansQuery->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->filter_department);
            });
        }

        // Search: Employee Name or Plan Number
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $plansQuery->where(function ($q) use ($term) {
                $q->where('plan_number', 'like', $term)
                  ->orWhereHas('employee', function ($eq) use ($term) {
                      $eq->where('full_name', 'like', $term)
                         ->orWhere('employee_id', 'like', $term);
                  });
            });
        }

        // Sorting
        $sort = $request->input('sort', 'latest');
        if ($sort === 'score_desc') {
            $plansQuery->orderBy('final_score', 'desc');
        } elseif ($sort === 'score_asc') {
            $plansQuery->orderBy('final_score', 'asc');
        } elseif ($sort === 'oldest') {
            $plansQuery->orderBy('created_at', 'asc');
        } else {
            $plansQuery->orderBy('created_at', 'desc');
        }

        $allPlans = $plansQuery->paginate(10)->withQueryString();

        // 5. KRA Categories & KPI Master Library
        $kraCategories = KraCategory::where('tenant_id', $tenantId)->withCount('kpiMasters')->get();
        $kpiMasters = KpiMaster::with('kraCategory')->where('tenant_id', $tenantId)->orderBy('name')->get();
        $kpiTemplates = KpiTemplate::with(['department', 'designation', 'items'])->where('tenant_id', $tenantId)->get();

        // Master Dropdown Data
        $departments = Department::where('tenant_id', $tenantId)->orderBy('name')->get();
        $designations = Designation::where('tenant_id', $tenantId)->orderBy('name')->get();
        $employees = Employee::where('tenant_id', $tenantId)->where('status', true)->orderBy('full_name')->get();

        // 6. Summary Statistics
        $totalPlansCount = EmployeeGoalPlan::where('tenant_id', $tenantId)->count();
        $pendingSelfReviewCount = EmployeeGoalPlan::where('tenant_id', $tenantId)->where('status', 'approved')->count();
        $pendingManagerReviewCount = EmployeeGoalPlan::where('tenant_id', $tenantId)->where('status', 'self_reviewed')->count();
        $calibratedCount = EmployeeGoalPlan::where('tenant_id', $tenantId)->whereIn('status', ['calibrated', 'signed_off'])->count();
        $highPerformersCount = EmployeeGoalPlan::where('tenant_id', $tenantId)->where('final_score', '>=', 90)->count();
        $underperformersCount = EmployeeGoalPlan::where('tenant_id', $tenantId)->where('final_score', '<', 60)->whereNotNull('final_score')->count();

        $activeTab = $request->input('active_tab', 'plans');

        return view('modules.hrms.kra-kpi.index', compact(
            'activeTab',
            'cycles',
            'activeCycle',
            'myPlan',
            'teamPlans',
            'allPlans',
            'kraCategories',
            'kpiMasters',
            'kpiTemplates',
            'departments',
            'designations',
            'employees',
            'currentEmployee',
            'isHrOrAdmin',
            'totalPlansCount',
            'pendingSelfReviewCount',
            'pendingManagerReviewCount',
            'calibratedCount',
            'highPerformersCount',
            'underperformersCount'
        ));
    }

    /**
     * Show detailed employee scorecard / appraisal breakdown.
     */
    public function show(int $id): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiService->ensureTablesExist($tenantId);
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $user && (
            $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.employees.view')
        );

        $plan = EmployeeGoalPlan::with([
            'employee.department',
            'employee.designation',
            'employee.reportingManager',
            'manager',
            'appraisalCycle',
            'items.kraCategory',
            'items.progressLogs.loggedBy',
            'reviews.reviewer',
        ])->where('tenant_id', $tenantId)->findOrFail($id);

        $kraCategories = KraCategory::where('tenant_id', $tenantId)->get();
        $kpiMasters = KpiMaster::where('tenant_id', $tenantId)->get();

        $isOwner = $currentEmployee && $currentEmployee->id === $plan->employee_id;
        $isManager = $currentEmployee && $currentEmployee->id === $plan->manager_id;

        $activePip = null;
        if ($plan->pip_triggered) {
            $activePip = \App\Domains\HRMS\Models\PerformanceImprovementPlan::where('tenant_id', $tenantId)
                ->where('employee_id', $plan->employee_id)
                ->latest()
                ->first();
        }

        return view('modules.hrms.kra-kpi.show', compact(
            'plan',
            'kraCategories',
            'kpiMasters',
            'currentEmployee',
            'isHrOrAdmin',
            'isOwner',
            'isManager',
            'activePip'
        ));
    }

    /**
     * Store new Appraisal Cycle.
     */
    public function storeCycle(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'period_type' => 'required|in:annual,semi_annual,quarterly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'goal_setting_deadline' => 'nullable|date',
            'self_review_deadline' => 'nullable|date',
            'manager_review_deadline' => 'nullable|date',
            'goal_weightage_percent' => 'nullable|numeric|min:0|max:100',
            'competency_weightage_percent' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|in:draft,goal_setting,in_progress,in_review,calibration,completed,archived',
            'description' => 'nullable|string',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['goal_weightage_percent'] = $validated['goal_weightage_percent'] ?? 70.0;
        $validated['competency_weightage_percent'] = $validated['competency_weightage_percent'] ?? 30.0;

        AppraisalCycle::create($validated);

        return redirect()->back()->with('success', 'Appraisal Cycle created successfully.');
    }

    /**
     * Update Appraisal Cycle.
     */
    public function updateCycle(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $cycle = AppraisalCycle::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'period_type' => 'required|in:annual,semi_annual,quarterly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'goal_setting_deadline' => 'nullable|date',
            'self_review_deadline' => 'nullable|date',
            'manager_review_deadline' => 'nullable|date',
            'status' => 'required|in:draft,goal_setting,in_progress,in_review,calibration,completed,archived',
            'description' => 'nullable|string',
        ]);

        $cycle->update($validated);

        return redirect()->back()->with('success', 'Appraisal Cycle updated successfully.');
    }

    /**
     * Delete Appraisal Cycle.
     */
    public function deleteCycle(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $cycle = AppraisalCycle::where('tenant_id', $tenantId)->findOrFail($id);
        $cycle->delete();

        return redirect()->back()->with('success', 'Appraisal Cycle deleted successfully.');
    }

    /**
     * Store KRA Category.
     */
    public function storeKraCategory(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['color'] = $validated['color'] ?? '#3b82f6';
        $validated['status'] = 'active';

        KraCategory::create($validated);

        return redirect()->back()->with('success', 'KRA Category added to library.');
    }

    /**
     * Delete KRA Category.
     */
    public function deleteKraCategory(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $kra = KraCategory::where('tenant_id', $tenantId)->findOrFail($id);
        $kra->delete();

        return redirect()->back()->with('success', 'KRA Focus Area deleted successfully.');
    }

    /**
     * Store KPI Master metric.
     */
    public function storeKpiMaster(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'kra_category_id' => 'required|exists:kra_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'unit' => 'required|in:percentage,currency,number,rating,boolean',
            'calculation_type' => 'required|in:higher_is_better,lower_is_better,milestone',
            'default_target' => 'required|numeric',
            'default_weightage' => 'required|numeric|min:1|max:100',
            'description' => 'nullable|string',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = 'active';

        KpiMaster::create($validated);

        return redirect()->back()->with('success', 'KPI Metric Master created successfully.');
    }

    /**
     * Delete KPI Master Metric.
     */
    public function deleteKpiMaster(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $km = KpiMaster::where('tenant_id', $tenantId)->findOrFail($id);
        $km->delete();

        return redirect()->back()->with('success', 'KPI Metric deleted successfully.');
    }

    /**
     * Delete Role KPI Template.
     */
    public function deleteTemplate(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $tpl = KpiTemplate::where('tenant_id', $tenantId)->findOrFail($id);
        
        DB::transaction(function () use ($tpl) {
            $tpl->items()->delete();
            $tpl->delete();
        });

        return redirect()->back()->with('success', 'Role KPI Template deleted successfully.');
    }

    /**
     * Delete Employee Scorecard.
     */
    public function deletePlan(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);
        
        DB::transaction(function () use ($plan) {
            $plan->items()->delete();
            $plan->reviews()->delete();
            $plan->delete();
        });

        return redirect()->back()->with('success', 'Employee Scorecard deleted successfully.');
    }

    /**
     * Store KPI Template with item rows.
     */
    public function storeTemplate(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|max:255',
            'items.*.kra_category_id' => 'nullable|exists:kra_categories,id',
            'items.*.unit' => 'required|in:percentage,currency,number,rating,boolean',
            'items.*.calculation_type' => 'required|in:higher_is_better,lower_is_better,milestone',
            'items.*.target' => 'required|numeric',
            'items.*.weightage' => 'required|numeric|min:1|max:100',
        ]);

        DB::transaction(function () use ($validated, $tenantId) {
            $template = KpiTemplate::create([
                'tenant_id' => $tenantId,
                'department_id' => $validated['department_id'] ?? null,
                'designation_id' => $validated['designation_id'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => 'active',
            ]);

            foreach ($validated['items'] as $item) {
                KpiTemplateItem::create([
                    'tenant_id' => $tenantId,
                    'kpi_template_id' => $template->id,
                    'kra_category_id' => $item['kra_category_id'] ?? null,
                    'title' => $item['title'],
                    'unit' => $item['unit'],
                    'calculation_type' => $item['calculation_type'],
                    'target' => $item['target'],
                    'weightage' => $item['weightage'],
                ]);
            }
        });

        return redirect()->back()->with('success', 'KPI Template pack created successfully.');
    }

    /**
     * Update KPI Template with item rows.
     */
    public function updateTemplate(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $template = KpiTemplate::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|max:255',
            'items.*.kra_category_id' => 'nullable|exists:kra_categories,id',
            'items.*.unit' => 'required|in:percentage,currency,number,rating,boolean',
            'items.*.calculation_type' => 'required|in:higher_is_better,lower_is_better,milestone',
            'items.*.target' => 'required|numeric',
            'items.*.weightage' => 'required|numeric|min:1|max:100',
        ]);

        DB::transaction(function () use ($template, $validated, $tenantId) {
            $template->update([
                'department_id' => $validated['department_id'] ?? null,
                'designation_id' => $validated['designation_id'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            // Re-sync template items
            $template->items()->delete();

            foreach ($validated['items'] as $item) {
                KpiTemplateItem::create([
                    'tenant_id' => $tenantId,
                    'kpi_template_id' => $template->id,
                    'kra_category_id' => $item['kra_category_id'] ?? null,
                    'title' => $item['title'],
                    'unit' => $item['unit'],
                    'calculation_type' => $item['calculation_type'],
                    'target' => $item['target'],
                    'weightage' => $item['weightage'],
                ]);
            }
        });

        return redirect()->back()->with('success', 'Role KPI Template updated successfully.');
    }

    /**
     * Bulk Assign Template to Employees.
     */
    public function assignTemplateToEmployees(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'appraisal_cycle_id' => 'required|exists:appraisal_cycles,id',
            'kpi_template_id' => 'nullable|exists:kpi_templates,id',
            'target_type' => 'required|in:individual,department,all',
            'employee_id' => 'nullable|exists:employees,id',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $cycleId = $validated['appraisal_cycle_id'];
        $templateId = $validated['kpi_template_id'] ?? null;

        $targetEmployees = collect();

        if ($validated['target_type'] === 'individual' && !empty($validated['employee_id'])) {
            $targetEmployees = Employee::where('tenant_id', $tenantId)->where('id', $validated['employee_id'])->get();
        } elseif ($validated['target_type'] === 'department' && !empty($validated['department_id'])) {
            $targetEmployees = Employee::where('tenant_id', $tenantId)->where('department_id', $validated['department_id'])->where('status', true)->get();
        } else {
            $targetEmployees = Employee::where('tenant_id', $tenantId)->where('status', true)->get();
        }

        $assignedCount = 0;
        foreach ($targetEmployees as $emp) {
            $this->kraKpiService->assignTemplateToEmployee($emp->id, $cycleId, $templateId, $tenantId);
            $assignedCount++;
        }

        return redirect()->back()->with('success', sprintf('Successfully created/assigned %d Goal Scorecards for this cycle.', $assignedCount));
    }

    /**
     * Add single KPI Goal item to Scorecard.
     */
    public function addGoalItem(Request $request, int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $validated = $request->validate([
            'kra_category_id' => 'nullable|exists:kra_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|in:percentage,currency,number,rating,boolean',
            'calculation_type' => 'required|in:higher_is_better,lower_is_better,milestone',
            'target' => 'required|numeric',
            'weightage' => 'required|numeric|min:1|max:100',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['employee_goal_plan_id'] = $plan->id;
        $validated['status'] = 'pending';

        EmployeeGoalItem::create($validated);
        $this->kraKpiService->recalculatePlanScore($plan);

        return redirect()->back()->with('success', 'KPI Goal added to scorecard.');
    }

    /**
     * Delete KPI Goal item from Scorecard.
     */
    public function deleteGoalItem(int $itemId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $item = EmployeeGoalItem::where('tenant_id', $tenantId)->findOrFail($itemId);
        $plan = $item->goalPlan;

        $item->delete();
        $this->kraKpiService->recalculatePlanScore($plan);

        return redirect()->back()->with('success', 'KPI Goal removed from scorecard.');
    }

    /**
     * Employee submits Goals to Manager for sign-off.
     */
    public function submitGoals(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $this->kraKpiService->recalculatePlanScore($plan);

        $plan->status = 'submitted';
        $plan->submitted_at = Carbon::now();
        $plan->save();

        return redirect()->back()->with('success', 'Goals locked and submitted to Manager for approval.');
    }

    /**
     * Manager approves Employee Goals.
     */
    public function approveGoals(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $plan->status = 'approved';
        $plan->approved_at = Carbon::now();
        $plan->save();

        return redirect()->back()->with('success', 'Goal plan approved. Active for cycle tracking.');
    }

    /**
     * Log mid-cycle progress against a KPI.
     */
    public function logProgress(Request $request, int $itemId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $item = EmployeeGoalItem::where('tenant_id', $tenantId)->findOrFail($itemId);
        $plan = $item->goalPlan;

        $validated = $request->validate([
            'current_value' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        $prev = $item->actual;
        $item->actual = $validated['current_value'];
        $item->save();

        GoalProgressLog::create([
            'tenant_id' => $tenantId,
            'employee_goal_item_id' => $item->id,
            'employee_id' => $plan->employee_id,
            'logged_by_id' => auth()->id(),
            'previous_value' => $prev,
            'current_value' => $validated['current_value'],
            'notes' => $validated['notes'] ?? 'Progress updated',
        ]);

        $this->kraKpiService->recalculatePlanScore($plan);

        return redirect()->back()->with('success', 'Progress check-in logged successfully.');
    }

    /**
     * Submit Self-Appraisal (Employee Ratings & Comments).
     */
    public function submitSelfAppraisal(Request $request, int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.self_rating' => 'nullable|numeric|min:1|max:5',
            'items.*.self_comment' => 'nullable|string',
            'employee_comments' => 'nullable|string',
        ]);

        foreach ($validated['items'] as $itemId => $data) {
            $item = EmployeeGoalItem::where('tenant_id', $tenantId)
                ->where('employee_goal_plan_id', $plan->id)
                ->find($itemId);

            if ($item) {
                $item->self_rating = $data['self_rating'] ?? null;
                $item->self_comment = $data['self_comment'] ?? null;
                $item->save();
            }
        }

        $plan->employee_comments = $validated['employee_comments'] ?? $plan->employee_comments;
        $plan->status = 'self_reviewed';
        $plan->self_reviewed_at = Carbon::now();
        $plan->save();

        $this->kraKpiService->recalculatePlanScore($plan);

        return redirect()->back()->with('success', 'Self-Appraisal submitted successfully. Routed to Manager for review.');
    }

    /**
     * Submit Manager-Appraisal (Manager Ratings, Scores & Feedback).
     */
    public function submitManagerAppraisal(Request $request, int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.manager_rating' => 'required|numeric|min:1|max:5',
            'items.*.manager_comment' => 'nullable|string',
            'manager_comments' => 'nullable|string',
            'competency_score' => 'nullable|numeric|min:0|max:150',
            'promotion_recommended' => 'nullable|boolean',
        ]);

        foreach ($validated['items'] as $itemId => $data) {
            $item = EmployeeGoalItem::where('tenant_id', $tenantId)
                ->where('employee_goal_plan_id', $plan->id)
                ->find($itemId);

            if ($item) {
                $item->manager_rating = $data['manager_rating'];
                $item->manager_comment = $data['manager_comment'] ?? null;
                $item->save();
            }
        }

        $plan->competency_score = $validated['competency_score'] ?? 85.0;
        $plan->manager_comments = $validated['manager_comments'] ?? null;
        $plan->promotion_recommended = $request->has('promotion_recommended');
        $plan->status = 'manager_reviewed';
        $plan->manager_reviewed_at = Carbon::now();
        $plan->save();

        $this->kraKpiService->recalculatePlanScore($plan);

        return redirect()->back()->with('success', 'Manager Appraisal submitted. Ready for HR Calibration.');
    }

    /**
     * HR / Committee Calibration & Normalization.
     */
    public function calibrateAppraisal(Request $request, int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $validated = $request->validate([
            'normalized_score' => 'required|numeric|min:0|max:150',
            'final_grade' => 'required|string|max:100',
            'hr_comments' => 'nullable|string',
        ]);

        $plan->normalized_score = $validated['normalized_score'];
        $plan->final_grade = $validated['final_grade'];
        $plan->hr_comments = $validated['hr_comments'] ?? null;
        $plan->status = 'calibrated';
        $plan->calibrated_at = Carbon::now();
        $plan->save();

        return redirect()->back()->with('success', 'Score calibrated & normalized. Published for Employee Sign-Off.');
    }

    /**
     * Employee Final Acknowledgement & Sign-Off.
     */
    public function signOffAppraisal(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $plan->status = 'signed_off';
        $plan->signed_off_at = Carbon::now();
        $plan->save();

        return redirect()->back()->with('success', 'Appraisal signed off and completed.');
    }

    /**
     * 1-Click Transition from Low Appraisal Score to PIP Module.
     */
    public function triggerPip(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $pip = $this->kraKpiService->triggerPipFromLowPerformance($plan, auth()->id());

        return redirect()->route('hrms.pip.show', $pip->id)
            ->with('success', sprintf('Performance Improvement Plan #%s generated successfully from low appraisal scores.', $pip->pip_number));
    }
}
