<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Domains\HRMS\Repositories\OvertimeRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OvertimeRequestController extends Controller
{
    public function __construct(
        private readonly OvertimeRequestRepositoryInterface $overtimeRepository
    ) {}

    public function index(Request $request): View
    {
        $data = $this->overtimeRepository->getIndexData($request->all());

        return view('modules.hrms.overtime.index', $data);
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
            'employee_id'       => $isAdmin ? 'required|exists:employees,id' : 'nullable',
            'date'              => 'required|date',
            'start_time'        => 'required|date_format:H:i',
            'end_time'          => 'required|date_format:H:i',
            'compensation_type' => 'required|string|in:payout,comp_off',
            'reason'            => 'required|string|max:1000',
            'attachment'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
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

        // Check if an overtime request already exists for this employee on this date
        $exists = OvertimeRequest::where('employee_id', $employee->id)
            ->where('date', $validated['date'])
            ->exists();
        if ($exists) {
            return redirect()->back()->withInput()->with('error', 'An overtime request already exists for this date.');
        }

        // Calculate hours
        $startTime = Carbon::parse($validated['start_time']);
        $endTime   = Carbon::parse($validated['end_time']);
        if ($endTime->lessThan($startTime)) {
            $endTime->addDay();
        }
        $durationHours = $startTime->diffInMinutes($endTime) / 60.0;

        // Fetch minimum overtime request hours setting
        $minHours = 0.5;
        $tenant = $user ? \App\Models\Tenant::find($user->tenant_id) : null;
        if ($tenant && is_array($tenant->settings)) {
            $minHours = (float) ($tenant->settings['min_overtime_request_hours'] ?? 0.5);
        }

        if ($durationHours < $minHours) {
            return redirect()->back()->withInput()->with('error', "Overtime duration must be at least {$minHours} hours.");
        }

        $validated['duration_hours']          = $durationHours;
        $validated['approved_duration_hours'] = $durationHours;
        $validated['employee_id']             = $employee->id;
        $validated['company_id']              = $employee->company_id;

        $this->overtimeRepository->storeOvertimeRequest($validated, $request);

        return redirect()->back()->with('success', 'Overtime application submitted successfully.');
    }

    public function approve(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        return $this->updateStatus($request, $overtimeRequest, 'approved');
    }

    public function reject(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        return $this->updateStatus($request, $overtimeRequest, 'rejected');
    }

    public function updateStatus(Request $request, OvertimeRequest $overtimeRequest, ?string $overrideAction = null): RedirectResponse
    {
        $user    = auth()->user();
        $isAdmin = $user && ($user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.leaves.manage')
            || !empty($user->role_id));

        if (!$isAdmin) {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

        if ($overtimeRequest->status === 'cancelled') {
            return redirect()->back()->with('error', 'Cannot change the status of a cancelled application.');
        }

        $action = $overrideAction ?? $request->input('action');
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
        } else {
            $reason = $request->input('rejection_reason');
        }

        $this->overtimeRepository->updateStatus($overtimeRequest, [
            'action'                  => $action,
            'rejection_reason'        => $reason,
            'approved_duration_hours' => $approvedHours,
        ], $request);

        return redirect()->back()->with('success', 'Overtime application status updated.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $user    = auth()->user();
        $isAdmin = $user && ($user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.leaves.manage')
            || !empty($user->role_id));

        if (!$isAdmin) {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

        $validated = $request->validate([
            'auto_overtime_threshold_hours' => 'required|numeric|min:0',
            'min_overtime_request_hours'    => 'required|numeric|min:0.5',
        ]);
        $validated['overtime_rate_multiplier'] = 1.0;

        $this->overtimeRepository->updateGlobalSettings($validated);

        return redirect()->back()->with('success', 'Overtime policies updated successfully.');
    }

    public function destroy(OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $user    = auth()->user();
        $isAdmin = $user && ($user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.leaves.manage')
            || !empty($user->role_id));

        if (!$isAdmin) {
            $employee = Employee::where('personal_email', $user?->email)
                ->orWhere('office_email', $user?->email)
                ->first();
            if (!$employee || $employee->id !== $overtimeRequest->employee_id) {
                return redirect()->back()->with('error', 'Unauthorized action.');
            }
        }

        if ($overtimeRequest->status === 'approved') {
            return redirect()->back()->with('error', 'Approved applications cannot be deleted.');
        }

        $overtimeRequest->delete();

        return redirect()->back()->with('success', 'Overtime request deleted successfully.');
    }
}
