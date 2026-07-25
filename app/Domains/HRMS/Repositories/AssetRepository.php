<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\AssetItem;
use App\Domains\HRMS\Models\AssetRequest;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssetRepository implements AssetRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        // 1. Asset Registry Query
        $assetsQuery = Asset::query()
            ->with(['company', 'category', 'item', 'assignedEmployee']);

        if (!empty($inputs['registry_search'])) {
            $search = $inputs['registry_search'];
            $assetsQuery->where(function($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if (!empty($inputs['registry_category_id'])) {
            $assetsQuery->where('asset_category_id', $inputs['registry_category_id']);
        }

        if (!empty($inputs['registry_item_id'])) {
            $assetsQuery->where('asset_item_id', $inputs['registry_item_id']);
        }

        if (!empty($inputs['registry_status'])) {
            $assetsQuery->where('status', $inputs['registry_status']);
        }

        if (!empty($inputs['registry_condition'])) {
            $assetsQuery->where('condition', $inputs['registry_condition']);
        }

        $registrySort = $inputs['registry_sort'] ?? 'code_asc';
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

        $assets = $assetsQuery->paginate(10, ['*'], 'registry_page')->withQueryString();

        // 2. Categories & Items Dropdowns (Unfiltered for modals)
        $categories = AssetCategory::query()->orderBy('name')->get();
        $items = AssetItem::query()->with('category')->orderBy('name')->get();

        // 3. Filtered Categories for Categories Tab list
        $categoriesQuery = AssetCategory::query();

        if (!empty($inputs['category_search'])) {
            $search = $inputs['category_search'];
            $categoriesQuery->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!empty($inputs['category_company_id'])) {
            $categoriesQuery->where('company_id', $inputs['category_company_id']);
        }

        $categorySort = $inputs['category_sort'] ?? 'name_asc';
        if ($categorySort === 'name_desc') {
            $categoriesQuery->orderBy('name', 'desc');
        } elseif ($categorySort === 'newest') {
            $categoriesQuery->orderBy('created_at', 'desc');
        } else {
            $categoriesQuery->orderBy('name', 'asc');
        }

        $filteredCategories = $categoriesQuery->paginate(10, ['*'], 'category_page')->withQueryString();

        // 3b. Filtered Items for Items Tab list
        $itemsQuery = AssetItem::query()->with(['company', 'category']);

        if (!empty($inputs['item_search'])) {
            $search = $inputs['item_search'];
            $itemsQuery->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!empty($inputs['item_category_id'])) {
            $itemsQuery->where('asset_category_id', $inputs['item_category_id']);
        }

        if (!empty($inputs['item_company_id'])) {
            $itemsQuery->where('company_id', $inputs['item_company_id']);
        }

        $itemSort = $inputs['item_sort'] ?? 'name_asc';
        if ($itemSort === 'name_desc') {
            $itemsQuery->orderBy('name', 'desc');
        } elseif ($itemSort === 'newest') {
            $itemsQuery->orderBy('created_at', 'desc');
        } else {
            $itemsQuery->orderBy('name', 'asc');
        }

        $filteredItems = $itemsQuery->paginate(10, ['*'], 'item_page')->withQueryString();

        // 4. Other collections
        $companies = Company::query()->where('status', true)->orderBy('company_name')->get();
        $employees = Employee::query()->where('status', true)->orderBy('full_name')->get();
        
        $hasRequestColumn = Schema::hasColumn('assets', 'asset_request_id');

        // 5. Requests Search & Filter
        $requestsQuery = AssetRequest::query()
            ->with(['company', 'employee', 'category', 'item', 'allocatedAsset', 'requestedAsset', 'allocatedAssets']);

        if ($hasRequestColumn) {
            $requestsQuery->withCount('allocatedAssets');
        }

        if (!empty($inputs['request_search'])) {
            $search = $inputs['request_search'];
            $requestsQuery->where(function($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhereHas('employee', function($eq) use ($search) {
                      $eq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($inputs['request_category_id'])) {
            $requestsQuery->where('asset_category_id', $inputs['request_category_id']);
        }

        if (!empty($inputs['request_item_id'])) {
            $requestsQuery->where('asset_item_id', $inputs['request_item_id']);
        }

        if (!empty($inputs['request_company_id'])) {
            $requestsQuery->where('company_id', $inputs['request_company_id']);
        }

        if (!empty($inputs['request_status'])) {
            $requestsQuery->where('status', $inputs['request_status']);
        }

        $requestSort = $inputs['request_sort'] ?? 'newest';
        if ($requestSort === 'oldest') {
            $requestsQuery->orderBy('created_at', 'asc');
        } elseif ($requestSort === 'status_asc') {
            $requestsQuery->orderBy('status', 'asc');
        } elseif ($requestSort === 'status_desc') {
            $requestsQuery->orderBy('status', 'desc');
        } else {
            $requestsQuery->orderBy('created_at', 'desc');
        }

        $requests = $requestsQuery->paginate(10, ['*'], 'request_page')->withQueryString();

        $pendingRequestsCount = AssetRequest::query()->whereIn('status', ['pending', 'partially_allocated'])->count();

        $availableAssets = Asset::query()
            ->where('status', 'available')
            ->orderBy('name')
            ->get();

        return compact(
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
        );
    }

    public function storeAsset(array $validated): Asset
    {
        return Asset::create($validated);
    }

    public function createAssetItemWithUnits(array $validated): AssetItem
    {
        $category = AssetCategory::findOrFail($validated['asset_category_id']);
        $companyId = $category->company_id;
        $categoryId = $category->id;
        $name = $validated['name'];

        $item = AssetItem::create([
            'company_id' => $companyId,
            'asset_category_id' => $categoryId,
            'name' => $name,
            'description' => $validated['description'] ?? null,
        ]);

        DB::transaction(function () use ($validated, $companyId, $categoryId, $name, $item) {
            foreach ($validated['units'] as $unit) {
                $condition = $unit['condition'] ?? 'good';
                $status = 'available';
                if ($condition === 'damaged') {
                    $status = 'maintenance';
                } elseif ($condition === 'scrapped') {
                    $status = 'scrapped';
                }

                Asset::create([
                    'company_id' => $companyId,
                    'asset_category_id' => $categoryId,
                    'asset_item_id' => $item->id,
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
        });

        return $item;
    }

    public function updateAsset(Asset $asset, array $validated): bool
    {
        if (isset($validated['asset_item_id'])) {
            $item = AssetItem::findOrFail($validated['asset_item_id']);
            $validated['company_id'] = $item->company_id;
            $validated['asset_category_id'] = $item->asset_category_id;
            $validated['name'] = $item->name;
        } elseif (isset($validated['asset_category_id'])) {
            $category = AssetCategory::findOrFail($validated['asset_category_id']);
            $validated['company_id'] = $category->company_id;
        }

        if ($asset->status !== 'allocated' && isset($validated['condition'])) {
            $status = 'available';
            if ($validated['condition'] === 'damaged') {
                $status = 'maintenance';
            } elseif ($validated['condition'] === 'scrapped') {
                $status = 'scrapped';
            }
            $validated['status'] = $status;
        }

        return $asset->update($validated);
    }

    public function updateAssetItem(AssetItem $assetItem, array $validated): bool
    {
        return $assetItem->update($validated);
    }

    public function deleteAsset(Asset $asset): bool
    {
        return $asset->delete();
    }

    public function storeCategory(array $validated): AssetCategory
    {
        return AssetCategory::create($validated);
    }

    public function updateCategory(AssetCategory $category, array $validated): bool
    {
        return $category->update($validated);
    }

    public function deleteCategory(AssetCategory $category): bool
    {
        return $category->delete();
    }

    public function allocateAsset(Asset $asset, array $validated): bool
    {
        $hasRequestColumn = Schema::hasColumn('assets', 'asset_request_id');
        $updateData = [
            'status' => 'allocated',
            'assigned_employee_id' => $validated['assigned_employee_id'],
            'allocated_at' => $validated['allocated_at'],
            'expected_return_date' => $validated['expected_return_date'] ?? null,
        ];
        if ($hasRequestColumn && !empty($validated['request_id'])) {
            $updateData['asset_request_id'] = $validated['request_id'];
        }

        $asset->update($updateData);

        // Record history log if exists
        try {
            DB::table('asset_assignment_histories')->insert([
                'tenant_id' => $asset->tenant_id ?? require_tenant_id(),
                'asset_id' => $asset->id,
                'employee_id' => $validated['assigned_employee_id'],
                'allocated_at' => $validated['allocated_at'],
                'expected_return_date' => $validated['expected_return_date'] ?? null,
                'condition_on_allocation' => $asset->condition,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Ignore if table missing
        }

        return true;
    }

    public function returnAsset(Asset $asset, array $validated): bool
    {
        $newStatus = 'available';
        if ($validated['condition_on_return'] === 'damaged') {
            $newStatus = 'maintenance';
        } elseif ($validated['condition_on_return'] === 'scrapped') {
            $newStatus = 'scrapped';
        }

        $updateData = [
            'status' => $newStatus,
            'condition' => $validated['condition_on_return'],
            'assigned_employee_id' => null,
            'allocated_at' => null,
            'expected_return_date' => null,
        ];
        if (Schema::hasColumn('assets', 'asset_request_id')) {
            $updateData['asset_request_id'] = null;
        }

        return $asset->update($updateData);
    }

    public function allocateItem(AssetItem $assetItem, array $validated): bool
    {
        $quantity = (int) $validated['quantity'];
        $availableAssets = Asset::where('asset_item_id', $assetItem->id)
            ->where('status', 'available')
            ->limit($quantity)
            ->get();

        if ($availableAssets->count() < $quantity) {
            return false;
        }

        $hasRequestColumn = Schema::hasColumn('assets', 'asset_request_id');

        DB::transaction(function() use ($availableAssets, $validated, $hasRequestColumn) {
            foreach ($availableAssets as $asset) {
                $upd = [
                    'status' => 'allocated',
                    'assigned_employee_id' => $validated['assigned_employee_id'],
                    'allocated_at' => $validated['allocated_at'],
                    'expected_return_date' => $validated['expected_return_date'] ?? null,
                ];
                if ($hasRequestColumn && !empty($validated['request_id'])) {
                    $upd['asset_request_id'] = $validated['request_id'];
                }
                $asset->update($upd);
            }
        });

        return true;
    }

    public function returnItem(AssetItem $assetItem, array $validated): bool
    {
        $quantity = (int) $validated['quantity'];
        $allocatedAssets = Asset::where('asset_item_id', $assetItem->id)
            ->where('status', 'allocated')
            ->where('assigned_employee_id', $validated['employee_id'])
            ->limit($quantity)
            ->get();

        if ($allocatedAssets->count() < $quantity) {
            return false;
        }

        $newStatus = 'available';
        if ($validated['condition_on_return'] === 'damaged') {
            $newStatus = 'maintenance';
        } elseif ($validated['condition_on_return'] === 'scrapped') {
            $newStatus = 'scrapped';
        }

        $hasRequestColumn = Schema::hasColumn('assets', 'asset_request_id');

        DB::transaction(function() use ($allocatedAssets, $validated, $newStatus, $hasRequestColumn) {
            foreach ($allocatedAssets as $asset) {
                $upd = [
                    'status' => $newStatus,
                    'condition' => $validated['condition_on_return'],
                    'assigned_employee_id' => null,
                    'allocated_at' => null,
                    'expected_return_date' => null,
                ];
                if ($hasRequestColumn) {
                    $upd['asset_request_id'] = null;
                }
                $asset->update($upd);
            }
        });

        return true;
    }

    public function storeRequest(array $validated): AssetRequest
    {
        return AssetRequest::create($validated);
    }

    public function updateRequest(AssetRequest $assetRequest, array $validated): bool
    {
        return $assetRequest->update($validated);
    }

    public function deleteRequest(AssetRequest $assetRequest): bool
    {
        return $assetRequest->delete();
    }
}
