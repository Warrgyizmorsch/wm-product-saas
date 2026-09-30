<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Repositories\AttendanceCorrectionRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceCorrectionController extends Controller
{
    public function __construct(
        private readonly AttendanceCorrectionRepositoryInterface $attendanceCorrectionRepository
    ) {}

    /**
     * GET /api/hrms/attendance-corrections
     * List paginated attendance correction requests with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $data = $this->attendanceCorrectionRepository->getIndexData($request->all(), $user, $tenantId);

        return response()->json([
            'success' => true,
            'data'    => $data['items'],
            'pagination' => $data['pagination'],
        ]);
    }

    /**
     * POST /api/hrms/attendance-corrections
     * Submit a new attendance correction request.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'employee_id'         => 'required|integer|exists:employees,id',
            'date'                => 'required|date_format:Y-m-d',
            'requested_check_in'  => 'nullable|date_format:H:i',
            'requested_check_out' => 'nullable|date_format:H:i',
            'reason'              => 'required|string|max:500',
        ]);

        if (empty($validated['requested_check_in']) && empty($validated['requested_check_out'])) {
            return response()->json([
                'success' => false,
                'message' => 'At least one of requested_check_in or requested_check_out must be provided.',
            ], 422);
        }

        $res = $this->attendanceCorrectionRepository->storeCorrection($validated, auth()->user(), $tenantId);

        return response()->json([
            'success' => $res['success'],
            'message' => $res['message'],
            'data'    => $res['data'] ?? null,
        ], $res['status_code']);
    }

    /**
     * POST /api/hrms/attendance-corrections/{correction}/approve
     * Approve a pending attendance correction request.
     */
    public function approve(Request $request, AttendanceCorrection $correction): JsonResponse
    {
        $this->authorizeHrms('hrms.attendance_corrections.approve');

        $res = $this->attendanceCorrectionRepository->approve($correction, auth()->user());

        return response()->json([
            'success' => $res['success'],
            'message' => $res['message'],
            'data'    => $res['data'] ?? null,
        ], $res['status_code']);
    }

    /**
     * POST /api/hrms/attendance-corrections/{correction}/reject
     * Reject a pending attendance correction request.
     */
    public function reject(Request $request, AttendanceCorrection $correction): JsonResponse
    {
        $this->authorizeHrms('hrms.attendance_corrections.approve');

        $validated = $request->validate([
            'rejected_reason' => 'nullable|string|max:500',
        ]);

        $res = $this->attendanceCorrectionRepository->reject($correction, $validated['rejected_reason'] ?? null, auth()->user());

        return response()->json([
            'success' => $res['success'],
            'message' => $res['message'],
            'data'    => $res['data'] ?? null,
        ], $res['status_code']);
    }

    /**
     * POST /api/hrms/attendance-corrections/{correction}/withdraw
     * Withdraw a pending correction request.
     */
    public function withdraw(Request $request, AttendanceCorrection $correction): JsonResponse
    {
        $res = $this->attendanceCorrectionRepository->withdraw($correction, auth()->user());

        return response()->json([
            'success' => $res['success'],
            'message' => $res['message'],
            'data'    => $res['data'] ?? null,
        ], $res['status_code']);
    }

    /**
     * POST /api/hrms/attendance-corrections/{correction}/request-cancellation
     * Request cancellation of an approved correction.
     */
    public function requestCancellation(Request $request, AttendanceCorrection $correction): JsonResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        $res = $this->attendanceCorrectionRepository->requestCancellation($correction, $validated['cancellation_reason'], auth()->user());

        return response()->json([
            'success' => $res['success'],
            'message' => $res['message'],
            'data'    => $res['data'] ?? null,
        ], $res['status_code']);
    }

    /**
     * POST /api/hrms/attendance-corrections/{correction}/approve-cancellation
     * Admin approves the cancellation of an approved correction.
     */
    public function approveCancellation(Request $request, AttendanceCorrection $correction): JsonResponse
    {
        $this->authorizeHrms('hrms.attendance_corrections.approve');

        $res = $this->attendanceCorrectionRepository->approveCancellation($correction, auth()->user());

        return response()->json([
            'success' => $res['success'],
            'message' => $res['message'],
            'data'    => $res['data'] ?? null,
        ], $res['status_code']);
    }

    /**
     * POST /api/hrms/attendance-corrections/{correction}/deny-cancellation
     * Admin denies the cancellation request.
     */
    public function denyCancellation(Request $request, AttendanceCorrection $correction): JsonResponse
    {
        $this->authorizeHrms('hrms.attendance_corrections.approve');

        $validated = $request->validate([
            'cancellation_denial_reason' => 'nullable|string|max:500',
        ]);

        $res = $this->attendanceCorrectionRepository->denyCancellation($correction, $validated['cancellation_denial_reason'] ?? null, auth()->user());

        return response()->json([
            'success' => $res['success'],
            'message' => $res['message'],
            'data'    => $res['data'] ?? null,
        ], $res['status_code']);
    }

    private function authorizeHrms(string $permission): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $context = ['tenant_id' => $user->tenant_id];
        $access = app(\App\Services\Access\AccessService::class);

        $allowed = $access->allows($user, $permission, $context)
            || $access->allows($user, 'hrms.attendance_corrections.manage', $context)
            || $access->allows($user, 'hrms.attendance.approve', $context)
            || $access->allows($user, 'hrms.attendance.manage', $context)
            || $access->allows($user, 'hr.settings.manage', $context)
            || in_array($user->role, ['admin', 'super_admin', 'company_admin', 'hr_manager']);

        abort_unless($allowed, 403, 'Unauthorized action in Attendance Corrections.');
    }
}
