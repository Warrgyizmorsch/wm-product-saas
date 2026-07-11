<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
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

        $assets = $assetsQuery->orderBy('asset_code')
            ->paginate(12)
            ->withQueryString();

        // 2. Categories Dropdown (Unfiltered for modals)
        $categories = AssetCategory::query()->orderBy('name')->get();

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

        $filteredCategories = $categoriesQuery->orderBy('name')->get();

        // 4. Other collections
        $companies = Company::query()->where('status', true)->orderBy('company_name')->get();
        $employees = Employee::query()->where('status', true)->orderBy('full_name')->get();
        
        // 5. Requests Search & Filter
        $requestsQuery = AssetRequest::query()
            ->with(['company', 'employee', 'category', 'allocatedAsset']);

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

        if ($request->filled('request_company_id')) {
            $requestsQuery->where('company_id', $request->input('request_company_id'));
        }

        if ($request->filled('request_status')) {
            $requestsQuery->where('status', $request->input('request_status'));
        }

        $requests = $requestsQuery->orderBy('created_at', 'desc')->get();

        // Total Pending Requests Count (unaffected by filters, for the tab badge)
        $pendingRequestsCount = AssetRequest::query()->where('status', 'pending')->count();

        $availableAssets = Asset::query()
            ->where('status', 'available')
            ->orderBy('name')
            ->get();

        return view('modules.hrms.assets.index', compact(
            'assets', 
            'categories', 
            'filteredCategories', 
            'companies', 
            'employees', 
            'requests', 
            'pendingRequestsCount', 
            'availableAssets'
        ));
    }

    /**
     * Store a newly created asset.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'asset_category_id' => 'required|exists:asset_categories,id',
            'asset_code' => 'required|string|max:255|unique:assets,asset_code',
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'condition' => 'nullable|string|in:new,good,fair,damaged,scrapped',
            'notes' => 'nullable|string|max:1000',
        ]);

        $category = AssetCategory::findOrFail($validated['asset_category_id']);
        $validated['company_id'] = $category->company_id;
        $validated['condition'] = $validated['condition'] ?? 'good';

        // Determine status based on condition
        $status = 'available';
        if ($validated['condition'] === 'damaged') {
            $status = 'maintenance';
        } elseif ($validated['condition'] === 'scrapped') {
            $status = 'scrapped';
        }
        $validated['status'] = $status;
        
        Asset::create($validated);

        return redirect()->route('hrms.assets.index')->with('success', 'Asset logged in the registry.');
    }

    /**
     * Update the specified asset.
     */
    public function update(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'asset_category_id' => 'required|exists:asset_categories,id',
            'asset_code' => ['required', 'string', 'max:255', Rule::unique('assets', 'asset_code')->ignore($asset->id)],
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'condition' => 'required|string|in:new,good,fair,damaged,scrapped',
            'notes' => 'nullable|string|max:1000',
        ]);

        $category = AssetCategory::findOrFail($validated['asset_category_id']);
        $validated['company_id'] = $category->company_id;

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

        return redirect()->route('hrms.assets.index')->with('success', 'Asset details updated.');
    }

    /**
     * Remove the specified asset.
     */
    public function destroy(Asset $asset): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);
        $asset->delete();

        return redirect()->route('hrms.assets.index')->with('success', 'Asset record removed.');
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

        return redirect()->route('hrms.assets.index')->with('success', 'Asset category created.');
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

        return redirect()->back()->with('success', 'Asset successfully allocated.');
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

        return redirect()->back()->with('success', 'Asset returned to inventory.');
    }

    /**
     * Store a newly created asset request.
     */
    public function storeRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'reason' => 'required|string|max:1000',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $validated['company_id'] = $employee->company_id;
        $validated['request_date'] = date('Y-m-d');
        $validated['status'] = 'pending';

        AssetRequest::create($validated);

        return redirect()->back()->with('success', 'Asset request submitted successfully.');
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

        return redirect()->back()->with('success', 'Asset request rejected.');
    }
}
