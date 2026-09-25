<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Enterprise Scope Engine for HRMS.
 * Dynamically resolves data visibility & management boundaries based on
 * organizational assignments (Business Unit Head, Branch Manager, Department Head, Reporting Manager).
 */
class HrmsScopeService
{
    /**
     * Resolve the employee instance for a given user or employee actor.
     */
    public function resolveEmployee(User|Employee|null $actor): ?Employee
    {
        if ($actor instanceof Employee) {
            return $actor;
        }

        if ($actor instanceof User) {
            return Employee::where('user_id', $actor->id)->first();
        }

        if (auth()->check()) {
            return Employee::where('user_id', auth()->id())->first();
        }

        return null;
    }

    /**
     * Determine whether the actor has unrestricted company-wide administrative scope.
     */
    public function isCompanyAdmin(User|Employee|null $actor): bool
    {
        $user = $actor instanceof User ? $actor : ($actor?->user ?? auth()->user());
        if (!$user) {
            return false;
        }

        // Superadmin or root administrative roles
        if (!empty($user->is_super_admin)) {
            return true;
        }

        $adminRoles = [
            'admin', 'superadmin', 'super_admin', 'super-admin', 'company_admin', 
            'tenant_owner', 'owner', 'hr_director', 'hr_manager', 'hr_admin', 
            'director', 'general_manager'
        ];

        // Direct user role string check
        if (!empty($user->role) && in_array(strtolower($user->role), $adminRoles, true)) {
            return true;
        }

        // Employee model role check if attached
        $employee = $this->resolveEmployee($actor);
        if ($employee && !empty($employee->role) && in_array(strtolower($employee->role), $adminRoles, true)) {
            return true;
        }

        // HR permission check
        if (method_exists($user, 'hasHrPermission') && (
            $user->hasHrPermission('hr.settings.manage') || 
            $user->hasHrPermission('hrms.employees.manage') || 
            $user->hasHrPermission('hrms.attendance.view_all')
        )) {
            return true;
        }

        // Dynamic / UserRole check
        if (method_exists($user, 'roles')) {
            $hasAdminRole = $user->roles()->whereIn('slug', $adminRoles)->exists();
            if ($hasAdminRole) {
                return true;
            }
        }

        // Spatie role checks if method exists
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($adminRoles)) {
            return true;
        }

        return false;
    }

    /**
     * Get a comprehensive summary of the actor's organizational scope.
     *
     * @return array{
     *     is_admin: bool,
     *     scope_type: 'company'|'unit'|'branch'|'department'|'team'|'self',
     *     business_unit_ids: array<int>,
     *     branch_ids: array<int>,
     *     department_ids: array<int>,
     *     is_reporting_manager: bool,
     *     employee_id: ?int
     * }
     */
    public function getScopeSummary(User|Employee|null $actor): array
    {
        $employee = $this->resolveEmployee($actor);
        $isAdmin = $this->isCompanyAdmin($actor);

        if ($isAdmin || !$employee) {
            return [
                'is_admin'             => $isAdmin,
                'scope_type'           => $isAdmin ? 'company' : 'self',
                'business_unit_ids'    => [],
                'branch_ids'           => [],
                'department_ids'       => [],
                'is_reporting_manager' => false,
                'employee_id'          => $employee?->id,
            ];
        }

        $empId = $employee->id;
        $tenantId = $employee->tenant_id ?? $employee->company_id;

        // 1. Business Units where this employee is Head
        $buQuery = BusinessUnit::where('head_employee_id', $empId);
        if ($tenantId) {
            $buQuery->where(function ($q) use ($tenantId) {
                $q->whereNull('company_id')->orWhere('company_id', $tenantId);
            });
        }
        $buIds = $buQuery->pluck('id')->toArray();

        // 2. Branches where this employee is Branch Manager (or inside headed BU)
        $branchQuery = Branch::query();
        if ($tenantId) {
            $branchQuery->where(function ($q) use ($tenantId) {
                $q->whereNull('company_id')->orWhere('company_id', $tenantId);
            });
        }
        $branchQuery->where(function ($q) use ($empId, $buIds) {
            $q->where('manager_employee_id', $empId);
            if (!empty($buIds)) {
                $q->orWhereIn('business_unit_id', $buIds);
            }
        });
        $branchIds = $branchQuery->pluck('id')->toArray();

        // 3. Departments where this employee is Department Head (or inside headed BU / managed Branch)
        $deptQuery = Department::query();
        if ($tenantId) {
            $deptQuery->where(function ($q) use ($tenantId) {
                $q->whereNull('company_id')->orWhere('company_id', $tenantId);
            });
        }
        $deptQuery->where(function ($q) use ($empId, $branchIds, $buIds) {
            $q->where('head_employee_id', $empId);
            if (!empty($branchIds)) {
                $q->orWhereIn('branch_id', $branchIds);
            }
            if (!empty($buIds)) {
                $q->orWhereIn('business_unit_id', $buIds);
            }
        });
        $deptIds = $deptQuery->pluck('id')->toArray();

        // 4. Check if reporting manager for any direct reports
        $isReportingManager = Employee::where('reporting_manager_id', $empId)->exists();

        // Determine primary scope label
        $scopeType = 'self';
        if (!empty($buIds)) {
            $scopeType = 'unit';
        } elseif (!empty($branchIds)) {
            $scopeType = 'branch';
        } elseif (!empty($deptIds)) {
            $scopeType = 'department';
        } elseif ($isReportingManager) {
            $scopeType = 'team';
        }

        return [
            'is_admin'             => false,
            'scope_type'           => $scopeType,
            'business_unit_ids'    => $buIds,
            'branch_ids'           => $branchIds,
            'department_ids'       => $deptIds,
            'is_reporting_manager' => $isReportingManager,
            'employee_id'          => $empId,
        ];
    }

    /**
     * Get all Employee IDs accessible within the actor's scope.
     * Returns null if actor has full unrestricted company-wide access.
     *
     * @return array<int>|null
     */
    public function getAccessibleEmployeeIds(User|Employee|null $actor): ?array
    {
        $summary = $this->getScopeSummary($actor);
        if ($summary['is_admin']) {
            return null; // Full unrestricted access
        }

        $empId = $summary['employee_id'];
        if (!$empId) {
            return [];
        }

        $accessibleIds = [$empId]; // Employee can always see their own records

        // Add all employees within headed Business Units
        if (!empty($summary['business_unit_ids'])) {
            $unitEmpIds = Employee::whereIn('business_unit_id', $summary['business_unit_ids'])->pluck('id')->toArray();
            $accessibleIds = array_merge($accessibleIds, $unitEmpIds);
        }

        // Add all employees within managed Branches
        if (!empty($summary['branch_ids'])) {
            $branchEmpIds = Employee::whereIn('branch_id', $summary['branch_ids'])->pluck('id')->toArray();
            $accessibleIds = array_merge($accessibleIds, $branchEmpIds);
        }

        // Add all employees within headed Departments
        if (!empty($summary['department_ids'])) {
            $deptEmpIds = Employee::whereIn('department_id', $summary['department_ids'])->pluck('id')->toArray();
            $accessibleIds = array_merge($accessibleIds, $deptEmpIds);
        }

        // Add direct reports
        if ($summary['is_reporting_manager']) {
            $directReportIds = Employee::where('reporting_manager_id', $empId)->pluck('id')->toArray();
            $accessibleIds = array_merge($accessibleIds, $directReportIds);
        }

        return array_values(array_unique($accessibleIds));
    }

    /**
     * Apply scope filter directly onto an Employee query.
     */
    public function applyEmployeeScope(Builder $query, User|Employee|null $actor, string $idColumn = 'id'): Builder
    {
        $accessibleIds = $this->getAccessibleEmployeeIds($actor);
        if ($accessibleIds === null) {
            return $query; // Company Admin: unrestricted
        }

        return $query->whereIn($idColumn, $accessibleIds);
    }

    /**
     * Apply scope filter onto queries that reference an employee (e.g. Attendance, LeaveRequest, OvertimeRequest).
     */
    public function applyRelatedScope(Builder $query, User|Employee|null $actor, string $employeeIdColumn = 'employee_id'): Builder
    {
        $accessibleIds = $this->getAccessibleEmployeeIds($actor);
        if ($accessibleIds === null) {
            return $query; // Company Admin: unrestricted
        }

        return $query->whereIn($employeeIdColumn, $accessibleIds);
    }

    /**
     * Check whether the actor is authorized to access / manage a specific employee.
     */
    public function canAccessEmployee(User|Employee|null $actor, int|Employee $targetEmployee): bool
    {
        if ($this->isCompanyAdmin($actor)) {
            return true;
        }

        $targetId = $targetEmployee instanceof Employee ? $targetEmployee->id : (int) $targetEmployee;
        $accessibleIds = $this->getAccessibleEmployeeIds($actor);

        return in_array($targetId, $accessibleIds ?? [], true);
    }
}
