<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\AssetItem;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\AssetRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    /**
     * Display a listing of assets and categories.
     */
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        // 1. Asset Registry Query
        $assetsQuery = Asset::query()
            ->with(['company', 'category', 'item', 'assignedEmployee']);

        if ($request->filled('registry_search')) {
            $search = $request->input('registry_search');
            $assetsQuery->where(function($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('registry_category_id')) {
            $assetsQuery->where('asset_category_id', $request->input('registry_category_id'));
        }

        if ($request->filled('registry_item_id')) {
            $assetsQuery->where('asset_item_id', $request->input('registry_item_id'));
        }

        if ($request->filled('registry_status')) {
            $assetsQuery->where('status', $request->input('registry_status'));
        }

        if ($request->filled('registry_condition')) {
            $assetsQuery->where('condition', $request->input('registry_condition'));
        }

        $registrySort = $request->input('registry_sort', 'code_asc');
        if ($registrySort === 'code_desc') {
            $assetsQuery->orderBy('asset_code', 'desc');
        } elseif ($registrySort === 'name_asc') {
            $assetsQuery->orderBy('name', 'asc');
        } elseif ($registrySort === 'name_desc') {
            $assetsQuery->orderBy('name', 'desc');
        } elseif ($registrySort === 'newest') {
            $assetsQuery->orderBy('created_at', 'desc');
        } else {
            $assetsQuery->orderBy('asset_code', 'asc');
        }

        $assets = $assetsQuery->paginate(10)
            ->withQueryString();

        // 2. Categories & Items Dropdowns (Unfiltered for modals)
        $categories = AssetCategory::query()->orderBy('name')->get();
        $items = AssetItem::query()->with('category')->orderBy('name')->get();

        // 3. Filtered Categories for Categories Tab list
        $categoriesQuery = AssetCategory::query();

        if ($request->filled('category_search')) {
            $search = $request->input('category_search');
            $categoriesQuery->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_company_id')) {
            $categoriesQuery->where('company_id', $request->input('category_company_id'));
        }

        $categorySort = $request->input('category_sort', 'name_asc');
        if ($categorySort === 'name_desc') {
            $categoriesQuery->orderBy('name', 'desc');
        } elseif ($categorySort === 'newest') {
            $categoriesQuery->orderBy('created_at', 'desc');
        } else {
            $categoriesQuery->orderBy('name', 'asc');
        }

        $filteredCategories = $categoriesQuery->paginate(10)
            ->withQueryString();

        // 3b. Filtered Items for Items Tab list
        $itemsQuery = AssetItem::query()->with(['company', 'category']);

        if ($request->filled('item_search')) {
            $search = $request->input('item_search');
            $itemsQuery->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('item_category_id')) {
            $itemsQuery->where('asset_category_id', $request->input('item_category_id'));
        }

        if ($request->filled('item_company_id')) {
            $itemsQuery->where('company_id', $request->input('item_company_id'));
        }

        $itemSort = $request->input('item_sort', 'name_asc');
        if ($itemSort === 'name_desc') {
            $itemsQuery->orderBy('name', 'desc');
        } elseif ($itemSort === 'newest') {
            $itemsQuery->orderBy('created_at', 'desc');
        } else {
            $itemsQuery->orderBy('name', 'asc');
        }

        $filteredItems = $itemsQuery->paginate(10)
            ->withQueryString();

        // 4. Other collections
        $companies = Company::query()->where('status', true)->orderBy('company_name')->get();
        $employees = Employee::query()->where('status', true)->orderBy('full_name')->get();
        
        // 5. Requests Search & Filter
        $requestsQuery = AssetRequest::query()
            ->with(['company', 'employee', 'category', 'item', 'allocatedAsset', 'requestedAsset']);

        if ($request->filled('request_search')) {
            $search = $request->input('request_search');
            $requestsQuery->where(function($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhereHas('employee', function($eq) use ($search) {
                      $eq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('request_category_id')) {
            $requestsQuery->where('asset_category_id', $request->input('request_category_id'));
        }

        if ($request->filled('request_item_id')) {
            $requestsQuery->where('asset_item_id', $request->input('request_item_id'));
        }

        if ($request->filled('request_company_id')) {
            $requestsQuery->where('company_id', $request->input('request_company_id'));
        }

        if ($request->filled('request_status')) {
            $requestsQuery->where('status', $request->input('request_status'));
        }

        $requestSort = $request->input('request_sort', 'newest');
        if ($requestSort === 'oldest') {
            $requestsQuery->orderBy('created_at', 'asc');
        } elseif ($requestSort === 'status_asc') {
            $requestsQuery->orderBy('status', 'asc');
        } elseif ($requestSort === 'status_desc') {
            $requestsQuery->orderBy('status', 'desc');
        } else {
            $requestsQuery->orderBy('created_at', 'desc');
        }

        $requests = $requestsQuery->paginate(10)
            ->withQueryString();

        // Total Pending Requests Count (unaffected by filters, for the tab badge)
        $pendingRequestsCount = AssetRequest::query()->where('status', 'pending')->count();

        $availableAssets = Asset::query()
            ->where('status', 'available')
            ->orderBy('name')
            ->get();

        return view('modules.hrms.assets.index', compact(
            'assets', 
            'categories', 
            'items',
            'filteredCategories', 
            'filteredItems',
            'companies', 
            'employees', 
            'requests', 
            'pendingRequestsCount', 
            'availableAssets'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $rules = [
            'brand' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'condition' => 'nullable|string|in:new,good,fair,damaged,scrapped',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($request->has('units')) {
            $rules['asset_item_id'] = 'required|exists:asset_items,id';
            $rules['units'] = 'required|array|min:1';
            $rules['units.*.asset_code'] = 'required|string|max:255|unique:assets,asset_code';
            $rules['units.*.serial_number'] = 'nullable|string|max:255';
            $validated = $request->validate($rules);
            
            $item = AssetItem::findOrFail($validated['asset_item_id']);
            $companyId = $item->company_id;
            $categoryId = $item->asset_category_id;
            $name = $item->name;
        } else {
            // Old single asset creation logic for compatibility
            $rules['asset_category_id'] = 'required|exists:asset_categories,id';
            $rules['asset_code'] = 'required|string|max:255|unique:assets,asset_code';
            $rules['name'] = 'required|string|max:255';
            $rules['serial_number'] = 'nullable|string|max:255';
            $validated = $request->validate($rules);
            
            $category = AssetCategory::findOrFail($validated['asset_category_id']);
            $companyId = $category->company_id;
            $categoryId = $category->id;
            $name = $validated['name'];
            
            // Auto-create or find matching AssetItem to keep data structure synchronized
            $item = AssetItem::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'asset_category_id' => $categoryId,
                    'name' => $name
                ],
                [
                    'description' => 'Automatically created from single asset registration.'
                ]
            );
            $validated['asset_item_id'] = $item->id;
        }

        $condition = $validated['condition'] ?? 'good';
        $status = 'available';
        if ($condition === 'damaged') {
            $status = 'maintenance';
        } elseif ($condition === 'scrapped') {
            $status = 'scrapped';
        }

        \DB::transaction(function () use ($validated, $companyId, $categoryId, $name, $status, $condition) {
            if (isset($validated['units'])) {
                foreach ($validated['units'] as $unit) {
                    Asset::create([
                        'company_id' => $companyId,
                        'asset_category_id' => $categoryId,
                        'asset_item_id' => $validated['asset_item_id'],
                        'name' => $name,
                        'brand' => $validated['brand'] ?? null,
                        'model_number' => $validated['model_number'] ?? null,
                        'purchase_date' => $validated['purchase_date'] ?? null,
                        'purchase_cost' => $validated['purchase_cost'] ?? null,
                        'condition' => $condition,
                        'status' => $status,
                        'notes' => $validated['notes'] ?? null,
                        'asset_code' => $unit['asset_code'],
                        'serial_number' => $unit['serial_number'] ?? null,
                    ]);
                }
            } else {
                Asset::create([
                    'company_id' => $companyId,
                    'asset_category_id' => $categoryId,
                    'asset_item_id' => $validated['asset_item_id'],
                    'name' => $name,
                    'brand' => $validated['brand'] ?? null,
                    'model_number' => $validated['model_number'] ?? null,
                    'purchase_date' => $validated['purchase_date'] ?? null,
                    'purchase_cost' => $validated['purchase_cost'] ?? null,
                    'condition' => $condition,
                    'status' => $status,
                    'notes' => $validated['notes'] ?? null,
                    'asset_code' => $validated['asset_code'],
                    'serial_number' => $validated['serial_number'] ?? null,
                ]);
            }
        });
 
        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_logged'));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $rules = [
            'asset_code' => ['required', 'string', 'max:255', Rule::unique('assets', 'asset_code')->ignore($asset->id)],
            'brand' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'condition' => 'required|string|in:new,good,fair,damaged,scrapped',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($request->has('asset_item_id')) {
            $rules['asset_item_id'] = 'required|exists:asset_items,id';
            $validated = $request->validate($rules);
            
            $item = AssetItem::findOrFail($validated['asset_item_id']);
            $validated['company_id'] = $item->company_id;
            $validated['asset_category_id'] = $item->asset_category_id;
            $validated['name'] = $item->name;
        } else {
            $rules['asset_category_id'] = 'required|exists:asset_categories,id';
            $rules['name'] = 'required|string|max:255';
            $validated = $request->validate($rules);
            
            $category = AssetCategory::findOrFail($validated['asset_category_id']);
            $validated['company_id'] = $category->company_id;
        }

        // Determine status based on condition (if not currently allocated)
        if ($asset->status !== 'allocated') {
            $status = 'available';
            if ($validated['condition'] === 'damaged') {
                $status = 'maintenance';
            } elseif ($validated['condition'] === 'scrapped') {
                $status = 'scrapped';
            }
            $validated['status'] = $status;
        }

        $asset->update($validated);
 
        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_updated'));
    }

    /**
     * Remove the specified asset.
     */
    public function destroy(Asset $asset): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);
        $asset->delete();
 
        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_deleted'));
    }

    /**
     * Store a newly created category.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        AssetCategory::create($validated);
 
        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_cat_created'));
    }

    /**
     * Update an existing asset category.
     */
    public function updateCategory(Request $request, AssetCategory $assetCategory): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $assetCategory->update($validated);
 
        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_cat_updated'));
    }

    /**
     * Delete an asset category and its associated assets and allocations.
     */
    public function destroyCategory(AssetCategory $assetCategory): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        // Option 2: Check if there are assets linked to the category
        $assetCount = $assetCategory->assets()->count();
        if ($assetCount > 0) {
            return redirect()->back()->with('error', __('hrms.assets.error_cat_has_assets', ['name' => $assetCategory->name, 'count' => $assetCount]));
        }
 
        // Also check if there are asset requests linked to this category
        $requestCount = AssetRequest::where('asset_category_id', $assetCategory->id)->count();
        if ($requestCount > 0) {
            return redirect()->back()->with('error', __('hrms.assets.error_cat_has_requests', ['name' => $assetCategory->name, 'count' => $requestCount]));
        }
 
        // Delete the category itself since it is empty
        $assetCategory->delete();
 
        return redirect()->route('hrms.assets.index')->with('success', __('hrms.assets.success_cat_deleted'));
    }

    /**
     * Allocate an asset to an employee.
     */
    public function allocate(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'assigned_employee_id' => 'required|exists:employees,id',
            'allocated_at' => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:allocated_at',
            'request_id' => 'nullable|exists:asset_requests,id',
        ]);

        // Capture allocation condition (default to current asset condition)
        $allocCondition = $asset->condition;

        // Update asset status
        $asset->update([
            'status' => 'allocated',
            'assigned_employee_id' => $validated['assigned_employee_id'],
            'allocated_at' => $validated['allocated_at'],
            'expected_return_date' => $validated['expected_return_date'],
        ]);

        // Log transaction history
        $asset->allocations()->create([
            'employee_id' => $validated['assigned_employee_id'],
            'allocated_at' => $validated['allocated_at'],
            'allocation_condition' => $allocCondition,
            'notes' => $asset->notes,
        ]);

        // Link and resolve request if allocated via a request ticket
        if (!empty($validated['request_id'])) {
            $assetRequest = AssetRequest::find($validated['request_id']);
            if ($assetRequest) {
                $assetRequest->update([
                    'status' => 'allocated',
                    'allocated_asset_id' => $asset->id,
                    'admin_notes' => 'Allocated asset ' . $asset->asset_code . ' (' . $asset->name . ') on ' . date('d M, Y'),
                ]);
            }
        } else {
            // Auto-resolve any pending request for this employee and this category!
            $pendingRequest = AssetRequest::query()
                ->where('employee_id', $validated['assigned_employee_id'])
                ->where('asset_category_id', $asset->asset_category_id)
                ->where('status', 'pending')
                ->orderBy('created_at', 'asc') // Resolve oldest request first
                ->first();

            if ($pendingRequest) {
                $pendingRequest->update([
                    'status' => 'allocated',
                    'allocated_asset_id' => $asset->id,
                    'admin_notes' => 'Allocated asset ' . $asset->asset_code . ' (' . $asset->name . ') directly from registry on ' . date('d M, Y'),
                ]);
            }
        }
 
        return redirect()->back()->with('success', __('hrms.assets.success_allocated'));
    }

    /**
     * Return an allocated asset to inventory.
     */
    public function returnAsset(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'returned_at' => 'nullable|date',
            'return_condition' => 'nullable|string|in:new,good,fair,damaged,scrapped',
            'return_notes' => 'nullable|string|max:1000',
        ]);

        $returnedAt = $validated['returned_at'] ?? date('Y-m-d');
        $returnCondition = $validated['return_condition'] ?? $asset->condition;
        $returnNotes = $validated['return_notes'] ?? null;

        // Find and update the active allocation log
        $activeAllocation = $asset->allocations()
            ->whereNull('returned_at')
            ->orderBy('allocated_at', 'desc')
            ->first();

        if ($activeAllocation) {
            $activeAllocation->update([
                'returned_at' => $returnedAt,
                'return_condition' => $returnCondition,
                'notes' => $returnNotes ?: $activeAllocation->notes,
            ]);
        }

        // Determine status from return condition
        $status = 'available';
        if ($returnCondition === 'damaged') {
            $status = 'maintenance';
        } elseif ($returnCondition === 'scrapped') {
            $status = 'scrapped';
        }

        $asset->update([
            'status' => $status,
            'condition' => $returnCondition,
            'assigned_employee_id' => null,
            'allocated_at' => null,
            'expected_return_date' => null,
        ]);
 
        return redirect()->back()->with('success', __('hrms.assets.success_returned'));
    }

    /**
     * Store a newly created asset request.
     */
    public function storeRequest(Request $request): RedirectResponse
    {
        $employee = Employee::findOrFail($request->input('employee_id'));
        $companyId = $employee->company_id;
        $requestDate = date('Y-m-d');
        $reason = $request->input('reason');

        if ($request->has('asset_item_id')) {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'asset_item_id' => 'required|exists:asset_items,id',
                'quantity' => 'required|integer|min:1',
                'reason' => 'required|string|max:1000',
            ]);

            $item = AssetItem::findOrFail($request->input('asset_item_id'));

            AssetRequest::create([
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'asset_category_id' => $item->asset_category_id,
                'asset_item_id' => $item->id,
                'quantity' => $request->input('quantity', 1),
                'reason' => $reason,
                'request_date' => $requestDate,
                'status' => 'pending',
            ]);
        } else {
            // Old request compatibility
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'reason' => 'required|string|max:1000',
            ]);

            $requestedAssetIds = (array) $request->input('requested_asset_ids', []);
            $categoryIds = (array) $request->input('asset_category_ids', $request->input('asset_category_id', []));

            if (count($requestedAssetIds) > 0) {
                foreach ($requestedAssetIds as $assetId) {
                    $asset = Asset::find($assetId);
                    if ($asset) {
                        AssetRequest::create([
                            'company_id' => $companyId,
                            'employee_id' => $employee->id,
                            'asset_category_id' => $asset->asset_category_id,
                            'asset_item_id' => $asset->asset_item_id,
                            'requested_asset_id' => $asset->id,
                            'reason' => $reason,
                            'request_date' => $requestDate,
                            'status' => 'pending',
                        ]);
                    }
                }
            } else {
                foreach ($categoryIds as $categoryId) {
                    $item = AssetItem::where('asset_category_id', $categoryId)->first();
                    AssetRequest::create([
                        'company_id' => $companyId,
                        'employee_id' => $employee->id,
                        'asset_category_id' => $categoryId,
                        'asset_item_id' => $item ? $item->id : null,
                        'reason' => $reason,
                        'request_date' => $requestDate,
                        'status' => 'pending',
                    ]);
                }
            }
        }
 
        return redirect()->back()->with('success', __('hrms.assets.success_req_submitted'));
    }

    /**
     * Reject a pending asset request.
     */
    public function rejectRequest(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

        $assetRequest->update([
            'status' => 'rejected',
            'admin_notes' => $validated['admin_notes'],
        ]);
 
        return redirect()->back()->with('success', __('hrms.assets.success_req_rejected'));
    }

    /**
     * Directly allocate a pending asset request without expected return date.
     */
    public function allocateDirect(AssetRequest $assetRequest): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        if ($assetRequest->status !== 'pending') {
            return redirect()->back()->with('error', __('hrms.assets.error_req_not_pending'));
        }

        $quantity = $assetRequest->quantity ?? 1;

        // Find available assets matching the requested item type & company
        $assets = Asset::query()
            ->where('asset_item_id', $assetRequest->asset_item_id)
            ->where('company_id', $assetRequest->company_id)
            ->where('status', 'available')
            ->limit($quantity)
            ->get();

        if ($assets->count() < $quantity) {
            return redirect()->back()->with('error', 'Not enough available assets of the requested item type.');
        }

        \DB::transaction(function () use ($assetRequest, $assets) {
            $first = true;
            foreach ($assets as $asset) {
                $asset->update([
                    'status' => 'allocated',
                    'assigned_employee_id' => $assetRequest->employee_id,
                    'allocated_at' => date('Y-m-d'),
                    'expected_return_date' => null,
                ]);

                $asset->allocations()->create([
                    'employee_id' => $assetRequest->employee_id,
                    'allocated_at' => date('Y-m-d'),
                    'allocation_condition' => $asset->condition,
                    'notes' => $asset->notes,
                ]);

                if ($first) {
                    $assetRequest->update([
                        'allocated_asset_id' => $asset->id,
                    ]);
                    $first = false;
                }
            }

            $assetCodes = $assets->pluck('asset_code')->implode(', ');
            $assetRequest->update([
                'status' => 'allocated',
                'admin_notes' => "Directly allocated assets: {$assetCodes} on " . date('d M, Y'),
            ]);
        });

        return redirect()->back()->with('success', __('hrms.assets.success_req_allocated_dir'));
    }

    /**
     * Allocate specific asset units to fulfill an asset request.
     */
    public function allocateRequest(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        if ($assetRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'Request is not pending.');
        }

        $validated = $request->validate([
            'allocated_asset_ids' => 'required|array|min:1',
            'allocated_asset_ids.*' => 'exists:assets,id',
            'allocated_at' => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:allocated_at',
        ]);

        $assets = Asset::whereIn('id', $validated['allocated_asset_ids'])->get();

        foreach ($assets as $asset) {
            if ($asset->status !== 'available') {
                return redirect()->back()->with('error', "Asset {$asset->asset_code} is not available.");
            }
            if ($asset->asset_item_id != $assetRequest->asset_item_id) {
                return redirect()->back()->with('error', "Asset {$asset->asset_code} does not match the requested item type.");
            }
        }

        \DB::transaction(function () use ($assetRequest, $assets, $validated) {
            $first = true;
            foreach ($assets as $asset) {
                $asset->update([
                    'status' => 'allocated',
                    'assigned_employee_id' => $assetRequest->employee_id,
                    'allocated_at' => $validated['allocated_at'],
                    'expected_return_date' => $validated['expected_return_date'],
                ]);

                $asset->allocations()->create([
                    'employee_id' => $assetRequest->employee_id,
                    'allocated_at' => $validated['allocated_at'],
                    'allocation_condition' => $asset->condition,
                    'notes' => 'Allocated via request #' . $assetRequest->id,
                ]);

                if ($first) {
                    $assetRequest->update([
                        'allocated_asset_id' => $asset->id,
                    ]);
                    $first = false;
                }
            }

            $assetCodes = $assets->pluck('asset_code')->implode(', ');
            $assetRequest->update([
                'status' => 'allocated',
                'admin_notes' => "Allocated assets: {$assetCodes} on " . date('d M, Y'),
            ]);
        });

        return redirect()->back()->with('success', 'Assets successfully allocated.');
    }

    /**
     * Store a newly created asset item master.
     */
    public function storeItem(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'asset_category_id' => 'required|exists:asset_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $category = AssetCategory::findOrFail($validated['asset_category_id']);
        $validated['company_id'] = $category->company_id;

        AssetItem::create($validated);

        return redirect()->route('hrms.assets.index')->with('success', 'Asset item created successfully.');
    }

    /**
     * Update the specified asset item.
     */
    public function updateItem(Request $request, AssetItem $assetItem): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'asset_category_id' => 'required|exists:asset_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $category = AssetCategory::findOrFail($validated['asset_category_id']);
        $validated['company_id'] = $category->company_id;

        $assetItem->update($validated);

        return redirect()->route('hrms.assets.index')->with('success', 'Asset item updated successfully.');
    }

    /**
     * Remove the specified asset item.
     */
    public function destroyItem(AssetItem $assetItem): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        if ($assetItem->assets()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete item because it has registered assets.');
        }

        $requestCount = AssetRequest::where('asset_item_id', $assetItem->id)->count();
        if ($requestCount > 0) {
            return redirect()->back()->with('error', 'Cannot delete item because it has asset requests.');
        }

        $assetItem->delete();

        return redirect()->route('hrms.assets.index')->with('success', 'Asset item deleted successfully.');
    }

    /**
     * Bulk allocate selected asset requests.
     */
    public function bulkAllocate(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'allocations' => 'required|array',
            'allocations.*' => 'nullable|exists:assets,id',
            'allocated_at' => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:allocated_at',
        ]);

        $allocatedAt = $request->input('allocated_at');
        $expectedReturnDate = $request->input('expected_return_date');
        $allocatedCount = 0;

        foreach ($request->input('allocations') as $requestId => $assetId) {
            if (empty($assetId)) {
                continue;
            }

            $assetRequest = AssetRequest::find($requestId);
            $asset = Asset::find($assetId);

            if ($assetRequest && $asset && $asset->status === 'available') {
                $asset->update([
                    'status' => 'allocated',
                    'assigned_employee_id' => $assetRequest->employee_id,
                    'allocated_at' => $allocatedAt,
                    'expected_return_date' => $expectedReturnDate,
                ]);

                $asset->allocations()->create([
                    'employee_id' => $assetRequest->employee_id,
                    'allocated_at' => $allocatedAt,
                    'allocation_condition' => $asset->condition,
                    'notes' => $asset->notes,
                ]);

                $assetRequest->update([
                    'status' => 'allocated',
                    'allocated_asset_id' => $asset->id,
                    'admin_notes' => 'Allocated asset ' . $asset->asset_code . ' (' . $asset->name . ') on ' . date('d M, Y') . ' via bulk allocation.',
                ]);

                $allocatedCount++;
            }
        }

        if ($allocatedCount > 0) {
            return redirect()->back()->with('success', "Successfully allocated {$allocatedCount} asset(s).");
        }

        return redirect()->back()->with('error', "No assets were allocated. Make sure selected assets are available.");
    }

    /**
     * Bulk reject selected asset requests.
     */
    public function bulkReject(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'request_ids' => 'required|array',
            'request_ids.*' => 'exists:asset_requests,id',
            'admin_notes' => 'required|string|max:1000',
        ]);

        $adminNotes = $request->input('admin_notes');
        $requestIds = $request->input('request_ids');

        AssetRequest::query()
            ->whereIn('id', $requestIds)
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'admin_notes' => $adminNotes,
            ]);

        return redirect()->back()->with('success', 'Selected asset requests rejected successfully.');
    }

    /**
     * Export all assets.
     */
    public function export(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $assetsQuery = Asset::query()
            ->with(['company', 'category', 'assignedEmployee']);

        if ($request->filled('registry_search')) {
            $search = $request->input('registry_search');
            $assetsQuery->where(function($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('registry_category_id')) {
            $assetsQuery->where('asset_category_id', $request->input('registry_category_id'));
        }

        if ($request->filled('registry_status')) {
            $assetsQuery->where('status', $request->input('registry_status'));
        }

        if ($request->filled('registry_condition')) {
            $assetsQuery->where('condition', $request->input('registry_condition'));
        }

        $registrySort = $request->input('registry_sort', 'code_asc');
        if ($registrySort === 'code_desc') {
            $assetsQuery->orderBy('asset_code', 'desc');
        } elseif ($registrySort === 'name_asc') {
            $assetsQuery->orderBy('name', 'asc');
        } elseif ($registrySort === 'name_desc') {
            $assetsQuery->orderBy('name', 'desc');
        } elseif ($registrySort === 'newest') {
            $assetsQuery->orderBy('created_at', 'desc');
        } else {
            $assetsQuery->orderBy('asset_code', 'asc');
        }

        $assets = $assetsQuery->get();

        $headers = [
            'Asset Code',
            'Asset Name',
            'Category',
            'Company',
            'Brand',
            'Model Number',
            'Serial Number',
            'Purchase Date',
            'Purchase Cost',
            'Condition',
            'Status',
            'Current Holder',
            'Notes'
        ];

        $data = [];
        foreach ($assets as $asset) {
            $purchaseDate = '';
            if ($asset->purchase_date) {
                if ($asset->purchase_date instanceof \Carbon\Carbon) {
                    $purchaseDate = $asset->purchase_date->format('Y-m-d');
                } else {
                    $purchaseDate = date('Y-m-d', strtotime($asset->purchase_date));
                }
            }

            $data[] = [
                $asset->asset_code,
                $asset->name,
                $asset->category ? $asset->category->name : 'N/A',
                $asset->company ? $asset->company->company_name : 'N/A',
                $asset->brand ?? '',
                $asset->model_number ?? '',
                $asset->serial_number ?? '',
                $purchaseDate,
                $asset->purchase_cost ?? '',
                ucfirst($asset->condition ?? 'good'),
                ucfirst($asset->status ?? 'available'),
                $asset->assignedEmployee ? $asset->assignedEmployee->full_name : 'In Inventory',
                $asset->notes ?? ''
            ];
        }

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $data, 'assets_registry_export_' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Download Excel template for Asset import.
     */
    public function downloadTemplate()
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $headers = [
            'asset_code',
            'name',
            'category_name',
            'brand',
            'model_number',
            'serial_number',
            'purchase_date',
            'purchase_cost',
            'condition',
            'notes'
        ];

        $sampleCategory = AssetCategory::first();

        $data = [
            [
                'AST-0001',
                'Dell Latitude 5420',
                $sampleCategory ? $sampleCategory->name : 'Laptops',
                'Dell',
                'Latitude 5420',
                'SN123456789',
                '2026-07-15',
                '1200.00',
                'new',
                'Developer work laptop.'
            ]
        ];

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $data, 'assets_import_template.xlsx');
    }

    /**
     * Import Assets from Excel file.
     */
    public function import(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $filePath = $request->file('file')->getRealPath();
            $rows = \App\Domains\HRMS\Helpers\XlsxHelper::import($filePath);

            if (empty($rows)) {
                return redirect()->back()->with('error', __('hrms.assets.error_empty_excel'));
            }

            $headers = array_shift($rows);
            $headers = array_map(function($h) {
                $h = strtolower(trim(preg_replace('/[\x{FEFF}\x{FFFE}]/u', '', $h)));
                $h = str_replace([' ', '-'], '_', $h);
                $h = preg_replace('/[^a-z0-9_]/', '', $h);

                // Normalize common header aliases to make the import robust
                if (str_starts_with($h, 'category') || $h === 'cat') {
                    return 'category_name';
                }
                if (str_starts_with($h, 'model')) {
                    return 'model_number';
                }
                if (str_starts_with($h, 'serial')) {
                    return 'serial_number';
                }
                if ($h === 'purchase_d' || $h === 'purchase_dt' || str_starts_with($h, 'purchase_date')) {
                    return 'purchase_date';
                }
                if ($h === 'purchase_c' || $h === 'purchase_amt' || $h === 'purchase_val' || $h === 'purchase_price' || str_starts_with($h, 'purchase_cost')) {
                    return 'purchase_cost';
                }

                return $h;
            }, $headers);

            $required = ['asset_code', 'name', 'category_name'];
            foreach ($required as $req) {
                if (!in_array($req, $headers)) {
                    return redirect()->back()->with('error', __('hrms.assets.error_missing_column', ['column' => str_replace('_', ' ', $req)]));
                }
            }

            $importedCount = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowData = [];
                foreach ($headers as $colIdx => $headerName) {
                    $rowData[$headerName] = isset($row[$colIdx]) ? trim((string)$row[$colIdx]) : '';
                }

                if (empty($rowData['asset_code']) && empty($rowData['name'])) {
                    continue;
                }

                $rowNum = $index + 2;

                if (empty($rowData['asset_code'])) {
                    $errors[] = "Row {$rowNum}: Asset Code is required.";
                    continue;
                }
                if (empty($rowData['name'])) {
                    $errors[] = "Row {$rowNum}: Asset Name is required.";
                    continue;
                }
                if (empty($rowData['category_name'])) {
                    $errors[] = "Row {$rowNum}: Category Name is required.";
                    continue;
                }

                if (Asset::where('asset_code', $rowData['asset_code'])->exists()) {
                    $errors[] = "Row {$rowNum}: Asset Code '{$rowData['asset_code']}' already exists.";
                    continue;
                }

                $categoryInput = $rowData['category_name'];
                $category = AssetCategory::whereRaw('LOWER(name) = ?', [strtolower($categoryInput)])
                    ->first();

                if (!$category) {
                    $singularName = \Illuminate\Support\Str::singular($categoryInput);
                    $category = AssetCategory::whereRaw('LOWER(name) = ?', [strtolower($singularName)])
                        ->first();
                }

                if (!$category) {
                    $pluralName = \Illuminate\Support\Str::plural($categoryInput);
                    $category = AssetCategory::whereRaw('LOWER(name) = ?', [strtolower($pluralName)])
                        ->first();
                }

                if (!$category) {
                    $fallbackCompany = Company::where('status', true)->first();
                    if (!$fallbackCompany) {
                        $errors[] = "Row {$rowNum}: No active company found to assign the category '{$categoryInput}'.";
                        continue;
                    }
                    $category = AssetCategory::create([
                        'company_id' => $fallbackCompany->id,
                        'name' => $categoryInput,
                        'description' => 'Automatically created via Excel Asset Import',
                    ]);
                }

                $companyId = $category->company_id;

                $purchaseDate = null;
                if (!empty($rowData['purchase_date'])) {
                    $parsed = strtotime($rowData['purchase_date']);
                    if ($parsed) {
                        $purchaseDate = date('Y-m-d', $parsed);
                    }
                }

                $condition = strtolower($rowData['condition'] ?? 'good');
                if (!in_array($condition, ['new', 'good', 'fair', 'damaged', 'scrapped'])) {
                    $condition = 'good';
                }

                $status = 'available';
                if ($condition === 'damaged') {
                    $status = 'maintenance';
                } elseif ($condition === 'scrapped') {
                    $status = 'scrapped';
                }

                Asset::create([
                    'company_id' => $companyId,
                    'asset_category_id' => $category->id,
                    'asset_code' => $rowData['asset_code'],
                    'name' => $rowData['name'],
                    'brand' => $rowData['brand'] ?: null,
                    'model_number' => $rowData['model_number'] ?: null,
                    'serial_number' => $rowData['serial_number'] ?: null,
                    'purchase_date' => $purchaseDate,
                    'purchase_cost' => is_numeric($rowData['purchase_cost']) ? $rowData['purchase_cost'] : null,
                    'condition' => $condition,
                    'status' => $status,
                    'notes' => $rowData['notes'] ?: null,
                ]);

                $importedCount++;
            }

            if (!empty($errors)) {
                return redirect()->back()->with('success', __('hrms.assets.success_imported_with_warnings', ['count' => $importedCount, 'warnings' => implode(', ', $errors)]));
            }
 
            return redirect()->back()->with('success', __('hrms.assets.success_assets_imported', ['count' => $importedCount]));
 
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('hrms.assets.error_import_failed', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Export Asset Categories.
     */
    public function exportCategories(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $categoriesQuery = AssetCategory::query();

        if ($request->filled('category_search')) {
            $search = $request->input('category_search');
            $categoriesQuery->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_company_id')) {
            $categoriesQuery->where('company_id', $request->input('category_company_id'));
        }

        $categorySort = $request->input('category_sort', 'name_asc');
        if ($categorySort === 'name_desc') {
            $categoriesQuery->orderBy('name', 'desc');
        } elseif ($categorySort === 'newest') {
            $categoriesQuery->orderBy('created_at', 'desc');
        } else {
            $categoriesQuery->orderBy('name', 'asc');
        }

        $categories = $categoriesQuery->get();

        $headers = [
            'Category Name',
            'Description',
            'Company Name',
            'Total Assets Linked'
        ];

        $data = [];
        foreach ($categories as $category) {
            $data[] = [
                $category->name,
                $category->description ?? '',
                $category->company ? $category->company->company_name : 'N/A',
                $category->assets()->count()
            ];
        }

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $data, 'asset_categories_export_' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Download template for Category Import.
     */
    public function downloadCategoriesTemplate()
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $headers = [
            'name',
            'description',
            'company_name'
        ];

        $sampleCompany = Company::first();

        $data = [
            [
                'Laptops',
                'Company laptops and notebooks.',
                $sampleCompany ? $sampleCompany->company_name : 'Acme Corporation'
            ]
        ];

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $data, 'asset_categories_import_template.xlsx');
    }

    /**
     * Import Categories from Excel.
     */
    public function importCategories(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $filePath = $request->file('file')->getRealPath();
            $rows = \App\Domains\HRMS\Helpers\XlsxHelper::import($filePath);

            if (empty($rows)) {
                return redirect()->back()->with('error', __('hrms.assets.error_empty_excel'));
            }

            $headers = array_shift($rows);
            $headers = array_map(function($h) {
                $h = strtolower(trim(preg_replace('/[\x{FEFF}\x{FFFE}]/u', '', $h)));
                $h = str_replace([' ', '-'], '_', $h);
                return preg_replace('/[^a-z0-9_]/', '', $h);
            }, $headers);

            $required = ['name', 'company_name'];
            foreach ($required as $req) {
                if (!in_array($req, $headers)) {
                    return redirect()->back()->with('error', __('hrms.assets.error_missing_column', ['column' => str_replace('_', ' ', $req)]));
                }
            }

            $importedCount = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowData = [];
                foreach ($headers as $colIdx => $headerName) {
                    $rowData[$headerName] = isset($row[$colIdx]) ? trim((string)$row[$colIdx]) : '';
                }

                if (empty($rowData['name'])) {
                    continue;
                }

                $rowNum = $index + 2;

                if (empty($rowData['company_name'])) {
                    $errors[] = "Row {$rowNum}: Company Name is required.";
                    continue;
                }

                $company = Company::where('company_name', 'like', $rowData['company_name'])->first();
                if (!$company) {
                    $errors[] = "Row {$rowNum}: Company '{$rowData['company_name']}' not found.";
                    continue;
                }

                if (AssetCategory::where('company_id', $company->id)->where('name', $rowData['name'])->exists()) {
                    $errors[] = "Row {$rowNum}: Category '{$rowData['name']}' already exists for company '{$rowData['company_name']}'.";
                    continue;
                }

                AssetCategory::create([
                    'company_id' => $company->id,
                    'name' => $rowData['name'],
                    'description' => $rowData['description'] ?: null,
                ]);

                $importedCount++;
            }

            if (!empty($errors)) {
                return redirect()->back()->with('success', __('hrms.assets.success_imported_with_warnings', ['count' => $importedCount, 'warnings' => implode(', ', $errors)]));
            }
 
            return redirect()->back()->with('success', __('hrms.assets.success_categories_imported', ['count' => $importedCount]));
 
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('hrms.assets.error_import_failed', ['message' => $e->getMessage()]));
        }
    }
}
