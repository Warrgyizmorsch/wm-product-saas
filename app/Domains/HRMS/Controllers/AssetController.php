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
        // Self-healing 1: Ensure all currently allocated assets have an active AssetAllocation record
        $allocatedAssetsWithoutActiveAlloc = Asset::where('status', 'allocated')
            ->whereNotNull('assigned_employee_id')
            ->whereDoesntHave('allocations', function ($query) {
                $query->whereNull('returned_at');
            })
            ->get();

        foreach ($allocatedAssetsWithoutActiveAlloc as $asset) {
            \App\Domains\HRMS\Models\AssetAllocation::create([
                'asset_id'             => $asset->id,
                'employee_id'          => $asset->assigned_employee_id,
                'allocated_at'         => $asset->allocated_at ?? now(),
                'allocation_condition' => $asset->condition ?? 'good',
                'notes'                => 'Auto-generated active allocation record (self-healing)',
            ]);
        }

        // Self-healing 2: Ensure any returned allocations have return_condition populated
        \App\Domains\HRMS\Models\AssetAllocation::whereNotNull('returned_at')
            ->whereNull('return_condition')
            ->get()
            ->each(function($alloc) {
                $cond = 'good';
                if ($alloc->notes && str_contains($alloc->notes, 'Condition: lost')) {
                    $cond = 'scrapped';
                } elseif ($alloc->notes && str_contains($alloc->notes, 'Condition: damaged')) {
                    $cond = 'damaged';
                } elseif ($alloc->notes && str_contains($alloc->notes, 'Condition: fair')) {
                    $cond = 'fair';
                }
                $alloc->update(['return_condition' => $cond]);
            });

        $data = $this->assetRepository->getIndexData($request->all());

        return view('modules.hrms.assets.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
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
        if ($asset->status === 'allocated' || $asset->assigned_employee_id !== null) {
            return redirect()->back()->with('error', "Cannot delete asset '{$asset->asset_code}' because it is currently allocated to an employee. Please return or deallocate it first.");
        }

        $this->assetRepository->deleteAsset($asset);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_deleted'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'fixed_asset_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
        ]);

        $this->assetRepository->storeCategory($validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_cat_created'));
    }

    public function updateCategory(Request $request, AssetCategory $assetCategory): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'fixed_asset_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
        ]);

        $this->assetRepository->updateCategory($assetCategory, $validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_cat_updated'));
    }

    public function destroyCategory(AssetCategory $assetCategory): RedirectResponse
    {
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
        $validated = $request->validate([
            'condition_on_return' => 'required|string|in:new,good,fair,damaged,scrapped',
        ]);

        $this->assetRepository->returnAsset($asset, $validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_returned'));
    }

    public function allocateItem(Request $request, AssetItem $assetItem): RedirectResponse
    {
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
        if (!$request->has('quantity') && $request->has('allocated_asset_ids')) {
            $request->merge([
                'quantity' => count($request->input('allocated_asset_ids', []))
            ]);
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'quantity' => 'required|integer|min:1',
            'condition_on_return' => 'required|string|in:new,good,fair,damaged,scrapped',
            'allocated_asset_ids' => 'nullable|array',
            'allocated_asset_ids.*' => 'exists:assets,id',
        ]);

        $success = $this->assetRepository->returnItem($assetItem, $validated);
        if (!$success) {
            return redirect()->back()->withErrors(['quantity' => 'Could not find that many allocated units for this employee.']);
        }

        return redirect()->back()->with('success', __('hrms.assets.success_returned'));
    }

    public function updateItem(Request $request, AssetItem $assetItem): RedirectResponse
    {
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
        $allocatedCount = $assetItem->assets()->where('status', 'allocated')->count();
        if ($allocatedCount > 0) {
            return redirect()->back()->with('error', "Cannot delete item '{$assetItem->name}' because {$allocatedCount} unit(s) are currently allocated.");
        }

        $assetItem->assets()->delete();
        $assetItem->delete();

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_deleted'));
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_category_id' => 'required|exists:asset_categories,id',
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string|max:500',
        ]);

        $this->assetRepository->storeAssetItem($validated);

        return redirect()->back()->with('success', 'Asset item created successfully.');
    }

    public function export(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->assetRepository->export();
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $result = $this->assetRepository->import($request->file('file'));

        return redirect()->back()->with('success', $result['message'] ?? 'Assets imported successfully.');
    }

    public function downloadTemplate(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->assetRepository->downloadTemplate();
    }

    public function exportCategories(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->assetRepository->exportCategories();
    }

    public function importCategories(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $result = $this->assetRepository->importCategories($request->file('file'));

        return redirect()->back()->with('success', $result['message'] ?? 'Categories imported successfully.');
    }

    public function downloadCategoriesTemplate(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->assetRepository->downloadCategoriesTemplate();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Asset Requests
    // ─────────────────────────────────────────────────────────────────────────

    public function storeRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id'              => 'required|exists:employees,id',
            'reason'                   => 'required|string|max:1000',
            'items'                    => 'required|array|min:1',
            'items.*.asset_item_id'    => 'required|exists:asset_items,id',
            'items.*.quantity'         => 'required|integer|min:1',
        ]);

        $employee    = \App\Domains\HRMS\Models\Employee::findOrFail($validated['employee_id']);
        $companyId   = $employee->company_id;
        $requestDate = date('Y-m-d');
        $reason      = $validated['reason'];

        foreach ($validated['items'] as $item) {
            $assetItem = \App\Domains\HRMS\Models\AssetItem::find($item['asset_item_id']);
            if (!$assetItem) {
                continue;
            }

            AssetRequest::create([
                'company_id'        => $companyId,
                'employee_id'       => $employee->id,
                'asset_category_id' => $assetItem->asset_category_id,
                'asset_item_id'     => $assetItem->id,
                'quantity'          => $item['quantity'],
                'reason'            => $reason,
                'request_date'      => $requestDate,
                'status'            => 'pending',
            ]);
        }

        return redirect()->back()->with('success', 'Asset request(s) submitted successfully.');
    }


    public function rejectRequest(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $assetRequest->update([
            'status'      => 'rejected',
            'admin_notes' => $validated['admin_notes'] ?? 'Withdrawn by employee.',
        ]);

        return redirect()->back()->with('success', 'Asset request rejected successfully.');
    }

    public function allocateDirect(AssetRequest $assetRequest): RedirectResponse
    {
        if ($assetRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending asset requests can be allocated.');
        }

        $asset = null;
        if ($assetRequest->requested_asset_id) {
            $asset = Asset::find($assetRequest->requested_asset_id);
            if (!$asset || $asset->status !== 'available') {
                return redirect()->back()->with('error', 'The specifically requested asset is not currently available.');
            }
        } else {
            $asset = Asset::query()
                ->where('asset_category_id', $assetRequest->asset_category_id)
                ->where('company_id', $assetRequest->company_id)
                ->where('status', 'available')
                ->first();

            if (!$asset) {
                return redirect()->back()->with('error', 'No available asset found in this category for allocation.');
            }
        }

        $asset->update([
            'status'               => 'allocated',
            'assigned_employee_id' => $assetRequest->employee_id,
            'allocated_at'         => date('Y-m-d'),
            'expected_return_date' => null,
        ]);

        $asset->allocations()->create([
            'employee_id'          => $assetRequest->employee_id,
            'allocated_at'         => date('Y-m-d'),
            'allocation_condition' => $asset->condition,
            'notes'                => $asset->notes,
        ]);

        $assetRequest->update([
            'status'             => 'allocated',
            'allocated_asset_id' => $asset->id,
            'admin_notes'        => "Allocated asset {$asset->asset_code} ({$asset->name}) directly on " . date('d M, Y'),
        ]);

        return redirect()->back()->with('success', 'Asset allocated directly for request.');
    }

    public function allocateRequest(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        if ($request->has('allocated_asset_ids') && !$request->has('asset_ids')) {
            $request->merge([
                'asset_ids' => $request->input('allocated_asset_ids')
            ]);
        }

        $validated = $request->validate([
            'asset_ids'            => 'required|array|min:1',
            'asset_ids.*'          => 'required|exists:assets,id',
            'allocated_at'         => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:allocated_at',
        ]);

        $assetIds = $validated['asset_ids'];
        $assets = Asset::whereIn('id', $assetIds)->get();

        foreach ($assets as $asset) {
            if ($asset->status !== 'available') {
                return redirect()->back()->with('error', "Asset {$asset->asset_code} ({$asset->name}) is not currently available.");
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($assets, $assetRequest, $validated, $assetIds) {
            $assetCodes = [];
            foreach ($assets as $asset) {
                $asset->update([
                    'status'               => 'allocated',
                    'assigned_employee_id' => $assetRequest->employee_id,
                    'allocated_at'         => $validated['allocated_at'],
                    'expected_return_date' => $validated['expected_return_date'] ?? null,
                ]);

                $asset->allocations()->create([
                    'employee_id'          => $assetRequest->employee_id,
                    'allocated_at'         => $validated['allocated_at'],
                    'allocation_condition' => $asset->condition,
                    'notes'                => $asset->notes,
                ]);

                $assetCodes[] = $asset->asset_code;
            }

            $assetRequest->update([
                'status'             => 'allocated',
                'allocated_asset_id' => $assetIds[0],
                'admin_notes'        => "Allocated asset(s): " . implode(', ', $assetCodes) . " on " . date('d M, Y'),
            ]);
        });

        return redirect()->back()->with('success', 'Asset request allocated successfully.');
    }

    public function bulkAllocate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'allocations'          => 'required|array',
            'allocations.*'        => 'nullable|exists:assets,id',
            'allocated_at'         => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:allocated_at',
        ]);

        $allocatedAt        = $validated['allocated_at'];
        $expectedReturnDate = $validated['expected_return_date'] ?? null;
        $allocatedCount     = 0;

        foreach ($validated['allocations'] as $requestId => $assetId) {
            if (empty($assetId)) {
                continue;
            }

            $assetRequest = AssetRequest::find($requestId);
            $asset        = Asset::find($assetId);

            if ($assetRequest && $asset && $asset->status === 'available') {
                $asset->update([
                    'status'               => 'allocated',
                    'assigned_employee_id' => $assetRequest->employee_id,
                    'allocated_at'         => $allocatedAt,
                    'expected_return_date' => $expectedReturnDate,
                ]);

                $asset->allocations()->create([
                    'employee_id'          => $assetRequest->employee_id,
                    'allocated_at'         => $allocatedAt,
                    'allocation_condition' => $asset->condition,
                    'notes'                => $asset->notes,
                ]);

                $assetRequest->update([
                    'status'             => 'allocated',
                    'allocated_asset_id' => $asset->id,
                    'admin_notes'        => "Bulk allocated asset {$asset->asset_code} ({$asset->name}) on " . date('d M, Y'),
                ]);

                $allocatedCount++;
            }
        }

        return redirect()->back()->with('success', "Successfully allocated {$allocatedCount} asset request(s).");
    }

    public function bulkReject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'request_ids'   => 'required|array',
            'request_ids.*' => 'exists:asset_requests,id',
            'admin_notes'   => 'nullable|string|max:1000',
        ]);

        AssetRequest::whereIn('id', $validated['request_ids'])
            ->where('status', 'pending')
            ->update([
                'status'      => 'rejected',
                'admin_notes' => $validated['admin_notes'] ?? 'Bulk rejected.',
            ]);

        return redirect()->back()->with('success', 'Selected asset requests have been rejected.');
    }
}

