<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Carbon\Carbon;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        // Self-healing: Automatically reconcile used balances to ensure they match approved durations exactly
        try {
            $allBalances = LeaveBalance::all();
            foreach ($allBalances as $bal) {
                $approvedDuration = LeaveRequest::where('employee_id', $bal->employee_id)
                    ->where('leave_type_id', $bal->leave_type_id)
                    ->where('status', 'approved')
                    ->sum('duration');
                if (floatval($bal->used) !== floatval($approvedDuration)) {
                    $bal->update(['used' => $approvedDuration]);
                }
            }
        } catch (\Exception $e) {
            // Silence errors in case migration is not fully run yet
        }

        $isAdmin = auth()->user()->hasHrPermission('hr.settings.manage');
        $employee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $allEmployees = Employee::where('status', true)->orderBy('full_name')->get();

        // Build a complete lookup map of all active employees' leave types, quotas, remaining balances, and rules
        $employeeDataMap = [];
        foreach ($allEmployees as $emp) {
            if ($emp->leavePlan) {
                foreach ($emp->leavePlan->types()->where('status', true)->get() as $type) {
                    LeaveBalance::firstOrCreate([
                        'tenant_id' => $emp->tenant_id,
                        'company_id' => $emp->company_id,
                        'employee_id' => $emp->id,
                        'leave_type_id' => $type->id,
                    ], [
                        'allocated' => $type->quota,
                        'used' => 0.0
                    ]);
                }
            }

            $balances = LeaveBalance::where('employee_id', $emp->id)->with('leaveType')->get();
            $typesList = [];
            foreach ($balances as $bal) {
                $typesList[] = [
                    'id' => $bal->leaveType->id,
                    'name' => $bal->leaveType->name,
                    'quota' => floatval($bal->allocated),
                    'remaining' => floatval($bal->remaining),
                    'type' => $bal->leaveType->type,
                    'rules' => $bal->leaveType->rules ?? [],
                ];
            }
            $employeeDataMap[$emp->id] = $typesList;
        }

        // Fetch leave balances for the logged-in employee (if exists)
        $balances = $employee ? LeaveBalance::where('employee_id', $employee->id)->with('leaveType')->get() : collect();

        $query = LeaveRequest::query()->with(['employee', 'leaveType']);

        if ($isAdmin) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
        } else {
            if ($employee) {
                $query->where('employee_id', $employee->id);
            } else {
                $query->where('id', 0);
            }
        }

        // Apply dynamic Search query filtering
        if ($request->filled('search')) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->search . '%')
                  ->orWhere('employee_id', 'like', '%' . $request->search . '%');
            });
        }

        $leaveRequests = $query->orderBy('created_at', 'desc')->get();

        return view('modules.hrms.leaves.index', compact('leaveRequests', 'balances', 'isAdmin', 'employee', 'allEmployees', 'employeeDataMap'));
    }

    public function store(Request $request): RedirectResponse
    {
        $isAdmin = auth()->user()->hasHrPermission('hr.settings.manage');

        $request->validate([
            'employee_id' => $isAdmin ? 'required|exists:employees,id' : 'nullable',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_date_type' => 'required|string|in:full_day,first_half,second_half',
            'end_date_type' => 'required|string|in:full_day,first_half,second_half',
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'notified_contacts' => 'nullable|array',
            'notified_contacts.*' => 'exists:employees,id'
        ]);

        if ($isAdmin && $request->filled('employee_id')) {
            $employee = Employee::findOrFail($request->employee_id);
        } else {
            $employee = Employee::where('personal_email', auth()->user()->email)
                ->orWhere('office_email', auth()->user()->email)
                ->first();
            if (!$employee) {
                return redirect()->back()->with('error', 'Employee record not found for your account.');
            }
        }

        $leaveType = LeaveType::findOrFail($request->leave_type_id);
        $rules = $leaveType->rules ?? [];
        
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $startType = $request->input('start_date_type');
        $endType = $request->input('end_date_type');

        $duration = 0;

        if ($startDate->equalTo($endDate)) {
            // Single day leave application
            if ($startDate->dayOfWeek !== Carbon::SUNDAY) {
                $duration = ($startType === 'full_day') ? 1.0 : 0.5;
            }
        } else {
            // Range application
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
            return redirect()->back()->withErrors(['end_date' => 'Applied duration cannot be 0 days.']);
        }

        // Prevent overlapping active (pending or approved) leave requests for this employee
        $overlapExists = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate->format('Y-m-d'))
                  ->where('end_date', '>=', $startDate->format('Y-m-d'));
            })
            ->exists();

        if ($overlapExists) {
            return redirect()->back()->withErrors(['start_date' => 'An active leave application already exists that overlaps with this date range.']);
        }

        $appRules = $rules['application'] ?? [];

        // 1. Validate Probation Restriction Rule
        $probationRules = $rules['probation'] ?? [];
        if (!empty($probationRules['probation_rule'])) {
            $probRule = $probationRules['probation_rule'];
            $doj = $employee->date_of_joining;
            
            if ($probRule === 'disallow' && $employee->employee_stage === 'Probation') {
                return redirect()->back()->withErrors(['start_date' => 'You cannot apply for this leave during your probation period.']);
            }
            if ($probRule === 'allow_after_months') {
                $requiredMonths = intval($probationRules['probation_months'] ?? 3);
                if ($doj && Carbon::parse($doj)->addMonths($requiredMonths)->isFuture()) {
                    return redirect()->back()->withErrors(['start_date' => "You can only apply for this leave after {$requiredMonths} months of service."]);
                }
            }
        }

        // 2. Validate Notice Period Restriction Rule
        $noticeRules = $rules['notice'] ?? [];
        if (!empty($noticeRules['notice_rule']) && $noticeRules['notice_rule'] === 'disallow') {
            if ($employee->employee_stage === 'Notice Period') {
                return redirect()->back()->withErrors(['start_date' => 'You cannot apply for this leave during your notice period.']);
            }
        }

        // 3. Validate Apply in Advance Rule
        if (!empty($appRules['apply_in_advance'])) {
            $advanceDays = intval($appRules['advance_days'] ?? 3);
            $minAllowedDate = Carbon::today()->addDays($advanceDays);
            if ($startDate->lt($minAllowedDate)) {
                return redirect()->back()->withErrors(['start_date' => "This leave must be applied at least {$advanceDays} days in advance (earliest allowed: " . $minAllowedDate->format('d M, Y') . ")."]);
            }
        }

        // 4. Validate Duration Limits
        $minDuration = floatval($appRules['min_duration'] ?? 1);
        $maxDuration = floatval($appRules['max_duration'] ?? 10);
        if ($duration < $minDuration) {
            return redirect()->back()->withErrors(['end_date' => "Leave duration must be at least {$minDuration} day(s)."]);
        }
        if ($duration > $maxDuration) {
            return redirect()->back()->withErrors(['end_date' => "Leave duration cannot exceed {$maxDuration} day(s)."]);
        }

        // 5. Validate Attachment Requirements
        if (!empty($appRules['require_attachment'])) {
            $attachmentDays = intval($appRules['attachment_days'] ?? 3);
            if ($duration >= $attachmentDays && !$request->hasFile('attachment')) {
                return redirect()->back()->withErrors(['attachment' => "An attachment is required for leave duration of {$attachmentDays} days or more."]);
            }
        }

        // 6. Validate Leave Balance Availability
        $isPaid = strtolower($leaveType->type) === 'paid';
        $isLimited = empty($rules['accrual']['quota_type']) || $rules['accrual']['quota_type'] !== 'unlimited';

        if ($isPaid && $isLimited) {
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->first();
            
            $remaining = $balance ? floatval($balance->remaining) : 0.0;
            if ($duration > $remaining) {
                return redirect()->back()->withErrors(['end_date' => "Insufficient leave balance. You have {$remaining} days remaining, but requested {$duration} days."]);
            }
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leave_attachments', 'public');
        }

        $approvalRules = $rules['approval'] ?? [];
        $status = 'pending';
        if (($approvalRules['workflow_level'] ?? '1_level') === 'auto') {
            $status = 'approved';
        }

        $leaveRequest = LeaveRequest::create([
            'tenant_id' => auth()->user()->tenant_id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'duration' => $duration,
            'start_date_type' => $startType,
            'end_date_type' => $endType,
            'notified_contacts' => $request->input('notified_contacts'),
            'reason' => $request->reason,
            'status' => $status,
            'current_level' => $status === 'approved' ? 'approved' : '1',
            'attachment_path' => $attachmentPath
        ]);

        // If auto-approved, deduct balance instantly
        if ($status === 'approved' && $isPaid && $isLimited) {
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->first();
            if ($balance) {
                $balance->increment('used', $duration);
            }
        }

        return redirect()->back()->with('success', $status === 'approved' ? 'Leave request submitted and auto-approved!' : 'Leave request submitted successfully.');
    }

    public function approve(LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $adminEmployee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $rules = $leaveRequest->leaveType->rules ?? [];
        $workflowLevel = $rules['approval']['workflow_level'] ?? '1_level';

        if ($workflowLevel === '2_level' && $leaveRequest->current_level === '1') {
            // First level approved, advance to level 2
            $leaveRequest->update([
                'current_level' => '2'
            ]);
            return redirect()->back()->with('success', 'First level approval recorded. Pending final second level approval.');
        }

        // Final approval (level 1 of a 1_level workflow, or level 2 of a 2_level workflow)
        $leaveRequest->update([
            'status' => 'approved',
            'current_level' => 'approved',
            'approved_by' => $adminEmployee ? $adminEmployee->id : null
        ]);

        // Deduct balance on approval
        $leaveType = $leaveRequest->leaveType;
        $isPaid = strtolower($leaveType->type) === 'paid';
        $isLimited = empty($rules['accrual']['quota_type']) || $rules['accrual']['quota_type'] !== 'unlimited';

        if ($isPaid && $isLimited) {
            $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveType->id)
                ->first();
            if ($balance) {
                $balance->increment('used', floatval($leaveRequest->duration));
            }
        }

        return redirect()->back()->with('success', 'Leave request approved successfully.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);

        $leaveRequest->update([
            'status' => 'rejected',
            'current_level' => 'rejected',
            'rejection_reason' => $request->rejection_reason
        ]);

        return redirect()->back()->with('success', 'Leave request rejected.');
    }

    public function updateStatus(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'status' => 'required|string|in:approved,rejected,unauthorized,unpaid',
            'rejection_reason' => 'nullable|string|max:500'
        ]);

        $status = $request->input('status');
        $adminEmployee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        // 1. If it was already approved/rejected/unauthorized/unpaid and we are changing it:
        // Let's first restore the balance if it was previously deducted as approved/paid!
        $oldStatus = $leaveRequest->status;
        $rules = $leaveRequest->leaveType->rules ?? [];
        $isPaid = strtolower($leaveRequest->leaveType->type) === 'paid';
        $isLimited = empty($rules['accrual']['quota_type']) || $rules['accrual']['quota_type'] !== 'unlimited';

        if ($oldStatus === 'approved' && $isPaid && $isLimited) {
            $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->first();
            if ($balance) {
                $balance->decrement('used', floatval($leaveRequest->duration));
            }
        }

        // 2. Apply new status logic
        if ($status === 'approved') {
            $workflowLevel = $rules['approval']['workflow_level'] ?? '1_level';
            if ($workflowLevel === '2_level' && $leaveRequest->current_level === '1') {
                $leaveRequest->update([
                    'current_level' => '2'
                ]);
                return redirect()->back()->with('success', 'First level approval recorded. Pending final second level approval.');
            }

            $leaveRequest->update([
                'status' => 'approved',
                'current_level' => 'approved',
                'approved_by' => $adminEmployee ? $adminEmployee->id : null,
                'rejection_reason' => null
            ]);

            // Deduct balance
            if ($isPaid && $isLimited) {
                $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                    ->where('leave_type_id', $leaveRequest->leave_type_id)
                    ->first();
                if ($balance) {
                    $balance->increment('used', floatval($leaveRequest->duration));
                }
            }
        } elseif ($status === 'rejected') {
            $leaveRequest->update([
                'status' => 'rejected',
                'current_level' => 'rejected',
                'rejection_reason' => $request->input('rejection_reason', 'Rejected by Admin')
            ]);
        } else {
            // unauthorized or unpaid
            $leaveRequest->update([
                'status' => $status,
                'current_level' => $status,
                'approved_by' => $adminEmployee ? $adminEmployee->id : null,
                'rejection_reason' => null
            ]);
        }

        return redirect()->back()->with('success', 'Leave request status updated to ' . ucfirst($status) . ' successfully.');
    }
}
