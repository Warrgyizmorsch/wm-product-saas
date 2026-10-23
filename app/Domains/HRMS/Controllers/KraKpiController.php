<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Repositories\KraKpiRepositoryInterface;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $isHrOrAdmin = $this->isHrOrAdminUser($user);

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
        $isHrOrAdmin = $this->isHrOrAdminUser($user);

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
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can create appraisal cycles.');

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
            'status' => 'nullable|in:draft,goal_setting,in_progress,in_review,calibration,completed,archived',
            'description' => 'nullable|string',
        ]);

        $validated['status'] = $validated['status'] ?? 'in_progress';
        $validated['goal_weightage_percent'] = $validated['goal_weightage_percent'] ?? 100.0;
        $validated['competency_weightage_percent'] = $validated['competency_weightage_percent'] ?? 0.0;

        $this->kraKpiRepository->storeCycle($validated, $tenantId);

        return redirect()->back()->with('success', 'Appraisal Review Period created successfully.');
    }

    /**
     * Update Appraisal Cycle.
     */
    public function updateCycle(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can update appraisal cycles.');

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
            'status' => 'nullable|in:draft,goal_setting,in_progress,in_review,calibration,completed,archived',
            'description' => 'nullable|string',
        ]);

        if (empty($validated['status'])) {
            unset($validated['status']);
        }

        $this->kraKpiRepository->updateCycle($id, $validated, $tenantId);

        return redirect()->back()->with('success', 'Appraisal Review Period updated successfully.');
    }

    /**
     * Delete Appraisal Cycle.
     */
    public function deleteCycle(int $id): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can delete appraisal cycles.');

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->deleteCycle($id, $tenantId);

        return redirect()->back()->with('success', 'Appraisal Cycle deleted successfully.');
    }

    /**
     * Store KRA Category.
     */
    public function storeKraCategory(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can manage KRA categories.');

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
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can delete KRA categories.');

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->deleteKraCategory($id, $tenantId);

        return redirect()->back()->with('success', 'KRA Focus Area deleted successfully.');
    }

    /**
     * Store KPI Master metric.
     */
    public function storeKpiMaster(Request $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can manage KPI metrics.');

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'kra_category_id' => ['required', Rule::exists('kra_categories', 'id')->where('tenant_id', $tenantId)],
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'unit' => 'required|in:percentage,currency,number,rating,boolean',
            'calculation_type' => 'required|in:higher_is_better,lower_is_better,milestone',
            'default_target' => 'nullable|numeric',
            'default_weightage' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
        ]);

        $validated['default_target'] = $validated['default_target'] ?? 100.00;
        $validated['default_weightage'] = $validated['default_weightage'] ?? 20.00;

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
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can delete KPI metrics.');

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->deleteKpiMaster($id, $tenantId);

        return redirect()->back()->with('success', 'KPI Metric deleted successfully.');
    }

    /**
     * Delete Role KPI Template.
     */
    public function deleteTemplate(int $id): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can delete KPI templates.');

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->deleteTemplate($id, $tenantId);

        return redirect()->back()->with('success', 'Role KPI Template deleted successfully.');
    }

    /**
     * Delete Employee Scorecard.
     */
    public function deletePlan(int $planId): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can delete scorecards.');

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->kraKpiRepository->deletePlan($planId, $tenantId);

        return redirect()->back()->with('success', 'Employee Scorecard deleted successfully.');
    }

    /**
     * Store KPI Template with item rows.
     */
    public function storeTemplate(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can create KPI templates.');

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        if ($request->has('items') && is_array($request->input('items'))) {
            $items = array_values(array_filter($request->input('items'), function ($item) {
                return !empty(trim($item['title'] ?? ''));
            }));
            foreach ($items as $k => $item) {
                if (isset($item['kra_category_id'])) {
                    $items[$k]['kra_category_id'] = $this->kraKpiRepository->resolveKraCategoryId($item['kra_category_id'], $tenantId);
                }
            }
            $request->merge(['items' => $items]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('tenant_id', $tenantId)],
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|max:255',
            'items.*.kra_category_id' => ['nullable', Rule::exists('kra_categories', 'id')->where('tenant_id', $tenantId)],
            'items.*.unit' => 'required|in:percentage,currency,number,rating,boolean',
            'items.*.calculation_type' => 'required|in:higher_is_better,lower_is_better,milestone',
            'items.*.target' => 'required|numeric',
            'items.*.weightage' => 'required|numeric|min:0.01|max:100',
        ], [
            'items.required' => 'Please add at least one KPI goal to the template.',
            'items.min' => 'Please add at least one KPI goal to the template.',
            'items.*.title.required' => 'Every KPI goal row requires a title or standard KPI metric.',
            'items.*.target.required' => 'Every KPI goal row requires a numerical target.',
            'items.*.weightage.required' => 'Every KPI goal row requires a weightage percentage.',
        ]);

        $totalWeight = (float) array_sum(array_column($validated['items'], 'weightage'));
        if (abs($totalWeight - 100.0) > 0.01) {
            return redirect()->back()->withInput()->withErrors([
                'items' => sprintf('Total KPI weightage must equal exactly 100%% to save the template. Current total: %.2f%% (it cannot be less than or exceed 100%%).', $totalWeight)
            ]);
        }

        $this->kraKpiRepository->storeTemplate($validated, $tenantId);

        return redirect()->back()->with('success', 'KPI Template pack created successfully.');
    }

    /**
     * Update KPI Template with item rows.
     */
    public function updateTemplate(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can update KPI templates.');

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        if ($request->has('items') && is_array($request->input('items'))) {
            $items = array_values(array_filter($request->input('items'), function ($item) {
                return !empty(trim($item['title'] ?? ''));
            }));
            foreach ($items as $k => $item) {
                if (isset($item['kra_category_id'])) {
                    $items[$k]['kra_category_id'] = $this->kraKpiRepository->resolveKraCategoryId($item['kra_category_id'], $tenantId);
                }
            }
            $request->merge(['items' => $items]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('tenant_id', $tenantId)],
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|max:255',
            'items.*.kra_category_id' => ['nullable', Rule::exists('kra_categories', 'id')->where('tenant_id', $tenantId)],
            'items.*.unit' => 'required|in:percentage,currency,number,rating,boolean',
            'items.*.calculation_type' => 'required|in:higher_is_better,lower_is_better,milestone',
            'items.*.target' => 'required|numeric',
            'items.*.weightage' => 'required|numeric|min:0.01|max:100',
        ], [
            'items.required' => 'Please add at least one KPI goal to the template.',
            'items.min' => 'Please add at least one KPI goal to the template.',
            'items.*.title.required' => 'Every KPI goal row requires a title or standard KPI metric.',
            'items.*.target.required' => 'Every KPI goal row requires a numerical target.',
            'items.*.weightage.required' => 'Every KPI goal row requires a weightage percentage.',
        ]);

        $totalWeight = (float) array_sum(array_column($validated['items'], 'weightage'));
        if (abs($totalWeight - 100.0) > 0.01) {
            return redirect()->back()->withInput()->withErrors([
                'items' => sprintf('Total KPI weightage must equal exactly 100%% to update the template. Current total: %.2f%% (it cannot be less than or exceed 100%%).', $totalWeight)
            ]);
        }

        $this->kraKpiRepository->updateTemplate($id, $validated, $tenantId);

        return redirect()->back()->with('success', 'Role KPI Template updated successfully.');
    }

    /**
     * Bulk Assign Template to Employees.
     */
    public function assignTemplateToEmployees(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($this->isHrOrAdminUser($user), 403, 'Unauthorized. Only HR Admins can assign templates to employees.');

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        // Check if user is creating a new appraisal cycle on the fly
        $cycleMode = $request->input('cycle_mode', 'existing');
        if ($cycleMode === 'new' || !$request->filled('appraisal_cycle_id')) {
            $cycleData = $request->validate([
                'new_cycle_name' => 'required|string|max:255',
                'period_type' => 'nullable|in:annual,semi_annual,quarterly,monthly',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'self_review_deadline' => 'nullable|date',
                'goal_setting_deadline' => 'nullable|date',
            ]);

            $newCycle = $this->kraKpiRepository->storeCycle([
                'name' => $cycleData['new_cycle_name'],
                'period_type' => $cycleData['period_type'] ?? 'monthly',
                'start_date' => $cycleData['start_date'],
                'end_date' => $cycleData['end_date'],
                'goal_setting_deadline' => $cycleData['goal_setting_deadline'] ?? null,
                'self_review_deadline' => $cycleData['self_review_deadline'] ?? null,
                'status' => 'goal_setting',
                'goal_weightage_percent' => 100.0,
                'competency_weightage_percent' => 0.0,
            ], $tenantId);

            $request->merge(['appraisal_cycle_id' => $newCycle->id]);
        }

        $validated = $request->validate([
            'appraisal_cycle_id' => ['required', Rule::exists('appraisal_cycles', 'id')->where('tenant_id', $tenantId)],
            'kpi_template_id' => ['nullable', Rule::exists('kpi_templates', 'id')->where('tenant_id', $tenantId)],
            'target_type' => 'required|in:individual,department,all',
            'employee_id' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
        ]);

        $assignedCount = $this->kraKpiRepository->assignTemplateToEmployees($validated, $tenantId);

        return redirect()->back()->with('success', sprintf('Successfully created/assigned %d Goal Scorecards for this cycle.', $assignedCount));
    }

    /**
     * Add KPI Goal item(s) to Scorecard.
     */
    public function addGoalItem(Request $request, int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $this->isHrOrAdminUser($user);

        $plan = \App\Domains\HRMS\Models\EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);
        $isOwner = ($currentEmployee && $plan->employee_id == $currentEmployee->id) || ($user && $plan->employee?->user_id == $user->id);
        $isManager = ($currentEmployee && ($plan->manager_id == $currentEmployee->id || $plan->employee?->reporting_manager_id == $currentEmployee->id))
            || ($user && ($plan->manager?->user_id == $user->id || $plan->employee?->reportingManager?->user_id == $user->id));

        $canModify = $isHrOrAdmin || $isManager || ($isOwner && in_array($plan->status, ['draft', 'submitted']));
        if (!$canModify) {
            return redirect()->back()->with('error', 'You are not authorized to add or modify KPI goals on this goal sheet.');
        }

        // 1. Batch Multi-Row Submission (from multi-row repeater table)
        if ($request->has('items') && is_array($request->input('items'))) {
            $itemsData = $request->input('items');
            $validatedItems = [];

            foreach ($itemsData as $row) {
                if (empty($row['title']) || trim($row['title']) === '') {
                    continue;
                }

                $rawUnit = $row['unit'] ?? 'percentage';
                $unit = match($rawUnit) {
                    '%', 'percentage' => 'percentage',
                    '₹', '$', 'currency' => 'currency',
                    'number', 'qty' => 'number',
                    'rating' => 'rating',
                    'boolean', 'milestone' => 'boolean',
                    default => 'percentage',
                };

                $calc = in_array($row['calculation_type'] ?? '', ['higher_is_better', 'lower_is_better', 'milestone'])
                    ? $row['calculation_type']
                    : 'higher_is_better';

                $kraId = !empty($row['kra_category_id'])
                    ? $this->kraKpiRepository->resolveKraCategoryId($row['kra_category_id'], $tenantId)
                    : null;

                $itemPayload = [
                    'title' => trim($row['title']),
                    'kra_category_id' => $kraId,
                    'calculation_type' => $calc,
                    'unit' => $unit,
                    'target' => is_numeric($row['target'] ?? null) ? (float) $row['target'] : 100.0,
                    'weightage' => is_numeric($row['weightage'] ?? null) ? (float) $row['weightage'] : 20.0,
                    'description' => !empty($row['description']) ? trim($row['description']) : null,
                ];

                if (!empty($row['id']) && is_numeric($row['id'])) {
                    $itemPayload['id'] = (int) $row['id'];
                }

                $validatedItems[] = $itemPayload;
            }

            if (empty($validatedItems)) {
                return redirect()->back()->with('error', 'Please provide at least one valid KPI goal title.');
            }

            $totalSum = (float) array_sum(array_column($validatedItems, 'weightage'));
            if ($totalSum > 100.0) {
                return redirect()->back()->with('error', "Total goal weightage cannot exceed 100% (currently {$totalSum}%). Please reduce weights before saving.");
            }

            $count = $this->kraKpiRepository->syncGoalItems($planId, $validatedItems, $tenantId);

            return redirect()->back()->with('success', "Scorecard goals and weights updated successfully ({$count} goals active).");
        }

        // 2. Fallback for single-item submission
        if ($request->has('kra_category_id')) {
            $request->merge(['kra_category_id' => $this->kraKpiRepository->resolveKraCategoryId($request->input('kra_category_id'), $tenantId)]);
        }

        $validated = $request->validate([
            'kra_category_id' => ['nullable', Rule::exists('kra_categories', 'id')->where('tenant_id', $tenantId)],
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
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $this->isHrOrAdminUser($user);

        $item = \App\Domains\HRMS\Models\EmployeeGoalItem::where('tenant_id', $tenantId)->findOrFail($itemId);
        $plan = $item->goalPlan;
        $isOwner = ($currentEmployee && $plan->employee_id == $currentEmployee->id) || ($user && $plan->employee?->user_id == $user->id);
        $isManager = ($currentEmployee && ($plan->manager_id == $currentEmployee->id || $plan->employee?->reporting_manager_id == $currentEmployee->id))
            || ($user && ($plan->manager?->user_id == $user->id || $plan->employee?->reportingManager?->user_id == $user->id));

        $canDelete = $isHrOrAdmin || $isManager || ($isOwner && in_array($plan->status, ['draft', 'submitted']));
        if (!$canDelete) {
            return redirect()->back()->with('error', 'You are not authorized to remove KPI goals from this goal sheet.');
        }

        $this->kraKpiRepository->deleteGoalItem($itemId, $tenantId);

        return redirect()->back()->with('success', 'KPI Goal removed from scorecard.');
    }

    /**
     * Employee submits Goals to Manager for sign-off.
     */
    public function submitGoals(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $this->isHrOrAdminUser($user);

        $plan = \App\Domains\HRMS\Models\EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);
        $isOwner = ($currentEmployee && $plan->employee_id == $currentEmployee->id) || ($user && $plan->employee?->user_id == $user->id);
        if (!$isOwner && !$isHrOrAdmin) {
            return redirect()->back()->with('error', 'Unauthorized. Only the assigned employee can submit this goal plan.');
        }

        try {
            $this->kraKpiRepository->submitGoals($planId, $tenantId);
            return redirect()->back()->with('success', 'Goals locked and submitted to Manager for approval.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Manager approves Employee Goals.
     */
    public function approveGoals(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $this->isHrOrAdminUser($user);

        $plan = \App\Domains\HRMS\Models\EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);
        $isManager = ($currentEmployee && ($plan->manager_id == $currentEmployee->id || $plan->employee?->reporting_manager_id == $currentEmployee->id))
            || ($user && ($plan->manager?->user_id == $user->id || $plan->employee?->reportingManager?->user_id == $user->id));
        if (!$isManager && !$isHrOrAdmin) {
            return redirect()->back()->with('error', 'Unauthorized. Only the manager or HR Admin can approve this goal plan.');
        }

        try {
            $this->kraKpiRepository->approveGoals($planId, $tenantId);
            return redirect()->back()->with('success', 'Goal plan approved. Active for cycle tracking.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Log mid-cycle progress against a KPI.
     */
    public function logProgress(Request $request, int $itemId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $this->isHrOrAdminUser($user);

        $item = \App\Domains\HRMS\Models\EmployeeGoalItem::where('tenant_id', $tenantId)->findOrFail($itemId);
        $plan = $item->goalPlan;
        $isOwner = ($currentEmployee && $plan->employee_id == $currentEmployee->id) || ($user && $plan->employee?->user_id == $user->id);
        $isManager = ($currentEmployee && ($plan->manager_id == $currentEmployee->id || $plan->employee?->reporting_manager_id == $currentEmployee->id))
            || ($user && ($plan->manager?->user_id == $user->id || $plan->employee?->reportingManager?->user_id == $user->id));

        if (!$isOwner && !$isManager && !$isHrOrAdmin) {
            return redirect()->back()->with('error', 'Unauthorized to log progress for this KPI goal.');
        }

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
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $this->isHrOrAdminUser($user);

        $plan = \App\Domains\HRMS\Models\EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);
        $isOwner = ($currentEmployee && $plan->employee_id == $currentEmployee->id) || ($user && $plan->employee?->user_id == $user->id);
        if (!$isOwner && !$isHrOrAdmin) {
            return redirect()->back()->with('error', 'Unauthorized. Only the assigned employee can submit self-appraisal.');
        }

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.self_rating' => 'nullable|numeric|min:1|max:5',
            'items.*.self_comment' => 'nullable|string',
            'employee_comments' => 'nullable|string',
        ]);

        try {
            $this->kraKpiRepository->submitSelfAppraisal($planId, $validated, $tenantId);
            return redirect()->back()->with('success', 'Self-Appraisal submitted successfully. Routed to Manager for review.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Submit Manager-Appraisal (Manager Ratings, Scores & Feedback).
     */
    public function submitManagerAppraisal(Request $request, int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $this->isHrOrAdminUser($user);

        $plan = \App\Domains\HRMS\Models\EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);
        $isManager = ($currentEmployee && ($plan->manager_id == $currentEmployee->id || $plan->employee?->reporting_manager_id == $currentEmployee->id))
            || ($user && ($plan->manager?->user_id == $user->id || $plan->employee?->reportingManager?->user_id == $user->id));
        if (!$isManager && !$isHrOrAdmin) {
            return redirect()->back()->with('error', 'Unauthorized. Only the manager or HR Admin can submit manager appraisal.');
        }

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.manager_rating' => 'required|numeric|min:1|max:5',
            'items.*.manager_comment' => 'nullable|string',
            'manager_comments' => 'nullable|string',
            'competency_score' => 'nullable|numeric|min:0|max:150',
            'promotion_recommended' => 'nullable|boolean',
        ]);

        try {
            $this->kraKpiRepository->submitManagerAppraisal(
                $planId,
                $validated,
                $request->has('promotion_recommended'),
                $tenantId
            );

            return redirect()->back()->with('success', 'Manager Appraisal submitted successfully. Performance review is completed and finalized.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }



    /**
     * 1-Click Transition from Low Appraisal Score to PIP Module.
     */
    public function triggerPip(int $planId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $this->isHrOrAdminUser($user);

        $plan = \App\Domains\HRMS\Models\EmployeeGoalPlan::where('tenant_id', $tenantId)->findOrFail($planId);
        $isManager = ($currentEmployee && ($plan->manager_id == $currentEmployee->id || $plan->employee?->reporting_manager_id == $currentEmployee->id))
            || ($user && ($plan->manager?->user_id == $user->id || $plan->employee?->reportingManager?->user_id == $user->id));
        if (!$isManager && !$isHrOrAdmin) {
            return redirect()->back()->with('error', 'Unauthorized. Only the manager or HR Admin can initiate PIP from an appraisal.');
        }

        $pip = $this->kraKpiRepository->triggerPip($planId, auth()->id(), $tenantId);

        return redirect()->route('hrms.pip.show', $pip->id)
            ->with('success', sprintf('Performance Improvement Plan #%s generated successfully from low appraisal scores.', $pip->pip_number));
    }

    /**
     * Resolve whether the user has administrative/HR permissions dynamically without hardcoded roles.
     */
    protected function isHrOrAdminUser(?\App\Models\User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->scopeService->isCompanyAdmin($user)) {
            return true;
        }

        if (method_exists($user, 'hasHrPermission') && (
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.performance.manage') ||
            $user->hasHrPermission('hrms.kpi.manage') ||
            $user->hasHrPermission('hrms.employees.view')
        )) {
            return true;
        }

        if (method_exists($user, 'can') && (
            $user->can('hrms.performance.manage') ||
            $user->can('hr.settings.manage')
        )) {
            return true;
        }

        return false;
    }
}
