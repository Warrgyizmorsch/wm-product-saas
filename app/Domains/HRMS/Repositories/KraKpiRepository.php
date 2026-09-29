<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\AppraisalCycle;
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
use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Services\KraKpiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KraKpiRepository implements KraKpiRepositoryInterface
{
    public function __construct(
        private readonly KraKpiService $kraKpiService
    ) {}

    /**
     * Retrieve all data required for the main KRA & KPI dashboard / index view.
     */
    public function getIndexData(array $inputs, ?Employee $currentEmployee, bool $isHrOrAdmin, int $tenantId): array
    {
        $this->kraKpiService->ensureTablesExist($tenantId);

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
        if (!empty($inputs['filter_cycle'])) {
            $plansQuery->where('appraisal_cycle_id', $inputs['filter_cycle']);
        }

        // Filter: Status
        if (!empty($inputs['filter_status'])) {
            if ($inputs['filter_status'] === 'overdue') {
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
                $plansQuery->where('status', $inputs['filter_status']);
            }
        }

        // Filter: Department
        if (!empty($inputs['filter_department'])) {
            $plansQuery->whereHas('employee', function ($q) use ($inputs) {
                $q->where('department_id', $inputs['filter_department']);
            });
        }

        // Search: Employee Name or Plan Number
        if (!empty($inputs['search'])) {
            $term = '%' . $inputs['search'] . '%';
            $plansQuery->where(function ($q) use ($term) {
                $q->where('plan_number', 'like', $term)
                  ->orWhereHas('employee', function ($eq) use ($term) {
                      $eq->where('full_name', 'like', $term)
                         ->orWhere('employee_id', 'like', $term);
                  });
            });
        }

        // Sorting
        $sort = $inputs['sort'] ?? 'latest';
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

        $activeTab = $inputs['active_tab'] ?? 'plans';

        return compact(
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
        );
    }

    /**
     * Retrieve data for single employee scorecard / appraisal breakdown.
     */
    public function getScorecardData(int $id, ?Employee $currentEmployee, bool $isHrOrAdmin, int $tenantId): array
    {
        $this->kraKpiService->ensureTablesExist($tenantId);

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
            $activePip = PerformanceImprovementPlan::where('tenant_id', $tenantId)
                ->where('employee_id', $plan->employee_id)
                ->latest()
                ->first();
        }

        return compact(
            'plan',
            'kraCategories',
            'kpiMasters',
            'currentEmployee',
            'isHrOrAdmin',
            'isOwner',
            'isManager',
            'activePip'
        );
    }

    /**
     * Store new Appraisal Cycle.
     */
    public function storeCycle(array $validated, int $tenantId): AppraisalCycle
    {
        $validated['tenant_id'] = $tenantId;
        $validated['goal_weightage_percent'] = $validated['goal_weightage_percent'] ?? 70.0;
        $validated['competency_weightage_percent'] = $validated['competency_weightage_percent'] ?? 30.0;

        return AppraisalCycle::create($validated);
    }

    /**
     * Update existing Appraisal Cycle.
     */
    public function updateCycle(int $id, array $validated, int $tenantId): AppraisalCycle
    {
        $cycle = AppraisalCycle::where('tenant_id', $tenantId)->findOrFail($id);
        $cycle->update($validated);

        return $cycle;
    }

    /**
     * Delete Appraisal Cycle.
     */
    public function deleteCycle(int $id, int $tenantId): bool
    {
        $cycle = AppraisalCycle::where('tenant_id', $tenantId)->findOrFail($id);
        return (bool) $cycle->delete();
    }

    /**
     * Store KRA Category.
     */
    public function storeKraCategory(array $validated, int $tenantId): KraCategory
    {
        $validated['tenant_id'] = $tenantId;
        $validated['color'] = $validated['color'] ?? '#3b82f6';
        $validated['status'] = 'active';

        return KraCategory::create($validated);
    }

    /**
     * Delete KRA Category.
     */
    public function deleteKraCategory(int $id, int $tenantId): bool
    {
        $kra = KraCategory::where('tenant_id', $tenantId)->findOrFail($id);
        return (bool) $kra->delete();
    }

    /**
     * Store KPI Master metric.
     */
    public function storeKpiMaster(array $validated, int $tenantId): KpiMaster
    {
        $validated['tenant_id'] = $tenantId;
        $kpi = KpiMaster::create($validated);
        $kpi->load('kraCategory');

        return $kpi;
    }

    /**
     * Delete KPI Master metric.
     */
    public function deleteKpiMaster(int $id, int $tenantId): bool
    {
        $km = KpiMaster::where('tenant_id', $tenantId)->findOrFail($id);
        return (bool) $km->delete();
    }

    /**
     * Store KPI Template with its items.
     */
    public function storeTemplate(array $validated, int $tenantId): KpiTemplate
    {
        return DB::transaction(function () use ($validated, $tenantId) {
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

            return $template;
        });
    }

    /**
     * Update KPI Template with its items.
     */
    public function updateTemplate(int $id, array $validated, int $tenantId): KpiTemplate
    {
        $template = KpiTemplate::where('tenant_id', $tenantId)->findOrFail($id);

        return DB::transaction(function () use ($template, $validated, $tenantId) {
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

            return $template;
        });
    }

    /**
     * Delete KPI Template and its items.
     */
    public function deleteTemplate(int $id, int $tenantId): bool
    {
        $tpl = KpiTemplate::where('tenant_id', $tenantId)->findOrFail($id);
        
        return DB::transaction(function () use ($tpl) {
            $tpl->items()->delete();
            return (bool) $tpl->delete();
        });
    }

    /**
     * Bulk assign template or scorecard to target employees.
     */
    public function assignTemplateToEmployees(array $validated, int $tenantId): int
    {
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

        return $assignedCount;
    }

    /**
     * Delete Employee Scorecard Plan and associated items & reviews.
     */
    public function deletePlan(int $planId, int $tenantId): bool
    {
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);
        
        return DB::transaction(function () use ($plan) {
            $plan->items()->delete();
            $plan->reviews()->delete();
            return (bool) $plan->delete();
        });
    }

    /**
     * Add single KPI goal item to an employee scorecard.
     */
    public function addGoalItem(int $planId, array $validated, int $tenantId): EmployeeGoalItem
    {
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $validated['tenant_id'] = $tenantId;
        $validated['employee_goal_plan_id'] = $plan->id;
        $validated['status'] = 'pending';

        $item = EmployeeGoalItem::create($validated);
        $this->kraKpiService->recalculatePlanScore($plan);

        return $item;
    }

    /**
     * Delete single KPI goal item from scorecard and recalculate score.
     */
    public function deleteGoalItem(int $itemId, int $tenantId): bool
    {
        $item = EmployeeGoalItem::where('tenant_id', $tenantId)->findOrFail($itemId);
        $plan = $item->goalPlan;

        $deleted = (bool) $item->delete();
        $this->kraKpiService->recalculatePlanScore($plan);

        return $deleted;
    }

    /**
     * Submit goals to Manager for sign-off.
     */
    public function submitGoals(int $planId, int $tenantId): EmployeeGoalPlan
    {
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $this->kraKpiService->recalculatePlanScore($plan);

        $plan->status = 'submitted';
        $plan->submitted_at = Carbon::now();
        $plan->save();

        return $plan;
    }

    /**
     * Manager approves goals.
     */
    public function approveGoals(int $planId, int $tenantId): EmployeeGoalPlan
    {
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $plan->status = 'approved';
        $plan->approved_at = Carbon::now();
        $plan->save();

        return $plan;
    }

    /**
     * Log mid-cycle progress against a KPI item.
     */
    public function logProgress(int $itemId, array $validated, ?int $loggedById, int $tenantId): GoalProgressLog
    {
        $item = EmployeeGoalItem::where('tenant_id', $tenantId)->findOrFail($itemId);
        $plan = $item->goalPlan;

        $prev = $item->actual;
        $item->actual = $validated['current_value'];
        $item->save();

        $log = GoalProgressLog::create([
            'tenant_id' => $tenantId,
            'employee_goal_item_id' => $item->id,
            'employee_id' => $plan->employee_id,
            'logged_by_id' => $loggedById,
            'previous_value' => $prev,
            'current_value' => $validated['current_value'],
            'notes' => $validated['notes'] ?? 'Progress updated',
        ]);

        $this->kraKpiService->recalculatePlanScore($plan);

        return $log;
    }

    /**
     * Submit Self-Appraisal.
     */
    public function submitSelfAppraisal(int $planId, array $validated, int $tenantId): EmployeeGoalPlan
    {
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

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

        return $plan;
    }

    /**
     * Submit Manager-Appraisal.
     */
    public function submitManagerAppraisal(int $planId, array $validated, bool $promotionRecommended, int $tenantId): EmployeeGoalPlan
    {
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

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
        $plan->promotion_recommended = $promotionRecommended;
        $plan->status = 'manager_reviewed';
        $plan->manager_reviewed_at = Carbon::now();
        $plan->save();

        $this->kraKpiService->recalculatePlanScore($plan);

        return $plan;
    }

    /**
     * HR / Committee Calibration & Normalization.
     */
    public function calibrateAppraisal(int $planId, array $validated, int $tenantId): EmployeeGoalPlan
    {
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $plan->normalized_score = $validated['normalized_score'];
        $plan->final_grade = $validated['final_grade'];
        $plan->hr_comments = $validated['hr_comments'] ?? null;
        $plan->status = 'calibrated';
        $plan->calibrated_at = Carbon::now();
        $plan->save();

        return $plan;
    }

    /**
     * Employee final sign-off.
     */
    public function signOffAppraisal(int $planId, int $tenantId): EmployeeGoalPlan
    {
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        $plan->status = 'signed_off';
        $plan->signed_off_at = Carbon::now();
        $plan->save();

        return $plan;
    }

    /**
     * Trigger PIP from low appraisal score.
     */
    public function triggerPip(int $planId, ?int $hrUserId, int $tenantId): PerformanceImprovementPlan
    {
        $plan = EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);

        return $this->kraKpiService->triggerPipFromLowPerformance($plan, $hrUserId);
    }

    /**
     * Resolve KRA Category ID from either an ID or a custom typed name.
     */
    public function resolveKraCategoryId(mixed $kraValue, int $tenantId): ?int
    {
        if (empty($kraValue) || $kraValue === '__custom__') {
            return null;
        }
        if (is_numeric($kraValue)) {
            return (int) $kraValue;
        }
        $name = trim((string) $kraValue);
        if ($name === '') {
            return null;
        }

        $kra = KraCategory::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => $name],
            [
                'code' => 'KRA-' . strtoupper(Str::slug(Str::limit($name, 6, ''))),
                'color' => '#3b82f6',
                'status' => 'active'
            ]
        );
        return $kra->id;
    }
}
