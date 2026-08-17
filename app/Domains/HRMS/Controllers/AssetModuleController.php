<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetAllocation;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\AssetRequest;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssetModuleController extends Controller
{
    /**
     * Display the asset requests and custody history dashboard.
     */
    public function index(Request $request): View
    {
        // Self-healing: Ensure all currently allocated assets have an active AssetAllocation record
        $allocatedAssetsWithoutActiveAlloc = Asset::where('status', 'allocated')
            ->whereNotNull('assigned_employee_id')
            ->whereDoesntHave('allocations', function ($query) {
                $query->whereNull('returned_at');
            })
            ->get();

        foreach ($allocatedAssetsWithoutActiveAlloc as $asset) {
            AssetAllocation::create([
                'asset_id'             => $asset->id,
                'employee_id'          => $asset->assigned_employee_id,
                'allocated_at'         => $asset->allocated_at ?? now(),
                'allocation_condition' => $asset->condition ?? 'good',
                'notes'                => 'Auto-generated active allocation record (self-healing)',
            ]);
        }

        $companies = Company::orderBy('company_name')->get();
        $employees = Employee::where('status', true)
            ->orderBy('full_name')
            ->get();

        $categories = AssetCategory::orderBy('name')->get();

        // 1. Asset Requests Query (with search, sort, and filters)
        $requestsQuery = AssetRequest::with(['employee', 'category', 'item', 'allocatedAsset']);

        if ($requestSearch = $request->input('request_search')) {
            $requestsQuery->where(function($q) use ($requestSearch) {
                $q->where('reason', 'like', "%{$requestSearch}%")
                  ->orWhereHas('employee', function($eq) use ($requestSearch) {
                      $eq->where('full_name', 'like', "%{$requestSearch}%")
                        ->orWhere('employee_id', 'like', "%{$requestSearch}%");
                  });
            });
        }

        if ($requestCategoryId = $request->input('request_category_id')) {
            $requestsQuery->where('asset_category_id', $requestCategoryId);
        }

        if ($requestCompanyId = $request->input('request_company_id')) {
            $requestsQuery->where('company_id', $requestCompanyId);
        }

        if ($requestStatus = $request->input('request_status')) {
            $requestsQuery->where('status', $requestStatus);
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

        $requests = $requestsQuery->paginate(10, ['*'], 'request_page')->withQueryString();
        $pendingRequestsCount = AssetRequest::whereIn('status', ['pending', 'partially_allocated'])->count();

        // 2. Active Custodians Query (with search and filters)
        $employeesWithAllocationsQuery = Employee::whereHas('allocations', function($q) {
            $q->whereNull('returned_at');
        })->with(['company', 'allocations' => function($q) {
            $q->whereNull('returned_at')->with(['asset.category', 'asset.item']);
        }]);

        if ($historySearch = $request->input('history_search')) {
            $employeesWithAllocationsQuery->where(function ($q) use ($historySearch) {
                $q->where('full_name', 'like', "%{$historySearch}%")
                  ->orWhere('employee_id', 'like', "%{$historySearch}%")
                  ->orWhereHas('allocations', function($aq) use ($historySearch) {
                      $aq->whereNull('returned_at')
                         ->whereHas('asset', function($ast) use ($historySearch) {
                             $ast->where('name', 'like', "%{$historySearch}%")
                                 ->orWhere('asset_code', 'like', "%{$historySearch}%")
                                 ->orWhere('serial_number', 'like', "%{$historySearch}%");
                         });
                  });
            });
        }

        if ($historyCategoryId = $request->input('history_category_id')) {
            $employeesWithAllocationsQuery->whereHas('allocations', function ($aq) use ($historyCategoryId) {
                $aq->whereNull('returned_at')
                   ->whereHas('asset', function ($ast) use ($historyCategoryId) {
                       $ast->where('asset_category_id', $historyCategoryId);
                   });
            });
        }

        if ($historyCompanyId = $request->input('history_company_id')) {
            $employeesWithAllocationsQuery->where('company_id', $historyCompanyId);
        }

        $allocations = $employeesWithAllocationsQuery->paginate(10, ['*'], 'history_page')->withQueryString();

        // Available physical assets for direct allocation
        $availableAssets = Asset::where('status', 'available')
            ->orderBy('name')
            ->get();

        return view('modules.hrms.assets-module.index', compact(
            'companies',
            'employees',
            'categories',
            'requests',
            'pendingRequestsCount',
            'allocations',
            'availableAssets'
        ));
    }

    /**
     * Allocate an available asset directly to an employee.
     */
    public function allocateDirect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id'          => 'required|exists:employees,id',
            'allocated_at'         => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:allocated_at',
            'notes'                => 'nullable|string|max:1000',
            'items'                => 'required|array|min:1',
            'items.*.asset_id'     => 'required|exists:assets,id',
            'items.*.quantity'     => 'required|integer|min:1',
        ]);

        $assetIds = collect($validated['items'])->pluck('asset_id')->all();

        if (count($assetIds) !== count(array_unique($assetIds))) {
            return redirect()->back()->withInput()->with('error', 'Duplicate assets selected for allocation.');
        }

        $assets = Asset::whereIn('id', $assetIds)->get();
        foreach ($assets as $asset) {
            if ($asset->status !== 'available') {
                return redirect()->back()->withInput()->with('error', "Asset '{$asset->name} ({$asset->asset_code})' is not currently available.");
            }
        }

        DB::transaction(function () use ($assets, $validated) {
            foreach ($assets as $asset) {
                $asset->update([
                    'status'               => 'allocated',
                    'assigned_employee_id' => $validated['employee_id'],
                    'allocated_at'         => $validated['allocated_at'],
                    'expected_return_date' => $validated['expected_return_date'] ?? null,
                ]);

                AssetAllocation::create([
                    'asset_id'             => $asset->id,
                    'employee_id'          => $validated['employee_id'],
                    'allocated_at'         => $validated['allocated_at'],
                    'allocation_condition' => $asset->condition,
                    'notes'                => $validated['notes'] ?? 'Allocated directly by Admin',
                ]);
            }
        });

        return redirect()->route('hrms.assets-module.index')->with('success', 'Asset(s) allocated directly successfully.');
    }

    /**
     * Return an allocated asset to inventory.
     */
    public function returnDirect(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'condition_on_return' => 'required|string|in:new,good,fair,damaged,scrapped',
            'notes'               => 'nullable|string|max:1000',
        ]);

        if ($asset->status !== 'allocated') {
            return redirect()->back()->with('error', 'Only allocated assets can be returned.');
        }

        $allocation = AssetAllocation::where('asset_id', $asset->id)
            ->whereNull('returned_at')
            ->first();

        DB::transaction(function () use ($asset, $allocation, $validated) {
            $newStatus = 'available';
            if ($validated['condition_on_return'] === 'damaged') {
                $newStatus = 'maintenance';
            } elseif ($validated['condition_on_return'] === 'scrapped') {
                $newStatus = 'scrapped';
            }

            if ($allocation) {
                $allocation->update([
                    'returned_at'      => now(),
                    'return_condition' => $validated['condition_on_return'],
                    'notes'            => $validated['notes'] ?? $allocation->notes,
                ]);
            }

            $asset->update([
                'status'               => $newStatus,
                'condition'            => $validated['condition_on_return'],
                'assigned_employee_id' => null,
                'allocated_at'         => null,
                'expected_return_date' => null,
            ]);
        });

        return redirect()->back()->with('success', 'Asset returned to inventory successfully.');
    }

    /**
     * Return multiple selected allocated assets to inventory.
     */
    public function returnDirectMulti(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'allocated_asset_ids' => 'required|array',
            'allocated_asset_ids.*' => 'exists:assets,id',
            'condition_on_return' => 'required|string|in:new,good,fair,damaged,scrapped',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $assetIds = $request->input('allocated_asset_ids');

        DB::transaction(function () use ($assetIds, $validated) {
            foreach ($assetIds as $assetId) {
                $asset = Asset::findOrFail($assetId);
                $allocation = AssetAllocation::where('asset_id', $assetId)
                    ->whereNull('returned_at')
                    ->first();

                $newStatus = 'available';
                if ($validated['condition_on_return'] === 'damaged') {
                    $newStatus = 'maintenance';
                } elseif ($validated['condition_on_return'] === 'scrapped') {
                    $newStatus = 'scrapped';
                }

                if ($allocation) {
                    $allocation->update([
                        'returned_at'      => now(),
                        'return_condition' => $validated['condition_on_return'],
                        'notes'            => $validated['notes'] ?? $allocation->notes,
                    ]);
                }

                $asset->update([
                    'status'               => $newStatus,
                    'condition'            => $validated['condition_on_return'],
                    'assigned_employee_id' => null,
                    'allocated_at'         => null,
                    'expected_return_date' => null,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Selected asset(s) returned to inventory successfully.');
    }

    /**
     * Display the current logged-in employee's active assets and requests history.
     */
    public function myAssets(Request $request): View
    {
        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            abort(403, 'You do not have an active employee profile associated with your user account.');
        }

        $categories = AssetCategory::orderBy('name')->get();

        // Group assigned assets by name (each Asset row = one unit)
        $assignedAssetsRaw = $employee->assets()->with('category')->get();
        $assignedAssetsGrouped = $assignedAssetsRaw->groupBy('name');
        
        $assignedAssets = $assignedAssetsGrouped->map(function($group) {
            $first = $group->first();
            $latestDate = $group->max('allocated_at');
            
            $mappedUnits = $group->map(function($unit) {
                return [
                    'id'            => $unit->id,
                    'asset_code'    => $unit->asset_code,
                    'serial_number' => $unit->serial_number,
                    'allocated_at'  => $unit->allocated_at ? (\Carbon\Carbon::parse($unit->allocated_at)->format('Y-m-d')) : null,
                    'condition'     => $unit->condition,
                    'notes'         => $unit->notes,
                ];
            })->values()->all();

            return [
                'asset'               => $first,
                'units'               => $mappedUnits,
                'latest_assigned_date'=> $latestDate ? \Carbon\Carbon::parse($latestDate) : null,
            ];
        })->values();

        $assignedAssetCategories = $assignedAssetsRaw->pluck('category.name')->filter()->unique()->sort()->values();
        $requestAssetCategories = $employee->assetRequests->pluck('category.name')->filter()->unique()->sort()->values();
        $requestAssetStatuses = $employee->assetRequests->pluck('status')->filter()->unique()->sort()->values();

        $availableAssetItems = \App\Domains\HRMS\Models\AssetItem::whereHas('category', function($q) use ($employee) {
            $q->where('company_id', $employee->company_id);
        })->get();

        return view('modules.hrms.assets-module.my-assets', compact(
            'employee',
            'categories',
            'assignedAssets',
            'assignedAssetCategories',
            'requestAssetCategories',
            'requestAssetStatuses',
            'availableAssetItems'
        ));
    }
}
