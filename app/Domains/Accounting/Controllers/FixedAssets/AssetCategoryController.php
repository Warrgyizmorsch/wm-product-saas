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
        $optionsOfType = fn (string $type) => $chartOfAccounts->where('type', $type)
            ->mapWithKeys(fn ($acc) => [$acc->id => $acc->code . ' - ' . $acc->name])->all();

        return view('modules.accounting.fixed-assets.categories', [
            'categories' => $categories,
            'companies' => $companies,
            'chartOfAccounts' => $chartOfAccounts,
            // Each head only offers accounts of the type it posts to, so a
            // category cannot send depreciation to a balance-sheet account.
            'assetAccountOptions' => $optionsOfType(ChartOfAccount::TYPE_ASSET),
            'expenseAccountOptions' => $optionsOfType(ChartOfAccount::TYPE_EXPENSE),
            'incomeAccountOptions' => $optionsOfType(ChartOfAccount::TYPE_INCOME),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AssetCategory::class);

        $validated = $request->validate([
            'company_id' => ['required', tenant_exists('companies')],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            ...$this->accountHeadRules(),
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
            'company_id' => ['required', tenant_exists('companies')],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            ...$this->accountHeadRules(),
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

    /**
     * Each posting head must point at an account of the type it is posted as:
     * fixed asset and accumulated depreciation are balance-sheet asset accounts
     * (the latter a credit-balance contra asset), depreciation expense and loss
     * on disposal are expenses, gain on disposal is income.
     *
     * @return array<string, array<int, mixed>>
     */
    private function accountHeadRules(): array
    {
        $ofType = fn (string $type) => ['nullable', 'integer', tenant_exists('chart_of_accounts')->where('type', $type)];

        return [
            'fixed_asset_account_id' => $ofType(ChartOfAccount::TYPE_ASSET),
            'accumulated_depreciation_account_id' => $ofType(ChartOfAccount::TYPE_ASSET),
            'depreciation_expense_account_id' => $ofType(ChartOfAccount::TYPE_EXPENSE),
            'gain_on_disposal_account_id' => $ofType(ChartOfAccount::TYPE_INCOME),
            'loss_on_disposal_account_id' => $ofType(ChartOfAccount::TYPE_EXPENSE),
        ];
    }
}
