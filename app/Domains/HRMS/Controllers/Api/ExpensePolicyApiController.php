<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\ExpenseApprovalWorkflow;
use App\Domains\HRMS\Models\ExpenseCategory;
use App\Domains\HRMS\Models\ExpensePolicy;
use App\Domains\HRMS\Models\ExpensePolicyRule;
use App\Domains\HRMS\Repositories\ExpensePolicyRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpensePolicyApiController extends Controller
{
    public function __construct(
        private readonly ExpensePolicyRepositoryInterface $policyRepository
    ) {}

    /**
     * Standard success JSON response.
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
     * Standard error JSON response.
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
     * Check if user is authorized for HR/Expense settings administration.
     */
    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->role === 'admin'
            || $user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hrms.travel_expenses.manage')
            || $user->hasHrPermission('hrms.travel_expenses.approve')
            || $user->hasHrPermission('hrms.expense_policies.manage');
    }

    /**
     * Null-safe authorization check.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();

            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access. Please provide valid bearer token or credentials.', 401);
            }
        }

        return null;
    }

    // =========================================================================
    // 1. EXPENSE POLICIES
    // =========================================================================

    /**
     * GET /api/hrms/expense-policies
     */
    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $query = ExpensePolicy::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['designation:id,name', 'department:id,name', 'company:id,company_name', 'branch:id,name'])
            ->withCount('rules')
            ->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        $perPage = min(100, max(5, (int) $request->input('per_page', 20)));
        $policies = $query->paginate($perPage);

        return $this->sendSuccess($policies, 'Expense policies retrieved successfully.');
    }

    /**
     * GET /api/hrms/expense-policies/summary
     */
    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $policyBase = ExpensePolicy::where('tenant_id', $tenantId);
        $workflowBase = ExpenseApprovalWorkflow::where('tenant_id', $tenantId);
        $categoryBase = ExpenseCategory::where('tenant_id', $tenantId);

        $summary = [
            'total_policies'    => (clone $policyBase)->count(),
            'active_policies'   => (clone $policyBase)->where('status', true)->count(),
            'total_workflows'   => (clone $workflowBase)->count(),
            'total_categories'  => (clone $categoryBase)->count(),
            'active_categories' => (clone $categoryBase)->where('status', true)->count(),
        ];

        return $this->sendSuccess($summary, 'Expense policy summary loaded successfully.');
    }

    /**
     * POST /api/hrms/expense-policies
     */
    public function store(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to create expense policy.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;

        $validated = $request->validate([
            'name'                         => 'required|string|max:255',
            'description'                  => 'nullable|string|max:1000',
            'designation_id'               => 'nullable|exists:designations,id',
            'department_id'                => 'nullable|exists:departments,id',
            'company_id'                   => 'nullable|exists:companies,id',
            'business_unit_id'             => 'nullable|exists:business_units,id',
            'branch_id'                    => 'nullable|exists:branches,id',
            'status'                       => 'nullable|boolean',
            'approval_type'                => 'required|string|in:1_level,2_level,conditional_threshold',
            'first_approver'               => 'required|string|in:reporting_manager,department_head,hr_admin',
            'second_approver'              => 'required|string|in:finance_manager,hr_admin,department_head',
            'amount_threshold_for_2_level' => 'nullable|numeric|min:0',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $policy = $this->policyRepository->storePolicy($validated);

        return $this->sendSuccess($policy->fresh(['designation:id,name', 'department:id,name']), 'Expense policy created successfully.', 201);
    }

    /**
     * GET /api/hrms/expense-policies/{id}
     */
    public function show(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $policy = ExpensePolicy::with([
            'designation:id,name',
            'department:id,name',
            'company:id,company_name',
            'branch:id,name',
            'rules:id,expense_policy_id,expense_category_id,max_limit_per_claim,max_daily_limit,max_monthly_limit,receipt_required,receipt_required_threshold,notes',
            'rules.category:id,name,code,icon',
        ])
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$policy) {
            return $this->sendError("Expense policy with ID '{$id}' not found.", 404);
        }

        return $this->sendSuccess($policy, 'Expense policy details loaded successfully.');
    }

    /**
     * PUT /api/hrms/expense-policies/{id}
     */
    public function update(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to update expense policy.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $policy = ExpensePolicy::where('tenant_id', $tenantId)->find($id);

        if (!$policy) {
            return $this->sendError("Expense policy with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'name'                         => 'required|string|max:255',
            'description'                  => 'nullable|string|max:1000',
            'designation_id'               => 'nullable|exists:designations,id',
            'department_id'                => 'nullable|exists:departments,id',
            'company_id'                   => 'nullable|exists:companies,id',
            'business_unit_id'             => 'nullable|exists:business_units,id',
            'branch_id'                    => 'nullable|exists:branches,id',
            'status'                       => 'nullable|boolean',
            'approval_type'                => 'required|string|in:1_level,2_level,conditional_threshold',
            'first_approver'               => 'required|string|in:reporting_manager,department_head,hr_admin',
            'second_approver'              => 'required|string|in:finance_manager,hr_admin,department_head',
            'amount_threshold_for_2_level' => 'nullable|numeric|min:0',
        ]);

        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $this->policyRepository->updatePolicy($policy, $validated);

        return $this->sendSuccess($policy->fresh(['designation:id,name', 'department:id,name']), 'Expense policy updated successfully.');
    }

    /**
     * DELETE /api/hrms/expense-policies/{id}
     */
    public function destroy(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to delete expense policy.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $policy = ExpensePolicy::where('tenant_id', $tenantId)->find($id);

        if (!$policy) {
            return $this->sendError("Expense policy with ID '{$id}' not found.", 404);
        }

        $this->policyRepository->deletePolicy($policy);

        return $this->sendSuccess(['id' => (int) $id], 'Expense policy deleted successfully.');
    }

    // =========================================================================
    // 2. CATEGORY POLICY RULES
    // =========================================================================

    /**
     * GET /api/hrms/expense-policies/{policyId}/rules
     */
    public function listRules(mixed $policyId): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $policy = ExpensePolicy::where('tenant_id', $tenantId)->find($policyId);

        if (!$policy) {
            return $this->sendError("Expense policy with ID '{$policyId}' not found.", 404);
        }

        $rules = ExpensePolicyRule::with('category:id,name,code,icon')
            ->where('expense_policy_id', $policy->id)
            ->get();

        return $this->sendSuccess($rules, 'Policy category rules loaded successfully.');
    }

    /**
     * POST /api/hrms/expense-policies/{policyId}/rules
     */
    public function storeRule(Request $request, mixed $policyId): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to save category rule.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $policy = ExpensePolicy::where('tenant_id', $tenantId)->find($policyId);

        if (!$policy) {
            return $this->sendError("Expense policy with ID '{$policyId}' not found.", 404);
        }

        $validated = $request->validate([
            'expense_category_id'        => 'required|exists:expense_categories,id',
            'max_limit_per_claim'        => 'nullable|numeric|min:0',
            'max_daily_limit'            => 'nullable|numeric|min:0',
            'max_monthly_limit'          => 'nullable|numeric|min:0',
            'receipt_required_threshold' => 'nullable|numeric|min:0',
            'receipt_required'           => 'nullable|boolean',
            'notes'                      => 'nullable|string|max:500',
        ]);

        $validated['expense_policy_id'] = $policy->id;
        $validated['receipt_required'] = (bool) ($request->input('receipt_required', 0));

        $rule = $this->policyRepository->storeRule($policy, $validated);

        return $this->sendSuccess($rule->fresh('category:id,name,code'), 'Category limit rule saved successfully.', 201);
    }

    /**
     * DELETE /api/hrms/expense-policies/{policyId}/rules/{ruleId}
     */
    public function destroyRule(mixed $policyId, mixed $ruleId): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to delete category rule.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $policy = ExpensePolicy::where('tenant_id', $tenantId)->find($policyId);

        if (!$policy) {
            return $this->sendError("Expense policy with ID '{$policyId}' not found.", 404);
        }

        $rule = ExpensePolicyRule::where('expense_policy_id', $policy->id)->find($ruleId);
        if (!$rule) {
            return $this->sendError("Policy rule with ID '{$ruleId}' not found.", 404);
        }

        $this->policyRepository->deleteRule($rule);

        return $this->sendSuccess(['id' => (int) $ruleId], 'Category limit rule deleted successfully.');
    }

    // =========================================================================
    // 3. APPROVAL WORKFLOWS
    // =========================================================================

    /**
     * GET /api/hrms/expense-policies/workflows
     */
    public function listWorkflows(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $workflows = ExpenseApprovalWorkflow::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['designation:id,name', 'department:id,name', 'company:id,company_name', 'branch:id,name'])
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return $this->sendSuccess($workflows, 'Approval workflows retrieved successfully.');
    }

    /**
     * POST /api/hrms/expense-policies/workflows
     */
    public function storeWorkflow(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to create workflow.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;

        $validated = $request->validate([
            'name'                         => 'required|string|max:255',
            'description'                  => 'nullable|string|max:1000',
            'designation_id'               => 'nullable|exists:designations,id',
            'department_id'                => 'nullable|exists:departments,id',
            'company_id'                   => 'nullable|exists:companies,id',
            'business_unit_id'             => 'nullable|exists:business_units,id',
            'branch_id'                    => 'nullable|exists:branches,id',
            'approval_type'                => 'required|string|in:1_level,2_level,conditional_threshold',
            'first_approver'               => 'required|string|in:reporting_manager,department_head,hr_admin',
            'second_approver'              => 'required|string|in:finance_manager,hr_admin,department_head',
            'amount_threshold_for_2_level' => 'nullable|numeric|min:0',
            'is_default'                   => 'nullable|boolean',
            'status'                       => 'nullable|boolean',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = (bool) ($request->input('status', 1));
        $validated['is_default'] = (bool) ($request->input('is_default', 0));

        $workflow = $this->policyRepository->storeWorkflow($validated);

        return $this->sendSuccess($workflow->fresh(['designation:id,name', 'department:id,name']), 'Approval workflow created successfully.', 201);
    }

    /**
     * PUT /api/hrms/expense-policies/workflows/{id}
     */
    public function updateWorkflow(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to update workflow.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $workflow = ExpenseApprovalWorkflow::where('tenant_id', $tenantId)->find($id);

        if (!$workflow) {
            return $this->sendError("Approval workflow with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'name'                         => 'required|string|max:255',
            'description'                  => 'nullable|string|max:1000',
            'designation_id'               => 'nullable|exists:designations,id',
            'department_id'                => 'nullable|exists:departments,id',
            'company_id'                   => 'nullable|exists:companies,id',
            'business_unit_id'             => 'nullable|exists:business_units,id',
            'branch_id'                    => 'nullable|exists:branches,id',
            'approval_type'                => 'required|string|in:1_level,2_level,conditional_threshold',
            'first_approver'               => 'required|string|in:reporting_manager,department_head,hr_admin',
            'second_approver'              => 'required|string|in:finance_manager,hr_admin,department_head',
            'amount_threshold_for_2_level' => 'nullable|numeric|min:0',
            'is_default'                   => 'nullable|boolean',
            'status'                       => 'nullable|boolean',
        ]);

        $validated['status'] = (bool) ($request->input('status', 1));
        $validated['is_default'] = (bool) ($request->input('is_default', 0));

        $this->policyRepository->updateWorkflow($workflow, $validated);

        return $this->sendSuccess($workflow->fresh(['designation:id,name', 'department:id,name']), 'Approval workflow updated successfully.');
    }

    /**
     * DELETE /api/hrms/expense-policies/workflows/{id}
     */
    public function destroyWorkflow(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to delete workflow.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $workflow = ExpenseApprovalWorkflow::where('tenant_id', $tenantId)->find($id);

        if (!$workflow) {
            return $this->sendError("Approval workflow with ID '{$id}' not found.", 404);
        }

        $this->policyRepository->deleteWorkflow($workflow);

        return $this->sendSuccess(['id' => (int) $id], 'Approval workflow deleted successfully.');
    }
}
