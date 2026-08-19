<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\ExpenseCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('hrms.expense-policy.index', [
            'tab'        => 'categories',
            'cat_search' => $request->query('search', ''),
            'cat_status' => $request->query('status', ''),
            'cat_sort'   => $request->query('sort', 'name_asc'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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

        ExpenseCategory::create($validated);

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'categories'])
            ->with('success', 'Expense category created successfully.');
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
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

        $category->update($validated);

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'categories'])
            ->with('success', 'Expense category updated successfully.');
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        // Check if category has claims before deleting
        if ($category->claims()->exists()) {
            return redirect()->route('hrms.expense-policy.index', ['tab' => 'categories'])
                ->with('error', 'Cannot delete category as it contains submitted expense claims.');
        }

        $category->delete();

        return redirect()->route('hrms.expense-policy.index', ['tab' => 'categories'])
            ->with('success', 'Expense category deleted successfully.');
    }
}
