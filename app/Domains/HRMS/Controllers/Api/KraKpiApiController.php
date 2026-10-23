<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\AppraisalCycle;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeGoalItem;
use App\Domains\HRMS\Models\EmployeeGoalPlan;
use App\Domains\HRMS\Models\KpiMaster;
use App\Domains\HRMS\Models\KpiTemplate;
use App\Domains\HRMS\Models\KraCategory;
use App\Domains\HRMS\Repositories\KraKpiRepositoryInterface;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class KraKpiApiController extends Controller
{
    public function __construct(
        private readonly KraKpiRepositoryInterface $kraKpiRepository,
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
     * Resolve current employee and tenant context.
     */
    private function resolveContext(): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 1;
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $user && (
            $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.performance.manage') ||
            $user->hasHrPermission('hrms.kpi.manage') ||
            $user->hasHrPermission('hrms.employees.view')
        );

        return [$tenantId, $user, $currentEmployee, (bool) $isHrOrAdmin];
    }

    // =========================================================================
    // 1. SUMMARY / DASHBOARD HUB
    // =========================================================================

    /**
     * GET /api/hrms/kra-kpi/summary
     * Retrieve full enterprise KRA & KPI dashboard state, active cycles, and metrics.
     */
    public function summary(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        try {
            $data = $this->kraKpiRepository->getIndexData(
                $request->all(),
                $currentEmployee,
                $isHrOrAdmin,
                $tenantId
            );

            return $this->sendSuccess($data, 'KRA & KPI summary loaded successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // 2. APPRAISAL CYCLES API
    // =========================================================================

    /**
     * GET /api/hrms/kra-kpi/cycles
     */
    public function indexCycles(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        $cycles = AppraisalCycle::where('tenant_id', $tenantId)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendSuccess($cycles, 'Appraisal cycles retrieved.');
    }

    /**
     * POST /api/hrms/kra-kpi/cycles
     */
    public function storeCycle(Request $request): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to create appraisal cycles.', 403);
        }

        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $cycle = $this->kraKpiRepository->storeCycle($validator->validated(), $tenantId);
            return $this->sendSuccess($cycle, 'Appraisal Cycle created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/hrms/kra-kpi/cycles/{id}
     */
    public function showCycle(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        $cycle = AppraisalCycle::where('tenant_id', $tenantId)->find($id);
        if (!$cycle) {
            return $this->sendError('Appraisal Cycle not found.', 404);
        }

        return $this->sendSuccess($cycle, 'Appraisal Cycle retrieved.');
    }

    /**
     * PUT /api/hrms/kra-kpi/cycles/{id}
     */
    public function updateCycle(Request $request, int $id): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to update appraisal cycles.', 403);
        }

        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $cycle = $this->kraKpiRepository->updateCycle($id, $validator->validated(), $tenantId);
            return $this->sendSuccess($cycle, 'Appraisal Cycle updated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/hrms/kra-kpi/cycles/{id}
     */
    public function destroyCycle(int $id): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to delete appraisal cycles.', 403);
        }

        try {
            $this->kraKpiRepository->deleteCycle($id, $tenantId);
            return $this->sendSuccess(null, 'Appraisal Cycle deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 3. KRA FOCUS AREAS / CATEGORIES API
    // =========================================================================

    /**
     * GET /api/hrms/kra-kpi/categories
     */
    public function indexCategories(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        $categories = KraCategory::where('tenant_id', $tenantId)
            ->withCount('kpiMasters')
            ->orderBy('name')
            ->get();

        return $this->sendSuccess($categories, 'KRA categories retrieved.');
    }

    /**
     * POST /api/hrms/kra-kpi/categories
     */
    public function storeCategory(Request $request): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to create KRA categories.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $category = $this->kraKpiRepository->storeKraCategory($validator->validated(), $tenantId);
            return $this->sendSuccess($category, 'KRA Category created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/hrms/kra-kpi/categories/{id}
     */
    public function destroyCategory(int $id): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to delete KRA categories.', 403);
        }

        try {
            $this->kraKpiRepository->deleteKraCategory($id, $tenantId);
            return $this->sendSuccess(null, 'KRA Category deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 4. KPI MASTER METRICS LIBRARY API
    // =========================================================================

    /**
     * GET /api/hrms/kra-kpi/kpi-masters
     */
    public function indexKpiMasters(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        $masters = KpiMaster::with('kraCategory')
            ->where('tenant_id', $tenantId)
            ->when($request->filled('kra_category_id'), fn($q) => $q->where('kra_category_id', $request->kra_category_id))
            ->orderBy('name')
            ->get();

        return $this->sendSuccess($masters, 'KPI masters retrieved.');
    }

    /**
     * POST /api/hrms/kra-kpi/kpi-masters
     */
    public function storeKpiMaster(Request $request): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to create KPI master metrics.', 403);
        }

        $validator = Validator::make($request->all(), [
            'kra_category_id' => ['required', Rule::exists('kra_categories', 'id')->where('tenant_id', $tenantId)],
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'unit' => 'required|in:percentage,currency,number,rating,boolean',
            'calculation_type' => 'required|in:higher_is_better,lower_is_better,milestone',
            'default_target' => 'nullable|numeric',
            'default_weightage' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $data = $validator->validated();
            $data['default_target'] = $data['default_target'] ?? 100.00;
            $data['default_weightage'] = $data['default_weightage'] ?? 20.00;

            $kpi = $this->kraKpiRepository->storeKpiMaster($data, $tenantId);
            $kpi->load('kraCategory');
            return $this->sendSuccess($kpi, 'KPI Metric created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/hrms/kra-kpi/kpi-masters/{id}
     */
    public function destroyKpiMaster(int $id): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to delete KPI master metrics.', 403);
        }

        try {
            $this->kraKpiRepository->deleteKpiMaster($id, $tenantId);
            return $this->sendSuccess(null, 'KPI Metric deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 5. ROLE KPI TEMPLATES API
    // =========================================================================

    /**
     * GET /api/hrms/kra-kpi/templates
     */
    public function indexTemplates(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        $templates = KpiTemplate::with(['department', 'designation', 'items.kraCategory'])
            ->where('tenant_id', $tenantId)
            ->when($request->filled('department_id'), fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('designation_id'), fn($q) => $q->where('designation_id', $request->designation_id))
            ->orderBy('name')
            ->get();

        return $this->sendSuccess($templates, 'KPI templates retrieved.');
    }

    /**
     * POST /api/hrms/kra-kpi/templates
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to create KPI templates.', 403);
        }

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

        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $validated = $validator->validated();
        $totalWeight = (float) array_sum(array_column($validated['items'] ?? [], 'weightage'));
        if (abs($totalWeight - 100.0) > 0.01) {
            return $this->sendError(
                sprintf('Total KPI weightage must equal exactly 100%% to save the template. Current total: %.2f%% (it cannot be less than or exceed 100%%).', $totalWeight),
                422,
                ['items' => [sprintf('Total KPI weightage must equal exactly 100%%. Current total: %.2f%%.', $totalWeight)]]
            );
        }

        try {
            $template = $this->kraKpiRepository->storeTemplate($validated, $tenantId);
            $template->load(['department', 'designation', 'items.kraCategory']);
            return $this->sendSuccess($template, 'Role KPI Template created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/hrms/kra-kpi/templates/{id}
     */
    public function showTemplate(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        $template = KpiTemplate::with(['department', 'designation', 'items.kraCategory'])
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$template) {
            return $this->sendError('Role KPI Template not found.', 404);
        }

        return $this->sendSuccess($template, 'Role KPI Template retrieved.');
    }

    /**
     * PUT /api/hrms/kra-kpi/templates/{id}
     */
    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to update KPI templates.', 403);
        }

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

        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $validated = $validator->validated();
        $totalWeight = (float) array_sum(array_column($validated['items'] ?? [], 'weightage'));
        if (abs($totalWeight - 100.0) > 0.01) {
            return $this->sendError(
                sprintf('Total KPI weightage must equal exactly 100%% to save the template. Current total: %.2f%% (it cannot be less than or exceed 100%%).', $totalWeight),
                422,
                ['items' => [sprintf('Total KPI weightage must equal exactly 100%%. Current total: %.2f%%.', $totalWeight)]]
            );
        }

        try {
            $template = $this->kraKpiRepository->updateTemplate($id, $validated, $tenantId);
            $template->load(['department', 'designation', 'items.kraCategory']);
            return $this->sendSuccess($template, 'Role KPI Template updated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/hrms/kra-kpi/templates/{id}
     */
    public function destroyTemplate(int $id): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to delete KPI templates.', 403);
        }

        try {
            $this->kraKpiRepository->deleteTemplate($id, $tenantId);
            return $this->sendSuccess(null, 'Role KPI Template deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/kra-kpi/templates/assign
     * Bulk Assign Template to target Employees.
     */
    public function assignTemplate(Request $request): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to assign KPI templates.', 403);
        }

        $validator = Validator::make($request->all(), [
            'appraisal_cycle_id' => ['required', Rule::exists('appraisal_cycles', 'id')->where('tenant_id', $tenantId)],
            'kpi_template_id' => ['nullable', Rule::exists('kpi_templates', 'id')->where('tenant_id', $tenantId)],
            'target_type' => 'required|in:individual,department,all',
            'employee_id' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $assignedCount = $this->kraKpiRepository->assignTemplateToEmployees($validator->validated(), $tenantId);
            return $this->sendSuccess([
                'assigned_count' => $assignedCount,
            ], sprintf('Successfully assigned/generated %d scorecards for this cycle.', $assignedCount));
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 6. EMPLOYEE SCORECARDS & GOAL PLANS API
    // =========================================================================

    /**
     * GET /api/hrms/kra-kpi/scorecards
     * List employee scorecards with search, filter, scope and pagination.
     */
    public function indexScorecards(Request $request): JsonResponse
    {
        [$tenantId, , $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        $query = EmployeeGoalPlan::with(['employee.department', 'employee.designation', 'manager', 'appraisalCycle'])
            ->where('tenant_id', $tenantId);

        // Scope access
        if (!$isHrOrAdmin && $currentEmployee) {
            $query->where(function ($q) use ($currentEmployee) {
                $q->where('employee_id', $currentEmployee->id)
                  ->orWhere('manager_id', $currentEmployee->id);
            });
        }

        // Filters
        if ($request->filled('appraisal_cycle_id')) {
            $query->where('appraisal_cycle_id', $request->appraisal_cycle_id);
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->filled('manager_id')) {
            $query->where('manager_id', $request->manager_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $request->department_id));
        }
        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', $search)
                  ->orWhere('employee_id', 'like', $search);
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $scorecards = $query->latest()->paginate($perPage);

        $scorecards->getCollection()->transform(function ($sc) use ($currentEmployee, $isHrOrAdmin) {
            $isOwner = $currentEmployee && (int)$sc->employee_id === (int)$currentEmployee->id;
            $isManager = $currentEmployee && (int)$sc->manager_id === (int)$currentEmployee->id;
            $sc->capabilities = [
                'can_view'             => true,
                'can_self_appraise'    => $isOwner && in_array($sc->status, ['draft', 'goal_setting', 'self_review', 'active']),
                'can_manager_appraise' => ($isManager || $isHrOrAdmin) && in_array($sc->status, ['submitted', 'manager_review', 'in_review']),
                'can_edit'             => $isHrOrAdmin || ($isOwner && in_array($sc->status, ['draft', 'goal_setting'])),
                'can_delete'           => $isHrOrAdmin,
            ];
            return $sc;
        });

        return $this->sendSuccess($scorecards, 'Scorecards retrieved.');
    }

    /**
     * GET /api/hrms/kra-kpi/scorecards/{id}
     * Detailed scorecard breakdown, goal items, reviews, progress logs.
     */
    public function showScorecard(int $id): JsonResponse
    {
        [$tenantId, , $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        try {
            $data = $this->kraKpiRepository->getScorecardData($id, $currentEmployee, $isHrOrAdmin, $tenantId);
            return $this->sendSuccess($data, 'Scorecard details retrieved.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 404);
        }
    }

    /**
     * DELETE /api/hrms/kra-kpi/scorecards/{id}
     */
    public function destroyScorecard(int $id): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to delete employee scorecards.', 403);
        }

        try {
            $this->kraKpiRepository->deletePlan($id, $tenantId);
            return $this->sendSuccess(null, 'Employee Scorecard deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/kra-kpi/scorecards/{id}/items
     * Add single KPI Goal item or batch items to Scorecard.
     */
    public function addGoalItem(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        $plan = EmployeeGoalPlan::with(['employee', 'manager'])->where('tenant_id', $tenantId)->find($id);
        if (!$plan) {
            return $this->sendError('Employee Scorecard not found.', 404);
        }

        $isOwner = ($currentEmployee && $plan->employee_id == $currentEmployee->id) || ($user && $plan->employee?->user_id == $user->id);
        $isManager = ($currentEmployee && ($plan->manager_id == $currentEmployee->id || $plan->employee?->reporting_manager_id == $currentEmployee->id))
            || ($user && ($plan->manager?->user_id == $user->id || $plan->employee?->reportingManager?->user_id == $user->id));

        $canModify = $isHrOrAdmin || $isManager || ($isOwner && in_array($plan->status, ['draft', 'submitted']));
        if (!$canModify) {
            return $this->sendError('You are not authorized to add or modify KPI goals on this goal sheet.', 403);
        }

        // Multi-item batch support
        if ($request->has('items') && is_array($request->input('items'))) {
            $itemsData = $request->input('items');
            $createdItems = [];
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
                    'description' => $row['description'] ?? null,
                ];
                $createdItems[] = $this->kraKpiRepository->addGoalItem($id, $itemPayload, $tenantId);
            }
            return $this->sendSuccess($createdItems, sprintf('%d KPI Goal(s) added to scorecard.', count($createdItems)), 201);
        }

        if ($request->has('kra_category_id')) {
            $request->merge(['kra_category_id' => $this->kraKpiRepository->resolveKraCategoryId($request->input('kra_category_id'), $tenantId)]);
        }

        $validator = Validator::make($request->all(), [
            'kra_category_id' => ['nullable', Rule::exists('kra_categories', 'id')->where('tenant_id', $tenantId)],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|in:percentage,currency,number,rating,boolean',
            'calculation_type' => 'required|in:higher_is_better,lower_is_better,milestone',
            'target' => 'required|numeric',
            'weightage' => 'required|numeric|min:0.01|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $item = $this->kraKpiRepository->addGoalItem($id, $validator->validated(), $tenantId);
            $item->load('kraCategory');
            return $this->sendSuccess($item, 'KPI Goal added to scorecard.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/hrms/kra-kpi/items/{itemId}
     * Remove single KPI Goal item from Scorecard.
     */
    public function destroyGoalItem(int $itemId): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        $item = EmployeeGoalItem::with('goalPlan.employee', 'goalPlan.manager')->where('tenant_id', $tenantId)->find($itemId);
        if (!$item) {
            return $this->sendError('KPI Goal item not found.', 404);
        }

        $plan = $item->goalPlan;
        $isOwner = ($currentEmployee && $plan->employee_id == $currentEmployee->id) || ($user && $plan->employee?->user_id == $user->id);
        $isManager = ($currentEmployee && ($plan->manager_id == $currentEmployee->id || $plan->employee?->reporting_manager_id == $currentEmployee->id))
            || ($user && ($plan->manager?->user_id == $user->id || $plan->employee?->reportingManager?->user_id == $user->id));

        $canDelete = $isHrOrAdmin || $isManager || ($isOwner && in_array($plan->status, ['draft', 'submitted']));
        if (!$canDelete) {
            return $this->sendError('You are not authorized to remove KPI goals from this goal sheet.', 403);
        }

        try {
            $this->kraKpiRepository->deleteGoalItem($itemId, $tenantId);
            return $this->sendSuccess(null, 'KPI Goal removed from scorecard.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/kra-kpi/items/{itemId}/progress
     * Log mid-cycle progress against a KPI Goal.
     */
    public function logProgress(Request $request, int $itemId): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        $item = EmployeeGoalItem::with('goalPlan.employee', 'goalPlan.manager')->where('tenant_id', $tenantId)->find($itemId);
        if (!$item) {
            return $this->sendError('KPI Goal item not found.', 404);
        }

        $plan = $item->goalPlan;
        $isOwner = ($currentEmployee && $plan->employee_id == $currentEmployee->id) || ($user && $plan->employee?->user_id == $user->id);
        $isManager = ($currentEmployee && ($plan->manager_id == $currentEmployee->id || $plan->employee?->reporting_manager_id == $currentEmployee->id))
            || ($user && ($plan->manager?->user_id == $user->id || $plan->employee?->reportingManager?->user_id == $user->id));

        if (!$isOwner && !$isManager && !$isHrOrAdmin) {
            return $this->sendError('Unauthorized to log progress on this goal item.', 403);
        }

        $validator = Validator::make($request->all(), [
            'current_value' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $log = $this->kraKpiRepository->logProgress($itemId, $validator->validated(), auth()->id(), $tenantId);
            return $this->sendSuccess($log, 'Progress check-in logged successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/kra-kpi/scorecards/{id}/submit-goals
     * Employee submits Goals to Manager for sign-off.
     */
    public function submitGoals(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        $plan = EmployeeGoalPlan::with('employee')->where('tenant_id', $tenantId)->find($id);
        if (!$plan) {
            return $this->sendError('Employee Scorecard not found.', 404);
        }

        $isOwner = ($currentEmployee && $plan->employee_id == $currentEmployee->id) || ($user && $plan->employee?->user_id == $user->id);
        if (!$isOwner && !$isHrOrAdmin) {
            return $this->sendError('Only the assigned employee or HR can submit goals for approval.', 403);
        }

        try {
            $plan = $this->kraKpiRepository->submitGoals($id, $tenantId);
            return $this->sendSuccess($plan, 'Goals submitted to Manager for approval.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/kra-kpi/scorecards/{id}/approve-goals
     * Manager approves Employee Goals.
     */
    public function approveGoals(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        $plan = EmployeeGoalPlan::with('employee', 'manager')->where('tenant_id', $tenantId)->find($id);
        if (!$plan) {
            return $this->sendError('Employee Scorecard not found.', 404);
        }

        $isManager = ($currentEmployee && ($plan->manager_id == $currentEmployee->id || $plan->employee?->reporting_manager_id == $currentEmployee->id))
            || ($user && ($plan->manager?->user_id == $user->id || $plan->employee?->reportingManager?->user_id == $user->id));

        if (!$isManager && !$isHrOrAdmin) {
            return $this->sendError('Only the reporting manager or HR can approve goals.', 403);
        }

        try {
            $plan = $this->kraKpiRepository->approveGoals($id, $tenantId);
            return $this->sendSuccess($plan, 'Goal plan approved and activated.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/kra-kpi/scorecards/{id}/self-appraisal
     * Submit Self-Appraisal (ratings and comments).
     */
    public function submitSelfAppraisal(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        $plan = EmployeeGoalPlan::with('employee')->where('tenant_id', $tenantId)->find($id);
        if (!$plan) {
            return $this->sendError('Employee Scorecard not found.', 404);
        }

        $isOwner = ($currentEmployee && $plan->employee_id == $currentEmployee->id) || ($user && $plan->employee?->user_id == $user->id);
        if (!$isOwner && !$isHrOrAdmin) {
            return $this->sendError('Only the assigned employee can submit self-appraisal.', 403);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.self_rating' => 'nullable|numeric|min:1|max:5',
            'items.*.self_comment' => 'nullable|string',
            'employee_comments' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $plan = $this->kraKpiRepository->submitSelfAppraisal($id, $validator->validated(), $tenantId);
            return $this->sendSuccess($plan, 'Self-Appraisal submitted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/kra-kpi/scorecards/{id}/manager-appraisal
     * Submit Manager-Appraisal (ratings, scores, and feedback).
     */
    public function submitManagerAppraisal(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        $plan = EmployeeGoalPlan::with('employee', 'manager')->where('tenant_id', $tenantId)->find($id);
        if (!$plan) {
            return $this->sendError('Employee Scorecard not found.', 404);
        }

        $isManager = ($currentEmployee && ($plan->manager_id == $currentEmployee->id || $plan->employee?->reporting_manager_id == $currentEmployee->id))
            || ($user && ($plan->manager?->user_id == $user->id || $plan->employee?->reportingManager?->user_id == $user->id));

        if (!$isManager && !$isHrOrAdmin) {
            return $this->sendError('Only the reporting manager or HR can submit manager appraisal.', 403);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.manager_rating' => 'required|numeric|min:1|max:5',
            'items.*.manager_comment' => 'nullable|string',
            'manager_comments' => 'nullable|string',
            'competency_score' => 'nullable|numeric|min:0|max:150',
            'promotion_recommended' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $plan = $this->kraKpiRepository->submitManagerAppraisal(
                $id,
                $validator->validated(),
                $request->boolean('promotion_recommended'),
                $tenantId
            );
            return $this->sendSuccess($plan, 'Manager Appraisal submitted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }



    /**
     * POST /api/hrms/kra-kpi/scorecards/{id}/trigger-pip
     * 1-Click Transition from Low Appraisal Score to PIP.
     */
    public function triggerPip(int $id): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to initiate PIP from appraisal.', 403);
        }

        try {
            $pip = $this->kraKpiRepository->triggerPip($id, auth()->id(), $tenantId);
            return $this->sendSuccess($pip, sprintf('PIP #%s created successfully from appraisal.', $pip->pip_number), 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }
}
