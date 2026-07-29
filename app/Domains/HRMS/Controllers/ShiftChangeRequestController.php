<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ShiftChangeRequest;
use App\Domains\HRMS\Repositories\ShiftChangeRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftChangeRequestController extends Controller
{
    public function __construct(
        private readonly ShiftChangeRequestRepositoryInterface $shiftChangeRepository
    ) {}

    public function index(Request $request): View
    {
        $data = $this->shiftChangeRepository->getIndexData($request->all());

        return view('modules.hrms.shift-change.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $user    = auth()->user();
        $isAdmin = false;
        if ($user) {
            $isAdmin = $user->hasHrPermission('hr.settings.manage')
                || $user->hasHrPermission('hr.leaves.manage')
                || !empty($user->role_id);
        }

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
            $employee = Employee::findOrFail($validated['employee_id']);
        } else {
            $employee = Employee::where('personal_email', $user?->email)
                ->orWhere('office_email', $user?->email)
                ->first();
        }

        if (!$employee) {
            return redirect()->back()->with('error', 'Employee profile not found.');
        }

        // Set current shift
        $targetDate = Carbon::parse($validated['start_date']);
        $currentShift = $employee->resolveShiftForDate($targetDate);
        $currentShiftId = $currentShift ? $currentShift->id : null;

        // Prevent requesting same shift
        $requestedShiftId = $validated['requested_shift_id'] ?? null;
        if ($requestedShiftId && (int)$requestedShiftId === (int)$currentShiftId) {
            return redirect()->back()->with('error', 'The requested shift cannot be the same as the current active shift for that date.')->withInput();
        }
        if (!$requestedShiftId && !$currentShiftId) {
            return redirect()->back()->with('error', 'The requested shift (Day Off) cannot be the same as the current active shift (Day Off).')->withInput();
        }

        $validated['current_shift_id'] = $currentShiftId;
        $validated['employee_id'] = $employee->id;
        $validated['company_id']  = $employee->company_id;

        $this->shiftChangeRepository->storeShiftChangeRequest($validated, $request);

        return redirect()->back()->with('success', 'Shift Change application submitted successfully.');
    }

    public function approve(Request $request, ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        return $this->updateStatus($request, $shiftChangeRequest, 'approved');
    }

    public function reject(Request $request, ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        return $this->updateStatus($request, $shiftChangeRequest, 'rejected');
    }

    public function updateStatus(Request $request, ShiftChangeRequest $shiftChangeRequest, ?string $overrideAction = null): RedirectResponse
    {
        $user    = auth()->user();
        $isAdmin = $user && ($user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.leaves.manage')
            || !empty($user->role_id));

        if (!$isAdmin) {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

        if ($shiftChangeRequest->status === 'cancelled') {
            return redirect()->back()->with('error', 'Cannot change the status of a cancelled application.');
        }

        $action = $overrideAction ?? $request->input('action');
        if (!$action) {
            $validated = $request->validate([
                'action'           => 'required|in:approved,rejected,pending',
                'rejection_reason' => 'nullable|string|max:1000',
            ]);
            $action = $validated['action'];
            $reason = $validated['rejection_reason'] ?? null;
        } else {
            $reason = $request->input('rejection_reason');
        }

        $this->shiftChangeRepository->updateStatus($shiftChangeRequest, [
            'action'           => $action,
            'rejection_reason' => $reason,
        ], $request);

        return redirect()->back()->with('success', 'Shift Change application status updated.');
    }

    public function destroy(ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        $user    = auth()->user();
        $isAdmin = $user && ($user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.leaves.manage')
            || !empty($user->role_id));

        if (!$isAdmin) {
            $employee = Employee::where('personal_email', $user?->email)
                ->orWhere('office_email', $user?->email)
                ->first();
            if (!$employee || $employee->id !== $shiftChangeRequest->employee_id) {
                return redirect()->back()->with('error', 'Unauthorized action.');
            }
        }

        if ($shiftChangeRequest->status === 'approved') {
            return redirect()->back()->with('error', 'Approved applications cannot be deleted.');
        }

        $shiftChangeRequest->delete();

        return redirect()->back()->with('success', 'Shift change request deleted successfully.');
    }
}
