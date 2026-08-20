<?php

namespace App\Domains\HRMS\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\ExpensePolicy;
use App\Domains\HRMS\Models\ExpensePolicyRule;
use App\Domains\HRMS\Models\ExpenseCategory;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * ExpensePolicyController
 *
 * Manages the 2-layer Expense Policy structure:
 *   Layer 1 → Named Policy (e.g. "Manager Travel Policy")
 *   Layer 2 → Category-wise limits within that policy
 *
 * The policy is then assigned to designations/departments for
 * automatic validation during expense claim submission.
 */
class ExpensePolicyController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // Policy Header CRUD
    // ─────────────────────────────────────────────────────────────────

    /**
     * List all expense policies for the current tenant.
     */
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $activeTab = $request->query('tab', 'policies');

        // Policy tab filters
        $filters = [
            'search' => $request->query('search', ''),
            'status' => $request->query('status', ''),
            'sort'   => $request->query('sort', 'name_asc'),
        ];

        // Category tab filters
        $catFilters = [
            'search' => $request->query('cat_search', ''),
            'status' => $request->query('cat_status', ''),
            'sort'   => $request->query('cat_sort', 'name_asc'),
        ];

        // 1. Query Policies
        $policyQuery = ExpensePolicy::where('tenant_id', $tenantId)
            ->with(['designation', 'department', 'company', 'businessUnit', 'branch', 'rules.category']);

        if ($activeTab === 'policies') {
            if ($filters['search'] !== '') {
                $policyQuery->where('name', 'like', '%' . $filters['search'] . '%');
            }
            if ($filters['status'] !== '') {
                $policyQuery->where('status', (bool) $filters['status']);
            }
            match ($filters['sort']) {
                'name_desc' => $policyQuery->orderBy('name', 'desc'),
                'newest'    => $policyQuery->orderBy('created_at', 'desc'),
                'oldest'    => $policyQuery->orderBy('created_at', 'asc'),
                default     => $policyQuery->orderBy('name', 'asc'),
            };
        } else {
            $policyQuery->orderBy('name', 'asc');
        }
        $policies = $policyQuery->get();

        // 2. Query Categories
        $catQuery = ExpenseCategory::where('tenant_id', $tenantId);
        
        if ($activeTab === 'categories') {
            if ($catFilters['search'] !== '') {
                $search = $catFilters['search'];
                $catQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
            if ($catFilters['status'] !== '') {
                $catQuery->where('status', (bool) $catFilters['status']);
            }
            match ($catFilters['sort']) {
                'name_desc' => $catQuery->orderBy('name', 'desc'),
                'code_asc'  => $catQuery->orderBy('code', 'asc'),
                'code_desc' => $catQuery->orderBy('code', 'desc'),
                'newest'    => $catQuery->orderBy('created_at', 'desc'),
                'oldest'    => $catQuery->orderBy('created_at', 'asc'),
                default     => $catQuery->orderBy('name', 'asc'),
            };
        } else {
            $catQuery->orderBy('name', 'asc');
        }
        $categoriesList = $catQuery->get();

        // Constants/scope helpers
        $categories    = ExpenseCategory::where('tenant_id', $tenantId)->where('status', true)->orderBy('name')->get();
        $designations  = Designation::where('status', true)->orderBy('name')->get();
        $departments   = Department::orderBy('name')->get();
        $companies     = Company::orderBy('company_name')->get();
        $businessUnits = BusinessUnit::orderBy('name')->get();
        $branches      = Branch::orderBy('name')->get();

        return view('modules.hrms.expense-policy.index', compact(
            'policies', 'categoriesList', 'categories', 'designations', 'departments',
            'companies', 'businessUnits', 'branches', 'filters', 'catFilters', 'activeTab'
        ));
    }

    /**
     * Create a new named expense policy (header only, rules added separately).
     */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:1000',
            'designation_id'   => 'nullable|exists:designations,id',
            'department_id'    => 'nullable|exists:departments,id',
            'company_id'       => 'nullable|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id'        => 'nullable|exists:branches,id',
            'status'           => 'nullable|boolean',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status']    = (bool) ($request->input('status', 1));

        ExpensePolicy::create($validated);

        return redirect()->route('hrms.expense-policy.index')
            ->with('success', 'Expense policy created successfully.');
    }

    /**
     * Update the policy header (name, description, assignment, status).
     */
    public function update(Request $request, ExpensePolicy $policy): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:1000',
            'designation_id'   => 'nullable|exists:designations,id',
            'department_id'    => 'nullable|exists:departments,id',
            'company_id'       => 'nullable|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id'        => 'nullable|exists:branches,id',
            'status'           => 'nullable|boolean',
        ]);

        $validated['status'] = (bool) ($request->input('status', 1));

        $policy->update($validated);

        return redirect()->route('hrms.expense-policy.index')
            ->with('success', 'Expense policy updated successfully.');
    }

    /**
     * Delete an expense policy (and cascade its rules).
     */
    public function destroy(ExpensePolicy $policy): RedirectResponse
    {
        $policy->rules()->delete();
        $policy->delete();

        return redirect()->route('hrms.expense-policy.index')
            ->with('success', 'Expense policy deleted successfully.');
    }

    // ─────────────────────────────────────────────────────────────────
    // Policy Rules (Category-wise limits)
    // ─────────────────────────────────────────────────────────────────

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

        // Upsert: one rule per category per policy
        ExpensePolicyRule::updateOrCreate(
            [
                'expense_policy_id'   => $policy->id,
                'expense_category_id' => $validated['expense_category_id'],
            ],
            $validated
        );

        return redirect()->route('hrms.expense-policy.rules', $policy)
            ->with('success', 'Category limit added/updated successfully.');
    }

    /**
     * Show the rules (category limits) for a specific policy.
     */
    public function showRules(ExpensePolicy $policy): View
    {
        $tenantId  = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $policy->load(['rules.category', 'designation', 'department']);

        $categories = ExpenseCategory::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        // Categories not yet added to this policy
        $usedCategoryIds  = $policy->rules->pluck('expense_category_id')->toArray();
        $availableCategories = $categories->whereNotIn('id', $usedCategoryIds)->values();

        return view('modules.hrms.expense-policy.rules', compact('policy', 'availableCategories'));
    }

    /**
     * Delete a single category rule from a policy.
     */
    public function destroyRule(ExpensePolicy $policy, ExpensePolicyRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('hrms.expense-policy.rules', $policy)
            ->with('success', 'Category rule removed.');
    }
}
