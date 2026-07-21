<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\AssetRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AssetApiController extends Controller
{
    /**
     * Helper for standardized success JSON response.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Helper for standardized error JSON response.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Null-safe authorization check supporting Web Sessions & HTTP Basic Auth.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();

            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth username or password.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access. Please log in or provide HTTP Basic Auth credentials.', 401);
            }
        }

        if (!auth()->user()->hasHrPermission('hr.settings.manage')) {
            return $this->sendError('Unauthorized access. Your user role does not have hr.settings.manage permission.', 403);
        }

        return null;
    }

    /**
     * GET /api/hrms/assets/summary
     * Get summary metrics & reference collections.
     */
    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess([
            'total_assets'           => Asset::count(),
            'available_assets_count' => Asset::where('status', 'available')->count(),
            'allocated_assets_count' => Asset::where('status', 'allocated')->count(),
            'pending_requests_count' => AssetRequest::where('status', 'pending')->count(),
            'categories'             => AssetCategory::orderBy('name')->get(),
            'companies'              => Company::where('status', true)->orderBy('company_name')->get(),
            'available_assets'       => Asset::where('status', 'available')->orderBy('name')->get(),
        ], 'Asset management summary loaded successfully');
    }

    // ==========================================
    // 1. ASSET REGISTRY APIs
    // ==========================================

    public function indexAssets(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $query = Asset::query()->with(['company', 'category', 'assignedEmployee']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }
        if ($request->filled('asset_category_id')) {
            $query->where('asset_category_id', $request->input('asset_category_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->input('condition'));
        }

        $sort = $request->input('sort', 'code_asc');
        switch ($sort) {
            case 'code_desc': $query->orderBy('asset_code', 'desc'); break;
            case 'name_asc':  $query->orderBy('name', 'asc'); break;
            case 'name_desc': $query->orderBy('name', 'desc'); break;
            case 'newest':    $query->orderBy('created_at', 'desc'); break;
            case 'code_asc':
            default: $query->orderBy('asset_code', 'asc'); break;
        }

        $assets = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($assets, 'Asset registry retrieved successfully');
    }

    public function showAsset(Asset $asset): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($asset->load(['company', 'category', 'assignedEmployee', 'allocations.employee']), 'Asset details loaded');
    }

    public function storeAsset(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'asset_category_id' => 'required|exists:asset_categories,id',
            'asset_code'        => 'required|string|max:255|unique:assets,asset_code',
            'name'              => 'required|string|max:255',
            'brand'             => 'nullable|string|max:255',
            'model_number'      => 'nullable|string|max:255',
            'serial_number'     => 'nullable|string|max:255',
            'purchase_date'     => 'nullable|date',
            'purchase_cost'     => 'nullable|numeric|min:0',
            'condition'         => 'nullable|string|in:new,good,fair,damaged,scrapped',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $category = AssetCategory::findOrFail($validated['asset_category_id']);
        $validated['company_id'] = $category->company_id;
        $validated['condition']  = $validated['condition'] ?? 'good';

        $status = 'available';
        if ($validated['condition'] === 'damaged') {
            $status = 'maintenance';
        } elseif ($validated['condition'] === 'scrapped') {
            $status = 'scrapped';
        }
        $validated['status'] = $status;

        $asset = Asset::create($validated);

        return $this->sendSuccess($asset, 'Asset created successfully', 201);
    }

    public function updateAsset(Request $request, Asset $asset): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'asset_category_id' => 'required|exists:asset_categories,id',
            'asset_code'        => ['required', 'string', 'max:255', Rule::unique('assets', 'asset_code')->ignore($asset->id)],
            'name'              => 'required|string|max:255',
            'brand'             => 'nullable|string|max:255',
            'model_number'      => 'nullable|string|max:255',
            'serial_number'     => 'nullable|string|max:255',
            'purchase_date'     => 'nullable|date',
            'purchase_cost'     => 'nullable|numeric|min:0',
            'condition'         => 'required|string|in:new,good,fair,damaged,scrapped',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $category = AssetCategory::findOrFail($validated['asset_category_id']);
        $validated['company_id'] = $category->company_id;

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

        return $this->sendSuccess($asset, 'Asset updated successfully');
    }

    public function destroyAsset(Asset $asset): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $asset->delete();

        return $this->sendSuccess(null, 'Asset deleted successfully');
    }

    public function allocateAsset(Request $request, Asset $asset): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'assigned_employee_id' => 'required|exists:employees,id',
            'allocated_at'         => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:allocated_at',
            'request_id'           => 'nullable|exists:asset_requests,id',
        ]);

        $allocCondition = $asset->condition;

        $asset->update([
            'status'               => 'allocated',
            'assigned_employee_id' => $validated['assigned_employee_id'],
            'allocated_at'         => $validated['allocated_at'],
            'expected_return_date' => $validated['expected_return_date'] ?? null,
        ]);

        $asset->allocations()->create([
            'employee_id'          => $validated['assigned_employee_id'],
            'allocated_at'         => $validated['allocated_at'],
            'allocation_condition' => $allocCondition,
            'notes'                => $asset->notes,
        ]);

        if (!empty($validated['request_id'])) {
            $assetRequest = AssetRequest::find($validated['request_id']);
            if ($assetRequest) {
                $assetRequest->update([
                    'status'             => 'allocated',
                    'allocated_asset_id' => $asset->id,
                    'admin_notes'        => 'Allocated asset ' . $asset->asset_code . ' (' . $asset->name . ') on ' . date('d M, Y'),
                ]);
            }
        }

        return $this->sendSuccess($asset->load('assignedEmployee'), 'Asset allocated successfully');
    }

    public function returnAsset(Request $request, Asset $asset): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'returned_at'      => 'nullable|date',
            'return_condition' => 'nullable|string|in:new,good,fair,damaged,scrapped',
            'return_notes'     => 'nullable|string|max:1000',
        ]);

        $returnedAt      = $validated['returned_at'] ?? date('Y-m-d');
        $returnCondition = $validated['return_condition'] ?? $asset->condition;
        $returnNotes     = $validated['return_notes'] ?? null;

        $activeAllocation = $asset->allocations()
            ->whereNull('returned_at')
            ->orderBy('allocated_at', 'desc')
            ->first();

        if ($activeAllocation) {
            $activeAllocation->update([
                'returned_at'      => $returnedAt,
                'return_condition' => $returnCondition,
                'notes'            => $returnNotes ?: $activeAllocation->notes,
            ]);
        }

        $status = 'available';
        if ($returnCondition === 'damaged') {
            $status = 'maintenance';
        } elseif ($returnCondition === 'scrapped') {
            $status = 'scrapped';
        }

        $asset->update([
            'status'               => $status,
            'condition'            => $returnCondition,
            'assigned_employee_id' => null,
            'allocated_at'         => null,
            'expected_return_date' => null,
        ]);

        return $this->sendSuccess($asset, 'Asset returned to inventory successfully');
    }

    // ==========================================
    // 2. ASSET CATEGORIES APIs
    // ==========================================

    public function indexCategories(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $query = AssetCategory::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        $sort = $request->input('sort', 'name_asc');
        switch ($sort) {
            case 'name_desc': $query->orderBy('name', 'desc'); break;
            case 'newest':    $query->orderBy('created_at', 'desc'); break;
            case 'name_asc':
            default: $query->orderBy('name', 'asc'); break;
        }

        $categories = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($categories, 'Asset categories retrieved successfully');
    }

    public function showCategory(AssetCategory $category): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($category->load(['company', 'assets']), 'Asset category details loaded');
    }

    public function storeCategory(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_id'  => 'required|exists:companies,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $category = AssetCategory::create($validated);

        return $this->sendSuccess($category, 'Asset category created successfully', 201);
    }

    public function updateCategory(Request $request, AssetCategory $category): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_id'  => 'required|exists:companies,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $category->update($validated);

        return $this->sendSuccess($category, 'Asset category updated successfully');
    }

    public function destroyCategory(AssetCategory $category): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $assetCount = $category->assets()->count();
        if ($assetCount > 0) {
            return $this->sendError("Cannot delete category {$category->name} because it contains {$assetCount} linked asset(s).", 422);
        }

        $requestCount = AssetRequest::where('asset_category_id', $category->id)->count();
        if ($requestCount > 0) {
            return $this->sendError("Cannot delete category {$category->name} because it has {$requestCount} asset request(s).", 422);
        }

        $category->delete();

        return $this->sendSuccess(null, 'Asset category deleted successfully');
    }

    // ==========================================
    // 3. ASSET REQUESTS APIs
    // ==========================================

    public function indexRequests(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $query = AssetRequest::query()->with(['company', 'employee', 'category', 'allocatedAsset', 'requestedAsset']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }
        if ($request->filled('asset_category_id')) {
            $query->where('asset_category_id', $request->input('asset_category_id'));
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'oldest':      $query->orderBy('created_at', 'asc'); break;
            case 'status_asc':  $query->orderBy('status', 'asc'); break;
            case 'status_desc': $query->orderBy('status', 'desc'); break;
            case 'newest':
            default: $query->orderBy('created_at', 'desc'); break;
        }

        $requests = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($requests, 'Asset requests retrieved successfully');
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'           => 'required|exists:employees,id',
            'reason'                => 'required|string|max:1000',
            'requested_asset_ids'   => 'nullable|array',
            'requested_asset_ids.*' => 'exists:assets,id',
            'asset_category_ids'    => 'nullable|array',
            'asset_category_id'     => 'nullable|exists:asset_categories,id',
        ]);

        $categoryIds = [];
        if ($request->has('asset_category_ids')) {
            $categoryIds = (array) $request->input('asset_category_ids');
        } elseif ($request->has('asset_category_id')) {
            $categoryIds = [$request->input('asset_category_id')];
        }

        if (empty($categoryIds) && empty($validated['requested_asset_ids'])) {
            return $this->sendError('Either an asset category or specific asset must be selected.', 422);
        }

        $employee = Employee::findOrFail($validated['employee_id']);
        $companyId = $employee->company_id;
        $requestDate = date('Y-m-d');
        $reason = $validated['reason'];
        $requestedAssetIds = $validated['requested_asset_ids'] ?? [];

        $createdRequests = [];

        if (count($requestedAssetIds) > 0) {
            foreach ($requestedAssetIds as $assetId) {
                $asset = Asset::find($assetId);
                if ($asset) {
                    $createdRequests[] = AssetRequest::create([
                        'company_id'         => $companyId,
                        'employee_id'        => $employee->id,
                        'asset_category_id'  => $asset->asset_category_id,
                        'requested_asset_id' => $asset->id,
                        'reason'             => $reason,
                        'request_date'       => $requestDate,
                        'status'             => 'pending',
                    ]);
                }
            }
        } else {
            foreach ($categoryIds as $categoryId) {
                $createdRequests[] = AssetRequest::create([
                    'company_id'        => $companyId,
                    'employee_id'       => $employee->id,
                    'asset_category_id' => $categoryId,
                    'reason'            => $reason,
                    'request_date'      => $requestDate,
                    'status'            => 'pending',
                ]);
            }
        }

        return $this->sendSuccess($createdRequests, 'Asset request(s) submitted successfully', 201);
    }

    public function rejectRequest(Request $request, AssetRequest $assetRequest): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

        $assetRequest->update([
            'status'      => 'rejected',
            'admin_notes' => $validated['admin_notes'],
        ]);

        return $this->sendSuccess($assetRequest, 'Asset request rejected successfully');
    }

    public function allocateDirectRequest(AssetRequest $assetRequest): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if ($assetRequest->status !== 'pending') {
            return $this->sendError('Only pending asset requests can be allocated.', 422);
        }

        $asset = null;
        if ($assetRequest->requested_asset_id) {
            $asset = Asset::find($assetRequest->requested_asset_id);
            if (!$asset || $asset->status !== 'available') {
                return $this->sendError('The specifically requested asset is not currently available.', 422);
            }
        } else {
            $asset = Asset::query()
                ->where('asset_category_id', $assetRequest->asset_category_id)
                ->where('company_id', $assetRequest->company_id)
                ->where('status', 'available')
                ->first();

            if (!$asset) {
                return $this->sendError('No available asset found in this category for allocation.', 422);
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

        return $this->sendSuccess([
            'request' => $assetRequest,
            'asset'   => $asset,
        ], 'Asset allocated directly for request');
    }

    public function bulkAllocateRequests(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

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

        return $this->sendSuccess([
            'allocated_requests_count' => $allocatedCount,
        ], "Successfully allocated {$allocatedCount} asset request(s)");
    }
}
