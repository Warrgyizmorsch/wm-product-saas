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
        if (! $event->includes('hrms')) {
            return;
        }

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

        $payrollCategory = DocumentCategory::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'company_id' => $event->companyId, 'name' => 'Payroll Documents'],
            ['description' => 'Salary slips, compensation statements, and tax sheets'],
        );

        $exitCategory = DocumentCategory::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'company_id' => $event->companyId, 'name' => 'Exit Documents'],
            ['description' => 'Relieving letters, experience certificates, NOC clearance, and full & final settlement statements'],
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

        // 1. Offer Letter
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

        // 2. Selection Letter
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

        // 3. Standard Payslip Template
        DocumentTemplate::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'code' => 'PAYSLIP'],
            [
                'company_id' => $event->companyId,
                'document_category_id' => $payrollCategory->id,
                'name' => 'Standard Salary Slip',
                'header_content' => <<<'HTML'
                <div class="doc-header" style="border-bottom: 2px solid #1c3faa; padding-bottom: 12px; margin-bottom: 15px;">
                    <div style="float: left;">
                        <h2 style="margin: 0; color: #1c3faa; font-size: 18px; text-transform: uppercase;">{{company_name}}</h2>
                        <p style="margin: 3px 0; font-size: 11px; color: #64748b;">{{company_address}}<br>{{company_email}} | {{company_phone}}</p>
                    </div>
                    <div style="float: right; text-align: right;">
                        <h3 style="margin: 0; font-size: 14px; text-transform: uppercase; color: #1e293b;">Salary Slip</h3>
                        <p style="margin: 2px 0; font-size: 11px; color: #64748b;">Period: <strong>{{payslip_month}}</strong></p>
                        <p style="margin: 0; font-size: 10px; color: #94a3b8;">Ref: {{reference_number}}</p>
                    </div>
                    <div style="clear: both;"></div>
                </div>
                HTML,
                'body_content' => <<<'HTML'
                <table border="1" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; font-size: 11px; margin-bottom: 15px;">
                    <tr style="background-color: #f8fafc;">
                        <td width="25%"><strong>Employee Name:</strong><br>{{employee_name}}</td>
                        <td width="25%"><strong>Employee ID:</strong><br>{{employee_id}}</td>
                        <td width="25%"><strong>Designation:</strong><br>{{designation}}</td>
                        <td width="25%"><strong>Department:</strong><br>{{department}}</td>
                    </tr>
                    <tr>
                        <td><strong>Bank Name:</strong><br>{{bank_name}}</td>
                        <td><strong>Account No:</strong><br>{{bank_account_number}}</td>
                        <td><strong>PAN Number:</strong><br>{{pan_number}}</td>
                        <td><strong>UAN / PF:</strong><br>{{uan_number}}</td>
                    </tr>
                    <tr style="background-color: #f8fafc;">
                        <td><strong>Total Days:</strong><br>{{working_days}} Days</td>
                        <td><strong>Paid Days:</strong><br>{{paid_days}} Days</td>
                        <td><strong>LOP Days:</strong><br>{{lop_days}} Days</td>
                        <td><strong>Salary Mode:</strong><br>{{salary_mode}}</td>
                    </tr>
                </table>

                {{salary_breakdown_table}}

                <div style="background-color: #f0fdf6; border: 1px solid #bbf7d0; border-radius: 4px; padding: 12px 16px; margin: 15px 0;">
                    <table style="width: 100%;">
                        <tr>
                            <td style="font-size: 12px; font-weight: bold; color: #166534;">NET SALARY PAYOUT:</td>
                            <td style="font-size: 16px; font-weight: bold; color: #16a34a; text-align: right;">{{net_pay}}</td>
                        </tr>
                    </table>
                    <div style="font-size: 11px; font-style: italic; color: #475569; margin-top: 4px;">
                        <strong>In Words:</strong> {{net_pay_in_words}}
                    </div>
                </div>
                HTML,
                'footer_content' => <<<'HTML'
                <div style="margin-top: 40px; width: 100%;">
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 50%; text-align: center;">
                                <div style="width: 150px; border-bottom: 1px solid #64748b; margin: 0 auto 4px auto;"></div>
                                <span style="font-size: 10px; font-weight: bold; color: #64748b;">Employee Signature</span>
                            </td>
                            <td style="width: 50%; text-align: center;">
                                <div style="width: 150px; border-bottom: 1px solid #64748b; margin: 0 auto 4px auto;"></div>
                                <span style="font-size: 10px; font-weight: bold; color: #64748b;">Authorized Signatory</span>
                            </td>
                        </tr>
                    </table>
                </div>
                <div style="margin-top: 30px; border-top: 1px dashed #cbd5e1; padding-top: 8px; text-align: center; font-size: 9px; color: #94a3b8;">
                    This is a computer-generated document issued by {{company_name}} and does not require a physical signature or seal.
                </div>
                HTML,
                'css_styles' => $letterCss,
                'requires_signature' => false,
                'is_default' => true,
                'status' => 'active',
            ],
        );

        // 4. Relieving Letter Template
        DocumentTemplate::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'code' => 'RELIEVING_LT'],
            [
                'company_id' => $event->companyId,
                'document_category_id' => $exitCategory->id,
                'name' => 'Relieving Letter',
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
                    <p>To,<br><strong>{{employee_name}}</strong><br>Employee ID: {{employee_id}}<br>Designation: {{designation}}</p>
                    <p style="margin-top: 16px;"><strong>Subject: Formal Relieving Letter & Acceptance of Resignation</strong></p>
                    <p>Dear {{employee_name}},</p>
                    <p>With reference to your formal resignation, we hereby confirm that your resignation from the employment services of <strong>{{company_name}}</strong> has been accepted by the Management.</p>
                    <p>You are hereby officially relieved from your duties and responsibilities as <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department with effect from the close of business hours on <strong>{{last_working_day}}</strong>.</p>
                    <p>We confirm that you served the organization from <strong>{{joining_date}}</strong> to <strong>{{last_working_day}}</strong> and successfully completed all exit clearances, handover of company property, and full & final settlement formalities.</p>
                    <p>We thank you for your contributions during your tenure with us and wish you the very best in your future endeavors.</p>
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

        // 5. Experience Certificate Template
        DocumentTemplate::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'code' => 'EXPERIENCE_LT'],
            [
                'company_id' => $event->companyId,
                'document_category_id' => $exitCategory->id,
                'name' => 'Experience Certificate',
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
                    <div style="text-align: center; margin: 20px 0;">
                        <h3 style="text-transform: uppercase; letter-spacing: 1px; text-decoration: underline;">TO WHOMSOEVER IT MAY CONCERN</h3>
                    </div>
                    <p>This is to certify that <strong>{{employee_name}}</strong> (Employee Code: <strong>{{employee_id}}</strong>) was employed with <strong>{{company_name}}</strong> from <strong>{{joining_date}}</strong> to <strong>{{last_working_day}}</strong>.</p>
                    <p>During their tenure of <strong>{{tenure_string}}</strong>, they served as <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department.</p>
                    <p>{{conduct_statement}}</p>
                    <p>We appreciate their valuable contributions and wish them every success in their future career.</p>
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

        // 6. NOC Clearance Certificate Template
        DocumentTemplate::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'code' => 'NOC_LT'],
            [
                'company_id' => $event->companyId,
                'document_category_id' => $exitCategory->id,
                'name' => 'No Objection / No Dues Certificate',
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
                    <div style="text-align: center; margin: 20px 0;">
                        <h3 style="text-transform: uppercase; letter-spacing: 1px; text-decoration: underline;">NO OBJECTION & NO DUES CERTIFICATE</h3>
                    </div>
                    <p>This is to certify that <strong>{{employee_name}}</strong> (Employee ID: <strong>{{employee_id}}</strong>), formerly designated as <strong>{{designation}}</strong> in <strong>{{department}}</strong>, has completed their employment tenure ending on <strong>{{last_working_day}}</strong>.</p>
                    <p>{{clearance_status}}</p>
                    <p><strong>{{company_name}}</strong> has no pending claims, financial dues, or objections against the employee and has no objection to their seeking employment elsewhere.</p>
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

        // 7. FnF Settlement Statement Template
        DocumentTemplate::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'code' => 'FNF_STMT'],
            [
                'company_id' => $event->companyId,
                'document_category_id' => $exitCategory->id,
                'name' => 'Full & Final Settlement Statement',
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
                    <div style="text-align: center; margin: 15px 0;">
                        <h3 style="text-transform: uppercase; letter-spacing: 1px;">FULL & FINAL SETTLEMENT STATEMENT</h3>
                    </div>
                    <table border="1" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; font-size: 11px; margin-bottom: 15px;">
                        <tr style="background-color: #f8fafc;">
                            <td width="25%"><strong>Employee Name:</strong><br>{{employee_name}}</td>
                            <td width="25%"><strong>Employee ID:</strong><br>{{employee_id}}</td>
                            <td width="25%"><strong>Designation:</strong><br>{{designation}}</td>
                            <td width="25%"><strong>Department:</strong><br>{{department}}</td>
                        </tr>
                        <tr>
                            <td><strong>Joining Date:</strong><br>{{joining_date}}</td>
                            <td><strong>Last Working Day:</strong><br>{{last_working_day}}</td>
                            <td><strong>Separation Type:</strong><br>{{separation_type}}</td>
                            <td><strong>Tenure:</strong><br>{{tenure_string}}</td>
                        </tr>
                    </table>

                    {{fnf_settlement_table}}
                </div>
                HTML,
                'footer_content' => <<<'HTML'
                <div class="letter-footer">
                    <p>For {{company_name}}</p>
                    <br><br>
                    <p>{{hr_signature}}</p>
                    <p>{{hr_name}}<br>{{hr_designation}}</p>
                    <p style="margin-top:24px;">I hereby acknowledge receipt and acceptance of the Full & Final Settlement statement.</p>
                    <p>Employee Signature: ________________________ &nbsp;&nbsp; Date: {{signature_date}}</p>
                </div>
                HTML,
                'css_styles' => $letterCss,
                'requires_signature' => true,
                'is_default' => true,
                'status' => 'active',
            ],
        );
    }
}
