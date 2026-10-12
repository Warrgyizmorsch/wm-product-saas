<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ShiftRoster;
use App\Domains\HRMS\Repositories\RosterRepositoryInterface;
use App\Domains\Production\Models\ProductionShift;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
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
        $this->authorize('viewAny', \App\Domains\HRMS\Models\Roster::class);

        $data = $this->rosterRepository->getIndexData($request->all());

        return view('modules.hrms.roster.index', $data);
    }

    public function storeShift(Request $request): RedirectResponse
    {
        $this->authorize('create', \App\Domains\HRMS\Models\Roster::class);

        $tenantId = auth()->user()?->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : 1) ?? 1;

        $validated = $request->validate([
            'company_id'            => 'nullable|exists:companies,id',
            'name'                  => 'required|string|max:255',
            'code'                  => [
                'required',
                'string',
                'max:50',
                Rule::unique('production_shifts', 'code')->where(function ($query) use ($tenantId, $request) {
                    $q = $query->where('tenant_id', $tenantId);
                    if ($request->filled('company_id')) {
                        $q->where('company_id', $request->company_id);
                    } else {
                        $q->whereNull('company_id');
                    }
                    return $q;
                }),
            ],
            'start_time'            => 'required',
            'end_time'              => 'required',
            'break_minutes'         => 'nullable|integer|min:0',
            'grace_period_minutes'  => 'nullable|integer|min:0',
            'overtime_allowed'      => 'nullable|boolean',
            'active'                => 'nullable|boolean',
            'description'           => 'nullable|string',
        ]);

        $validated['tenant_id']        = $tenantId;
        $validated['company_id']       = $request->filled('company_id') ? (int) $request->company_id : null;
        $validated['break_minutes']    = (int) ($request->input('break_minutes', 0));
        $validated['overtime_allowed'] = ($request->overtime_allowed === '1' || $request->overtime_allowed === 1 || $request->overtime_allowed === true);
        $validated['active']           = ($request->active === '1' || $request->active === 1 || $request->active === true);

        $this->rosterRepository->storeShift($validated);

        return redirect()->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', __('hrms.roster.shift_created'));
    }

    public function updateShift(Request $request, ProductionShift $shift): RedirectResponse
    {
        $this->authorize('update', \App\Domains\HRMS\Models\Roster::class);

        $tenantId = auth()->user()?->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : 1) ?? 1;

        $validated = $request->validate([
            'company_id'           => 'nullable|exists:companies,id',
            'name'                 => 'required|string|max:255',
            'code'                 => [
                'required',
                'string',
                'max:50',
                Rule::unique('production_shifts', 'code')
                    ->where(function ($query) use ($tenantId, $request) {
                        $q = $query->where('tenant_id', $tenantId);
                        if ($request->filled('company_id')) {
                            $q->where('company_id', $request->company_id);
                        } else {
                            $q->whereNull('company_id');
                        }
                        return $q;
                    })
                    ->ignore($shift->id),
            ],
            'start_time'           => 'required',
            'end_time'             => 'required',
            'break_minutes'        => 'nullable|integer|min:0',
            'grace_period_minutes' => 'nullable|integer|min:0',
            'overtime_allowed'     => 'nullable|boolean',
            'active'               => 'nullable|boolean',
            'description'          => 'nullable|string',
        ]);

        $validated['company_id']       = $request->filled('company_id') ? (int) $request->company_id : null;
        $validated['break_minutes']    = (int) ($request->input('break_minutes', 0));
        $validated['overtime_allowed'] = ($request->overtime_allowed === '1' || $request->overtime_allowed === 1 || $request->overtime_allowed === true);
        $validated['active']           = ($request->active === '1' || $request->active === 1 || $request->active === true);

        $this->rosterRepository->updateShift($shift, $validated);

        return redirect()->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', __('hrms.roster.shift_updated'));
    }

    public function destroyShift(ProductionShift $shift): RedirectResponse
    {
        $this->authorize('delete', \App\Domains\HRMS\Models\Roster::class);

        $this->rosterRepository->deleteShift($shift);

        return redirect()->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', __('hrms.roster.shift_deleted'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Roster Assignment
    // ─────────────────────────────────────────────────────────────────────────

    public function assign(Request $request): RedirectResponse
    {
        $this->authorizeHrms('hrms.rosters.create');

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
            'status'                   => 'nullable|string',
            'notes'                    => 'nullable|string',
        ]);

        $employeeIds = $validated['employee_ids'] ?? [];
        $shiftId     = $validated['shift_id'] ?? null;
        $startDate   = Carbon::parse($validated['start_date']);
        $endDate     = Carbon::parse($validated['end_date']);
        $status      = !empty($validated['status']) ? $validated['status'] : 'scheduled';
        $notes       = $validated['notes'] ?? null;

        if (empty($employeeIds)) {
            $query = Employee::query()->where('status', true);
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyEmployeeScope($query, auth()->user());
            if ($request->filled('bulk_company_ids'))       { $query->whereIn('company_id', $validated['bulk_company_ids']); }
            if ($request->filled('bulk_business_unit_ids')) { $query->whereIn('business_unit_id', $validated['bulk_business_unit_ids']); }
            if ($request->filled('bulk_branch_ids'))        { $query->whereIn('branch_id', $validated['bulk_branch_ids']); }
            if ($request->filled('bulk_department_ids'))    { $query->whereIn('department_id', $validated['bulk_department_ids']); }
            if ($request->filled('bulk_designation_ids'))   { $query->whereIn('designation_id', $validated['bulk_designation_ids']); }
            $employeeIds = $query->pluck('id')->toArray();
        }

        if (empty($employeeIds)) {
            return redirect()->back()->with('error', __('hrms.roster.no_emp_found'));
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
            ->with('success', __('hrms.roster.shifts_assigned'));
    }

    public function updateCell(Request $request)
    {
        $this->authorizeHrms('hrms.rosters.update');

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
                return response()->json(['success' => true, 'message' => __('hrms.roster.cell_reset')]);
            }
            return redirect()->back()->with('success', __('hrms.roster.cell_reset'));
        }

        $shiftId  = $value === 'off' ? null : (int)$value;
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        ShiftRoster::updateOrCreate(
            ['tenant_id' => $tenantId, 'employee_id' => $validated['employee_id'], 'date' => $validated['date']],
            ['shift_id' => $shiftId, 'status' => 'scheduled']
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => __('hrms.roster.cell_updated')]);
        }

        return redirect()->back()->with('success', __('hrms.roster.cell_updated'));
    }

    public function updateWeeklyPattern(Request $request)
    {
        $this->authorizeHrms('hrms.rosters.update');

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
            return response()->json(['success' => true, 'message' => __('hrms.roster.weekly_pattern_updated')]);
        }

        return redirect()->back()->with('success', __('hrms.roster.weekly_pattern_updated'));
    }

    public function assignWeekly(Request $request): RedirectResponse
    {
        $this->authorizeHrms('hrms.rosters.update');

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
            'days'                     => 'nullable|array',
            'days.*'                   => 'integer|between:0,6',
            'shift_id'                 => 'nullable',
            'pattern'                  => 'nullable|array',
        ]);

        $employeeIds = $validated['employee_ids'] ?? [];

        if (empty($employeeIds)) {
            $query = Employee::query()->where('status', true);
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyEmployeeScope($query, auth()->user());
            if ($request->filled('bulk_company_ids'))       { $query->whereIn('company_id', $validated['bulk_company_ids']); }
            if ($request->filled('bulk_business_unit_ids')) { $query->whereIn('business_unit_id', $validated['bulk_business_unit_ids']); }
            if ($request->filled('bulk_branch_ids'))        { $query->whereIn('branch_id', $validated['bulk_branch_ids']); }
            if ($request->filled('bulk_department_ids'))    { $query->whereIn('department_id', $validated['bulk_department_ids']); }
            if ($request->filled('bulk_designation_ids'))   { $query->whereIn('designation_id', $validated['bulk_designation_ids']); }
            $employeeIds = $query->pluck('id')->toArray();
        }

        if (empty($employeeIds)) {
            return redirect()->route('hrms.roster.index', ['tab' => 'weekly_patterns'])
                ->with('error', __('hrms.roster.no_emp_found'));
        }

        $employees     = Employee::whereIn('id', $employeeIds)->get();
        $days          = $validated['days'] ?? [];
        $shiftId       = $validated['shift_id'] ?? null;
        $directPattern = $validated['pattern'] ?? null;

        foreach ($employees as $employee) {
            $pattern = is_array($employee->weekly_pattern) ? $employee->weekly_pattern : [];

            if (!empty($directPattern)) {
                foreach ($directPattern as $d => $sId) {
                    if ($sId === '' || $sId === null || $sId === 'default') {
                        unset($pattern[(int)$d]);
                    } else {
                        $pattern[(int)$d] = $sId === 'off' ? 'off' : (int)$sId;
                    }
                }
            } elseif (!empty($days)) {
                foreach ($days as $day) {
                    $dayInt = (int)$day;
                    if ($shiftId === '' || $shiftId === null || $shiftId === 'default') {
                        unset($pattern[$dayInt]);
                    } else {
                        $pattern[$dayInt] = $shiftId === 'off' ? 'off' : (int)$shiftId;
                    }
                }
            }

            ksort($pattern);
            $employee->update(['weekly_pattern' => !empty($pattern) ? $pattern : null]);
        }

        return redirect()->route('hrms.roster.index', ['tab' => 'weekly_patterns'])
            ->with('success', __('hrms.roster.weekly_shift_pattern_assigned'));
    }

    public function clearWeekly(Request $request): RedirectResponse
    {
        $this->authorizeHrms('hrms.rosters.update');

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
        ]);

        $employeeIds = $validated['employee_ids'] ?? [];

        if (empty($employeeIds)) {
            $query = Employee::query()->where('status', true);
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyEmployeeScope($query, auth()->user());
            if ($request->filled('bulk_company_ids'))       { $query->whereIn('company_id', $validated['bulk_company_ids']); }
            if ($request->filled('bulk_business_unit_ids')) { $query->whereIn('business_unit_id', $validated['bulk_business_unit_ids']); }
            if ($request->filled('bulk_branch_ids'))        { $query->whereIn('branch_id', $validated['bulk_branch_ids']); }
            if ($request->filled('bulk_department_ids'))    { $query->whereIn('department_id', $validated['bulk_department_ids']); }
            if ($request->filled('bulk_designation_ids'))   { $query->whereIn('designation_id', $validated['bulk_designation_ids']); }
            $employeeIds = $query->pluck('id')->toArray();
        }

        if (!empty($employeeIds)) {
            Employee::whereIn('id', $employeeIds)->update(['weekly_pattern' => null]);
        }

        return redirect()->route('hrms.roster.index', ['tab' => 'weekly_patterns'])
            ->with('success', __('hrms.roster.weekly_patterns_cleared'));
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->authorizeHrms('hrms.rosters.update');

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
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyEmployeeScope($query, auth()->user());
            if ($request->filled('bulk_company_ids'))       { $query->whereIn('company_id', $validated['bulk_company_ids']); }
            if ($request->filled('bulk_business_unit_ids')) { $query->whereIn('business_unit_id', $validated['bulk_business_unit_ids']); }
            if ($request->filled('bulk_branch_ids'))        { $query->whereIn('branch_id', $validated['bulk_branch_ids']); }
            if ($request->filled('bulk_department_ids'))    { $query->whereIn('department_id', $validated['bulk_department_ids']); }
            if ($request->filled('bulk_designation_ids'))   { $query->whereIn('designation_id', $validated['bulk_designation_ids']); }
            $employeeIds = $query->pluck('id')->toArray();
        }

        if (empty($employeeIds)) {
            return redirect()->back()->with('error', __('hrms.roster.no_emp_found'));
        }

        ShiftRoster::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->delete();

        return redirect()->route('hrms.roster.index', ['tab' => 'roster', 'start_date' => $startDate])
            ->with('success', __('hrms.roster.entries_cleared'));
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

    private function authorizeHrms(string $permission): void
    {
        abort_unless(
            app(AccessService::class)->allows(auth()->user(), $permission, [
                'tenant_id' => auth()->user()?->tenant_id,
            ]),
            403
        );
    }
}
