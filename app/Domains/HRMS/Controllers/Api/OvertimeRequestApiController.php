<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Domains\HRMS\Repositories\OvertimeRequestRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class OvertimeRequestApiController extends Controller
{
    public function __construct(
        private readonly OvertimeRequestRepositoryInterface $overtimeRepository
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
                    return $this->sendError('Invalid credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated.', 401);
            }
        }

        return null;
    }


    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $query = OvertimeRequest::query();

        $totalRequests   = (clone $query)->count();
        $pendingRequests = (clone $query)->where('status', 'pending')->count();
        $approvedRequests= (clone $query)->where('status', 'approved')->count();
        $rejectedRequests= (clone $query)->where('status', 'rejected')->count();

        return $this->sendSuccess([
            'total_requests'    => $totalRequests,
            'pending_requests'  => $pendingRequests,
            'approved_requests' => $approvedRequests,
            'rejected_requests' => $rejectedRequests,
        ], 'Overtime requests summary loaded');
    }

    public function indexRequests(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $data = $this->overtimeRepository->getIndexData($request->all());

        return $this->sendSuccess($data['requests'], 'Overtime requests retrieved successfully');
    }

    public function showRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $overtimeRequest = OvertimeRequest::with(['employee', 'approvedByEmployee'])->find($id);

        if (!$overtimeRequest) {
            return $this->sendError("Overtime request with ID '{$id}' not found.", 404);
        }

        return $this->sendSuccess($overtimeRequest, 'Overtime request details loaded');
    }

    public function storeRequest(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }



        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'date'              => 'required|date',
            'start_time'        => 'required|date_format:H:i',
            'end_time'          => 'required|date_format:H:i',
            'compensation_type' => 'required|string|in:payout,comp_off',
            'reason'            => 'required|string|max:1000',
            'attachment'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        $employee = Employee::find($validated['employee_id']);

        if (!$employee) {
            return $this->sendError('Employee record not found.', 404);
        }

        // Check if an overtime request already exists for this employee on this date
        $exists = OvertimeRequest::where('employee_id', $employee->id)
            ->where('date', $validated['date'])
            ->exists();
        if ($exists) {
            return $this->sendError(__('hrms.overtime.request_exists') ?? 'An overtime request already exists for this date.', 422);
        }

        $startTime = Carbon::parse($validated['start_time']);
        $endTime   = Carbon::parse($validated['end_time']);
        if ($endTime->lessThan($startTime)) {
            $endTime->addDay();
        }
        $durationHours = $startTime->diffInMinutes($endTime) / 60.0;

        // Fetch minimum overtime request hours setting
        $minHours = 0.5;
        $tenant = \App\Models\Tenant::find(auth()->user()->tenant_id);
        if ($tenant && is_array($tenant->settings)) {
            $minHours = (float) ($tenant->settings['min_overtime_request_hours'] ?? 0.5);
        }

        if ($durationHours < $minHours) {
            return $this->sendError(__('hrms.overtime.duration_error', ['min' => $minHours]) ?? "Overtime duration must be at least {$minHours} hours.", 422);
        }

        $validated['duration_hours']          = $durationHours;
        $validated['approved_duration_hours'] = $durationHours;
        $validated['employee_id']             = $employee->id;
        $validated['company_id']              = $employee->company_id;

        $requestModel = $this->overtimeRepository->storeOvertimeRequest($validated, $request);

        return $this->sendSuccess($requestModel, 'Overtime request submitted successfully', 201);
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

        $overtimeRequest = OvertimeRequest::find($id);
        if (!$overtimeRequest) {
            return $this->sendError("Overtime request with ID '{$id}' not found.", 404);
        }

        if ($overtimeRequest->status === 'cancelled') {
            return $this->sendError('Cannot update status of a cancelled application.', 400);
        }

        $action = $overrideAction ?? $request->input('action');
        $reason = $request->input('rejection_reason');
        $approvedHours = $request->input('approved_duration_hours');

        if (!$action) {
            $validated = $request->validate([
                'action'                  => 'required|in:approved,rejected,pending',
                'rejection_reason'        => 'nullable|string|max:1000',
                'approved_duration_hours' => 'nullable|numeric|min:0.5',
            ]);
            $action        = $validated['action'];
            $reason        = $validated['rejection_reason'] ?? null;
            $approvedHours = $validated['approved_duration_hours'] ?? null;
        }

        $this->overtimeRepository->updateStatus($overtimeRequest, [
            'action'                  => $action,
            'rejection_reason'        => $reason,
            'approved_duration_hours' => $approvedHours,
        ], $request);

        return $this->sendSuccess($overtimeRequest, 'Overtime request status updated successfully');
    }

    public function destroy(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $overtimeRequest = OvertimeRequest::find($id);
        if (!$overtimeRequest) {
            return $this->sendError("Overtime request with ID '{$id}' not found.", 404);
        }

        if ($overtimeRequest->status === 'approved') {
            return $this->sendError(__('hrms.overtime.approved_no_delete') ?? 'Approved request cannot be deleted.', 422);
        }

        $overtimeRequest->delete();

        return $this->sendSuccess(null, 'Overtime request deleted successfully');
    }
}
