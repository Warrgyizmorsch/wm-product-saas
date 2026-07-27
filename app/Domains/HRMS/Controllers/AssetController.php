<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\AssetItem;
use App\Domains\HRMS\Models\AssetRequest;
use App\Domains\HRMS\Repositories\AssetRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetRepositoryInterface $assetRepository
    ) {}

    /**
     * Display a listing of assets and categories.
     */
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $data = $this->assetRepository->getIndexData($request->all());

        return view('modules.hrms.assets.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $rules = [
            'asset_category_id' => 'required|exists:asset_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'brand' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'units' => 'required|array|min:1',
            'units.*.asset_code' => 'required|string|max:255',
            'units.*.serial_number' => 'required|string|max:255',
            'units.*.condition' => 'required|string|in:new,good,fair,damaged,scrapped',
        ];

        $submittedCodes = [];
        $submittedSerials = [];
        foreach ($request->input('units', []) as $u) {
            $code = trim($u['asset_code'] ?? '');
            $serial = trim($u['serial_number'] ?? '');
            if ($code !== '') {
                if (in_array($code, $submittedCodes)) {
                    return redirect()->back()->withInput()->with('error', "Asset code '{$code}' is duplicated within the unit list.");
                }
                $submittedCodes[] = $code;
            }
            if ($serial !== '') {
                if (in_array($serial, $submittedSerials)) {
                    return redirect()->back()->withInput()->with('error', "Serial number '{$serial}' is duplicated within the unit list.");
                }
                $submittedSerials[] = $serial;
            }
        }

        $validated = $request->validate($rules);
        $this->assetRepository->createAssetItemWithUnits($validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_logged'));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $rules = [
            'asset_code' => [
                'required', 'string', 'max:255',
                Rule::unique('assets', 'asset_code')
                    ->where('asset_item_id', $asset->asset_item_id)
                    ->where('tenant_id', $asset->tenant_id)
                    ->ignore($asset->id)
            ],
            'brand' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'serial_number' => 'required|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'condition' => 'required|string|in:new,good,fair,damaged,scrapped',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($request->has('asset_item_id')) {
            $rules['asset_item_id'] = 'required|exists:asset_items,id';
        } else {
            $rules['asset_category_id'] = 'required|exists:asset_categories,id';
            $rules['name'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);
        $this->assetRepository->updateAsset($asset, $validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_updated'));
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        if ($asset->status === 'allocated' || $asset->assigned_employee_id !== null) {
            return redirect()->back()->with('error', "Cannot delete asset '{$asset->asset_code}' because it is currently allocated to an employee. Please return or deallocate it first.");
        }

        $this->assetRepository->deleteAsset($asset);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_deleted'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $this->assetRepository->storeCategory($validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_cat_created'));
    }

    public function updateCategory(Request $request, AssetCategory $assetCategory): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $this->assetRepository->updateCategory($assetCategory, $validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_cat_updated'));
    }

    public function destroyCategory(AssetCategory $assetCategory): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $assetCount = $assetCategory->assets()->count();
        if ($assetCount > 0) {
            return redirect()->back()->with('error', __('hrms.assets.error_cat_has_assets', ['name' => $assetCategory->name, 'count' => $assetCount]));
        }

        $requestCount = AssetRequest::where('asset_category_id', $assetCategory->id)->count();
        if ($requestCount > 0) {
            return redirect()->back()->with('error', __('hrms.assets.error_cat_has_requests', ['name' => $assetCategory->name, 'count' => $requestCount]));
        }

        $this->assetRepository->deleteCategory($assetCategory);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_cat_deleted'));
    }

    public function allocate(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'assigned_employee_id' => 'required|exists:employees,id',
            'allocated_at' => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:allocated_at',
            'request_id' => 'nullable|exists:asset_requests,id',
        ]);

        $this->assetRepository->allocateAsset($asset, $validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_allocated'));
    }

    public function returnAsset(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'condition_on_return' => 'required|string|in:new,good,fair,damaged,scrapped',
        ]);

        $this->assetRepository->returnAsset($asset, $validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_returned'));
    }

    public function allocateItem(Request $request, AssetItem $assetItem): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'assigned_employee_id' => 'required|exists:employees,id',
            'quantity' => 'required|integer|min:1',
            'allocated_at' => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:allocated_at',
            'request_id' => 'nullable|exists:asset_requests,id',
        ]);

        $success = $this->assetRepository->allocateItem($assetItem, $validated);
        if (!$success) {
            return redirect()->back()->withErrors(['quantity' => 'Insufficient available units left to allocate.']);
        }

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_allocated'));
    }

    public function returnItem(Request $request, AssetItem $assetItem): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'quantity' => 'required|integer|min:1',
            'condition_on_return' => 'required|string|in:new,good,fair,damaged,scrapped',
        ]);

        $success = $this->assetRepository->returnItem($assetItem, $validated);
        if (!$success) {
            return redirect()->back()->withErrors(['quantity' => 'Could not find that many allocated units for this employee.']);
        }

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_returned'));
    }

    public function updateItem(Request $request, AssetItem $assetItem): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'asset_category_id' => 'required|exists:asset_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $this->assetRepository->updateAssetItem($assetItem, $validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_updated'));
    }

    public function destroyItem(AssetItem $assetItem): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $allocatedCount = $assetItem->assets()->where('status', 'allocated')->count();
        if ($allocatedCount > 0) {
            return redirect()->back()->with('error', "Cannot delete item '{$assetItem->name}' because {$allocatedCount} unit(s) are currently allocated.");
        }

        $assetItem->assets()->delete();
        $assetItem->delete();

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_deleted'));
    }
}
