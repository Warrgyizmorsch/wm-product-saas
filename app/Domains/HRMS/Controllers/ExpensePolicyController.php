<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\ExpenseApprovalWorkflow;
use App\Domains\HRMS\Models\ExpenseCategory;
use App\Domains\HRMS\Models\ExpensePolicy;
use App\Domains\HRMS\Models\ExpensePolicyRule;
use App\Domains\HRMS\Repositories\ExpensePolicyRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ExpensePolicyController
 *
 * Manages the 3-tab Expense Master structure:
 *   Tab 1 → Expense Categories
 *   Tab 2 → Approval Workflows (1-Level, 2-Level, Amount Threshold rules)
 *   Tab 3 → Expense Policies (Category-wise limits & receipt rules)
 */
class ExpensePolicyController extends Controller
{
    public function __construct(
        private readonly ExpensePolicyRepositoryInterface $policyRepository
    ) {
    }

    /**
     * List all expense categories, workflows, and policies for current tenant.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ExpensePolicy::class);

        $activeTab = $request->query('tab', 'categories');
        $data = $this->policyRepository->getIndexData($request->all(), $activeTab);

        return view('modules.hrms.expense-policy.index', $data);
    }

    /**
     * Create a new named expense policy (header only, rules added separately).
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ExpensePolicy::class);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name'                        => 'required|string|max:255',
            'description'                 => 'nullable|string|max:1000',
            'designation_id'              => 'nullable|exists:designations,id',
            'department_id'               => 'nullable|exists:departments,id',
            'company_id'                  => 'nullable|exists:companies,id',
            'business_unit_id'            => 'nullable|exists:business_units,id',
            'branch_id'                   => 'nullable|exists:branches,id',
            'status'                      => 'nullable|boolean',
            'approval_type'               => 'required|string|in:1_level,2_level,conditional_threshold',
            'first_approver'              => 'required|string|in:reporting_manager,department_head,hr_admin,finance_manager',
            'second_approver'             => 'required|string|in:finance_manager,hr_admin,department_head,reporting_manager',
            'amount_threshold_for_2_level'=> 'nullable|numeric|min:0',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status']    = (bool) ($request->input('status', 1));

        $this->policyRepository->storePolicy($validated);

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'policies'])
            ->with('success', __('hrms.expense_master.policy_created_success'));
    }

    /**
     * Update the policy header (name, description, assignment, status, approval rules).
     */
    public function update(Request $request, ExpensePolicy $policy): RedirectResponse
    {
        $this->authorize('update', $policy);
        $validated = $request->validate([
            'name'                        => 'required|string|max:255',
            'description'                 => 'nullable|string|max:1000',
            'designation_id'              => 'nullable|exists:designations,id',
            'department_id'               => 'nullable|exists:departments,id',
            'company_id'                  => 'nullable|exists:companies,id',
            'business_unit_id'            => 'nullable|exists:business_units,id',
            'branch_id'                   => 'nullable|exists:branches,id',
            'status'                      => 'nullable|boolean',
            'approval_type'               => 'required|string|in:1_level,2_level,conditional_threshold',
            'first_approver'              => 'required|string|in:reporting_manager,department_head,hr_admin,finance_manager',
            'second_approver'             => 'required|string|in:finance_manager,hr_admin,department_head,reporting_manager',
            'amount_threshold_for_2_level'=> 'nullable|numeric|min:0',
        ]);

        $validated['status'] = (bool) ($request->input('status', 1));

        $this->policyRepository->updatePolicy($policy, $validated);

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'policies'])
            ->with('success', __('hrms.expense_master.policy_updated_success'));
    }

    /**
     * Delete an expense policy (and cascade its rules).
     */
    public function destroy(ExpensePolicy $policy): RedirectResponse
    {
        $this->authorize('delete', $policy);
        $this->policyRepository->deletePolicy($policy);

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'policies'])
            ->with('success', __('hrms.expense_master.policy_deleted_success'));
    }

    /**
     * Add a category limit rule to an existing policy.
     */
    public function storeRule(Request $request, ExpensePolicy $policy): RedirectResponse
    {
        $validated = $request->validate([
            'expense_category_id'        => 'required|exists:expense_categories,id',
            'max_limit_per_claim'        => 'nullable|numeric|min:0',
            'max_daily_limit'            => 'nullable|numeric|min:0',
            'max_monthly_limit'          => 'nullable|numeric|min:0',
            'receipt_required_threshold' => 'nullable|numeric|min:0',
            'receipt_required'           => 'nullable|boolean',
            'notes'                      => 'nullable|string|max:500',
        ]);

        $validated['expense_policy_id']  = $policy->id;
        $validated['receipt_required']   = (bool) ($request->input('receipt_required', 0));

        $this->policyRepository->storeRule($policy, $validated);

        return redirect()->route('hrms.expense-policy.rules', $policy)
            ->with('success', __('hrms.expense_master.limit_saved_success'));
    }

    /**
     * Show the rules (category limits) for a specific policy.
     */
    public function showRules(Request $request, ExpensePolicy $policy): View
    {
        $data = $this->policyRepository->getPolicyRulesData($policy, $request->all());

        return view('modules.hrms.expense-policy.rules', $data);
    }

    /**
     * Delete a single category rule from a policy.
     */
    public function destroyRule(ExpensePolicy $policy, ExpensePolicyRule $rule): RedirectResponse
    {
        $this->policyRepository->deleteRule($rule);

        return redirect()->route('hrms.expense-policy.rules', $policy)
            ->with('success', __('hrms.expense_master.limit_removed_success'));
    }

    public function storeWorkflow(Request $request): RedirectResponse
    {
        $this->authorize('create', ExpensePolicy::class);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name'                        => 'required|string|max:255',
            'description'                 => 'nullable|string|max:1000',
            'designation_id'              => 'nullable|exists:designations,id',
            'department_id'               => 'nullable|exists:departments,id',
            'company_id'                  => 'nullable|exists:companies,id',
            'business_unit_id'            => 'nullable|exists:business_units,id',
            'branch_id'                   => 'nullable|exists:branches,id',
            'approval_type'               => 'required|string|in:1_level,2_level,conditional_threshold',
            'first_approver'              => 'required|string|in:reporting_manager,department_head,hr_admin,finance_manager',
            'second_approver'             => 'required|string|in:finance_manager,hr_admin,department_head,reporting_manager',
            'amount_threshold_for_2_level'=> 'nullable|numeric|min:0',
            'is_default'                  => 'nullable|boolean',
            'status'                      => 'nullable|boolean',
        ]);

        $validated['tenant_id']  = $tenantId;
        $validated['status']     = (bool) ($request->input('status', 1));
        $validated['is_default'] = (bool) ($request->input('is_default', 0));

        $this->policyRepository->storeWorkflow($validated);

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'workflows'])
            ->with('success', __('hrms.expense_master.workflow_created_success'));
    }

    public function updateWorkflow(Request $request, ExpenseApprovalWorkflow $workflow): RedirectResponse
    {
        $this->authorize('update', ExpensePolicy::class);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name'                        => 'required|string|max:255',
            'description'                 => 'nullable|string|max:1000',
            'designation_id'              => 'nullable|exists:designations,id',
            'department_id'               => 'nullable|exists:departments,id',
            'company_id'                  => 'nullable|exists:companies,id',
            'business_unit_id'            => 'nullable|exists:business_units,id',
            'branch_id'                   => 'nullable|exists:branches,id',
            'approval_type'               => 'required|string|in:1_level,2_level,conditional_threshold',
            'first_approver'              => 'required|string|in:reporting_manager,department_head,hr_admin,finance_manager',
            'second_approver'             => 'required|string|in:finance_manager,hr_admin,department_head,reporting_manager',
            'amount_threshold_for_2_level'=> 'nullable|numeric|min:0',
            'is_default'                  => 'nullable|boolean',
            'status'                      => 'nullable|boolean',
        ]);

        $validated['status']     = (bool) ($request->input('status', 1));
        $validated['is_default'] = (bool) ($request->input('is_default', 0));

        $this->policyRepository->updateWorkflow($workflow, $validated);

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'workflows'])
            ->with('success', __('hrms.expense_master.workflow_updated_success'));
    }

    public function destroyWorkflow(ExpenseApprovalWorkflow $workflow): RedirectResponse
    {
        $this->authorize('delete', ExpensePolicy::class);

        $this->policyRepository->deleteWorkflow($workflow);

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'workflows'])
            ->with('success', __('hrms.expense_master.workflow_deleted_success'));
    }
}
