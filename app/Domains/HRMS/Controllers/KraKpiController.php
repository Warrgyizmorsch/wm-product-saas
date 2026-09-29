<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Repositories\KraKpiRepositoryInterface;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KraKpiController extends Controller
{
    public function __construct(
        private readonly KraKpiRepositoryInterface $kraKpiRepository,
        private readonly HrmsScopeService $scopeService
    ) {}

    /**
     * Display the Enterprise KRA & KPI Performance Hub.
     */
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $user && (
            $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.performance.manage') ||
            $user->hasHrPermission('hrms.kpi.manage') ||
            $user->hasHrPermission('hrms.employees.view')
        );

        $data = $this->kraKpiRepository->getIndexData(
            $request->all(),
            $currentEmployee,
            (bool) $isHrOrAdmin,
            $tenantId
        );

        return view('modules.hrms.kra-kpi.index', $data);
    }

    /**
     * Show detailed employee scorecard / appraisal breakdown.
     */
    public function show(int $id): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $user && (
            $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.performance.manage') ||
            $user->hasHrPermission('hrms.kpi.manage') ||
            $user->hasHrPermission('hrms.employees.view')
        );

        $data = $this->kraKpiRepository->getScorecardData(
            $id,
            $currentEmployee,
            (bool) $isHrOrAdmin,
            $tenantId
        );

        return view('modules.hrms.kra-kpi.show', $data);
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

        $this->kraKpiRepository->storeCycle($validated, $tenantId);

        return redirect()->back()->with('success', 'Appraisal Cycle created successfully.');
    }

    /**
     * Update Appraisal Cycle.
     */
    public function updateCycle(Request $request, int $id): RedirectResponse
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
            'status' => 'required|in:draft,goal_setting,in_progress,in_review,calibration,completed,archived',
            'description' => 'nullable|string',
        ]);

        $this->kraKpiRepository->updateCycle($id, $validated, $tenantId);

        return redirect()->back()->with('success', 'Appraisal Cycle updated successfully.');
    }

    /**
     * Delete Appraisal Cycle.
     */
    public function deleteCycle(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->deleteCycle($id, $tenantId);

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

        $this->kraKpiRepository->storeKraCategory($validated, $tenantId);

        return redirect()->back()->with('success', 'KRA Category added to library.');
    }

    /**
     * Delete KRA Category.
     */
    public function deleteKraCategory(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->deleteKraCategory($id, $tenantId);

        return redirect()->back()->with('success', 'KRA Focus Area deleted successfully.');
    }

    /**
     * Store KPI Master metric.
     */
    public function storeKpiMaster(Request $request): JsonResponse|RedirectResponse
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

        $kpi = $this->kraKpiRepository->storeKpiMaster($validated, $tenantId);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'KPI Metric created successfully.',
                'kpi' => [
                    'id' => $kpi->id,
                    'name' => $kpi->name,
                    'kra_category_id' => $kpi->kra_category_id,
                    'kra_category_name' => $kpi->kraCategory->name ?? 'General',
                    'unit' => $kpi->unit,
                    'calculation_type' => $kpi->calculation_type,
                    'default_target' => $kpi->default_target,
                    'default_weightage' => $kpi->default_weightage,
                ]
            ]);
        }

        return redirect()->back()->with('success', 'KPI Metric Master created successfully.');
    }

    /**
     * Delete KPI Master Metric.
     */
    public function deleteKpiMaster(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->deleteKpiMaster($id, $tenantId);

        return redirect()->back()->with('success', 'KPI Metric deleted successfully.');
    }

    /**
     * Delete Role KPI Template.
     */
    public function deleteTemplate(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->deleteTemplate($id, $tenantId);

        return redirect()->back()->with('success', 'Role KPI Template deleted successfully.');
    }

    /**
     * Delete Employee Scorecard.
     */
    public function deletePlan(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->deletePlan($planId, $tenantId);

        return redirect()->back()->with('success', 'Employee Scorecard deleted successfully.');
    }

    /**
     * Store KPI Template with item rows.
     */
    public function storeTemplate(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        if ($request->has('items') && is_array($request->input('items'))) {
            $items = $request->input('items');
            foreach ($items as $k => $item) {
                if (isset($item['kra_category_id'])) {
                    $items[$k]['kra_category_id'] = $this->kraKpiRepository->resolveKraCategoryId($item['kra_category_id'], $tenantId);
                }
            }
            $request->merge(['items' => $items]);
        }

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

        $this->kraKpiRepository->storeTemplate($validated, $tenantId);

        return redirect()->back()->with('success', 'KPI Template pack created successfully.');
    }

    /**
     * Update KPI Template with item rows.
     */
    public function updateTemplate(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        if ($request->has('items') && is_array($request->input('items'))) {
            $items = $request->input('items');
            foreach ($items as $k => $item) {
                if (isset($item['kra_category_id'])) {
                    $items[$k]['kra_category_id'] = $this->kraKpiRepository->resolveKraCategoryId($item['kra_category_id'], $tenantId);
                }
            }
            $request->merge(['items' => $items]);
        }

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

        $this->kraKpiRepository->updateTemplate($id, $validated, $tenantId);

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

        $assignedCount = $this->kraKpiRepository->assignTemplateToEmployees($validated, $tenantId);

        return redirect()->back()->with('success', sprintf('Successfully created/assigned %d Goal Scorecards for this cycle.', $assignedCount));
    }

    /**
     * Add single KPI Goal item to Scorecard.
     */
    public function addGoalItem(Request $request, int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        if ($request->has('kra_category_id')) {
            $request->merge(['kra_category_id' => $this->kraKpiRepository->resolveKraCategoryId($request->input('kra_category_id'), $tenantId)]);
        }

        $validated = $request->validate([
            'kra_category_id' => 'nullable|exists:kra_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|in:percentage,currency,number,rating,boolean',
            'calculation_type' => 'required|in:higher_is_better,lower_is_better,milestone',
            'target' => 'required|numeric',
            'weightage' => 'required|numeric|min:1|max:100',
        ]);

        $this->kraKpiRepository->addGoalItem($planId, $validated, $tenantId);

        return redirect()->back()->with('success', 'KPI Goal added to scorecard.');
    }

    /**
     * Delete KPI Goal item from Scorecard.
     */
    public function deleteGoalItem(int $itemId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->deleteGoalItem($itemId, $tenantId);

        return redirect()->back()->with('success', 'KPI Goal removed from scorecard.');
    }

    /**
     * Employee submits Goals to Manager for sign-off.
     */
    public function submitGoals(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->submitGoals($planId, $tenantId);

        return redirect()->back()->with('success', 'Goals locked and submitted to Manager for approval.');
    }

    /**
     * Manager approves Employee Goals.
     */
    public function approveGoals(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->approveGoals($planId, $tenantId);

        return redirect()->back()->with('success', 'Goal plan approved. Active for cycle tracking.');
    }

    /**
     * Log mid-cycle progress against a KPI.
     */
    public function logProgress(Request $request, int $itemId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'current_value' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        $this->kraKpiRepository->logProgress($itemId, $validated, auth()->id(), $tenantId);

        return redirect()->back()->with('success', 'Progress check-in logged successfully.');
    }

    /**
     * Submit Self-Appraisal (Employee Ratings & Comments).
     */
    public function submitSelfAppraisal(Request $request, int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.self_rating' => 'nullable|numeric|min:1|max:5',
            'items.*.self_comment' => 'nullable|string',
            'employee_comments' => 'nullable|string',
        ]);

        $this->kraKpiRepository->submitSelfAppraisal($planId, $validated, $tenantId);

        return redirect()->back()->with('success', 'Self-Appraisal submitted successfully. Routed to Manager for review.');
    }

    /**
     * Submit Manager-Appraisal (Manager Ratings, Scores & Feedback).
     */
    public function submitManagerAppraisal(Request $request, int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.manager_rating' => 'required|numeric|min:1|max:5',
            'items.*.manager_comment' => 'nullable|string',
            'manager_comments' => 'nullable|string',
            'competency_score' => 'nullable|numeric|min:0|max:150',
            'promotion_recommended' => 'nullable|boolean',
        ]);

        $this->kraKpiRepository->submitManagerAppraisal(
            $planId,
            $validated,
            $request->has('promotion_recommended'),
            $tenantId
        );

        return redirect()->back()->with('success', 'Manager Appraisal submitted. Ready for HR Calibration.');
    }

    /**
     * HR / Committee Calibration & Normalization.
     */
    public function calibrateAppraisal(Request $request, int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'normalized_score' => 'required|numeric|min:0|max:150',
            'final_grade' => 'required|string|max:100',
            'hr_comments' => 'nullable|string',
        ]);

        $this->kraKpiRepository->calibrateAppraisal($planId, $validated, $tenantId);

        return redirect()->back()->with('success', 'Score calibrated & normalized. Published for Employee Sign-Off.');
    }

    /**
     * Employee Final Acknowledgement & Sign-Off.
     */
    public function signOffAppraisal(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->signOffAppraisal($planId, $tenantId);

        return redirect()->back()->with('success', 'Appraisal signed off and completed.');
    }

    /**
     * 1-Click Transition from Low Appraisal Score to PIP Module.
     */
    public function triggerPip(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $pip = $this->kraKpiRepository->triggerPip($planId, auth()->id(), $tenantId);

        return redirect()->route('hrms.pip.show', $pip->id)
            ->with('success', sprintf('Performance Improvement Plan #%s generated successfully from low appraisal scores.', $pip->pip_number));
    }
}
