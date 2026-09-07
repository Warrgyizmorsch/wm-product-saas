<?php

namespace App\Domains\Accounting\Controllers\FixedAssets;

use App\Domains\Accounting\FixedAssets\Services\AssetCapitalizationService;
use App\Domains\HRMS\Models\Asset;
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
        abort_unless($this->policy->viewAny($request->user()), 403);

        $filters = $request->only(['status', 'search', 'category_id']);

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
            ->orderByDesc('id')
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
        abort_unless($this->policy->viewAny($request->user()), 403);

        $asset->load([
            'category',
            'assignedEmployee',
            'depreciationSchedules',
            'disposals',
            'writeOffs',
            'revaluations',
        ]);

        return view('modules.accounting.fixed-assets.show', [
            'asset' => $asset,
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
            'asset_category_id' => 'required|exists:asset_categories,id',
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
        ]);

        try {
            $this->capitalization->capitalize($asset, $validated, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('accounting.fixed-assets.show', $asset)->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.fixed-assets.show', $asset)
            ->with('success', "Asset {$asset->asset_code} capitalized successfully.");
    }
}
