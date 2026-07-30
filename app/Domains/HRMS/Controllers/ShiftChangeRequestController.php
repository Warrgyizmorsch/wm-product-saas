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
        $validated = $request->validate([
            'employee_id'        => 'required|exists:employees,id',
            'type'               => 'required|string|in:temporary,permanent,recurring',
            'start_date'         => 'required|date',
            'end_date'           => 'nullable|required_if:type,temporary|date|after_or_equal:start_date',
            'recurring_days'     => 'nullable|required_if:type,recurring|array',
            'recurring_days.*'   => 'integer|min:0|max:6',
            'requested_shift_id' => 'nullable|exists:production_shifts,id',
            'reason'             => 'required|string|max:1000',
            'attachment'         => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        if (!$employee) {
            return redirect()->back()->with('error', __('hrms.shift_change.profile_not_found'));
        }

        // Set current shift
        $targetDate = Carbon::parse($validated['start_date']);
        $currentShift = $employee->resolveShiftForDate($targetDate);
        $currentShiftId = $currentShift ? $currentShift->id : null;

        // Prevent requesting same shift
        $requestedShiftId = $validated['requested_shift_id'] ?? null;
        if ($requestedShiftId && (int)$requestedShiftId === (int)$currentShiftId) {
            return redirect()->back()->with('error', __('hrms.shift_change.same_shift_error'))->withInput();
        }
        if (!$requestedShiftId && !$currentShiftId) {
            return redirect()->back()->with('error', __('hrms.shift_change.same_day_off_error'))->withInput();
        }

        $validated['current_shift_id'] = $currentShiftId;
        $validated['employee_id'] = $employee->id;
        $validated['company_id']  = $employee->company_id;

        $this->shiftChangeRepository->storeShiftChangeRequest($validated, $request);

        return redirect()->back()->with('success', __('hrms.shift_change.submitted_successfully'));
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
        if ($shiftChangeRequest->status === 'cancelled') {
            return redirect()->back()->with('error', __('hrms.shift_change.cancelled_status_error'));
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

        return redirect()->back()->with('success', __('hrms.shift_change.status_updated'));
    }

    public function destroy(ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        if ($shiftChangeRequest->status === 'approved') {
            return redirect()->back()->with('error', __('hrms.shift_change.approved_no_delete'));
        }

        $shiftChangeRequest->delete();

        return redirect()->back()->with('success', __('hrms.shift_change.deleted_successfully'));
    }
}
