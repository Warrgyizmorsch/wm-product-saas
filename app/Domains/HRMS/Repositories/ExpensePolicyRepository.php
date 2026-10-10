<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\ExpenseApprovalWorkflow;
use App\Domains\HRMS\Models\ExpenseCategory;
use App\Domains\HRMS\Models\ExpensePolicy;
use App\Domains\HRMS\Models\ExpensePolicyRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExpensePolicyRepository implements ExpensePolicyRepositoryInterface
{
    public function getIndexData(array $filters, string $activeTab): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $policyFilters = [
            'search' => $filters['search'] ?? '',
            'status' => $filters['status'] ?? '',
            'sort'   => $filters['sort'] ?? 'name_asc',
        ];

        $catFilters = [
            'search' => $filters['cat_search'] ?? '',
            'status' => $filters['cat_status'] ?? '',
            'sort'   => $filters['cat_sort'] ?? 'name_asc',
        ];

        $workflowFilters = [
            'search' => $filters['wf_search'] ?? '',
            'status' => $filters['wf_status'] ?? '',
            'sort'   => $filters['wf_sort'] ?? 'name_asc',
        ];

        // 1. Query Policies
        $policyQuery = ExpensePolicy::where('tenant_id', $tenantId)
            ->with(['designation', 'department', 'company', 'businessUnit', 'branch', 'rules.category']);

        if ($activeTab === 'policies') {
            if ($policyFilters['search'] !== '') {
                $search = $policyFilters['search'];
                $policyQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('designation', function ($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('department', function ($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('company', function ($sq) use ($search) {
                          $sq->where('company_name', 'like', "%{$search}%");
                      });
                });
            }
            if ($policyFilters['status'] !== '') {
                $policyQuery->where('status', (bool) $policyFilters['status']);
            }
            match ($policyFilters['sort']) {
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
        $categoriesList = $catQuery->paginate(10)->withQueryString();

        // 3. Query Approval Workflows
        $wfQuery = ExpenseApprovalWorkflow::where('tenant_id', $tenantId)
            ->with(['designation', 'department', 'company', 'businessUnit', 'branch']);

        if ($activeTab === 'workflows') {
            if ($workflowFilters['search'] !== '') {
                $search = $workflowFilters['search'];
                $wfQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('designation', function ($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('department', function ($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('company', function ($sq) use ($search) {
                          $sq->where('company_name', 'like', "%{$search}%");
                      });
                });
            }
            if ($workflowFilters['status'] !== '') {
                $wfQuery->where('status', (bool) $workflowFilters['status']);
            }
            match ($workflowFilters['sort']) {
                'name_desc' => $wfQuery->orderBy('name', 'desc'),
                'newest'    => $wfQuery->orderBy('created_at', 'desc'),
                'oldest'    => $wfQuery->orderBy('created_at', 'asc'),
                default     => $wfQuery->orderBy('name', 'asc'),
            };
        } else {
            $wfQuery->orderBy('is_default', 'desc')->orderBy('name', 'asc');
        }
        $workflowsList = $wfQuery->get();

        // Constants/scope helpers
        $categories    = ExpenseCategory::where('tenant_id', $tenantId)->where('status', true)->orderBy('name')->get();
        $designations  = Designation::where('status', true)->orderBy('name')->get();
        $departments   = Department::orderBy('name')->get();
        $companies     = Company::orderBy('company_name')->get();
        $businessUnits = BusinessUnit::orderBy('name')->get();
        $branches      = Branch::orderBy('name')->get();

        return [
            'policies'        => $policies,
            'categoriesList'  => $categoriesList,
            'workflowsList'   => $workflowsList,
            'categories'      => $categories,
            'designations'    => $designations,
            'departments'     => $departments,
            'companies'       => $companies,
            'businessUnits'   => $businessUnits,
            'branches'        => $branches,
            'filters'         => $policyFilters,
            'catFilters'      => $catFilters,
            'workflowFilters' => $workflowFilters,
            'activeTab'       => $activeTab,
        ];
    }

    public function storePolicy(array $data): ExpensePolicy
    {
        return DB::transaction(function () use ($data) {
            return ExpensePolicy::create($data);
        });
    }

    public function updatePolicy(ExpensePolicy $policy, array $data): bool
    {
        return DB::transaction(function () use ($policy, $data) {
            return $policy->update($data);
        });
    }

    public function deletePolicy(ExpensePolicy $policy): bool
    {
        return DB::transaction(function () use ($policy) {
            $policy->rules()->delete();
            return (bool) $policy->delete();
        });
    }

    public function storeRule(ExpensePolicy $policy, array $data): ExpensePolicyRule
    {
        return DB::transaction(function () use ($policy, $data) {
            return ExpensePolicyRule::updateOrCreate(
                [
                    'expense_policy_id'   => $policy->id,
                    'expense_category_id' => $data['expense_category_id'],
                ],
                $data
            );
        });
    }

    public function deleteRule(ExpensePolicyRule $rule): bool
    {
        return (bool) $rule->delete();
    }

    public function getPolicyRulesData(ExpensePolicy $policy, array $filters): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $policy->load(['designation', 'department']);

        $rulesQuery = $policy->rules()->with('category');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $rulesQuery->whereHas('category', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['receipt'])) {
            $receipt = $filters['receipt'];
            if ($receipt === 'always') {
                $rulesQuery->where('receipt_required', true);
            } elseif ($receipt === 'threshold') {
                $rulesQuery->where('receipt_required', false)
                           ->whereNotNull('receipt_required_threshold')
                           ->where('receipt_required_threshold', '>', 0);
            } elseif ($receipt === 'not_required') {
                $rulesQuery->where('receipt_required', false)
                           ->where(function ($q) {
                               $q->whereNull('receipt_required_threshold')
                                 ->orWhere('receipt_required_threshold', 0);
                           });
            }
        }

        $sort = $filters['sort'] ?? 'category_asc';
        if ($sort === 'category_desc') {
            $rulesQuery->join('expense_categories', 'expense_policy_rules.expense_category_id', '=', 'expense_categories.id')
                       ->select('expense_policy_rules.*')
                       ->orderBy('expense_categories.name', 'desc');
        } elseif ($sort === 'limit_desc') {
            $rulesQuery->orderByRaw('COALESCE(max_limit_per_claim, 0) desc');
        } elseif ($sort === 'limit_asc') {
            $rulesQuery->orderByRaw('COALESCE(max_limit_per_claim, 99999999) asc');
        } else {
            $rulesQuery->join('expense_categories', 'expense_policy_rules.expense_category_id', '=', 'expense_categories.id')
                       ->select('expense_policy_rules.*')
                       ->orderBy('expense_categories.name', 'asc');
        }

        $rules = $rulesQuery->get();

        $categories = ExpenseCategory::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $usedCategoryIds = $policy->rules()->pluck('expense_category_id')->toArray();
        $availableCategories = $categories->whereNotIn('id', $usedCategoryIds)->values();

        return [
            'policy'              => $policy,
            'rules'               => $rules,
            'availableCategories' => $availableCategories,
            'filters'             => [
                'search'  => $filters['search'] ?? '',
                'sort'    => $sort,
                'receipt' => $filters['receipt'] ?? '',
            ],
        ];
    }

    public function storeWorkflow(array $data): ExpenseApprovalWorkflow
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        return DB::transaction(function () use ($tenantId, $data) {
            if (!empty($data['is_default'])) {
                ExpenseApprovalWorkflow::where('tenant_id', $tenantId)->update(['is_default' => false]);
            }

            return ExpenseApprovalWorkflow::create($data);
        });
    }

    public function updateWorkflow(ExpenseApprovalWorkflow $workflow, array $data): bool
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        return DB::transaction(function () use ($tenantId, $workflow, $data) {
            if (!empty($data['is_default'])) {
                ExpenseApprovalWorkflow::where('tenant_id', $tenantId)->where('id', '!=', $workflow->id)->update(['is_default' => false]);
            }

            return $workflow->update($data);
        });
    }

    public function deleteWorkflow(ExpenseApprovalWorkflow $workflow): bool
    {
        return (bool) $workflow->delete();
    }

    public function storeCategory(array $data): ExpenseCategory
    {
        return DB::transaction(function () use ($data) {
            return ExpenseCategory::create($data);
        });
    }

    public function updateCategory(ExpenseCategory $category, array $data): bool
    {
        return DB::transaction(function () use ($category, $data) {
            return $category->update($data);
        });
    }

    public function deleteCategory(ExpenseCategory $category): bool
    {
        return (bool) $category->delete();
    }
}
