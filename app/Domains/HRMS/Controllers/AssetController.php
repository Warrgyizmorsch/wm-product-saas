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
        $this->authorize('viewAny', Asset::class);

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
        $this->authorize('create', Asset::class);

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
                    return redirect()->back()->withInput()->with('error', __('hrms.assets.error_dup_code', ['code' => $code]));
                }
                $submittedCodes[] = $code;
            }
            if ($serial !== '') {
                if (in_array($serial, $submittedSerials)) {
                    return redirect()->back()->withInput()->with('error', __('hrms.assets.error_dup_serial', ['serial' => $serial]));
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
        $this->authorize('update', $asset);

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
        $this->authorize('delete', $asset);

        if ($asset->status === 'allocated' || $asset->assigned_employee_id !== null) {
            return redirect()->back()->with('error', __('hrms.assets.error_asset_allocated', ['code' => $asset->asset_code]));
        }

        if ($reason = $asset->blockingAccountingRecords()) {
            return redirect()->back()->with('error', __('hrms.assets.error_asset_blocked_accounting', ['code' => $asset->asset_code, 'reason' => $reason]));
        }

        $this->assetRepository->deleteAsset($asset);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_deleted'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'fixed_asset_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'is_production_machinery' => 'boolean',
        ]);
        $validated['is_production_machinery'] = $request->boolean('is_production_machinery');

        $this->assetRepository->storeCategory($validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_cat_created'));
    }

    public function updateCategory(Request $request, AssetCategory $assetCategory): RedirectResponse
    {
        $this->authorize('update', Asset::class);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'fixed_asset_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'is_production_machinery' => 'boolean',
        ]);
        $validated['is_production_machinery'] = $request->boolean('is_production_machinery');

        $this->assetRepository->updateCategory($assetCategory, $validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_cat_updated'));
    }

    public function destroyCategory(AssetCategory $assetCategory): RedirectResponse
    {
        $this->authorize('delete', Asset::class);

        $assetCount = $assetCategory->assets()->count();
        if ($assetCount > 0) {
            return redirect()->back()->with('error', __('hrms.assets.error_cat_has_assets', ['name' => $assetCategory->name, 'count' => $assetCount]));
        }

        $itemCount = AssetItem::where('asset_category_id', $assetCategory->id)->count();
        if ($itemCount > 0) {
            return redirect()->back()->with('error', __('hrms.assets.error_cat_has_items', ['name' => $assetCategory->name, 'count' => $itemCount]));
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
        $this->authorize('approve', $asset);

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
        $this->authorize('approve', $asset);

        if (!$request->has('condition_on_return') && $request->has('return_condition')) {
            $request->merge([
                'condition_on_return' => $request->input('return_condition'),
            ]);
        }

        $validated = $request->validate([
            'condition_on_return' => 'required|string|in:new,good,fair,damaged,scrapped',
            'return_notes'        => 'nullable|string|max:1000',
            'returned_at'         => 'nullable|date',
        ]);

        $this->assetRepository->returnAsset($asset, $validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_returned'));
    }

    public function allocateItem(Request $request, AssetItem $assetItem): RedirectResponse
    {
        $this->authorize('approve', Asset::class);

        $validated = $request->validate([
            'assigned_employee_id' => 'required|exists:employees,id',
            'quantity'             => 'required|integer|min:1',
            'allocated_at'         => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:allocated_at',
            'request_id'           => 'nullable|exists:asset_requests,id',
        ]);

        $success = $this->assetRepository->allocateItem($assetItem, $validated);
        if (!$success) {
            return redirect()->back()->withErrors(['quantity' => __('hrms.assets.error_insufficient_units')]);
        }

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_allocated'));
    }

    public function returnItem(Request $request, AssetItem $assetItem): RedirectResponse
    {
        $this->authorize('approve', Asset::class);

        if (!$request->has('quantity') && $request->has('allocated_asset_ids')) {
            $request->merge([
                'quantity' => count($request->input('allocated_asset_ids', [])),
            ]);
        }

        if (!$request->has('condition_on_return') && $request->has('return_condition')) {
            $request->merge([
                'condition_on_return' => $request->input('return_condition'),
            ]);
        }

        $validated = $request->validate([
            'employee_id'         => 'required|exists:employees,id',
            'quantity'            => 'required|integer|min:1',
            'condition_on_return' => 'required|string|in:new,good,fair,damaged,scrapped',
            'allocated_asset_ids' => 'nullable|array',
            'allocated_asset_ids.*' => 'exists:assets,id',
            'return_notes'        => 'nullable|string|max:1000',
            'returned_at'         => 'nullable|date',
        ]);

        $success = $this->assetRepository->returnItem($assetItem, $validated);
        if (!$success) {
            return redirect()->back()->withErrors(['quantity' => __('hrms.assets.error_not_enough_allocated_units')]);
        }

        return redirect()->back()->with('success', __('hrms.assets.success_returned'));
    }

    public function updateItem(Request $request, AssetItem $assetItem): RedirectResponse
    {
        $this->authorize('update', Asset::class);

        $rules = [
            'asset_category_id'     => 'required|exists:asset_categories,id',
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string|max:500',
            'brand'                 => 'nullable|string|max:255',
            'model_number'          => 'nullable|string|max:255',
            'purchase_date'         => 'nullable|date',
            'purchase_cost'         => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string|max:1000',
            'units'                 => 'nullable|array',
            'units.*.id'            => 'nullable|integer|exists:assets,id',
            'units.*.asset_code'    => 'required_with:units|string|max:255',
            'units.*.serial_number' => 'nullable|string|max:255',
            'units.*.condition'     => 'nullable|string|in:new,good,fair,damaged,scrapped',
        ];

        $submittedCodes = [];
        $submittedSerials = [];
        foreach ($request->input('units', []) as $u) {
            $code = trim($u['asset_code'] ?? '');
            $serial = trim($u['serial_number'] ?? '');
            if ($code !== '') {
                if (in_array($code, $submittedCodes)) {
                    return redirect()->back()->withInput()->with('error', __('hrms.assets.error_dup_code', ['code' => $code]));
                }
                $submittedCodes[] = $code;
            }
            if ($serial !== '') {
                if (in_array($serial, $submittedSerials)) {
                    return redirect()->back()->withInput()->with('error', __('hrms.assets.error_dup_serial', ['serial' => $serial]));
                }
                $submittedSerials[] = $serial;
            }
        }

        $validated = $request->validate($rules);
        $this->assetRepository->updateAssetItem($assetItem, $validated);

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_updated'));
    }

    public function destroyItem(AssetItem $assetItem): RedirectResponse
    {
        $this->authorize('delete', Asset::class);

        $allocatedCount = $assetItem->assets()->where('status', 'allocated')->count();
        if ($allocatedCount > 0) {
            return redirect()->back()->with('error', __('hrms.assets.error_item_has_allocated_units', ['name' => $assetItem->name, 'count' => $allocatedCount]));
        }

        foreach ($assetItem->assets as $asset) {
            if ($reason = $asset->blockingAccountingRecords()) {
                return redirect()->back()->with('error', __('hrms.assets.error_item_blocked_accounting', ['name' => $assetItem->name, 'code' => $asset->asset_code, 'reason' => $reason]));
            }
        }

        $requestCount = AssetRequest::where('asset_item_id', $assetItem->id)
            ->whereIn('status', ['pending', 'partially_allocated'])
            ->count();
        if ($requestCount > 0) {
            return redirect()->back()->with('error', __('hrms.assets.error_item_has_pending_requests', ['name' => $assetItem->name, 'count' => $requestCount]));
        }

        $assetItem->assets()->delete();
        $assetItem->delete();

        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_deleted'));
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $validated = $request->validate([
            'asset_category_id' => 'required|exists:asset_categories,id',
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string|max:500',
        ]);

        $this->assetRepository->storeAssetItem($validated);

        return redirect()->back()->with('success', __('hrms.assets.success_item_created'));
    }

    public function export(Request $request): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('viewAny', Asset::class);

        return $this->assetRepository->export($request->all());
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $result = $this->assetRepository->import($request->file('file'));

        if (isset($result['success']) && !$result['success']) {
            return redirect()->back()->with('error', $result['message'] ?? __('hrms.assets.error_import_assets'));
        }

        return redirect()->back()->with('success', $result['message'] ?? __('hrms.assets.success_assets_imported', ['count' => '']));
    }

    public function downloadTemplate(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('viewAny', Asset::class);

        return $this->assetRepository->downloadTemplate();
    }

    public function exportCategories(Request $request): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('viewAny', Asset::class);

        return $this->assetRepository->exportCategories($request->all());
    }

    public function importCategories(Request $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $result = $this->assetRepository->importCategories($request->file('file'));

        if (isset($result['success']) && !$result['success']) {
            return redirect()->back()->with('error', $result['message'] ?? __('hrms.assets.error_import_categories'));
        }

        return redirect()->back()->with('success', $result['message'] ?? __('hrms.assets.success_categories_imported', ['count' => '']));
    }

    public function downloadCategoriesTemplate(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('viewAny', Asset::class);

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

        \App\Services\Notification\NotificationService::sendToHrAdmins(
            title: 'New Asset Request',
            message: "{$employee->full_name} submitted an asset request.",
            actionUrl: 'hrms.assets-module.index',
            type: 'asset_request',
            iconClass: 'feather-box'
        );

        return redirect()->back()->with('success', __('hrms.assets.success_req_submitted'));
    }


    public function rejectRequest(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $user = auth()->user();
        $isOwner = $user && $assetRequest->employee && (int)$assetRequest->employee->user_id === (int)$user->id;

        if (!$isOwner) {
            $this->authorize('approve', Asset::class);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $defaultNote = $isOwner ? 'Withdrawn by employee.' : 'Rejected by administrator.';
        $assetRequest->update([
            'status'      => 'rejected',
            'admin_notes' => $validated['admin_notes'] ?? $defaultNote,
        ]);

        if (!$isOwner) {
            \App\Services\Notification\NotificationService::sendToEmployee(
                employee: $assetRequest->employee_id,
                title: 'Asset Request Rejected',
                message: 'Your asset request has been rejected.',
                actionUrl: 'hrms.assets-module.my-assets',
                module: 'hrms',
                type: 'asset_request',
                iconClass: 'feather-x-circle'
            );
        }

        return redirect()->back()->with('success', $isOwner ? __('hrms.assets.success_req_withdrawn') : __('hrms.assets.success_req_rejected'));
    }

    public function allocateDirect(AssetRequest $assetRequest): RedirectResponse
    {
        $this->authorize('approve', Asset::class);

        if (!in_array($assetRequest->status, ['pending', 'partially_allocated'])) {
            return redirect()->back()->with('error', __('hrms.assets.error_req_not_pending'));
        }

        $asset = null;
        if ($assetRequest->requested_asset_id) {
            $asset = Asset::find($assetRequest->requested_asset_id);
            if (!$asset || $asset->status !== 'available') {
                return redirect()->back()->with('error', __('hrms.assets.error_spec_asset_not_avail'));
            }
        } elseif ($assetRequest->asset_item_id) {
            $asset = Asset::query()
                ->where('asset_item_id', $assetRequest->asset_item_id)
                ->where('status', 'available')
                ->first();

            if (!$asset) {
                return redirect()->back()->with('error', __('hrms.assets.error_no_avail_item_unit'));
            }
        } else {
            $asset = Asset::query()
                ->where('asset_category_id', $assetRequest->asset_category_id)
                ->where('company_id', $assetRequest->company_id)
                ->where('status', 'available')
                ->first();

            if (!$asset) {
                return redirect()->back()->with('error', __('hrms.assets.error_no_avail_asset_cat'));
            }
        }

        $hasRequestColumn = \Illuminate\Support\Facades\Schema::hasColumn('assets', 'asset_request_id');

        \Illuminate\Support\Facades\DB::transaction(function () use ($asset, $assetRequest, $hasRequestColumn) {
            $upd = [
                'status'               => 'allocated',
                'assigned_employee_id' => $assetRequest->employee_id,
                'allocated_at'         => date('Y-m-d'),
                'expected_return_date' => null,
            ];
            if ($hasRequestColumn) {
                $upd['asset_request_id'] = $assetRequest->id;
            }
            $asset->update($upd);

            $asset->allocations()->create([
                'employee_id'          => $assetRequest->employee_id,
                'allocated_at'         => date('Y-m-d'),
                'allocation_condition' => $asset->condition,
                'notes'                => $asset->notes ?? 'Direct allocation',
            ]);

            $totalAllocatedUnits = $hasRequestColumn ? $assetRequest->allocatedAssets()->count() : 1;
            $newStatus = ($totalAllocatedUnits >= $assetRequest->quantity) ? 'allocated' : 'partially_allocated';

            $assetRequest->update([
                'status'             => $newStatus,
                'allocated_asset_id' => $asset->id,
                'admin_notes'        => trim(($assetRequest->admin_notes ? $assetRequest->admin_notes . ' | ' : '') . "Allocated asset {$asset->asset_code} ({$asset->name}) directly on " . date('d M, Y')),
            ]);
        });

        \App\Services\Notification\NotificationService::sendToEmployee(
            employee: $assetRequest->employee_id,
            title: 'Asset Allocated',
            message: "Asset {$asset->asset_code} ({$asset->name}) has been allocated to you.",
            actionUrl: 'hrms.assets-module.my-assets',
            module: 'hrms',
            type: 'asset_allocation',
            iconClass: 'feather-box'
        );

        return redirect()->back()->with('success', __('hrms.assets.success_req_allocated_dir'));
    }

    public function allocateRequest(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $this->authorize('approve', Asset::class);

        if ($request->has('allocated_asset_ids') && !$request->has('asset_ids')) {
            $request->merge([
                'asset_ids' => $request->input('allocated_asset_ids'),
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
                return redirect()->back()->with('error', __('hrms.assets.error_asset_not_avail', ['code' => $asset->asset_code, 'name' => $asset->name]));
            }
        }

        $hasRequestColumn = \Illuminate\Support\Facades\Schema::hasColumn('assets', 'asset_request_id');

        \Illuminate\Support\Facades\DB::transaction(function () use ($assets, $assetRequest, $validated, $assetIds, $hasRequestColumn) {
            $assetCodes = [];
            foreach ($assets as $asset) {
                $upd = [
                    'status'               => 'allocated',
                    'assigned_employee_id' => $assetRequest->employee_id,
                    'allocated_at'         => $validated['allocated_at'],
                    'expected_return_date' => $validated['expected_return_date'] ?? null,
                ];
                if ($hasRequestColumn) {
                    $upd['asset_request_id'] = $assetRequest->id;
                }
                $asset->update($upd);

                $asset->allocations()->create([
                    'employee_id'          => $assetRequest->employee_id,
                    'allocated_at'         => $validated['allocated_at'],
                    'allocation_condition' => $asset->condition,
                    'notes'                => $asset->notes,
                ]);

                $assetCodes[] = $asset->asset_code;
            }

            $totalAllocatedUnits = $hasRequestColumn ? $assetRequest->allocatedAssets()->count() : count($assetCodes);
            $newStatus = ($totalAllocatedUnits >= $assetRequest->quantity) ? 'allocated' : 'partially_allocated';

            $assetRequest->update([
                'status'             => $newStatus,
                'allocated_asset_id' => $assetIds[0],
                'admin_notes'        => trim(($assetRequest->admin_notes ? $assetRequest->admin_notes . ' | ' : '') . "Allocated: " . implode(', ', $assetCodes) . " on " . date('d M, Y')),
            ]);
        });

        \App\Services\Notification\NotificationService::sendToEmployee(
            employee: $assetRequest->employee_id,
            title: 'Asset Allocated',
            message: 'An asset has been allocated for your request.',
            actionUrl: 'hrms.assets-module.my-assets',
            module: 'hrms',
            type: 'asset_allocation',
            iconClass: 'feather-box'
        );

        return redirect()->back()->with('success', __('hrms.assets.success_allocated'));
    }

    public function bulkAllocate(Request $request): RedirectResponse
    {
        $this->authorize('approve', Asset::class);

        $validated = $request->validate([
            'allocations'          => 'required|array',
            'allocated_at'         => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:allocated_at',
        ]);

        $allocatedAt        = $validated['allocated_at'];
        $expectedReturnDate = $validated['expected_return_date'] ?? null;
        $allocatedCount     = 0;
        $hasRequestColumn   = \Illuminate\Support\Facades\Schema::hasColumn('assets', 'asset_request_id');

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $allocatedAt, $expectedReturnDate, &$allocatedCount, $hasRequestColumn) {
            foreach ($validated['allocations'] as $requestId => $assetIds) {
                if (empty($assetIds)) {
                    continue;
                }

                $assetRequest = AssetRequest::find($requestId);
                if (!$assetRequest || !in_array($assetRequest->status, ['pending', 'partially_allocated'])) {
                    continue;
                }

                $unitIds = is_array($assetIds) ? $assetIds : [$assetIds];
                $unitIds = array_filter($unitIds);
                if (empty($unitIds)) {
                    continue;
                }

                $assets = Asset::whereIn('id', $unitIds)->where('status', 'available')->get();
                if ($assets->isEmpty()) {
                    continue;
                }

                $allocatedCodes = [];
                foreach ($assets as $asset) {
                    $upd = [
                        'status'               => 'allocated',
                        'assigned_employee_id' => $assetRequest->employee_id,
                        'allocated_at'         => $allocatedAt,
                        'expected_return_date' => $expectedReturnDate,
                    ];
                    if ($hasRequestColumn) {
                        $upd['asset_request_id'] = $assetRequest->id;
                    }
                    $asset->update($upd);

                    $asset->allocations()->create([
                        'employee_id'          => $assetRequest->employee_id,
                        'allocated_at'         => $allocatedAt,
                        'allocation_condition' => $asset->condition,
                        'notes'                => $asset->notes ?? 'Bulk allocated',
                    ]);

                    $allocatedCodes[] = $asset->asset_code;
                }

                $totalAllocatedUnits = $hasRequestColumn ? $assetRequest->allocatedAssets()->count() : count($allocatedCodes);
                $newStatus = ($totalAllocatedUnits >= $assetRequest->quantity) ? 'allocated' : 'partially_allocated';

                $assetRequest->update([
                    'status'             => $newStatus,
                    'allocated_asset_id' => $assets->first()->id,
                    'admin_notes'        => trim(($assetRequest->admin_notes ? $assetRequest->admin_notes . ' | ' : '') . "Allocated: " . implode(', ', $allocatedCodes) . " on " . date('d M, Y')),
                ]);

                $allocatedCount++;

                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $assetRequest->employee_id,
                    title: 'Asset Allocated',
                    message: "Asset unit(s) (" . implode(', ', $allocatedCodes) . ") have been allocated for your request.",
                    actionUrl: 'hrms.assets-module.my-assets',
                    module: 'hrms',
                    type: 'asset_allocation',
                    iconClass: 'feather-box'
                );
            }
        });

        return redirect()->back()->with('success', __('hrms.assets.success_bulk_allocated', ['count' => $allocatedCount]));
    }

    public function bulkReject(Request $request): RedirectResponse
    {
        $this->authorize('approve', Asset::class);

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

        return redirect()->back()->with('success', __('hrms.assets.success_bulk_rejected'));
    }
}

