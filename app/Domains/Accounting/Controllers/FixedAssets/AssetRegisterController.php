<?php

namespace App\Domains\Accounting\Controllers\FixedAssets;

use App\Domains\Accounting\FixedAssets\Services\AssetCapitalizationService;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\HRMS\Models\Asset;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Policies\AssetPolicy;
use App\Domains\HRMS\Repositories\AssetRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AssetRegisterController extends Controller
{
    public function __construct(
        private readonly AssetCapitalizationService $capitalization,
        private readonly AssetPolicy $policy,
        private readonly AssetRepositoryInterface $assetRepository,
        private readonly AccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->canView($request), 403);

        $filters = $request->only(['status', 'search', 'category_id', 'sort', 'direction']);

        $sortable = ['asset_code', 'name', 'purchase_date', 'capitalization_cost', 'status'];
        $sort = in_array($filters['sort'] ?? null, $sortable, true) ? $filters['sort'] : null;
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $assets = Asset::with('category')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['category_id'] ?? null, fn ($q, $categoryId) => $q->where('asset_category_id', $categoryId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%");
                });
            })
            ->when($sort, fn ($q) => $q->orderBy($sort, $direction), fn ($q) => $q->orderByDesc('id'))
            ->paginate(15)
            ->withQueryString();

        $categories = AssetCategory::orderBy('name')->get();

        return view('modules.accounting.fixed-assets.index', [
            'assets' => $assets,
            'categories' => $categories,
            'filters' => $filters,
            'canCapitalize' => $this->policy->capitalize($request->user()),
        ]);
    }

    public function show(Request $request, Asset $asset): View
    {
        abort_unless($this->canView($request), 403);

        $asset->load([
            'category',
            'assignedEmployee',
            'purchaseOrder',
            'depreciationSchedules',
            'disposals',
            'writeOffs',
            'revaluations',
        ]);

        $needsLedgerEntry = $this->capitalization->needsLedgerEntry($asset);
        $chartOfAccounts = $needsLedgerEntry
            ? ChartOfAccount::where('is_active', true)->orderBy('code')->get()
            : collect();

        return view('modules.accounting.fixed-assets.show', [
            'asset' => $asset,
            'needsLedgerEntry' => $needsLedgerEntry,
            'capitalizationJournal' => $asset->capitalization_date ? $this->capitalization->capitalizationJournal($asset) : null,
            'equityAccounts' => $chartOfAccounts->where('type', ChartOfAccount::TYPE_EQUITY)->values(),
            'cashBankAccounts' => $chartOfAccounts->where('is_cash_or_bank', true)->values(),
            'vendors' => $needsLedgerEntry ? Vendor::orderBy('name')->get(['id', 'name']) : collect(),
            'canCapitalize' => $this->policy->capitalize($request->user(), $asset),
        ]);
    }

    /**
     * Manual asset registration for tenants without HRMS — a single-asset
     * equivalent of HRMS's multi-unit "Add Item" flow, reusing the same
     * repository method under the hood.
     */
    public function create(Request $request): View
    {
        abort_unless(
            $this->access->allows($request->user(), 'fixed_assets.assets.create', ['tenant_id' => $request->user()->tenant_id]),
            403
        );

        $categories = AssetCategory::orderBy('name')->get();

        return view('modules.accounting.fixed-assets.register', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            $this->access->allows($request->user(), 'fixed_assets.assets.create', ['tenant_id' => $request->user()->tenant_id]),
            403
        );

        $validated = $request->validate([
            'asset_category_id' => ['required', tenant_exists('asset_categories')],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'brand' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'asset_code' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'condition' => 'required|string|in:new,good,fair,damaged,scrapped',
        ]);

        $validated['units'] = [[
            'asset_code' => $validated['asset_code'],
            'serial_number' => $validated['serial_number'] ?? null,
            'condition' => $validated['condition'],
        ]];

        $item = $this->assetRepository->createAssetItemWithUnits($validated);
        $asset = $item->assets()->latest('id')->first();

        return redirect()->route('accounting.fixed-assets.show', $asset)
            ->with('success', "Asset {$asset->asset_code} registered successfully.");
    }

    public function capitalize(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($this->policy->capitalize($request->user(), $asset), 403);

        $validated = $request->validate([
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'directly_attributable_cost' => ['nullable', 'numeric', 'min:0'],
            'recoverable_tax' => ['nullable', 'numeric', 'min:0'],
            'non_recoverable_tax' => ['nullable', 'numeric', 'min:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:1'],
            'depreciation_method' => ['nullable', 'string', 'in:straight_line,wdv'],
            'depreciation_start_date' => ['nullable', 'date'],
            'capitalization_date' => ['nullable', 'date'],
            ...$this->fundingRules($asset),
        ]);

        try {
            $this->capitalization->capitalize($asset, $validated, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('accounting.fixed-assets.show', $asset)->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.fixed-assets.show', $asset)
            ->with('success', "Asset {$asset->asset_code} capitalized successfully.");
    }

    /**
     * The register is an Accounting screen: the fixed-asset permission opens it,
     * as does the HRMS asset permission it was originally gated on (so existing
     * HR roles keep their access).
     */
    private function canView(Request $request): bool
    {
        $user = $request->user();

        return $this->access->allows($user, 'fixed_assets.assets.view', ['tenant_id' => $user->tenant_id])
            || $this->policy->viewAny($user);
    }

    /**
     * Post the ledger entry an asset added by hand never got (it was capitalized
     * before capitalization posted journals).
     */
    public function postCapitalizationEntry(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($this->policy->capitalize($request->user(), $asset), 403);

        $validated = $request->validate($this->fundingRules($asset));

        try {
            $journal = $this->capitalization->postMissingEntry($asset, $validated, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('accounting.fixed-assets.show', $asset)->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.fixed-assets.show', $asset)
            ->with('success', "Capitalization entry {$journal->journal_number} posted for {$asset->asset_code}.");
    }

    /**
     * How a manually added asset was paid for. Not asked for assets bought
     * through Purchase: their bill already posted the cost.
     *
     * @return array<string, array<int, mixed>>
     */
    private function fundingRules(Asset $asset): array
    {
        if (! $this->capitalization->needsLedgerEntry($asset)) {
            return [];
        }

        return [
            'funding_source' => ['required', 'in:' . implode(',', AssetCapitalizationService::FUNDING_SOURCES)],
            'funding_account_id' => [
                'nullable',
                'required_unless:funding_source,' . AssetCapitalizationService::FUNDING_VENDOR,
                'integer',
                tenant_exists('chart_of_accounts'),
            ],
            'vendor_id' => [
                'nullable',
                'required_if:funding_source,' . AssetCapitalizationService::FUNDING_VENDOR,
                'integer',
                tenant_exists('vendors'),
            ],
        ];
    }
}
