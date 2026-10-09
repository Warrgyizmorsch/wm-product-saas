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

        app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyEmployeeScope($requestsQuery, auth()->user(), 'employee_id');

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
        return DB::transaction(function () use ($assetItem, $validated) {
            $category = AssetCategory::findOrFail($validated['asset_category_id']);
            $companyId = $category->company_id;

            // 1. Update AssetItem parent
            $assetItem->update([
                'company_id'        => $companyId,
                'asset_category_id' => $validated['asset_category_id'],
                'name'              => $validated['name'],
                'description'       => $validated['description'] ?? null,
            ]);

            // 2. Update common metadata across all existing child Asset units
            $commonMetadata = [
                'company_id'        => $companyId,
                'asset_category_id' => $validated['asset_category_id'],
                'name'              => $validated['name'],
            ];
            if (array_key_exists('brand', $validated)) {
                $commonMetadata['brand'] = $validated['brand'];
            }
            if (array_key_exists('model_number', $validated)) {
                $commonMetadata['model_number'] = $validated['model_number'];
            }
            if (array_key_exists('purchase_date', $validated)) {
                $commonMetadata['purchase_date'] = $validated['purchase_date'];
            }
            if (array_key_exists('purchase_cost', $validated)) {
                $commonMetadata['purchase_cost'] = $validated['purchase_cost'];
            }
            if (array_key_exists('notes', $validated)) {
                $commonMetadata['notes'] = $validated['notes'];
            }

            $assetItem->assets()->update($commonMetadata);

            // 3. Process individual units if passed
            if (!empty($validated['units']) && is_array($validated['units'])) {
                $keptUnitIds = [];

                foreach ($validated['units'] as $unit) {
                    if (empty($unit['asset_code'])) {
                        continue;
                    }

                    $condition = $unit['condition'] ?? 'good';
                    $unitId = !empty($unit['id']) ? (int) $unit['id'] : null;

                    if ($unitId) {
                        $existingAsset = Asset::where('id', $unitId)
                            ->where('asset_item_id', $assetItem->id)
                            ->first();

                        if ($existingAsset) {
                            $unitUpdate = [
                                'asset_code'    => $unit['asset_code'],
                                'serial_number' => $unit['serial_number'] ?? null,
                                'condition'     => $condition,
                            ];

                            if ($existingAsset->status !== 'allocated') {
                                $unitStatus = 'available';
                                if ($condition === 'damaged') {
                                    $unitStatus = 'maintenance';
                                } elseif ($condition === 'scrapped') {
                                    $unitStatus = 'scrapped';
                                }
                                $unitUpdate['status'] = $unitStatus;
                            }

                            $existingAsset->update($unitUpdate);
                            $keptUnitIds[] = $existingAsset->id;
                        }
                    } else {
                        // Create new unit
                        $unitStatus = 'available';
                        if ($condition === 'damaged') {
                            $unitStatus = 'maintenance';
                        } elseif ($condition === 'scrapped') {
                            $unitStatus = 'scrapped';
                        }

                        $newAsset = Asset::create(array_merge($commonMetadata, [
                            'asset_item_id' => $assetItem->id,
                            'asset_code'    => $unit['asset_code'],
                            'serial_number' => $unit['serial_number'] ?? null,
                            'condition'     => $condition,
                            'status'        => $unitStatus,
                        ]));
                        $keptUnitIds[] = $newAsset->id;
                    }
                }

                // Clean up removed units that are NOT allocated
                if (!empty($keptUnitIds)) {
                    Asset::where('asset_item_id', $assetItem->id)
                        ->whereNotIn('id', $keptUnitIds)
                        ->where('status', '!=', 'allocated')
                        ->delete();
                }
            }

            return true;
        });
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
            'status'               => 'allocated',
            'assigned_employee_id' => $validated['assigned_employee_id'],
            'allocated_at'         => $validated['allocated_at'],
            'expected_return_date' => $validated['expected_return_date'] ?? null,
        ];
        if ($hasRequestColumn && !empty($validated['request_id'])) {
            $updateData['asset_request_id'] = $validated['request_id'];
        }

        $asset->update($updateData);

        // Record history log if exists
        try {
            DB::table('asset_assignment_histories')->insert([
                'tenant_id'               => $asset->tenant_id ?? require_tenant_id(),
                'asset_id'                => $asset->id,
                'employee_id'             => $validated['assigned_employee_id'],
                'allocated_at'            => $validated['allocated_at'],
                'expected_return_date'    => $validated['expected_return_date'] ?? null,
                'condition_on_allocation' => $asset->condition,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        } catch (\Throwable $e) {
            // Ignore if table missing
        }

        // Create AssetAllocation record
        \App\Domains\HRMS\Models\AssetAllocation::create([
            'asset_id'             => $asset->id,
            'employee_id'          => $validated['assigned_employee_id'],
            'allocated_at'         => $validated['allocated_at'],
            'allocation_condition' => $asset->condition,
            'notes'                => $validated['notes'] ?? null,
        ]);

        return true;
    }

    public function returnAsset(Asset $asset, array $validated): bool
    {
        $returnCondition = $validated['condition_on_return'] ?? ($validated['return_condition'] ?? $asset->condition);
        $newStatus = 'available';
        if ($returnCondition === 'damaged') {
            $newStatus = 'maintenance';
        } elseif ($returnCondition === 'scrapped') {
            $newStatus = 'scrapped';
        }

        $updateData = [
            'status'               => $newStatus,
            'condition'            => $returnCondition,
            'assigned_employee_id' => null,
            'allocated_at'         => null,
            'expected_return_date' => null,
        ];
        if (Schema::hasColumn('assets', 'asset_request_id')) {
            $updateData['asset_request_id'] = null;
        }

        // Update active AssetAllocation record
        $allocation = \App\Domains\HRMS\Models\AssetAllocation::where('asset_id', $asset->id)
            ->whereNull('returned_at')
            ->first();
        if ($allocation) {
            $allocation->update([
                'returned_at'      => $validated['returned_at'] ?? now(),
                'return_condition' => $returnCondition,
                'notes'            => $validated['return_notes'] ?? ($validated['notes'] ?? $allocation->notes),
            ]);
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
                    'status'               => 'allocated',
                    'assigned_employee_id' => $validated['assigned_employee_id'],
                    'allocated_at'         => $validated['allocated_at'],
                    'expected_return_date' => $validated['expected_return_date'] ?? null,
                ];
                if ($hasRequestColumn && !empty($validated['request_id'])) {
                    $upd['asset_request_id'] = $validated['request_id'];
                }
                $asset->update($upd);

                // Create AssetAllocation record
                \App\Domains\HRMS\Models\AssetAllocation::create([
                    'asset_id'             => $asset->id,
                    'employee_id'          => $validated['assigned_employee_id'],
                    'allocated_at'         => $validated['allocated_at'],
                    'allocation_condition' => $asset->condition,
                    'notes'                => $validated['notes'] ?? null,
                ]);
            }
        });

        return true;
    }

    public function returnItem(AssetItem $assetItem, array $validated): bool
    {
        $quantity = (int) $validated['quantity'];
        
        $query = Asset::where('asset_item_id', $assetItem->id)
            ->where('status', 'allocated')
            ->where('assigned_employee_id', $validated['employee_id']);
            
        if (!empty($validated['allocated_asset_ids'])) {
            $query->whereIn('id', $validated['allocated_asset_ids']);
        }
        
        $allocatedAssets = $query->limit($quantity)->get();

        if ($allocatedAssets->count() < $quantity) {
            return false;
        }

        $returnCondition = $validated['condition_on_return'] ?? ($validated['return_condition'] ?? 'good');
        $newStatus = 'available';
        if ($returnCondition === 'damaged') {
            $newStatus = 'maintenance';
        } elseif ($returnCondition === 'scrapped') {
            $newStatus = 'scrapped';
        }

        $hasRequestColumn = Schema::hasColumn('assets', 'asset_request_id');

        DB::transaction(function() use ($allocatedAssets, $validated, $newStatus, $returnCondition, $hasRequestColumn) {
            foreach ($allocatedAssets as $asset) {
                $upd = [
                    'status'               => $newStatus,
                    'condition'            => $returnCondition,
                    'assigned_employee_id' => null,
                    'allocated_at'         => null,
                    'expected_return_date' => null,
                ];
                if ($hasRequestColumn) {
                    $upd['asset_request_id'] = null;
                }
                $asset->update($upd);

                // Update active AssetAllocation record
                $allocation = \App\Domains\HRMS\Models\AssetAllocation::where('asset_id', $asset->id)
                    ->whereNull('returned_at')
                    ->first();
                if ($allocation) {
                    $allocation->update([
                        'returned_at'      => $validated['returned_at'] ?? now(),
                        'return_condition' => $returnCondition,
                        'notes'            => $validated['return_notes'] ?? ($validated['notes'] ?? $allocation->notes),
                    ]);
                }
            }
        });

        return true;
    }

    public function storeAssetItem(array $validated): AssetItem
    {
        $category = AssetCategory::findOrFail($validated['asset_category_id']);
        return AssetItem::create([
            'company_id'        => $category->company_id,
            'asset_category_id' => $category->id,
            'name'              => $validated['name'],
            'description'       => $validated['description'] ?? null,
        ]);
    }

    public function export(array $filters = []): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $query = Asset::with(['category', 'assignedEmployee', 'company']);

        $search = $filters['item_search'] ?? ($filters['registry_search'] ?? ($filters['search'] ?? null));
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('model_number', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        $catId = $filters['item_category_id'] ?? ($filters['registry_category_id'] ?? ($filters['category_id'] ?? ($filters['asset_category_id'] ?? null)));
        if (!empty($catId)) {
            $query->where('asset_category_id', $catId);
        }

        $compId = $filters['item_company_id'] ?? ($filters['category_company_id'] ?? ($filters['company_id'] ?? null));
        if (!empty($compId)) {
            $query->where('company_id', $compId);
        }

        $status = $filters['registry_status'] ?? ($filters['status'] ?? null);
        if (!empty($status)) {
            $query->where('status', $status);
        }

        $cond = $filters['registry_condition'] ?? ($filters['condition'] ?? null);
        if (!empty($cond)) {
            $query->where('condition', $cond);
        }

        $assets = $query->orderBy('name')->orderBy('asset_code')->get();
        $headers = [
            'Asset Code',
            'Item Name',
            'Category',
            'Company',
            'Brand',
            'Model Number',
            'Serial Number',
            'Condition',
            'Status',
            'Purchase Date',
            'Purchase Cost',
            'Assigned To'
        ];

        $rows = [];
        foreach ($assets as $asset) {
            $rows[] = [
                $asset->asset_code ?? '',
                $asset->name ?? '',
                $asset->category?->name ?? 'Uncategorized',
                $asset->company?->company_name ?? '',
                $asset->brand ?? '',
                $asset->model_number ?? '',
                $asset->serial_number ?? '',
                $asset->condition ?? 'good',
                $asset->status ?? 'available',
                $asset->purchase_date ? $asset->purchase_date->format('Y-m-d') : '',
                $asset->purchase_cost ?? '',
                $asset->assignedEmployee?->full_name ?? ($asset->assignedEmployee?->display_name ?? ''),
            ];
        }

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $rows, 'assets_catalog_export_' . date('Ymd_His') . '.xlsx');
    }

    public function import(\Illuminate\Http\UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();
        $rows = [];

        if (in_array($ext, ['xlsx', 'xls'])) {
            try {
                $rows = \App\Domains\HRMS\Helpers\XlsxHelper::import($path);
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'Failed to parse Excel file: ' . $e->getMessage()];
            }
        } else {
            if (($handle = fopen($path, 'r')) !== false) {
                while (($data = fgetcsv($handle)) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
        }

        if (empty($rows)) {
            return ['success' => false, 'message' => 'The uploaded file is empty.'];
        }

        $header = array_shift($rows);
        $headerNormalized = array_map(function($col) {
            $clean = strtolower(trim((string)$col));
            return str_replace(['*', '#', '_'], '', $clean);
        }, $header);

        $nameIdx = array_search('item name', $headerNormalized);
        if ($nameIdx === false) $nameIdx = array_search('name', $headerNormalized);
        if ($nameIdx === false) $nameIdx = array_search('item', $headerNormalized);

        $codeIdx = array_search('asset code', $headerNormalized);
        if ($codeIdx === false) $codeIdx = array_search('code', $headerNormalized);

        $categoryIdx = array_search('category', $headerNormalized);
        if ($categoryIdx === false) $categoryIdx = array_search('category name', $headerNormalized);

        $companyIdx = array_search('company', $headerNormalized);
        if ($companyIdx === false) $companyIdx = array_search('company name', $headerNormalized);

        $brandIdx = array_search('brand', $headerNormalized);
        $modelIdx = array_search('model number', $headerNormalized);
        if ($modelIdx === false) $modelIdx = array_search('model', $headerNormalized);

        $serialIdx = array_search('serial number', $headerNormalized);
        if ($serialIdx === false) $serialIdx = array_search('serial', $headerNormalized);

        $condIdx = array_search('condition', $headerNormalized);
        $statusIdx = array_search('status', $headerNormalized);
        $purchaseDateIdx = array_search('purchase date', $headerNormalized);
        $purchaseCostIdx = array_search('purchase cost', $headerNormalized);
        $notesIdx = array_search('notes', $headerNormalized);
        if ($notesIdx === false) $notesIdx = array_search('description', $headerNormalized);

        if ($nameIdx === false || $codeIdx === false) {
            return [
                'success' => false, 
                'message' => 'Invalid template format. The required columns "Asset Code *" and "Item Name *" were not found in the spreadsheet header.'
            ];
        }

        $rowErrors = [];
        $validData = [];
        $seenCodes = [];
        $fileRowNum = 1; // Header is row 1

        foreach ($rows as $row) {
            $fileRowNum++;

            // Skip completely empty rows
            $isRowEmpty = true;
            foreach ($row as $cell) {
                if (trim((string)$cell) !== '') {
                    $isRowEmpty = false;
                    break;
                }
            }
            if ($isRowEmpty) {
                continue;
            }

            $code = trim((string)($row[$codeIdx] ?? ''));
            $name = trim((string)($row[$nameIdx] ?? ''));
            $categoryName = ($categoryIdx !== false && !empty($row[$categoryIdx])) ? trim((string)$row[$categoryIdx]) : '';

            $hasError = false;
            if ($code === '') {
                $rowErrors[] = "Row {$fileRowNum}: 'Asset Code' is required and cannot be blank.";
                $hasError = true;
            } elseif (isset($seenCodes[strtolower($code)])) {
                $rowErrors[] = "Row {$fileRowNum}: Duplicate Asset Code '{$code}' in the file (already defined on row {$seenCodes[strtolower($code)]}).";
                $hasError = true;
            } else {
                $seenCodes[strtolower($code)] = $fileRowNum;
            }

            if ($name === '') {
                $rowErrors[] = "Row {$fileRowNum}: 'Item Name' is required and cannot be blank.";
                $hasError = true;
            }

            if ($categoryName === '') {
                $categoryName = 'General Assets';
            }

            if (!$hasError) {
                $validData[] = [
                    'rowNum'       => $fileRowNum,
                    'code'         => $code,
                    'name'         => $name,
                    'category'     => $categoryName,
                    'company'      => ($companyIdx !== false && !empty($row[$companyIdx])) ? trim((string)$row[$companyIdx]) : null,
                    'brand'        => ($brandIdx !== false && !empty($row[$brandIdx])) ? trim((string)$row[$brandIdx]) : null,
                    'model'        => ($modelIdx !== false && !empty($row[$modelIdx])) ? trim((string)$row[$modelIdx]) : null,
                    'serial'       => ($serialIdx !== false && !empty($row[$serialIdx])) ? trim((string)$row[$serialIdx]) : null,
                    'condition'    => ($condIdx !== false && !empty($row[$condIdx])) ? strtolower(trim((string)$row[$condIdx])) : 'good',
                    'status'       => ($statusIdx !== false && !empty($row[$statusIdx])) ? strtolower(trim((string)$row[$statusIdx])) : 'available',
                    'purchaseDate' => ($purchaseDateIdx !== false && !empty($row[$purchaseDateIdx])) ? trim((string)$row[$purchaseDateIdx]) : null,
                    'purchaseCost' => ($purchaseCostIdx !== false && !empty($row[$purchaseCostIdx])) ? trim((string)$row[$purchaseCostIdx]) : null,
                    'notes'        => ($notesIdx !== false && !empty($row[$notesIdx])) ? trim((string)$row[$notesIdx]) : null,
                ];
            }
        }

        if (empty($validData) && empty($rowErrors)) {
            return ['success' => false, 'message' => 'The uploaded file does not contain any asset data rows.'];
        }

        if (!empty($rowErrors)) {
            $errorSummary = implode('; ', array_slice($rowErrors, 0, 5));
            if (count($rowErrors) > 5) {
                $errorSummary .= ' ...and ' . (count($rowErrors) - 5) . ' more issue(s).';
            }
            return [
                'success' => false, 
                'message' => 'Import validation failed: ' . $errorSummary . ' Please correct your Excel file and upload again.'
            ];
        }

        $count = 0;
        $categoriesCreated = 0;
        $itemsCreated = 0;
        $tenantId = tenant_id() ?? 1;
        $defaultCompany = Company::where('status', true)->first();
        $defaultCompanyId = $defaultCompany?->id;

        try {
            DB::transaction(function() use (
                $validData, $tenantId, $defaultCompanyId, &$count, &$categoriesCreated, &$itemsCreated
            ) {
                foreach ($validData as $item) {
                    // 1. Resolve Company
                    $companyId = $defaultCompanyId;
                    if (!empty($item['company'])) {
                        $compVal = $item['company'];
                        $foundComp = is_numeric($compVal) 
                            ? Company::find($compVal) 
                            : Company::where('company_name', $compVal)->first();
                        if ($foundComp) {
                            $companyId = $foundComp->id;
                        }
                    }

                    // 2. Resolve or Auto-Create Category
                    $catVal = $item['category'];
                    $cat = is_numeric($catVal) 
                        ? AssetCategory::find($catVal) 
                        : AssetCategory::where('name', $catVal)->first();
                    
                    if (!$cat) {
                        $cat = AssetCategory::create([
                            'tenant_id'   => $tenantId,
                            'company_id'  => $companyId,
                            'name'        => $catVal,
                            'description' => 'Imported automatically with items catalog.',
                        ]);
                        $categoriesCreated++;
                    }
                    $categoryId = $cat->id;

                    // 3. Resolve or Auto-Create AssetItem Master Catalog Entry
                    $assetItem = AssetItem::where('name', $item['name'])
                        ->where('asset_category_id', $categoryId)
                        ->first();
                    
                    if (!$assetItem) {
                        $assetItem = AssetItem::create([
                            'tenant_id'         => $tenantId,
                            'company_id'        => $companyId,
                            'asset_category_id' => $categoryId,
                            'name'              => $item['name'],
                            'description'       => $item['notes'],
                        ]);
                        $itemsCreated++;
                    }

                    // 4. Normalize condition and status
                    $cond = $item['condition'];
                    if (!in_array($cond, ['new', 'good', 'fair', 'damaged', 'scrapped'])) {
                        $cond = 'good';
                    }

                    $status = $item['status'];
                    if (!in_array($status, ['available', 'allocated', 'maintenance', 'scrapped'])) {
                        $status = ($cond === 'damaged') ? 'maintenance' : (($cond === 'scrapped') ? 'scrapped' : 'available');
                    }

                    // 5. Parse purchase date
                    $purchaseDate = null;
                    if (!empty($item['purchaseDate'])) {
                        $parsed = strtotime($item['purchaseDate']);
                        if ($parsed !== false) {
                            $purchaseDate = date('Y-m-d', $parsed);
                        }
                    }

                    // 6. Parse purchase cost
                    $purchaseCost = null;
                    if (!empty($item['purchaseCost'])) {
                        $cleanCost = preg_replace('/[^0-9.]/', '', $item['purchaseCost']);
                        if (is_numeric($cleanCost)) {
                            $purchaseCost = (float)$cleanCost;
                        }
                    }

                    // 7. Create or update the Asset physical unit
                    Asset::updateOrCreate(
                        ['asset_code' => $item['code'], 'tenant_id' => $tenantId],
                        [
                            'company_id'        => $companyId,
                            'asset_category_id' => $categoryId,
                            'asset_item_id'     => $assetItem->id,
                            'name'              => $item['name'],
                            'brand'             => $item['brand'],
                            'model_number'      => $item['model'],
                            'serial_number'     => $item['serial'],
                            'purchase_date'     => $purchaseDate,
                            'purchase_cost'     => $purchaseCost,
                            'condition'         => $cond,
                            'status'            => $status,
                            'notes'             => $item['notes'],
                        ]
                    );
                    $count++;
                }
            });
        } catch (\Throwable $e) {
            return [
                'success' => false, 
                'message' => 'Database error during import: ' . $e->getMessage()
            ];
        }

        $extraDetails = [];
        if ($categoriesCreated > 0) {
            $extraDetails[] = "{$categoriesCreated} new category/categories created";
        }
        if ($itemsCreated > 0) {
            $extraDetails[] = "{$itemsCreated} new item catalog master(s) created";
        }

        $detailsStr = !empty($extraDetails) ? ' (' . implode(', ', $extraDetails) . ')' : '';
        return [
            'success' => true, 
            'message' => "Successfully imported {$count} asset unit(s){$detailsStr}."
        ];
    }

    public function downloadTemplate(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $headers = [
            'Asset Code *',
            'Item Name *',
            'Category *',
            'Company',
            'Brand',
            'Model Number',
            'Serial Number',
            'Condition',
            'Status',
            'Purchase Date',
            'Purchase Cost',
            'Notes'
        ];

        $sample = [
            [
                'AST-001',
                'Apple MacBook Pro M3 16"',
                'IT Hardware - Laptops & Desktops',
                '',
                'Apple',
                'A2991',
                'C02G80XYZ001',
                'new',
                'available',
                '2026-01-15',
                '2499.00',
                'Apple M3 Pro 36GB Unified RAM, 1TB SSD'
            ],
            [
                'AST-002',
                'Dell UltraSharp 32" 4K Monitor',
                'IT Peripherals & Displays',
                '',
                'Dell',
                'U3223QE',
                'SN992817288',
                'good',
                'available',
                '2026-02-10',
                '750.00',
                'IPS Black with 90W USB-C Power Delivery'
            ],
            [
                'AST-003',
                'Ergonomic High-Back Executive Chair',
                'Office Furniture & Fixtures',
                '',
                'Herman Miller',
                'Aeron B',
                'SN551239011',
                'good',
                'available',
                '2026-03-01',
                '1100.00',
                'Graphite frame with PostureFit SL'
            ]
        ];

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $sample, 'asset_items_and_categories_template.xlsx');
    }

    public function exportCategories(array $filters = []): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $query = AssetCategory::with('company');

        $search = $filters['category_search'] ?? ($filters['search'] ?? null);
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $companyId = $filters['category_company_id'] ?? ($filters['company_id'] ?? null);
        if (!empty($companyId)) {
            $query->where('company_id', $companyId);
        }

        $categories = $query->orderBy('name')->get();
        $headers = ['ID', 'Category Name', 'Code', 'Company', 'Description'];

        $rows = [];
        foreach ($categories as $cat) {
            $rows[] = [
                $cat->id,
                $cat->name ?? '',
                $cat->code ?? '',
                $cat->company?->company_name ?? '',
                $cat->description ?? '',
            ];
        }

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $rows, 'asset_categories_export_' . date('Ymd_His') . '.xlsx');
    }

    public function importCategories(\Illuminate\Http\UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();
        $rows = [];

        if (in_array($ext, ['xlsx', 'xls'])) {
            try {
                $rows = \App\Domains\HRMS\Helpers\XlsxHelper::import($path);
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'Failed to parse Excel file: ' . $e->getMessage()];
            }
        } else {
            if (($handle = fopen($path, 'r')) !== false) {
                while (($data = fgetcsv($handle)) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
        }

        if (empty($rows)) {
            return ['success' => false, 'message' => 'The uploaded file is empty.'];
        }

        $header = array_shift($rows);
        $headerNormalized = array_map(function($col) {
            $clean = strtolower(trim((string)$col));
            return str_replace(['*', '#', '_'], '', $clean);
        }, $header);

        $nameIdx = array_search('category name', $headerNormalized);
        if ($nameIdx === false) $nameIdx = array_search('name', $headerNormalized);
        $codeIdx = array_search('code', $headerNormalized);
        $descIdx = array_search('description', $headerNormalized);

        if ($nameIdx === false) {
            return ['success' => false, 'message' => 'Invalid template format. The required column "Category Name *" was not found.'];
        }

        $rowErrors = [];
        $validData = [];
        $seenNames = [];
        $fileRowNum = 1;

        foreach ($rows as $row) {
            $fileRowNum++;

            $isRowEmpty = true;
            foreach ($row as $cell) {
                if (trim((string)$cell) !== '') {
                    $isRowEmpty = false;
                    break;
                }
            }
            if ($isRowEmpty) continue;

            $name = trim((string)($row[$nameIdx] ?? ''));
            if ($name === '') {
                $rowErrors[] = "Row {$fileRowNum}: 'Category Name' is required.";
                continue;
            }

            if (isset($seenNames[strtolower($name)])) {
                $rowErrors[] = "Row {$fileRowNum}: Duplicate Category Name '{$name}' in file.";
                continue;
            }
            $seenNames[strtolower($name)] = true;

            $validData[] = [
                'name' => $name,
                'code' => ($codeIdx !== false && !empty($row[$codeIdx])) ? trim((string)$row[$codeIdx]) : null,
                'desc' => ($descIdx !== false && !empty($row[$descIdx])) ? trim((string)$row[$descIdx]) : null,
            ];
        }

        if (empty($validData) && empty($rowErrors)) {
            return ['success' => false, 'message' => 'The uploaded file contains no data rows.'];
        }

        if (!empty($rowErrors)) {
            $errorSummary = implode('; ', array_slice($rowErrors, 0, 5));
            if (count($rowErrors) > 5) {
                $errorSummary .= ' ...and ' . (count($rowErrors) - 5) . ' more issue(s).';
            }
            return ['success' => false, 'message' => 'Validation errors found: ' . $errorSummary];
        }

        $count = 0;
        $tenantId = tenant_id() ?? 1;

        try {
            DB::transaction(function() use ($validData, $tenantId, &$count) {
                foreach ($validData as $d) {
                    AssetCategory::updateOrCreate(
                        ['name' => $d['name'], 'tenant_id' => $tenantId],
                        [
                            'code'        => $d['code'] ?: strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $d['name']), 0, 10)),
                            'description' => $d['desc'],
                        ]
                    );
                    $count++;
                }
            });
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Database error during category import: ' . $e->getMessage()];
        }

        return ['success' => true, 'message' => "Successfully imported {$count} asset category/categories."];
    }

    public function downloadCategoriesTemplate(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $headers = ['Category Name *', 'Code', 'Description'];
        $sample = [
            ['IT Hardware - Laptops & Desktops', 'IT_HW', 'Laptops, desktops, workstations, and computing equipment'],
            ['IT Peripherals & Displays', 'IT_DISP', 'Monitors, docking stations, keyboards, and mice'],
            ['Office Furniture & Fixtures', 'FURN', 'Chairs, ergonomic desks, and conference tables'],
        ];

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $sample, 'asset_categories_template.xlsx');
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
