<?php

namespace App\Domains\HRMS\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ShiftRoster;
use App\Domains\Production\Models\ProductionShift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RosterController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $tab = $request->query('tab', 'shifts');

        // Fetch lists for filters and assignment modals
        $companies = Company::all();
        $businessUnits = BusinessUnit::with('company')->get();
        $branches = Branch::with('businessUnit')->get();
        $departments = Department::with('company')->get();
        $designations = Designation::all();

        // 1. Shift Master Data (used in Shifts tab)
        $shiftSearch = $request->string('shift_search')->trim()->value();
        $shiftSort = $request->string('shift_sort')->value() ?: 'name_asc';
        $shiftStatus = $request->filled('shift_status') ? $request->string('shift_status')->value() : null;
        $shiftOvertime = $request->filled('shift_overtime') ? $request->string('shift_overtime')->value() : null;
        $shiftCompanyId = $request->integer('shift_company_id') ?: null;

        $shiftsQuery = ProductionShift::with('company');
        if ($shiftSearch !== '') {
            $shiftsQuery->where(function ($query) use ($shiftSearch): void {
                $query->where('name', 'like', "%{$shiftSearch}%")
                    ->orWhere('code', 'like', "%{$shiftSearch}%")
                    ->orWhere('start_time', 'like', "%{$shiftSearch}%")
                    ->orWhere('end_time', 'like', "%{$shiftSearch}%");
            });
        }

        if ($shiftStatus !== null && $shiftStatus !== '') {
            $shiftsQuery->where('active', $shiftStatus === '1');
        }

        if ($shiftOvertime !== null && $shiftOvertime !== '') {
            $shiftsQuery->where('overtime_allowed', $shiftOvertime === '1');
        }

        if ($shiftCompanyId) {
            $shiftsQuery->where('company_id', $shiftCompanyId);
        }

        switch ($shiftSort) {
            case 'name_desc':
                $shiftsQuery->orderBy('name', 'desc');
                break;
            case 'code_asc':
                $shiftsQuery->orderBy('code', 'asc');
                break;
            case 'code_desc':
                $shiftsQuery->orderBy('code', 'desc');
                break;
            case 'start_asc':
                $shiftsQuery->orderBy('start_time', 'asc');
                break;
            case 'start_desc':
                $shiftsQuery->orderBy('start_time', 'desc');
                break;
            case 'name_asc':
            default:
                $shiftsQuery->orderBy('name', 'asc');
                break;
        }

        $shifts = $shiftsQuery->paginate(10, ['*'], 'shift_page')->withQueryString();
        $activeShifts = ProductionShift::where('active', true)->get();

        // 2. Roster Scheduling Matrix Data
        $selectedCompanyId = $request->integer('company_id') ?: null;
        $selectedDepartmentId = $request->integer('department_id') ?: null;
        $selectedDesignationId = $request->integer('designation_id') ?: null;
        $search = $request->string('search')->trim()->value();
        $sortBy = $request->string('sort', 'name-asc')->value();

        // Start Date resolution (defaults to today)
        $startDateStr = $request->string('start_date')->value();
        $startDate = $startDateStr ? Carbon::parse($startDateStr) : Carbon::today();
        $endDate = $startDate->copy()->addDays(6);

        // Date collection for grid columns
        $period = CarbonPeriod::create($startDate, $endDate);
        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date;
        }

        // Fetch filtered employees
        $employeesQuery = Employee::query()->where('employees.status', true);
        if ($selectedCompanyId) {
            $employeesQuery->where('employees.company_id', $selectedCompanyId);
        }
        if ($selectedDepartmentId) {
            $employeesQuery->where('employees.department_id', $selectedDepartmentId);
        }
        if ($selectedDesignationId) {
            $employeesQuery->where('employees.designation_id', $selectedDesignationId);
        }
        if ($search !== '') {
            $employeesQuery->where(function ($query) use ($search): void {
                $query->where('employees.full_name', 'like', "%{$search}%")
                    ->orWhereHas('designation', function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }
        switch ($sortBy) {
            case 'name-desc':
                $employeesQuery->orderBy('employees.full_name', 'desc');
                break;
            case 'designation':
            case 'designation-asc':
                $employeesQuery->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
                    ->orderBy('designations.name', 'asc');
                break;
            case 'designation-desc':
                $employeesQuery->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
                    ->orderBy('designations.name', 'desc');
                break;
            case 'name-asc':
            default:
                $employeesQuery->orderBy('employees.full_name', 'asc');
                break;
        }
        $employees = $employeesQuery->with(['department', 'designation', 'shift'])->paginate(10, ['employees.*'], 'roster_page')->withQueryString();

        // Fetch shift rosters in scope
        $rosters = ShiftRoster::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        // Matrix map: $rosterMap[employee_id][date_string] = ShiftRoster
        $rosterMap = [];
        foreach ($rosters as $roster) {
            $dateStr = $roster->date->format('Y-m-d');
            $rosterMap[$roster->employee_id][$dateStr] = $roster;
        }

        return view('modules.hrms.roster.index', compact(
            'tab', 'companies', 'businessUnits', 'branches', 'departments', 'designations', 'shifts', 'activeShifts',
            'selectedCompanyId', 'selectedDepartmentId', 'selectedDesignationId', 'search', 'sortBy', 'startDate', 'dates',
            'employees', 'rosterMap', 'shiftSearch', 'shiftSort', 'shiftStatus', 'shiftOvertime'
        ));
    }

    public function assign(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:employees,id',
            'bulk_company_ids' => 'nullable|array',
            'bulk_company_ids.*' => 'exists:companies,id',
            'bulk_business_unit_ids' => 'nullable|array',
            'bulk_business_unit_ids.*' => 'exists:business_units,id',
            'bulk_branch_ids' => 'nullable|array',
            'bulk_branch_ids.*' => 'exists:branches,id',
            'bulk_department_ids' => 'nullable|array',
            'bulk_department_ids.*' => 'exists:departments,id',
            'bulk_designation_ids' => 'nullable|array',
            'bulk_designation_ids.*' => 'exists:designations,id',
            'shift_id' => 'nullable|exists:production_shifts,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $employeeIds = $request->input('employee_ids', []);
        $shiftId = $request->input('shift_id');
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));
        $status = $request->input('status', 'scheduled');
        $notes = $request->input('notes');

        // Resolve employee list by group arrays if no individual employee was chosen
        if (empty($employeeIds)) {
            $query = Employee::query()->where('status', true);
            if ($request->filled('bulk_company_ids')) {
                $query->whereIn('company_id', $request->input('bulk_company_ids'));
            }
            if ($request->filled('bulk_business_unit_ids')) {
                $query->whereIn('business_unit_id', $request->input('bulk_business_unit_ids'));
            }
            if ($request->filled('bulk_branch_ids')) {
                $query->whereIn('branch_id', $request->input('bulk_branch_ids'));
            }
            if ($request->filled('bulk_department_ids')) {
                $query->whereIn('department_id', $request->input('bulk_department_ids'));
            }
            if ($request->filled('bulk_designation_ids')) {
                $query->whereIn('designation_id', $request->input('bulk_designation_ids'));
            }
            $employeeIds = $query->pluck('id')->toArray();
        }

        if (empty($employeeIds)) {
            return redirect()
                ->back()
                ->with('error', 'No employees found matching the selected filters.');
        }

        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($employeeIds as $employeeId) {
            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                
                ShiftRoster::updateOrCreate(
                    [
                        'tenant_id' => tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id(),
                        'employee_id' => $employeeId,
                        'date' => $dateStr,
                    ],
                    [
                        'shift_id' => $shiftId,
                        'status' => $status,
                        'notes' => $notes,
                    ]
                );
            }
        }

        return redirect()
            ->route('hrms.roster.index', [
                'tab' => 'roster',
                'company_id' => $request->input('company_id'),
                'department_id' => $request->input('department_id'),
                'start_date' => $request->input('start_date'),
            ])
            ->with('success', 'Shifts assigned successfully.');
    }

    public function updateCell(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'shift_id' => 'nullable|exists:production_shifts,id',
        ]);

        $roster = ShiftRoster::updateOrCreate(
            [
                'tenant_id' => tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id(),
                'employee_id' => $request->input('employee_id'),
                'date' => $request->input('date'),
            ],
            [
                'shift_id' => $request->input('shift_id'),
                'status' => 'scheduled',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Roster cell updated successfully.',
            'roster' => $roster
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:employees,id',
            'bulk_company_ids' => 'nullable|array',
            'bulk_company_ids.*' => 'exists:companies,id',
            'bulk_business_unit_ids' => 'nullable|array',
            'bulk_business_unit_ids.*' => 'exists:business_units,id',
            'bulk_branch_ids' => 'nullable|array',
            'bulk_branch_ids.*' => 'exists:branches,id',
            'bulk_department_ids' => 'nullable|array',
            'bulk_department_ids.*' => 'exists:departments,id',
            'bulk_designation_ids' => 'nullable|array',
            'bulk_designation_ids.*' => 'exists:designations,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $employeeIds = $request->input('employee_ids', []);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (empty($employeeIds)) {
            $query = Employee::query()->where('status', true);
            if ($request->filled('bulk_company_ids')) {
                $query->whereIn('company_id', $request->input('bulk_company_ids'));
            }
            if ($request->filled('bulk_business_unit_ids')) {
                $query->whereIn('business_unit_id', $request->input('bulk_business_unit_ids'));
            }
            if ($request->filled('bulk_branch_ids')) {
                $query->whereIn('branch_id', $request->input('bulk_branch_ids'));
            }
            if ($request->filled('bulk_department_ids')) {
                $query->whereIn('department_id', $request->input('bulk_department_ids'));
            }
            if ($request->filled('bulk_designation_ids')) {
                $query->whereIn('designation_id', $request->input('bulk_designation_ids'));
            }
            $employeeIds = $query->pluck('id')->toArray();
        }

        if (empty($employeeIds)) {
            return redirect()
                ->back()
                ->with('error', 'No employees found matching the filters.');
        }

        ShiftRoster::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->delete();

        return redirect()
            ->route('hrms.roster.index', [
                'company_id' => $request->input('company_id'),
                'department_id' => $request->input('department_id'),
                'start_date' => $request->input('start_date'),
            ])
            ->with('success', 'Roster entries cleared successfully.');
    }

    public function storeShift(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'code' => [
                'required',
                'max:50',
                Rule::unique('production_shifts', 'code')
                    ->where(function ($query) use ($request) {
                        return $query->where('tenant_id', tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id())
                            ->where('company_id', $request->company_id);
                    })
            ],
            'company_id' => 'nullable|exists:companies,id',
            'start_time' => 'required',
            'end_time' => 'required',
            'break_minutes' => 'required|integer|min:0',
            'overtime_allowed' => 'required|boolean',
            'active' => 'required|boolean',
        ]);

        ProductionShift::create([
            'tenant_id' => tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id(),
            'company_id' => $request->company_id ?: null,
            'name' => $request->name,
            'code' => $request->code,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'break_minutes' => $request->break_minutes,
            'overtime_allowed' => $request->overtime_allowed,
            'active' => $request->active,
        ]);

        return redirect()
            ->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', 'Shift created successfully.');
    }

    public function updateShift(Request $request, ProductionShift $shift)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'code' => [
                'required',
                'max:50',
                Rule::unique('production_shifts', 'code')
                    ->where(function ($query) use ($request) {
                        return $query->where('tenant_id', tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id())
                            ->where('company_id', $request->company_id);
                    })
                    ->ignore($shift->id)
            ],
            'company_id' => 'nullable|exists:companies,id',
            'start_time' => 'required',
            'end_time' => 'required',
            'break_minutes' => 'required|integer|min:0',
            'overtime_allowed' => 'required|boolean',
            'active' => 'required|boolean',
        ]);

        $shift->update([
            'company_id' => $request->company_id ?: null,
            'name' => $request->name,
            'code' => $request->code,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'break_minutes' => $request->break_minutes,
            'overtime_allowed' => $request->overtime_allowed,
            'active' => $request->active,
        ]);

        return redirect()
            ->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', 'Shift updated successfully.');
    }

    public function destroyShift(ProductionShift $shift)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $shift->delete();
        return redirect()
            ->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', 'Shift deleted successfully.');
    }
}
