<?php

namespace App\Domains\HRMS\Listeners;

use App\Core\Tenant\Events\TenantProvisioning;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\DocumentCategory;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\SalaryComponent;

/**
 * Minimal starter HRMS masters for a new tenant: departments, designations,
 * a leave plan with standard leave types, a default pay group, standard
 * salary components, and a starter "HR Letters" document category with
 * Offer Letter / Selection Letter templates. Scoped to the tenant's default
 * company/branch from the TenantProvisioning event (see DefaultOrganization).
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
        $this->provisionDocumentTemplates($event);
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

    private function provisionDocumentTemplates(TenantProvisioning $event): void
    {
        $category = DocumentCategory::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'company_id' => $event->companyId, 'name' => 'HR Letters'],
            ['description' => 'Offer, selection and other candidate/employee correspondence templates'],
        );

        $letterCss = <<<'CSS'
        .letter-header { text-align: center; margin-bottom: 24px; }
        .letter-header h2 { margin: 0; font-size: 20px; }
        .letter-header p { margin: 2px 0; color: #444; font-size: 13px; }
        .letter-meta { display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 13px; }
        .letter-body p { line-height: 1.6; margin-bottom: 12px; }
        .letter-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .letter-table td { border: 1px solid #ccc; padding: 8px 12px; font-size: 13px; }
        .letter-footer { margin-top: 32px; font-size: 13px; }
        CSS;

        DocumentTemplate::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'code' => 'OFFERLT'],
            [
                'company_id' => $event->companyId,
                'document_category_id' => $category->id,
                'name' => 'Offer Letter',
                'header_content' => <<<'HTML'
                <div class="letter-header">
                    <h2>{{company_name}}</h2>
                    <p>{{company_address}}</p>
                    <p>{{company_email}} | {{company_phone}}</p>
                </div>
                <div class="letter-meta">
                    <span>Ref: {{reference_number}}</span>
                    <span>Date: {{issue_date}}</span>
                </div>
                HTML,
                'body_content' => <<<'HTML'
                <div class="letter-body">
                    <p>Dear {{employee_name}},</p>
                    <p>We are pleased to offer you the position of <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department at {{company_name}}, {{branch}}. This offer is made on the basis of the information provided by you and the discussions held during the interview process.</p>
                    <p>Your date of joining will be <strong>{{joining_date}}</strong>. Your compensation and other terms of employment will be as per the appointment letter and company policy in force from time to time.</p>
                    <table class="letter-table">
                        <tr><td>Designation</td><td>{{designation}}</td></tr>
                        <tr><td>Department</td><td>{{department}}</td></tr>
                        <tr><td>Reporting Manager</td><td>{{reporting_manager}}</td></tr>
                        <tr><td>Date of Joining</td><td>{{joining_date}}</td></tr>
                    </table>
                    <p>This offer is subject to satisfactory verification of the documents and references provided by you. Please sign and return a copy of this letter as a token of your acceptance and confirmation of the date of joining.</p>
                    <p>We look forward to welcoming you to the {{company_name}} team.</p>
                </div>
                HTML,
                'footer_content' => <<<'HTML'
                <div class="letter-footer">
                    <p>For {{company_name}}</p>
                    <br><br>
                    <p>{{hr_signature}}</p>
                    <p>{{hr_name}}<br>{{hr_designation}}</p>
                    <p style="margin-top:24px;">I accept the offer and the terms mentioned above.</p>
                    <p>Signature: ________________________ &nbsp;&nbsp; Date: {{signature_date}}</p>
                </div>
                HTML,
                'css_styles' => $letterCss,
                'requires_signature' => true,
                'is_default' => true,
                'status' => 'active',
            ],
        );

        DocumentTemplate::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'code' => 'SELECTLT'],
            [
                'company_id' => $event->companyId,
                'document_category_id' => $category->id,
                'name' => 'Selection Letter',
                'header_content' => <<<'HTML'
                <div class="letter-header">
                    <h2>{{company_name}}</h2>
                    <p>{{company_address}}</p>
                    <p>{{company_email}} | {{company_phone}}</p>
                </div>
                <div class="letter-meta">
                    <span>Ref: {{reference_number}}</span>
                    <span>Date: {{issue_date}}</span>
                </div>
                HTML,
                'body_content' => <<<'HTML'
                <div class="letter-body">
                    <p>Dear {{employee_name}},</p>
                    <p>Congratulations! Following your interview and assessment with {{company_name}}, we are pleased to inform you that you have been <strong>selected</strong> for the role of <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department, reporting to {{reporting_manager}}.</p>
                    <p>A formal offer letter with your compensation and complete terms of employment will follow shortly. To proceed with onboarding, please complete the necessary documentation and verification formalities at the earliest.</p>
                    <table class="letter-table">
                        <tr><td>Designation</td><td>{{designation}}</td></tr>
                        <tr><td>Department</td><td>{{department}}</td></tr>
                        <tr><td>Reporting Manager</td><td>{{reporting_manager}}</td></tr>
                    </table>
                    <p>Please confirm your acceptance of this selection at the earliest so that we can proceed with the next steps.</p>
                    <p>We look forward to having you on board.</p>
                </div>
                HTML,
                'footer_content' => <<<'HTML'
                <div class="letter-footer">
                    <p>For {{company_name}}</p>
                    <br><br>
                    <p>{{hr_signature}}</p>
                    <p>{{hr_name}}<br>{{hr_designation}}</p>
                </div>
                HTML,
                'css_styles' => $letterCss,
                'requires_signature' => false,
                'is_default' => true,
                'status' => 'active',
            ],
        );
    }
}
