<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeProfileUpdateRequest;
use App\Domains\HRMS\Repositories\EmployeeProfileRequestRepositoryInterface;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmployeeProfileRequestApiController extends Controller
{
    public function __construct(
        private readonly EmployeeProfileRequestRepositoryInterface $profileRequestRepository,
        private readonly HrmsScopeService $scopeService
    ) {}

    /**
     * Standardized JSON success response envelope.
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
     * Standardized JSON error response envelope.
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
     * Resolve tenant ID and current employee context.
     */
    private function resolveContext(): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 1;
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);

        $access = app(AccessService::class);
        $context = ['tenant_id' => $tenantId];

        $isHrOrAdmin = $user && (
            $this->scopeService->isCompanyAdmin($user) ||
            $access->allows($user, 'hr.settings.manage', $context) ||
            $access->allows($user, 'hrms.employees.manage', $context) ||
            $access->allows($user, 'hrms.profile_requests.manage', $context) ||
            $access->allows($user, 'hrms.employees.update', $context)
        );

        return [$tenantId, $user, $currentEmployee, (bool) $isHrOrAdmin];
    }

    // =========================================================================
    // 1. SUMMARY / DASHBOARD STATS
    // =========================================================================

    /**
     * GET /api/hrms/employees/profile-requests/summary
     * Total counters for profile edit requests (pending, approved, rejected).
     */
    public function summary(Request $request): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        try {
            $baseQuery = EmployeeProfileUpdateRequest::where('tenant_id', $tenantId);

            $stats = [
                'total_requests' => (clone $baseQuery)->count(),
                'pending_count'  => (clone $baseQuery)->where('status', 'pending')->count(),
                'approved_count' => (clone $baseQuery)->where('status', 'approved')->count(),
                'rejected_count' => (clone $baseQuery)->where('status', 'rejected')->count(),
            ];

            return $this->sendSuccess($stats, 'Profile request counters retrieved.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // 2. LIST & FILTER REQUESTS (HR ADMIN)
    // =========================================================================

    /**
     * GET /api/hrms/employees/profile-requests
     * Paginated list of profile edit requests with status, department, and search filters.
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to view all profile update requests.', 403);
        }

        $query = EmployeeProfileUpdateRequest::with([
            'employee:id,tenant_id,full_name,employee_id,department_id,designation_id,photo,personal_email,personal_mobile_number',
            'employee.department:id,name',
            'employee.designation:id,name',
            'user:id,name,email',
            'reviewer:id,name,email',
        ])
            ->where('tenant_id', $tenantId);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $request->department_id));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', $search)
                  ->orWhere('employee_id', 'like', $search)
                  ->orWhere('personal_email', 'like', $search);
            });
        }

        $sort = $request->input('sort', 'created_at_desc');
        match ($sort) {
            'created_at_asc' => $query->orderBy('created_at', 'asc'),
            'reviewed_at_desc' => $query->orderBy('reviewed_at', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(function ($req) {
            return [
                'id'                      => $req->id,
                'employee_id'             => $req->employee_id,
                'employee_code'           => $req->employee?->employee_id ?? null,
                'employee_name'           => $req->employee?->full_name ?? null,
                'department'              => $req->employee?->department?->name ?? null,
                'designation'             => $req->employee?->designation?->name ?? null,
                'status'                  => $req->status,
                'requested_changes_count' => is_array($req->changes) ? count($req->changes) : 0,
                'requested_changes'       => $req->changes,
                'rejection_reason'        => $req->rejection_reason,
                'reviewer_name'           => $req->reviewer?->name ?? null,
                'reviewed_at'             => $req->reviewed_at?->toIso8601String(),
                'created_at'              => $req->created_at?->toIso8601String(),
            ];
        });

        return $this->sendSuccess([
            'items'        => $items,
            'current_page' => $paginated->currentPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
            'last_page'    => $paginated->lastPage(),
        ], 'Profile update requests retrieved.');
    }

    // =========================================================================
    // 3. MY PROFILE REQUESTS (EMPLOYEE SELF-SERVICE)
    // =========================================================================

    /**
     * GET /api/hrms/employees/profile-requests/my-requests
     * Authenticated employee's profile edit request history.
     */
    public function myRequests(Request $request): JsonResponse
    {
        [$tenantId, , $currentEmployee] = $this->resolveContext();

        if (!$currentEmployee) {
            return $this->sendError('Employee profile not found for authenticated user.', 404);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $paginated = EmployeeProfileUpdateRequest::with(['reviewer:id,name,email'])
            ->where('tenant_id', $tenantId)
            ->where('employee_id', $currentEmployee->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $items = collect($paginated->items())->map(function ($req) {
            return [
                'id'                      => $req->id,
                'status'                  => $req->status,
                'requested_changes_count' => is_array($req->changes) ? count($req->changes) : 0,
                'requested_changes'       => $req->changes,
                'rejection_reason'        => $req->rejection_reason,
                'reviewer_name'           => $req->reviewer?->name ?? null,
                'reviewed_at'             => $req->reviewed_at?->toIso8601String(),
                'created_at'              => $req->created_at?->toIso8601String(),
            ];
        });

        return $this->sendSuccess([
            'items'        => $items,
            'current_page' => $paginated->currentPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
            'last_page'    => $paginated->lastPage(),
        ], 'My profile update requests retrieved.');
    }

    // =========================================================================
    // 4. SUBMIT PROFILE EDIT REQUEST (EMPLOYEE SELF-SERVICE)
    // =========================================================================

    /**
     * POST /api/hrms/employees/profile-requests
     * Submit profile modifications for HR review.
     */
    public function store(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee] = $this->resolveContext();

        if (!$currentEmployee) {
            return $this->sendError('Associated employee record not found.', 404);
        }

        // Check if there is already an unresolved pending request
        $existingPending = EmployeeProfileUpdateRequest::where('tenant_id', $tenantId)
            ->where('employee_id', $currentEmployee->id)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return $this->sendError('You already have a pending profile update request awaiting HR approval. Please wait or cancel the existing request.', 400);
        }

        $validator = Validator::make($request->all(), [
            'personal_email'                => 'nullable|email|max:255',
            'personal_mobile_number'        => 'nullable|string|max:20',
            'home_phone'                    => 'nullable|string|max:20',
            'present_address'               => 'nullable|string|max:500',
            'permanent_address'             => 'nullable|string|max:500',
            'city'                          => 'nullable|string|max:100',
            'postal_code'                   => 'nullable|string|max:20',
            'marital_status'                => 'nullable|string|max:50',
            'blood_group'                   => 'nullable|string|max:10',
            'diet_preference'               => 'nullable|string|max:50',
            'emergency_contact_name'        => 'nullable|string|max:100',
            'emergency_contact_relationship'=> 'nullable|string|max:50',
            'emergency_contact_number'      => 'nullable|string|max:20',
            'bank_name'                     => 'nullable|string|max:150',
            'bank_account_number'           => 'nullable|string|max:50',
            'ifsc_code'                     => 'nullable|string|max:30',
            'pan_card_number'               => 'nullable|string|max:30',
            'aadhaar_card_number'           => 'nullable|string|max:30',
            'photo'                         => 'nullable|file|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $allowedFields = [
            'personal_email',
            'personal_mobile_number',
            'home_phone',
            'present_address',
            'permanent_address',
            'city',
            'postal_code',
            'marital_status',
            'blood_group',
            'diet_preference',
            'emergency_contact_name',
            'emergency_contact_relationship',
            'emergency_contact_number',
            'bank_name',
            'bank_account_number',
            'ifsc_code',
            'pan_card_number',
            'aadhaar_card_number',
        ];

        $changes = [];

        foreach ($allowedFields as $field) {
            if ($request->has($field)) {
                $newVal = trim((string) $request->input($field));
                $oldVal = trim((string) ($currentEmployee->{$field} ?? ''));

                if ($newVal !== $oldVal) {
                    $changes[$field] = [
                        'old' => !empty($oldVal) ? $oldVal : '—',
                        'new' => !empty($newVal) ? $newVal : '—',
                    ];
                }
            }
        }

        // Handle Photo Upload
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $tempFileName = 'temp_' . $currentEmployee->id . '_' . time() . '_' . Str::random(6) . '.' . $request->file('photo')->getClientOriginalExtension();
            $tempPath = $request->file('photo')->storeAs('temp/profile_requests', $tempFileName, 'public');

            $changes['photo'] = [
                'old' => $currentEmployee->photo ?? '—',
                'new' => $tempPath,
            ];
        }

        if (empty($changes)) {
            return $this->sendError('No changes detected compared to your current profile.', 422);
        }

        try {
            $profileRequest = EmployeeProfileUpdateRequest::create([
                'tenant_id'   => $tenantId,
                'employee_id' => $currentEmployee->id,
                'user_id'     => $user->id,
                'changes'     => $changes,
                'status'      => 'pending',
            ]);

            return $this->sendSuccess([
                'id'                      => $profileRequest->id,
                'employee_id'             => $profileRequest->employee_id,
                'status'                  => $profileRequest->status,
                'requested_changes_count' => count($changes),
                'requested_changes'       => $changes,
                'created_at'              => $profileRequest->created_at?->toIso8601String(),
            ], 'Profile edit request submitted successfully and queued for HR review.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 5. SHOW REQUEST DETAILS / DIFF
    // =========================================================================

    /**
     * GET /api/hrms/employees/profile-requests/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        $profileRequest = EmployeeProfileUpdateRequest::with([
            'employee:id,tenant_id,full_name,employee_id,department_id,designation_id,photo,personal_email,personal_mobile_number',
            'employee.department:id,name',
            'employee.designation:id,name',
            'user:id,name,email',
            'reviewer:id,name,email',
        ])
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$profileRequest) {
            return $this->sendError('Profile update request not found.', 404);
        }

        // Employee can only view their own request unless HR/Admin
        if (!$isHrOrAdmin && $profileRequest->employee_id !== $currentEmployee?->id) {
            return $this->sendError('Unauthorized access to this profile request.', 403);
        }

        return $this->sendSuccess([
            'id'                => $profileRequest->id,
            'employee'          => [
                'id'            => $profileRequest->employee?->id,
                'employee_code' => $profileRequest->employee?->employee_id,
                'name'          => $profileRequest->employee?->full_name,
                'department'    => $profileRequest->employee?->department?->name,
                'designation'   => $profileRequest->employee?->designation?->name,
                'photo'         => $profileRequest->employee?->photo,
            ],
            'status'            => $profileRequest->status,
            'requested_changes' => $profileRequest->changes,
            'rejection_reason'  => $profileRequest->rejection_reason,
            'reviewer'          => $profileRequest->reviewer ? [
                'id'   => $profileRequest->reviewer->id,
                'name' => $profileRequest->reviewer->name,
            ] : null,
            'reviewed_at'       => $profileRequest->reviewed_at?->toIso8601String(),
            'created_at'        => $profileRequest->created_at?->toIso8601String(),
        ], 'Profile update request details retrieved.');
    }

    // =========================================================================
    // 6. APPROVE REQUEST (HR ADMIN)
    // =========================================================================

    /**
     * POST /api/hrms/employees/profile-requests/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to approve profile update requests.', 403);
        }

        $profileRequest = EmployeeProfileUpdateRequest::where('tenant_id', $tenantId)->find($id);

        if (!$profileRequest) {
            return $this->sendError('Profile update request not found.', 404);
        }

        if ($profileRequest->status !== 'pending') {
            return $this->sendError("This request has already been processed with status '{$profileRequest->status}'.", 400);
        }

        try {
            $success = $this->profileRequestRepository->approveRequest($profileRequest);

            if (!$success) {
                return $this->sendError('Failed to process and update employee record.', 400);
            }

            return $this->sendSuccess([
                'id'     => $profileRequest->id,
                'status' => 'approved',
            ], 'Profile update request approved and employee record updated.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 7. REJECT REQUEST (HR ADMIN)
    // =========================================================================

    /**
     * POST /api/hrms/employees/profile-requests/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        [$tenantId, , , $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized to reject profile update requests.', 403);
        }

        $profileRequest = EmployeeProfileUpdateRequest::where('tenant_id', $tenantId)->find($id);

        if (!$profileRequest) {
            return $this->sendError('Profile update request not found.', 404);
        }

        if ($profileRequest->status !== 'pending') {
            return $this->sendError("This request has already been processed with status '{$profileRequest->status}'.", 400);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $reason = $request->input('rejection_reason', 'Changes rejected by HR administrator.');

        try {
            $this->profileRequestRepository->rejectRequest($profileRequest, $reason);

            return $this->sendSuccess([
                'id'               => $profileRequest->id,
                'status'           => 'rejected',
                'rejection_reason' => $reason,
            ], 'Profile update request has been rejected.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 8. CANCEL / WITHDRAW PENDING REQUEST (EMPLOYEE SELF-SERVICE)
    // =========================================================================

    /**
     * DELETE /api/hrms/employees/profile-requests/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId, , $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        $profileRequest = EmployeeProfileUpdateRequest::where('tenant_id', $tenantId)->find($id);

        if (!$profileRequest) {
            return $this->sendError('Profile update request not found.', 404);
        }

        // Only the owner or HR admin can withdraw
        if (!$isHrOrAdmin && $profileRequest->employee_id !== $currentEmployee?->id) {
            return $this->sendError('Unauthorized to cancel this request.', 403);
        }

        if ($profileRequest->status !== 'pending') {
            return $this->sendError('Cannot withdraw a request that has already been processed.', 400);
        }

        try {
            // If there's a temporary uploaded photo, delete it from storage
            if (isset($profileRequest->changes['photo']['new'])) {
                $tempPath = $profileRequest->changes['photo']['new'];
                if ($tempPath && Storage::disk('public')->exists($tempPath)) {
                    Storage::disk('public')->delete($tempPath);
                }
            }

            $profileRequest->delete();

            return $this->sendSuccess(null, 'Profile update request withdrawn successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }
}
