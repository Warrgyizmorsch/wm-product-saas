<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ShiftChangeRequest;
use App\Domains\HRMS\Repositories\ShiftChangeRequestRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ShiftChangeRequestApiController extends Controller
{
    public function __construct(
        private readonly ShiftChangeRequestRepositoryInterface $shiftChangeRepository
    ) {}

    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

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

    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();

            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access.', 401);
            }
        }

        return null;
    }

    private function isHrAdmin(): bool
    {
        return auth()->check() && (
            auth()->user()->hasHrPermission('hr.settings.manage') ||
            auth()->user()->hasHrPermission('hr.leaves.manage') ||
            !empty(auth()->user()->role_id)
        );
    }

    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $isAdmin = $this->isHrAdmin();
        $employee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $query = ShiftChangeRequest::query();
        if (!$isAdmin) {
            $query->where('employee_id', $employee?->id ?? 0);
        }

        $totalRequests   = (clone $query)->count();
        $pendingRequests = (clone $query)->where('status', 'pending')->count();
        $approvedRequests= (clone $query)->where('status', 'approved')->count();
        $rejectedRequests= (clone $query)->where('status', 'rejected')->count();

        return $this->sendSuccess([
            'is_admin'          => $isAdmin,
            'total_requests'    => $totalRequests,
            'pending_requests'  => $pendingRequests,
            'approved_requests' => $approvedRequests,
            'rejected_requests' => $rejectedRequests,
        ], 'Shift Change requests summary loaded');
    }

    public function indexRequests(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $data = $this->shiftChangeRepository->getIndexData($request->all());

        return $this->sendSuccess($data['requests'], 'Shift Change requests retrieved successfully');
    }

    public function showRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $shiftChangeRequest = ShiftChangeRequest::with(['employee', 'currentShift', 'requestedShift', 'approvedByEmployee'])->find($id);

        if (!$shiftChangeRequest) {
            return $this->sendError("Shift Change request with ID '{$id}' not found.", 404);
        }

        return $this->sendSuccess($shiftChangeRequest, 'Shift Change request details loaded');
    }

    public function storeRequest(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $isAdmin = $this->isHrAdmin();

        $validated = $request->validate([
            'employee_id'        => $isAdmin ? 'required|exists:employees,id' : 'nullable',
            'type'               => 'required|string|in:temporary,permanent,recurring',
            'start_date'         => 'required|date',
            'end_date'           => 'nullable|required_if:type,temporary|date|after_or_equal:start_date',
            'recurring_days'     => 'nullable|required_if:type,recurring|array',
            'recurring_days.*'   => 'integer|min:0|max:6',
            'requested_shift_id' => 'nullable|exists:production_shifts,id',
            'reason'             => 'required|string|max:1000',
            'attachment'         => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        if ($isAdmin && !empty($validated['employee_id'])) {
            $employee = Employee::find($validated['employee_id']);
        } else {
            $employee = Employee::where('personal_email', auth()->user()->email)
                ->orWhere('office_email', auth()->user()->email)
                ->first();
        }

        if (!$employee) {
            return $this->sendError('Employee record not found.', 404);
        }

        // Set current shift
        $targetDate = Carbon::parse($validated['start_date']);
        $currentShift = $employee->resolveShiftForDate($targetDate);
        $validated['current_shift_id'] = $currentShift ? $currentShift->id : null;

        $validated['employee_id'] = $employee->id;
        $validated['company_id']  = $employee->company_id;

        $requestModel = $this->shiftChangeRepository->storeShiftChangeRequest($validated, $request);

        return $this->sendSuccess($requestModel, 'Shift Change request submitted successfully', 211);
    }

    public function approveRequest(Request $request, mixed $id): JsonResponse
    {
        return $this->updateStatus($request, $id, 'approved');
    }

    public function rejectRequest(Request $request, mixed $id): JsonResponse
    {
        return $this->updateStatus($request, $id, 'rejected');
    }

    public function updateStatus(Request $request, mixed $id, ?string $overrideAction = null): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action.', 403);
        }

        $shiftChangeRequest = ShiftChangeRequest::find($id);
        if (!$shiftChangeRequest) {
            return $this->sendError("Shift Change request with ID '{$id}' not found.", 404);
        }

        if ($shiftChangeRequest->status === 'cancelled') {
            return $this->sendError('Cannot update the status of a cancelled application.', 400);
        }

        $action = $overrideAction ?? $request->input('action');
        $reason = $request->input('rejection_reason');

        if (!$action) {
            $validated = $request->validate([
                'action'           => 'required|in:approved,rejected,pending',
                'rejection_reason' => 'nullable|string|max:1000',
            ]);
            $action = $validated['action'];
            $reason = $validated['rejection_reason'] ?? null;
        }

        $this->shiftChangeRepository->updateStatus($shiftChangeRequest, [
            'action'           => $action,
            'rejection_reason' => $reason,
        ], $request);

        return $this->sendSuccess($shiftChangeRequest, 'Shift Change request status updated successfully');
    }
}
