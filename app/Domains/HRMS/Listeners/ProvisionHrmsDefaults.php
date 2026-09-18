<?php

namespace App\Domains\HRMS\Listeners;

use App\Core\Tenant\Events\TenantProvisioning;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\SalaryComponent;

/**
 * Minimal starter HRMS masters for a new tenant: departments, designations,
 * a leave plan with standard leave types, a default pay group, and standard
 * salary components. Scoped to the tenant's default company/branch from the
 * TenantProvisioning event (see DefaultOrganization).
 *
 * Scoping notes (confirmed against the live schema, not just migrations —
 * see 2026_07_15_000001_add_tenant_isolation_to_hrms_tables.php):
 *  - departments: tenant_id + branch_id + company_id + business_unit_id.
 *  - designations: tenant_id + department_id only (no branch/company).
 *  - leave_plans: tenant_id + company_id. leave_types: tenant_id + leave_plan_id
 *    only (no company/branch) — they hang off the plan, not the org unit.
 *  - pay_groups / salary_components: tenant_id + company_id (+ pay_group_id
 *    for components).
 *
 * Idempotent: every create is guarded by firstOrCreate/exists() keyed on
 * tenant_id + a natural key, so re-running (tenant:provision --all) never
 * duplicates rows.
 */
class ProvisionHrmsDefaults
{
    /** name => code */
    private const DEPARTMENTS = [
        'Administration' => 'ADMIN',
        'Sales' => 'SALES',
        'Operations' => 'OPS',
        'Finance' => 'FIN',
    ];

    /** name => level */
    private const DESIGNATIONS = [
        'Manager' => 'L3',
        'Executive' => 'L1',
    ];

    /** name => [code, type, quota] */
    private const LEAVE_TYPES = [
        'Casual Leave' => ['CL', 'paid', 12],
        'Sick Leave' => ['SL', 'paid', 12],
        'Earned Leave' => ['EL', 'paid', 18],
        'Unpaid Leave' => ['UL', 'unpaid', 0],
    ];

    /** name => [code, type, calculation_type, default_value] */
    private const SALARY_COMPONENTS = [
        'Basic Salary' => ['BASIC', 'earning', 'fixed', '30000'],
        'House Rent Allowance' => ['HRA', 'earning', 'fixed', '12000'],
        'Special Allowance' => ['SPL', 'earning', 'balancing', '0'],
        'Provident Fund' => ['PF', 'deduction', 'fixed', '1800'],
        'Professional Tax' => ['PT', 'deduction', 'fixed', '200'],
    ];

    public function handle(TenantProvisioning $event): void
    {
        $departments = $this->provisionDepartments($event);
        $this->provisionDesignations($event, $departments);
        $this->provisionLeaveTypes($event);
        $payGroup = $this->provisionPayGroup($event);
        $this->provisionSalaryComponents($event, $payGroup);
    }

    /** @return array<string, Department> */
    private function provisionDepartments(TenantProvisioning $event): array
    {
        $departments = [];

        foreach (self::DEPARTMENTS as $name => $code) {
            $departments[$name] = Department::query()->firstOrCreate(
                ['tenant_id' => $event->tenantId, 'branch_id' => $event->branchId, 'code' => $code],
                [
                    'company_id' => $event->companyId,
                    'name' => $name,
                    'status' => true,
                ],
            );
        }

        return $departments;
    }

    /** @param array<string, Department> $departments */
    private function provisionDesignations(TenantProvisioning $event, array $departments): void
    {
        foreach ($departments as $department) {
            foreach (self::DESIGNATIONS as $name => $level) {
                Designation::query()->firstOrCreate(
                    ['tenant_id' => $event->tenantId, 'department_id' => $department->id, 'name' => $name],
                    ['level' => $level, 'status' => true],
                );
            }
        }
    }

    private function provisionLeaveTypes(TenantProvisioning $event): void
    {
        $plan = LeavePlan::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'company_id' => $event->companyId, 'name' => 'Standard Leave Plan'],
            [
                'effective_from' => now()->toDateString(),
                'description' => 'Default starter leave plan',
                'status' => true,
            ],
        );

        foreach (self::LEAVE_TYPES as $name => [$code, $type, $quota]) {
            LeaveType::query()->firstOrCreate(
                ['tenant_id' => $event->tenantId, 'leave_plan_id' => $plan->id, 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'quota' => $quota,
                    'status' => true,
                ],
            );
        }
    }

    private function provisionPayGroup(TenantProvisioning $event): PayGroup
    {
        return PayGroup::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'company_id' => $event->companyId, 'name' => 'Standard'],
            ['description' => 'Default starter pay group', 'status' => true],
        );
    }

    private function provisionSalaryComponents(TenantProvisioning $event, PayGroup $payGroup): void
    {
        foreach (self::SALARY_COMPONENTS as $name => [$code, $type, $calculationType, $defaultValue]) {
            SalaryComponent::query()->firstOrCreate(
                ['tenant_id' => $event->tenantId, 'company_id' => $event->companyId, 'pay_group_id' => $payGroup->id, 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'calculation_type' => $calculationType,
                    'default_value' => $defaultValue,
                    'is_adhoc' => false,
                    'status' => true,
                ],
            );
        }
    }
}
