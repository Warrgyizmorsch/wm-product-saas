<?php

namespace App\Domains\Accounting\Controllers\FixedAssets;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Repositories\AssetRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Asset Category management surfaced under Accounting's own gated routes.
 *
 * Categories are required to capitalize/depreciate a Fixed Asset, but the
 * only place to manage them was HRMS's Asset Management screen — a dead end
 * for tenants subscribed to Accounting without HRMS. This reuses the exact
 * same AssetCategory model/repository methods as the HRMS screen; only the
 * authorization source (fixed_assets.categories.* instead of hrms.assets.*)
 * and the route prefix (accounting instead of hrms) differ.
 */
class AssetCategoryController extends Controller
{
    public function __construct(
        private readonly AssetRepositoryInterface $assetRepository,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AssetCategory::class);

        $query = AssetCategory::query()->with(['company', 'chartOfAccount', 'accumulatedDepreciationAccount', 'depreciationExpenseAccount']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($companyId = $request->input('company_id')) {
            $query->where('company_id', $companyId);
        }

        $categories = $query->orderBy('name')->paginate(15)->withQueryString();
        $companies = Company::where('status', true)->orderBy('company_name')->get();
        $chartOfAccounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();

        return view('modules.accounting.fixed-assets.categories', compact('categories', 'companies', 'chartOfAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AssetCategory::class);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'fixed_asset_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'accumulated_depreciation_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'depreciation_expense_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'default_depreciation_method' => 'nullable|string|in:straight_line,wdv',
            'default_useful_life_months' => 'nullable|integer|min:1',
            'is_production_machinery' => 'boolean',
        ]);
        $validated['is_production_machinery'] = $request->boolean('is_production_machinery');

        $this->assetRepository->storeCategory($validated);

        return redirect()->route('accounting.fixed-assets.categories.index')->with('success', 'Asset category created successfully.');
    }

    public function update(Request $request, AssetCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'fixed_asset_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'accumulated_depreciation_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'depreciation_expense_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'default_depreciation_method' => 'nullable|string|in:straight_line,wdv',
            'default_useful_life_months' => 'nullable|integer|min:1',
            'is_production_machinery' => 'boolean',
        ]);
        $validated['is_production_machinery'] = $request->boolean('is_production_machinery');

        $this->assetRepository->updateCategory($category, $validated);

        return redirect()->route('accounting.fixed-assets.categories.index')->with('success', 'Asset category updated successfully.');
    }

    public function destroy(AssetCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $assetCount = $category->assets()->count();
        if ($assetCount > 0) {
            return redirect()->back()->with('error', "Cannot delete '{$category->name}' — {$assetCount} asset(s) are linked to it.");
        }

        $this->assetRepository->deleteCategory($category);

        return redirect()->route('accounting.fixed-assets.categories.index')->with('success', 'Asset category deleted successfully.');
    }
}
