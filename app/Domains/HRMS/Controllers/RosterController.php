<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ShiftRoster;
use App\Domains\HRMS\Repositories\RosterRepositoryInterface;
use App\Domains\Production\Models\ProductionShift;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RosterController extends Controller
{
    public function __construct(
        private readonly RosterRepositoryInterface $rosterRepository
    ) {}

    public function index(Request $request): View
    {
        $data = $this->rosterRepository->getIndexData($request->all());

        return view('modules.hrms.roster.index', $data);
    }

    public function storeShift(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id'            => 'required|exists:companies,id',
            'name'                  => 'required|string|max:255',
            'code'                  => 'required|string|max:50|unique:production_shifts,code',
            'start_time'            => 'required|date_format:H:i',
            'end_time'              => 'required|date_format:H:i',
            'break_minutes'         => 'nullable|integer|min:0',
            'grace_period_minutes'  => 'nullable|integer|min:0',
            'overtime_allowed'      => 'nullable|boolean',
            'active'                => 'nullable|boolean',
            'description'           => 'nullable|string',
        ]);

        $validated['overtime_allowed'] = ($request->overtime_allowed === '1' || $request->overtime_allowed === 1 || $request->overtime_allowed === true);
        $validated['active']           = ($request->active === '1' || $request->active === 1 || $request->active === true);

        $this->rosterRepository->storeShift($validated);

        return redirect()->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', 'Shift created successfully.');
    }

    public function updateShift(Request $request, ProductionShift $shift): RedirectResponse
    {
        $validated = $request->validate([
            'company_id'           => 'required|exists:companies,id',
            'name'                 => 'required|string|max:255',
            'code'                 => ['required', 'string', 'max:50', Rule::unique('production_shifts', 'code')->ignore($shift->id)],
            'start_time'           => 'required',
            'end_time'             => 'required',
            'break_minutes'        => 'nullable|integer|min:0',
            'grace_period_minutes' => 'nullable|integer|min:0',
            'overtime_allowed'     => 'nullable|boolean',
            'active'               => 'nullable|boolean',
            'description'          => 'nullable|string',
        ]);

        $validated['overtime_allowed'] = ($request->overtime_allowed === '1' || $request->overtime_allowed === 1 || $request->overtime_allowed === true);
        $validated['active']           = ($request->active === '1' || $request->active === 1 || $request->active === true);

        $this->rosterRepository->updateShift($shift, $validated);

        return redirect()->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', 'Shift updated successfully.');
    }

    public function destroyShift(ProductionShift $shift): RedirectResponse
    {
        $this->rosterRepository->deleteShift($shift);

        return redirect()->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', 'Shift deleted successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Roster Assignment
    // ─────────────────────────────────────────────────────────────────────────

    public function assign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_ids'             => 'nullable|array',
            'employee_ids.*'           => 'exists:employees,id',
            'bulk_company_ids'         => 'nullable|array',
            'bulk_company_ids.*'       => 'exists:companies,id',
            'bulk_business_unit_ids'   => 'nullable|array',
            'bulk_business_unit_ids.*' => 'exists:business_units,id',
            'bulk_branch_ids'          => 'nullable|array',
            'bulk_branch_ids.*'        => 'exists:branches,id',
            'bulk_department_ids'      => 'nullable|array',
            'bulk_department_ids.*'    => 'exists:departments,id',
            'bulk_designation_ids'     => 'nullable|array',
            'bulk_designation_ids.*'   => 'exists:designations,id',
            'shift_id'                 => 'nullable|exists:production_shifts,id',
            'start_date'               => 'required|date',
            'end_date'                 => 'required|date|after_or_equal:start_date',
            'status'                   => 'required|string',
            'notes'                    => 'nullable|string',
        ]);

        $employeeIds = $validated['employee_ids'] ?? [];
        $shiftId     = $validated['shift_id'] ?? null;
        $startDate   = Carbon::parse($validated['start_date']);
        $endDate     = Carbon::parse($validated['end_date']);
        $status      = $validated['status'] ?? 'scheduled';
        $notes       = $validated['notes'] ?? null;

        if (empty($employeeIds)) {
            $query = Employee::query()->where('status', true);
            if ($request->filled('bulk_company_ids'))       { $query->whereIn('company_id', $validated['bulk_company_ids']); }
            if ($request->filled('bulk_business_unit_ids')) { $query->whereIn('business_unit_id', $validated['bulk_business_unit_ids']); }
            if ($request->filled('bulk_branch_ids'))        { $query->whereIn('branch_id', $validated['bulk_branch_ids']); }
            if ($request->filled('bulk_department_ids'))    { $query->whereIn('department_id', $validated['bulk_department_ids']); }
            if ($request->filled('bulk_designation_ids'))   { $query->whereIn('designation_id', $validated['bulk_designation_ids']); }
            $employeeIds = $query->pluck('id')->toArray();
        }

        if (empty($employeeIds)) {
            return redirect()->back()->with('error', 'No matching active employees found for shift assignment.');
        }

        $period   = CarbonPeriod::create($startDate, $endDate);
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        foreach ($employeeIds as $employeeId) {
            foreach ($period as $date) {
                ShiftRoster::updateOrCreate(
                    ['tenant_id' => $tenantId, 'employee_id' => $employeeId, 'date' => $date->format('Y-m-d')],
                    ['shift_id' => $shiftId, 'status' => $status, 'notes' => $notes]
                );
            }
        }

        return redirect()->route('hrms.roster.index', ['tab' => 'roster', 'start_date' => $validated['start_date']])
            ->with('success', 'Shifts assigned successfully.');
    }

    public function updateCell(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date'        => 'required|date',
            'shift_id'    => 'nullable',
            'value'       => 'nullable|string',
        ]);

        $value = $validated['value'] ?? null;
        if ($value === null) {
            $value = isset($validated['shift_id']) ? (string)$validated['shift_id'] : 'default';
        }

        if ($value === 'default' || $value === '') {
            ShiftRoster::where(['employee_id' => $validated['employee_id'], 'date' => $validated['date']])->delete();
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Shift roster cell reset to default.']);
            }
            return redirect()->back()->with('success', 'Shift roster cell reset to default.');
        }

        $shiftId  = $value === 'off' ? null : (int)$value;
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        ShiftRoster::updateOrCreate(
            ['tenant_id' => $tenantId, 'employee_id' => $validated['employee_id'], 'date' => $validated['date']],
            ['shift_id' => $shiftId, 'status' => 'scheduled']
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Shift roster cell updated.']);
        }

        return redirect()->back()->with('success', 'Shift roster cell updated.');
    }

    public function updateWeeklyPattern(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'day_of_week' => 'required|integer|between:0,6',
            'value'       => 'nullable|string',
        ]);

        $employee  = Employee::findOrFail($validated['employee_id']);
        $dayOfWeek = (int)$validated['day_of_week'];
        $val       = $validated['value'] ?? null;
        $pattern   = $employee->weekly_pattern ?: [];

        if ($val === '' || $val === null || $val === 'default') {
            unset($pattern[$dayOfWeek]);
        } else {
            $pattern[$dayOfWeek] = $val === 'off' ? 'off' : (int)$val;
        }

        ksort($pattern);
        $employee->update(['weekly_pattern' => $pattern]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Weekly pattern updated.']);
        }

        return redirect()->back()->with('success', 'Weekly pattern updated.');
    }

    public function assignWeekly(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'pattern'        => 'required|array',
        ]);

        foreach ($validated['employee_ids'] as $empId) {
            $employee = Employee::find($empId);
            if ($employee) {
                $pattern = [];
                foreach ($validated['pattern'] as $day => $shiftId) {
                    $pattern[(int)$day] = $shiftId === 'off' ? 'off' : (int)$shiftId;
                }
                ksort($pattern);
                $employee->update(['weekly_pattern' => $pattern]);
            }
        }

        return redirect()->back()->with('success', 'Weekly shift pattern assigned successfully.');
    }

    public function clearWeekly(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        Employee::whereIn('id', $validated['employee_ids'])->update(['weekly_pattern' => null]);

        return redirect()->back()->with('success', 'Weekly patterns cleared.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_ids'             => 'nullable|array',
            'employee_ids.*'           => 'exists:employees,id',
            'bulk_company_ids'         => 'nullable|array',
            'bulk_company_ids.*'       => 'exists:companies,id',
            'bulk_business_unit_ids'   => 'nullable|array',
            'bulk_business_unit_ids.*' => 'exists:business_units,id',
            'bulk_branch_ids'          => 'nullable|array',
            'bulk_branch_ids.*'        => 'exists:branches,id',
            'bulk_department_ids'      => 'nullable|array',
            'bulk_department_ids.*'    => 'exists:departments,id',
            'bulk_designation_ids'     => 'nullable|array',
            'bulk_designation_ids.*'   => 'exists:designations,id',
            'start_date'               => 'required|date',
            'end_date'                 => 'required|date|after_or_equal:start_date',
        ]);

        $employeeIds = $validated['employee_ids'] ?? [];
        $startDate   = $validated['start_date'];
        $endDate     = $validated['end_date'];

        if (empty($employeeIds)) {
            $query = Employee::query()->where('status', true);
            if ($request->filled('bulk_company_ids'))       { $query->whereIn('company_id', $validated['bulk_company_ids']); }
            if ($request->filled('bulk_business_unit_ids')) { $query->whereIn('business_unit_id', $validated['bulk_business_unit_ids']); }
            if ($request->filled('bulk_branch_ids'))        { $query->whereIn('branch_id', $validated['bulk_branch_ids']); }
            if ($request->filled('bulk_department_ids'))    { $query->whereIn('department_id', $validated['bulk_department_ids']); }
            if ($request->filled('bulk_designation_ids'))   { $query->whereIn('designation_id', $validated['bulk_designation_ids']); }
            $employeeIds = $query->pluck('id')->toArray();
        }

        if (empty($employeeIds)) {
            return redirect()->back()->with('error', 'No matching active employees found for roster clearance.');
        }

        ShiftRoster::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->delete();

        return redirect()->route('hrms.roster.index', ['tab' => 'roster', 'start_date' => $startDate])
            ->with('success', 'Roster entries cleared successfully.');
    }

    // Legacy aliases
    public function assignShift(Request $request): RedirectResponse
    {
        return $this->assign($request);
    }

    public function clearRoster(Request $request): RedirectResponse
    {
        return $this->clear($request);
    }
}
