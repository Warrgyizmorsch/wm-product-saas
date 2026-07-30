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
        if ($startDate->equalTo($endDate)) {
            if ($startDate->dayOfWeek !== Carbon::SUNDAY) {
                $duration = ($startType === 'full_day') ? 1.0 : 0.5;
            }
        } else {
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                if ($date->dayOfWeek === Carbon::SUNDAY) {
                    continue;
                }
                if ($date->equalTo($startDate)) {
                    $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                } elseif ($date->equalTo($endDate)) {
                    $duration += ($endType === 'full_day') ? 1.0 : 0.5;
                } else {
                    $duration += 1.0;
                }
            }
        }

        if ($duration == 0) {
            return $this->sendError('WFH duration cannot be 0 days.', 422);
        }

        // Check overlapping requests
        $overlapExists = WfhRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate->format('Y-m-d'))
                  ->where('end_date', '>=', $startDate->format('Y-m-d'));
            })
            ->exists();

        if ($overlapExists) {
            return $this->sendError('A WFH request already exists for the specified date range.', 422);
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
}
