<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\ExpenseCategory;
use App\Domains\HRMS\Repositories\ExpensePolicyRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        private readonly ExpensePolicyRepositoryInterface $policyRepository
    ) {
    }

    public function index(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        return redirect()->route('hrms.expense-policy.index', [
            'tab'        => 'categories',
            'cat_search' => $request->query('search', ''),
            'cat_status' => $request->query('status', ''),
            'cat_sort'   => $request->query('sort', 'name_asc'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => [
                'required',
                'string',
                'max:50',
                Rule::unique('expense_categories')->where('tenant_id', $tenantId),
            ],
            'description' => 'nullable|string|max:1000',
            'status'      => 'nullable|boolean',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $this->policyRepository->storeCategory($validated);

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'categories'])
            ->with('success', __('hrms.expense_master.category_created_success'));
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $this->authorizeManage();

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => [
                'required',
                'string',
                'max:50',
                Rule::unique('expense_categories')
                    ->where('tenant_id', $tenantId)
                    ->ignore($category->id),
            ],
            'description' => 'nullable|string|max:1000',
            'status'      => 'nullable|boolean',
        ]);

        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $this->policyRepository->updateCategory($category, $validated);

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'categories'])
            ->with('success', __('hrms.expense_master.category_updated_success'));
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        $this->authorizeManage();

        // Check if category has claims before deleting
        if ($category->claims()->exists()) {
            return redirect()->route('hrms.expense-policy.index', ['tab' => 'categories'])
                ->with('error', __('hrms.expense_master.category_cannot_delete_has_claims'));
        }

        $this->policyRepository->deleteCategory($category);

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'categories'])
            ->with('success', __('hrms.expense_master.category_deleted_success'));
    }

    /**
     * Expense categories are HR policy master data; every action here used to
     * be open to any signed-in user.
     */
    private function authorizeManage(): void
    {
        $user = auth()->user();

        abort_unless(
            $user && app(\App\Services\Access\AccessService::class)->allows($user, 'hrms.expense_policies.manage', [
                'tenant_id' => $user->tenant_id,
            ]),
            403
        );
    }
}
