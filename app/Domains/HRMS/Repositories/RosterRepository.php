<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ShiftRoster;
use App\Domains\Production\Models\ProductionShift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class RosterRepository implements RosterRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        $tab = $inputs['tab'] ?? 'shifts';

        $companies = Company::all();
        $businessUnits = BusinessUnit::with('company')->get();
        $branches = Branch::with('businessUnit')->get();
        $departments = Department::with('company')->get();
        $designations = Designation::all();

        // 1. Shift Master Data
        $shiftSearch = trim((string) ($inputs['shift_search'] ?? ''));
        $shiftSort = !empty($inputs['shift_sort']) ? (string) $inputs['shift_sort'] : 'name_asc';
        $shiftStatus = isset($inputs['shift_status']) && $inputs['shift_status'] !== '' ? (string) $inputs['shift_status'] : null;
        $shiftOvertime = isset($inputs['shift_overtime']) && $inputs['shift_overtime'] !== '' ? (string) $inputs['shift_overtime'] : null;
        $shiftCompanyId = !empty($inputs['shift_company_id']) ? (int) $inputs['shift_company_id'] : null;

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
        $selectedCompanyId = !empty($inputs['company_id']) ? (int) $inputs['company_id'] : null;
        $selectedDepartmentId = !empty($inputs['department_id']) ? (int) $inputs['department_id'] : null;
        $selectedDesignationId = !empty($inputs['designation_id']) ? (int) $inputs['designation_id'] : null;
        $search = trim((string) ($inputs['search'] ?? ''));
        $sortBy = !empty($inputs['sort']) ? (string) $inputs['sort'] : 'name-asc';

        $startDateStr = $inputs['start_date'] ?? null;
        $startDate = $startDateStr ? Carbon::parse($startDateStr) : Carbon::today();

        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $dates[] = $startDate->copy()->addDays($i);
        }

        $endDate = $dates[6];

        $employeesQuery = Employee::with(['company', 'department', 'designation']);

        if ($selectedCompanyId) {
            $employeesQuery->where('company_id', $selectedCompanyId);
        }
        if ($selectedDepartmentId) {
            $employeesQuery->where('department_id', $selectedDepartmentId);
        }
        if ($selectedDesignationId) {
            $employeesQuery->where('designation_id', $selectedDesignationId);
        }
        if ($search !== '') {
            $employeesQuery->where(function ($q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        switch ($sortBy) {
            case 'name-desc':
                $employeesQuery->orderBy('full_name', 'desc');
                break;
            case 'id-asc':
                $employeesQuery->orderBy('employee_id', 'asc');
                break;
            case 'id-desc':
                $employeesQuery->orderBy('employee_id', 'desc');
                break;
            case 'name-asc':
            default:
                $employeesQuery->orderBy('full_name', 'asc');
                break;
        }

        $employees = $employeesQuery->paginate(10, ['*'], 'roster_page')->withQueryString();

        $employeeIds = $employees->pluck('id')->toArray();
        $rosters = ShiftRoster::with('shift')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->groupBy(fn ($item) => $item->employee_id . '_' . $item->date->format('Y-m-d'));

        // 3. Weekly Patterns Data
        $patternSearch = trim((string) ($inputs['pattern_search'] ?? ''));
        $patternSort = !empty($inputs['pattern_sort']) ? (string) $inputs['pattern_sort'] : 'name_asc';
        $patternCompanyId = !empty($inputs['pattern_company_id']) ? (int) $inputs['pattern_company_id'] : null;

        $weeklyPatterns = collect();

        return compact(
            'tab',
            'companies',
            'businessUnits',
            'branches',
            'departments',
            'designations',
            'shifts',
            'activeShifts',
            'employees',
            'rosters',
            'dates',
            'startDate',
            'endDate',
            'selectedCompanyId',
            'selectedDepartmentId',
            'selectedDesignationId',
            'search',
            'sortBy',
            'shiftSearch',
            'shiftSort',
            'shiftStatus',
            'shiftOvertime',
            'shiftCompanyId',
            'weeklyPatterns',
            'patternSearch',
            'patternSort',
            'patternCompanyId'
        );
    }

    public function storeShift(array $validated): ProductionShift
    {
        return ProductionShift::create($validated);
    }

    public function updateShift(ProductionShift $shift, array $validated): bool
    {
        return $shift->update($validated);
    }

    public function deleteShift(ProductionShift $shift): bool
    {
        return $shift->delete();
    }

    public function assignShiftRoster(array $validated): bool
    {
        $employeeIds = $validated['employee_ids'];
        $shiftId = $validated['shift_id'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $note = $validated['note'] ?? null;

        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($employeeIds as $empId) {
            foreach ($period as $date) {
                ShiftRoster::updateOrCreate(
                    [
                        'employee_id' => $empId,
                        'date' => $date->format('Y-m-d'),
                    ],
                    [
                        'shift_id' => $shiftId,
                        'note' => $note,
                    ]
                );
            }
        }

        return true;
    }

    public function clearShiftRoster(array $validated): bool
    {
        $employeeIds = $validated['employee_ids'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        ShiftRoster::whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->delete();

        return true;
    }
}
