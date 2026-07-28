<?php

namespace Tests\Feature\HRMS\Concerns;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;
use App\Models\Tenant;

/**
 * Builds the minimum HRMS org-structure chain (Company -> BusinessUnit -> Branch
 * -> Department -> Designation -> Employee) needed to exercise HRMS controllers
 * in feature tests. No model factories exist for these models, so this mirrors
 * the direct ::create() convention already used by HrmsAuthorizationTest.
 */
trait BuildsHrmsOrgStructure
{
    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
    }

    private function makeEmployee(Tenant $tenant, string $uniqueSuffix, array $overrides = []): Employee
    {
        // Company/BusinessUnit/Branch/Department/Designation don't expose
        // tenant_id as fillable, so it can only be set via the global-scope
        // trait's creating() hook, which reads the current TenantContext.
        app(TenantContext::class)->set($tenant);

        $company = Company::create([
            'tenant_id' => $tenant->id,
            'company_name' => 'Company ' . $uniqueSuffix,
            'status' => true,
        ]);

        $businessUnit = BusinessUnit::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'BU ' . $uniqueSuffix,
            'code' => 'BU-' . $uniqueSuffix,
            'status' => true,
        ]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'business_unit_id' => $businessUnit->id,
            'name' => 'Branch ' . $uniqueSuffix,
            'code' => 'BR-' . $uniqueSuffix,
            'status' => true,
        ]);

        $department = Department::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Department ' . $uniqueSuffix,
            'code' => 'DPT-' . $uniqueSuffix,
            'status' => true,
        ]);

        $designation = Designation::create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => 'Designation ' . $uniqueSuffix,
            'status' => true,
        ]);

        return Employee::create(array_merge([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $businessUnit->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'full_name' => 'Employee ' . $uniqueSuffix,
            'date_of_joining' => now()->subYear(),
            'gender' => 'Other',
            'personal_email' => 'employee.' . strtolower($uniqueSuffix) . '@example.com',
            'status' => true,
        ], $overrides));
    }

    private function makeLeaveType(Tenant $tenant, Employee $employee, string $uniqueSuffix): LeaveType
    {
        app(TenantContext::class)->set($tenant);

        $leavePlan = LeavePlan::create([
            'tenant_id' => $tenant->id,
            'company_id' => $employee->company_id,
            'name' => 'Leave Plan ' . $uniqueSuffix,
            'effective_from' => now()->startOfYear(),
            'status' => true,
        ]);

        return LeaveType::create([
            'tenant_id' => $tenant->id,
            'leave_plan_id' => $leavePlan->id,
            'name' => 'Annual Leave',
            'code' => 'AL-' . $uniqueSuffix,
            'type' => 'paid',
            'quota' => 12,
            'status' => true,
        ]);
    }
}
