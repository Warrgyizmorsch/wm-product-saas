<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\WfhRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class WfhRequestApiController extends Controller
{
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
                    return $this->sendError('Invalid HTTP Basic Auth username or password.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access. Please log in or provide HTTP Basic Auth credentials.', 401);
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

        $query = WfhRequest::query();

        $totalRequests   = (clone $query)->count();
        $pendingRequests = (clone $query)->where('status', 'pending')->count();
        $approvedRequests= (clone $query)->where('status', 'approved')->count();
        $rejectedRequests= (clone $query)->where('status', 'rejected')->count();

        return $this->sendSuccess([
            'total_requests'    => $totalRequests,
            'pending_requests'  => $pendingRequests,
            'approved_requests' => $approvedRequests,
            'rejected_requests' => $rejectedRequests,
        ], 'WFH requests summary loaded successfully');
    }

    public function indexRequests(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $query = WfhRequest::query()->with(['employee', 'approvedByEmployee']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($requests, 'WFH requests retrieved successfully');
    }

    public function showRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $wfhRequest = WfhRequest::with(['employee', 'approvedByEmployee'])->find($id);

        if (!$wfhRequest) {
            return $this->sendError("WFH request with ID '{$id}' not found.", 404);
        }

        return $this->sendSuccess($wfhRequest, 'WFH request details loaded');
    }

    public function storeRequest(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date|after_or_equal:start_date',
            'start_date_type'   => 'required|string|in:full_day,first_half,second_half',
            'end_date_type'     => 'required|string|in:full_day,first_half,second_half',
            'reason'            => 'required|string|max:1000',
            'wfh_latitude'      => 'nullable|numeric|between:-90,90',
            'wfh_longitude'     => 'nullable|numeric|between:-180,180',
            'attachment'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'notified_contacts' => 'nullable|array',
            'notified_contacts.*' => 'exists:employees,id'
        ]);

        $employee = Employee::find($validated['employee_id']);

        if (!$employee) {
            return $this->sendError('Employee record not found for the current user.', 404);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);
        $startType = $validated['start_date_type'];
        $endType   = $validated['end_date_type'];

        $duration = 0;
        if ($startDate->isSameDay($endDate)) {
            $duration = ($startType === 'full_day') ? 1.0 : 0.5;
        } else {
            $daysDiff = $startDate->diffInDays($endDate);
            if ($daysDiff === 1) {
                $duration  = ($startType === 'full_day') ? 1.0 : 0.5;
                $duration += ($endType   === 'full_day') ? 1.0 : 0.5;
            } else {
                $duration  = ($startType === 'full_day') ? 1.0 : 0.5;
                $duration += ($daysDiff - 1);
                $duration += ($endType   === 'full_day') ? 1.0 : 0.5;
            }
        }

        if ($duration < 0.5) {
            return $this->sendError('Duration cannot be less than 0.5 days.', 422);
        }

        $conflict = \App\Domains\HRMS\Helpers\SessionConflictChecker::hasConflict(
            employeeId:   $employee->id,
            newStart:     $startDate,
            newEnd:       $endDate,
            newStartType: $startType,
            newEndType:   $endType
        );

        if ($conflict) {
            return $this->sendError($conflict, 422);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('wfh_attachments', 'public');
        }

        $wfhRequest = WfhRequest::create([
            'tenant_id'         => auth()->user()->tenant_id ?? null,
            'company_id'        => $employee->company_id,
            'employee_id'       => $employee->id,
            'start_date'        => $startDate->format('Y-m-d'),
            'end_date'          => $endDate->format('Y-m-d'),
            'duration'          => $duration,
            'start_date_type'   => $startType,
            'end_date_type'     => $endType,
            'notified_contacts'=> $validated['notified_contacts'] ?? null,
            'reason'            => $validated['reason'],
            'wfh_latitude'      => $validated['wfh_latitude'] ?? null,
            'wfh_longitude'     => $validated['wfh_longitude'] ?? null,
            'status'            => 'pending',
            'current_level'     => '1',
            'attachment_path'   => $attachmentPath,
        ]);

        return $this->sendSuccess($wfhRequest->load(['employee']), 'WFH request submitted successfully.', 201);
    }

    public function approveRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        $wfhRequest = WfhRequest::find($id);
        if (!$wfhRequest) {
            return $this->sendError("WFH request with ID '{$id}' not found.", 404);
        }

        $adminEmployee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $wfhRequest->update([
            'status'        => 'approved',
            'current_level' => 'approved',
            'approved_by'   => $adminEmployee ? $adminEmployee->id : null
        ]);

        return $this->sendSuccess($wfhRequest, 'WFH request approved successfully.');
    }

    public function rejectRequest(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        $wfhRequest = WfhRequest::find($id);
        if (!$wfhRequest) {
            return $this->sendError("WFH request with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);

        $wfhRequest->update([
            'status'           => 'rejected',
            'current_level'    => 'rejected',
            'rejection_reason' => $validated['rejection_reason']
        ]);

        return $this->sendSuccess($wfhRequest, 'WFH request rejected successfully.');
    }

    public function updateStatus(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        $wfhRequest = WfhRequest::find($id);
        if (!$wfhRequest) {
            return $this->sendError("WFH request with ID '{$id}' not found.", 404);
        }

        if ($wfhRequest->status === 'cancelled') {
            return $this->sendError('Cannot change the status of a cancelled WFH application.', 422);
        }

        $validated = $request->validate([
            'status'           => 'required|string|in:approved,rejected,pending',
            'rejection_reason' => 'nullable|string|max:500'
        ]);

        $status        = $validated['status'];
        $adminEmployee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        if ($status === 'approved') {
            $wfhRequest->update([
                'status'           => 'approved',
                'current_level'    => 'approved',
                'approved_by'      => $adminEmployee ? $adminEmployee->id : null,
                'rejection_reason' => null
            ]);
        } elseif ($status === 'rejected') {
            $wfhRequest->update([
                'status'           => 'rejected',
                'current_level'    => 'rejected',
                'rejection_reason' => $validated['rejection_reason'] ?? 'Rejected by Admin'
            ]);
        } else {
            $wfhRequest->update([
                'status'           => 'pending',
                'current_level'    => '1',
                'rejection_reason' => null
            ]);
        }

        return $this->sendSuccess($wfhRequest, "WFH request status updated to '{$status}' successfully.");
    }

    public function withdrawRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $wfhRequest = WfhRequest::find($id);
        if (!$wfhRequest) {
            return $this->sendError("WFH request with ID '{$id}' not found.", 404);
        }

        if (!$wfhRequest->canWithdraw()) {
            return $this->sendError('Only pending applications can be withdrawn.', 400);
        }

        $wfhRequest->delete();

        return $this->sendSuccess(null, 'WFH application withdrawn successfully.');
    }

    public function requestCancellation(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $wfhRequest = WfhRequest::find($id);
        if (!$wfhRequest) {
            return $this->sendError("WFH request with ID '{$id}' not found.", 404);
        }

        if (!$wfhRequest->canRequestCancellation()) {
            return $this->sendError('Only approved applications can have a cancellation requested.', 400);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $wfhRequest->update([
            'status'              => 'cancellation_requested',
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        return $this->sendSuccess($wfhRequest, 'Cancellation request submitted. Awaiting admin approval.');
    }

    public function approveCancellation(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!auth()->user()->hasHrPermission('hr.settings.manage')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $wfhRequest = WfhRequest::find($id);
        if (!$wfhRequest) {
            return $this->sendError("WFH request with ID '{$id}' not found.", 404);
        }

        if ($wfhRequest->status !== 'cancellation_requested') {
            return $this->sendError('This application does not have a pending cancellation request.', 400);
        }

        $wfhRequest->update([
            'status'        => 'cancelled',
            'current_level' => 'cancelled',
        ]);

        return $this->sendSuccess($wfhRequest, 'WFH cancellation approved.');
    }

    public function denyCancellation(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!auth()->user()->hasHrPermission('hr.settings.manage')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $wfhRequest = WfhRequest::find($id);
        if (!$wfhRequest) {
            return $this->sendError("WFH request with ID '{$id}' not found.", 404);
        }

        if ($wfhRequest->status !== 'cancellation_requested') {
            return $this->sendError('This application does not have a pending cancellation request.', 400);
        }

        $wfhRequest->update([
            'status'              => 'approved',
            'cancellation_reason' => null,
        ]);

        return $this->sendSuccess($wfhRequest, 'Cancellation request denied. Application remains approved.');
    }
}
