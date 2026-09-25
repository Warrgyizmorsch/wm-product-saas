<?php

namespace Database\Seeders;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Models\SalaryStructure;
use App\Domains\HRMS\Models\SalaryStructureItem;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\LeaveEncashment;
use App\Domains\HRMS\Models\AttendanceRule;
use App\Domains\HRMS\Models\AttendancePenalty;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceBreak;
use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Models\AttendanceLocationLog;
use App\Domains\HRMS\Models\BiometricDevice;
use App\Domains\HRMS\Models\BiometricPunchLog;
use App\Domains\HRMS\Models\WfhRequest;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Domains\HRMS\Models\ShiftRoster;
use App\Domains\HRMS\Models\ShiftChangeRequest;
use App\Domains\HRMS\Models\HolidayCalendar;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\AssetItem;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetAllocation;
use App\Domains\HRMS\Models\AssetRequest;
use App\Domains\HRMS\Models\DocumentCategory;
use App\Domains\HRMS\Models\DocumentMaster;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Models\Document;
use App\Domains\HRMS\Models\GeneratedDocument;
use App\Domains\HRMS\Models\ExpenseCategory;
use App\Domains\HRMS\Models\ExpensePolicy;
use App\Domains\HRMS\Models\ExpensePolicyRule;
use App\Domains\HRMS\Models\ExpenseApprovalWorkflow;
use App\Domains\HRMS\Models\ExpenseReport;
use App\Domains\HRMS\Models\ExpenseClaim;
use App\Domains\HRMS\Models\CashAdvance;
use App\Domains\HRMS\Models\TravelRequest;
use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Models\PipCategory;
use App\Domains\HRMS\Models\PipPolicyTemplate;
use App\Domains\HRMS\Models\PipObjective;
use App\Domains\HRMS\Models\PipCheckin;
use App\Domains\HRMS\Models\Broadcast;
use App\Domains\HRMS\Models\BroadcastReceipt;
use App\Domains\HRMS\Models\BroadcastComment;
use App\Domains\HRMS\Models\JobRequisition;
use App\Domains\HRMS\Models\Candidate;
use App\Domains\HRMS\Models\CandidateApplication;
use App\Domains\HRMS\Models\CandidateInterview;
use App\Domains\HRMS\Models\InterviewScorecard;
use App\Domains\HRMS\Models\JobOffer;
use App\Domains\HRMS\Models\HelpdeskCategory;
use App\Domains\HRMS\Models\HelpdeskTicket;
use App\Domains\HRMS\Models\HelpdeskTicketReply;
use App\Domains\HRMS\Models\HelpdeskTicketAttachment;
use App\Domains\HRMS\Models\HelpdeskKbArticle;
use App\Domains\HRMS\Models\HelpdeskSatisfactionRating;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\SalaryRevision;
use App\Domains\HRMS\Models\EmployeeAdhocComponent;
use App\Domains\HRMS\Models\PayrollHold;
use App\Domains\HRMS\Models\PayrollRetroactiveAdjustment;
use App\Domains\HRMS\Models\EmployeeEmploymentHistory;
use App\Domains\HRMS\Models\EmployeeProbationEvaluation;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Models\EmployeeFnfSettlement;
use App\Domains\HRMS\Models\ExitClearanceTemplate;
use App\Domains\Production\Models\ProductionShift;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HrmsDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // This wipes and rebuilds demo HR/asset data wholesale — safe on a local
        // sandbox, destructive anywhere real data might exist. It previously ran
        // against a shared database and orphaned depreciation schedules/disposals/
        // write-offs/revaluations pointing at assets it had just truncated.
        
        // if (! app()->environment(['local', 'testing'])) {
        //     $this->command?->error('HrmsDemoSeeder only runs in local/testing environments — refusing on '.app()->environment().'.');

        //     return;
        // }

        Schema::disableForeignKeyConstraints();

        // 1. Truncate all related HRMS tables safely
        $tablesToTruncate = [
            'wfh_requests',
            'shift_change_requests',
            'overtime_requests',
            'leave_encashments',
            'leave_requests',
            'leave_balances',
            'employee_adhoc_components',
            'employee_employment_histories',
            'employee_penalties',
            'shift_rosters',
            'asset_allocations',
            'asset_requests',
            'asset_depreciation_schedules',
            'asset_disposals',
            'asset_write_offs',
            'asset_revaluations',
            'assets',
            'asset_items',
            'asset_categories',
            'generated_documents',
            'documents',
            'document_templates',
            'document_masters',
            'document_categories',
            'attendance_corrections',
            'attendance_location_logs',
            'attendance_breaks',
            'biometric_punch_logs',
            'biometric_devices',
            'attendances',
            'attendance_penalties',
            'attendance_rules',
            'production_shifts',
            'holiday_calendars',
            'cash_advances',
            'expense_claims',
            'expense_reports',
            'expense_approval_workflows',
            'expense_policy_rules',
            'expense_policies',
            'expense_categories',
            'travel_requests',
            'pip_checkins',
            'pip_objectives',
            'performance_improvement_plans',
            'pip_policy_templates',
            'pip_categories',
            'broadcast_comments',
            'broadcast_receipts',
            'broadcasts',
            'interview_scorecards',
            'candidate_interviews',
            'job_offers',
            'candidate_applications',
            'candidates',
            'job_requisitions',
            'helpdesk_satisfaction_ratings',
            'helpdesk_ticket_attachments',
            'helpdesk_ticket_replies',
            'helpdesk_tickets',
            'helpdesk_kb_articles',
            'helpdesk_categories',
            'payroll_retroactive_adjustments',
            'payroll_holds',
            'salary_revisions',
            'payroll_runs',
            'employee_exit_documents',
            'employee_fnf_settlements',
            'employee_exit_clearances',
            'employee_exits',
            'exit_clearance_templates',
            'employee_probation_evaluations',
            'employees',
            'salary_structure_items',
            'salary_structures',
            'salary_components',
            'pay_groups',
            'leave_types',
            'leave_plans',
            'designations',
            'departments',
            'branches',
            'business_units',
            'companies',
        ];

        foreach ($tablesToTruncate as $tbl) {
            if (Schema::hasTable($tbl)) {
                DB::table($tbl)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        // 2. Fetch Tenant and Base Admin User
        $tenantSlug = config('tenancy.local_fallback_slug') ?: 'demo';
        $tenant = Tenant::where('slug', $tenantSlug)->first() ?? Tenant::first() ?? Tenant::create([
            'name' => 'Demo Tenant',
            'slug' => 'demo',
            'status' => 'active',
            'plan' => 'enterprise',
            'subscription_status' => 'active',
            'max_users' => 100,
            'max_storage_mb' => 10240,
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
        ]);

        // Fetch or create available system roles
        $rolesToEnsure = [
            ['slug' => 'super_admin', 'name' => 'Super Admin', 'level' => 1],
            ['slug' => 'tenant_owner', 'name' => 'Tenant Owner', 'level' => 10],
            ['slug' => 'company_admin', 'name' => 'Company Admin', 'level' => 20],
            ['slug' => 'production_manager', 'name' => 'Production Manager', 'level' => 40],
            ['slug' => 'production_engineer', 'name' => 'Production Engineer', 'level' => 50],
            ['slug' => 'sales_manager', 'name' => 'Sales Manager', 'level' => 40],
            ['slug' => 'sales_executive', 'name' => 'Sales Executive', 'level' => 50],
            ['slug' => 'inventory_manager', 'name' => 'Inventory Manager', 'level' => 40],
            ['slug' => 'purchase_manager', 'name' => 'Purchase Manager', 'level' => 40],
            ['slug' => 'hr_manager', 'name' => 'HR Manager', 'level' => 40],
            ['slug' => 'hr_executive', 'name' => 'HR Executive', 'level' => 50],
            ['slug' => 'tech_lead', 'name' => 'Tech Lead', 'level' => 40],
            ['slug' => 'software_engineer', 'name' => 'Software Engineer', 'level' => 50],
            ['slug' => 'accountant', 'name' => 'Accountant', 'level' => 40],
            ['slug' => 'auditor', 'name' => 'Auditor', 'level' => 80],
            ['slug' => 'read_only', 'name' => 'Read Only User', 'level' => 90],
        ];

        foreach ($rolesToEnsure as $rDef) {
            Role::query()->updateOrCreate(
                ['slug' => $rDef['slug'], 'tenant_id' => null],
                $rDef + ['is_system' => true]
            );
        }

        $rolesBySlug = Role::whereNull('tenant_id')->orWhere('tenant_id', $tenant->id)->get()->keyBy('slug');

        // 3. Organization Master Hierarchy
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'company_name' => 'Warrgyizmorsch',
            'legal_name' => 'Warrgyizmorsch Technologies Pvt Ltd',
            'status' => true,
        ]);

        $buTech = BusinessUnit::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Technology & Product Business Unit',
            'code' => 'TBU',
            'status' => true,
        ]);

        $buMfg = BusinessUnit::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Manufacturing & Operations BU',
            'code' => 'MOBU',
            'status' => true,
        ]);

        $buCorporate = BusinessUnit::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Corporate & Business Services BU',
            'code' => 'CSBU',
            'status' => true,
        ]);

        $branchHq = Branch::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buCorporate->id,
            'name' => 'Corporate Headquarters - Noida',
            'code' => 'HQ',
            'status' => true,
        ]);

        $branchPune = Branch::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buMfg->id,
            'name' => 'Pune Manufacturing Plant',
            'code' => 'PUNE-MFG',
            'status' => true,
        ]);

        $branchBlr = Branch::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buTech->id,
            'name' => 'Bangalore Tech Center',
            'code' => 'BLR-TECH',
            'status' => true,
        ]);

        // Departments
        $deptExec = Department::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buCorporate->id,
            'branch_id' => $branchHq->id,
            'name' => 'Executive Leadership',
            'code' => 'EXEC',
            'status' => true,
        ]);

        $deptHr = Department::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buCorporate->id,
            'branch_id' => $branchHq->id,
            'name' => 'Human Resources',
            'code' => 'HR',
            'status' => true,
        ]);

        $deptEng = Department::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buTech->id,
            'branch_id' => $branchBlr->id,
            'name' => 'Engineering & Technology',
            'code' => 'ENG',
            'status' => true,
        ]);

        $deptProd = Department::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buMfg->id,
            'branch_id' => $branchPune->id,
            'name' => 'Production & Operations',
            'code' => 'PROD',
            'status' => true,
        ]);

        $deptSales = Department::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buCorporate->id,
            'branch_id' => $branchHq->id,
            'name' => 'Sales & Business Development',
            'code' => 'SALES',
            'status' => true,
        ]);

        $deptFin = Department::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buCorporate->id,
            'branch_id' => $branchHq->id,
            'name' => 'Finance & Accounts',
            'code' => 'FIN',
            'status' => true,
        ]);

        $deptScm = Department::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buMfg->id,
            'branch_id' => $branchPune->id,
            'name' => 'Supply Chain & Inventory',
            'code' => 'SCM',
            'status' => true,
        ]);

        $deptQc = Department::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buMfg->id,
            'branch_id' => $branchPune->id,
            'name' => 'Quality Assurance & Compliance',
            'code' => 'QC',
            'status' => true,
        ]);

        // Designations mapped across all departments
        $desigCeo = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptExec->id, 'name' => 'Chief Executive Officer', 'level' => 'L5', 'status' => true]);
        $desigVpOps = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptExec->id, 'name' => 'VP of Operations', 'level' => 'L5', 'status' => true]);
        $desigVpHr = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptHr->id, 'name' => 'VP of Human Resources', 'level' => 'L4', 'status' => true]);
        $desigHrMgr = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptHr->id, 'name' => 'HR Manager', 'level' => 'L3', 'status' => true]);
        $desigHrOps = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptHr->id, 'name' => 'HR Operations Specialist', 'level' => 'L2', 'status' => true]);
        $desigTechLead = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptEng->id, 'name' => 'Tech Lead & Architect', 'level' => 'L4', 'status' => true]);
        $desigSrSwe = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptEng->id, 'name' => 'Senior Software Engineer', 'level' => 'L3', 'status' => true]);
        $desigSwe = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptEng->id, 'name' => 'Software Engineer', 'level' => 'L2', 'status' => true]);
        $desigProdMgr = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptProd->id, 'name' => 'Plant Production Manager', 'level' => 'L4', 'status' => true]);
        $desigProdEng = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptProd->id, 'name' => 'Senior Production Engineer', 'level' => 'L3', 'status' => true]);
        $desigSalesMgr = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptSales->id, 'name' => 'National Sales Manager', 'level' => 'L4', 'status' => true]);
        $desigSalesExec = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptSales->id, 'name' => 'Senior Sales Executive', 'level' => 'L2', 'status' => true]);
        $desigInvMgr = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptScm->id, 'name' => 'Warehouse & Inventory Manager', 'level' => 'L3', 'status' => true]);
        $desigPurchMgr = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptScm->id, 'name' => 'Procurement & Purchase Manager', 'level' => 'L3', 'status' => true]);
        $desigAccountant = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptFin->id, 'name' => 'Senior Financial Accountant', 'level' => 'L3', 'status' => true]);
        $desigAuditor = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptFin->id, 'name' => 'Lead Internal Auditor', 'level' => 'L4', 'status' => true]);
        $desigReadOnly = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptQc->id, 'name' => 'Compliance & Audit Observer', 'level' => 'L2', 'status' => true]);

        // 4. Pay Groups & Salary Structures
        $payGroupExec = PayGroup::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Executive & Leadership Pay Group',
            'payroll_rules' => [
                'proration_rule' => 'calendar_days',
                'lop_splicing_rule' => 'proportionate_gross',
                'attendance_lock_day' => 25,
                'variable_lock_day' => 25,
                'enable_pf' => false,
                'restrict_pf_ceiling' => false,
                'enable_esi' => false,
                'restrict_esi_threshold' => false,
            ],
            'status' => true,
        ]);

        $payGroupTech = PayGroup::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Engineering & Professional Pay Group',
            'payroll_rules' => [
                'proration_rule' => 'calendar_days',
                'lop_splicing_rule' => 'proportionate_gross',
                'attendance_lock_day' => 25,
                'variable_lock_day' => 25,
                'enable_pf' => true,
                'restrict_pf_ceiling' => true,
                'enable_esi' => false,
                'restrict_esi_threshold' => true,
            ],
            'status' => true,
        ]);

        $payGroupStaff = PayGroup::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Operations & Staff Pay Group',
            'payroll_rules' => [
                'proration_rule' => 'calendar_days',
                'lop_splicing_rule' => 'proportionate_gross',
                'attendance_lock_day' => 25,
                'variable_lock_day' => 25,
                'enable_pf' => true,
                'restrict_pf_ceiling' => true,
                'enable_esi' => true,
                'restrict_esi_threshold' => true,
            ],
            'status' => true,
        ]);

        // Salary Components
        // 1. Basic Salary
        $compBasic = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupTech->id,
            'name' => 'Basic Salary',
            'code' => 'BASIC',
            'type' => 'earning',
            'calculation_type' => 'percentage_of_ctc',
            'default_value' => '40',
            'description' => 'Primary taxable base earnings (50% of Annual CTC)',
            'is_adhoc' => false,
            'status' => true,
        ]);

        // 2. House Rent Allowance
        $compHra = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupTech->id,
            'name' => 'House Rent Allowance',
            'code' => 'HRA',
            'type' => 'earning',
            'calculation_type' => 'percentage_of_basic',
            'default_value' => '40',
            'description' => 'Housing allowance (50% of Basic for Metros, 40% for Non-Metros)',
            'is_adhoc' => false,
            'status' => true,
        ]);

        // 3. Special Allowance
        $compSpl = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupTech->id,
            'name' => 'Special Allowance',
            'code' => 'SPL',
            'type' => 'earning',
            'calculation_type' => 'balancing',
            'default_value' => '0',
            'description' => 'Balancing flexible component to match gross CTC',
            'is_adhoc' => false,
            'status' => true,
        ]);

        // 4. Conveyance Allowance
        $compConv = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupTech->id,
            'name' => 'Conveyance Allowance',
            'code' => 'CONV',
            'type' => 'earning',
            'calculation_type' => 'percentage_of_basic',
            'default_value' => '10',
            'description' => 'Standard travel and commute support (10% of Basic)',
            'is_adhoc' => false,
            'status' => true,
        ]);

        // 5. Medical Allowance
        $compMed = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupTech->id,
            'name' => 'Medical Allowance',
            'code' => 'MED',
            'type' => 'earning',
            'calculation_type' => 'percentage_of_basic',
            'default_value' => '5',
            'description' => 'Health and medical benefit (5% of Basic)',
            'is_adhoc' => false,
            'status' => true,
        ]);

        // 6. Performance Bonus (Adhoc Earning)
        $compBonus = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupTech->id,
            'name' => 'Performance Incentive / Bonus',
            'code' => 'PERF_BONUS',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'default_value' => '0',
            'description' => 'Discretionary adhoc bonus payout',
            'is_adhoc' => true,
            'status' => true,
        ]);

        // 7. Provident Fund Deduction
        $compPf = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupTech->id,
            'name' => 'Provident Fund (EPF)',
            'code' => 'PF',
            'type' => 'deduction',
            'calculation_type' => 'percentage_of_basic',
            'default_value' => '12',
            'description' => 'Employee Provident Fund statutory deduction (12% of Basic)',
            'is_adhoc' => false,
            'status' => true,
        ]);

        // 8. ESI Deduction
        $compEsi = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupStaff->id,
            'name' => 'Employee State Insurance (ESIC)',
            'code' => 'ESI',
            'type' => 'deduction',
            'calculation_type' => 'percentage_of_basic',
            'default_value' => '0.75',
            'description' => 'ESIC statutory deduction (0.75% of Basic/Gross)',
            'is_adhoc' => false,
            'status' => true,
        ]);

        // 9. Professional Tax
        $compPt = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupTech->id,
            'name' => 'Professional Tax',
            'code' => 'PT',
            'type' => 'deduction',
            'calculation_type' => 'fixed',
            'default_value' => '2400',
            'description' => 'State Professional Tax (₹200/month)',
            'is_adhoc' => false,
            'status' => true,
        ]);

        // 10. TDS / Tax Deduction
        $compTds = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupTech->id,
            'name' => 'Tax Deducted at Source (TDS)',
            'code' => 'TDS',
            'type' => 'deduction',
            'calculation_type' => 'fixed',
            'default_value' => '0',
            'description' => 'Income Tax TDS Deduction',
            'is_adhoc' => false,
            'status' => true,
        ]);

        // Salary Structures
        // Structure A: Executive Leadership Package (CTC 15L - 36L)
        $structExec = SalaryStructure::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupExec->id,
            'name' => 'Executive Leadership Salary Structure',
            'min_ctc' => 1500000,
            'max_ctc' => 3600000,
            'status' => true,
        ]);

        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structExec->id, 'salary_component_id' => $compBasic->id, 'calculation_type' => 'percentage_of_ctc', 'value' => 50.00, 'sort_order' => 1]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structExec->id, 'salary_component_id' => $compHra->id, 'calculation_type' => 'percentage_of_basic', 'value' => 50.00, 'sort_order' => 2]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structExec->id, 'salary_component_id' => $compConv->id, 'calculation_type' => 'percentage_of_basic', 'value' => 10.00, 'sort_order' => 3]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structExec->id, 'salary_component_id' => $compMed->id, 'calculation_type' => 'percentage_of_basic', 'value' => 5.00, 'sort_order' => 4]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structExec->id, 'salary_component_id' => $compSpl->id, 'calculation_type' => 'balancing', 'value' => 0.00, 'sort_order' => 5]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structExec->id, 'salary_component_id' => $compPf->id, 'calculation_type' => 'percentage_of_basic', 'value' => 12.00, 'sort_order' => 6]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structExec->id, 'salary_component_id' => $compPt->id, 'calculation_type' => 'fixed', 'value' => 2400.00, 'sort_order' => 7]);

        // Structure B: Senior Tech & Management Package (CTC 8L - 15L)
        $structTech = SalaryStructure::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupTech->id,
            'name' => 'Senior Tech & Management Structure',
            'min_ctc' => 800000,
            'max_ctc' => 1500000,
            'status' => true,
        ]);

        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structTech->id, 'salary_component_id' => $compBasic->id, 'calculation_type' => 'percentage_of_ctc', 'value' => 50.00, 'sort_order' => 1]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structTech->id, 'salary_component_id' => $compHra->id, 'calculation_type' => 'percentage_of_basic', 'value' => 50.00, 'sort_order' => 2]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structTech->id, 'salary_component_id' => $compConv->id, 'calculation_type' => 'percentage_of_basic', 'value' => 10.00, 'sort_order' => 3]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structTech->id, 'salary_component_id' => $compMed->id, 'calculation_type' => 'percentage_of_basic', 'value' => 5.00, 'sort_order' => 4]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structTech->id, 'salary_component_id' => $compSpl->id, 'calculation_type' => 'balancing', 'value' => 0.00, 'sort_order' => 5]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structTech->id, 'salary_component_id' => $compPf->id, 'calculation_type' => 'percentage_of_basic', 'value' => 12.00, 'sort_order' => 6]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structTech->id, 'salary_component_id' => $compPt->id, 'calculation_type' => 'fixed', 'value' => 2400.00, 'sort_order' => 7]);

        // Structure C: Standard Staff & Operations Package (CTC 3.6L - 8L)
        $structStaff = SalaryStructure::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupStaff->id,
            'name' => 'Standard Staff & Operations Structure',
            'min_ctc' => 360000,
            'max_ctc' => 800000,
            'status' => true,
        ]);

        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structStaff->id, 'salary_component_id' => $compBasic->id, 'calculation_type' => 'percentage_of_ctc', 'value' => 40.00, 'sort_order' => 1]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structStaff->id, 'salary_component_id' => $compHra->id, 'calculation_type' => 'percentage_of_basic', 'value' => 40.00, 'sort_order' => 2]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structStaff->id, 'salary_component_id' => $compConv->id, 'calculation_type' => 'fixed', 'value' => 19200.00, 'sort_order' => 3]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structStaff->id, 'salary_component_id' => $compSpl->id, 'calculation_type' => 'balancing', 'value' => 0.00, 'sort_order' => 4]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structStaff->id, 'salary_component_id' => $compPf->id, 'calculation_type' => 'percentage_of_basic', 'value' => 12.00, 'sort_order' => 5]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structStaff->id, 'salary_component_id' => $compEsi->id, 'calculation_type' => 'percentage_of_basic', 'value' => 0.75, 'sort_order' => 6]);
        SalaryStructureItem::create(['tenant_id' => $tenant->id, 'salary_structure_id' => $structStaff->id, 'salary_component_id' => $compPt->id, 'calculation_type' => 'fixed', 'value' => 2400.00, 'sort_order' => 7]);

        // 5. Leave Structure & Leave Plans
        $leavePlanExec = LeavePlan::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Executive Leadership Leave Policy',
            'effective_from' => '2026-01-01',
            'description' => 'Comprehensive executive leave benefits package',
            'status' => true,
        ]);

        $ltClExec = LeaveType::create(['tenant_id' => $tenant->id, 'leave_plan_id' => $leavePlanExec->id, 'name' => 'Casual Leave (Exec)', 'code' => 'CL-EXE', 'type' => 'paid', 'quota' => 15, 'status' => true]);
        $ltSlExec = LeaveType::create(['tenant_id' => $tenant->id, 'leave_plan_id' => $leavePlanExec->id, 'name' => 'Sick Leave (Exec)', 'code' => 'SL-EXE', 'type' => 'paid', 'quota' => 15, 'status' => true]);
        $ltElExec = LeaveType::create(['tenant_id' => $tenant->id, 'leave_plan_id' => $leavePlanExec->id, 'name' => 'Earned Privilege Leave (Exec)', 'code' => 'EL-EXE', 'type' => 'paid', 'quota' => 24, 'status' => true]);

        $leavePlanStandard = LeavePlan::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Standard Corporate Leave Policy',
            'effective_from' => '2026-01-01',
            'description' => 'Standard leave package for full-time staff',
            'status' => true,
        ]);

        $ltClStd = LeaveType::create(['tenant_id' => $tenant->id, 'leave_plan_id' => $leavePlanStandard->id, 'name' => 'Casual Leave', 'code' => 'CL', 'type' => 'paid', 'quota' => 12, 'status' => true]);
        $ltSlStd = LeaveType::create(['tenant_id' => $tenant->id, 'leave_plan_id' => $leavePlanStandard->id, 'name' => 'Sick Leave', 'code' => 'SL', 'type' => 'paid', 'quota' => 12, 'status' => true]);
        $ltElStd = LeaveType::create(['tenant_id' => $tenant->id, 'leave_plan_id' => $leavePlanStandard->id, 'name' => 'Earned Leave', 'code' => 'EL', 'type' => 'paid', 'quota' => 18, 'status' => true]);
        $ltLwp = LeaveType::create(['tenant_id' => $tenant->id, 'leave_plan_id' => $leavePlanStandard->id, 'name' => 'Leave Without Pay', 'code' => 'LWP', 'type' => 'unpaid', 'quota' => 0, 'status' => true]);

        // 6. Production Shifts Master
        $shiftDay = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'General Day Shift (09:00 - 18:00)',
            'code' => 'DAY',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'break_minutes' => 30,
            'overtime_allowed' => true,
            'active' => true,
        ]);

        $shiftMorning = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Plant Morning Shift (06:00 - 14:30)',
            'code' => 'MRN',
            'start_time' => '06:00:00',
            'end_time' => '14:30:00',
            'break_minutes' => 30,
            'overtime_allowed' => false,
            'active' => true,
        ]);

        $shiftEvening = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Plant Evening Shift (14:00 - 22:30)',
            'code' => 'EVE',
            'start_time' => '14:00:00',
            'end_time' => '22:30:00',
            'break_minutes' => 30,
            'overtime_allowed' => true,
            'active' => true,
        ]);

        $shiftNight = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Plant Night Shift (22:00 - 06:30)',
            'code' => 'NGT',
            'start_time' => '22:00:00',
            'end_time' => '06:30:00',
            'break_minutes' => 30,
            'overtime_allowed' => false,
            'active' => true,
        ]);

        // 7. Attendance Rules & Penalization Policy (Per-Branch Master Geofences & Policies)
        AttendanceRule::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buCorporate->id,
            'branch_id' => $branchHq->id,
            'office_biometric' => true,
            'office_web' => true,
            'office_geofence' => true,
            'office_latitude' => 28.62790000,
            'office_longitude' => 77.37250000,
            'office_radius' => 150,
            'office_tracking' => true,
            'office_tracking_minutes' => 60,
            'wfh_location' => true,
            'wfh_selfie' => false,
            'wfh_geofence' => false,
            'wfh_tracking' => false,
            'wfh_tracking_meters' => 500,
            'wfh_tracking_minutes' => 120,
            'site_location' => true,
            'site_selfie' => true,
            'site_geofence' => true,
            'site_tracking' => true,
            'site_tracking_meters' => 300,
            'site_tracking_minutes' => 60,
            'status' => true,
        ]);

        AttendanceRule::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buMfg->id,
            'branch_id' => $branchPune->id,
            'office_biometric' => true,
            'office_web' => true,
            'office_geofence' => true,
            'office_latitude' => 18.52040000,
            'office_longitude' => 73.85670000,
            'office_radius' => 200,
            'office_tracking' => true,
            'office_tracking_minutes' => 60,
            'wfh_location' => true,
            'wfh_selfie' => false,
            'wfh_geofence' => false,
            'wfh_tracking' => false,
            'wfh_tracking_meters' => 500,
            'wfh_tracking_minutes' => 120,
            'site_location' => true,
            'site_selfie' => true,
            'site_geofence' => true,
            'site_tracking' => true,
            'site_tracking_meters' => 300,
            'site_tracking_minutes' => 60,
            'status' => true,
        ]);

        AttendanceRule::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buTech->id,
            'branch_id' => $branchBlr->id,
            'office_biometric' => true,
            'office_web' => true,
            'office_geofence' => true,
            'office_latitude' => 12.97160000,
            'office_longitude' => 77.59460000,
            'office_radius' => 150,
            'office_tracking' => true,
            'office_tracking_minutes' => 60,
            'wfh_location' => true,
            'wfh_selfie' => false,
            'wfh_geofence' => false,
            'wfh_tracking' => false,
            'wfh_tracking_meters' => 500,
            'wfh_tracking_minutes' => 120,
            'site_location' => true,
            'site_selfie' => true,
            'site_geofence' => true,
            'site_tracking' => true,
            'site_tracking_meters' => 300,
            'site_tracking_minutes' => 60,
            'status' => true,
        ]);

        $penaltyLate = AttendancePenalty::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'rule_type' => 'late_arrival',
            'grace_period_minutes' => 15,
            'threshold_count' => 3,
            'penalty_action' => 'deduct_leave',
            'leave_type_id' => $ltClStd->id,
            'penalty_value' => 0.50,
            'status' => true,
            'penalty_tiers' => [
                ['threshold' => 3, 'action' => 'deduct_leave', 'value' => 0.5],
                ['threshold' => 5, 'action' => 'lop', 'value' => 1.0],
            ],
        ]);

        // 8. Holiday Calendar 2026
        $holidays = [
            ['name' => 'New Year Day', 'holiday_date' => '2026-01-01', 'type' => 'public'],
            ['name' => 'Republic Day', 'holiday_date' => '2026-01-26', 'type' => 'national'],
            ['name' => 'Holi', 'holiday_date' => '2026-03-04', 'type' => 'public'],
            ['name' => 'Eid-ul-Fitr', 'holiday_date' => '2026-03-21', 'type' => 'public'],
            ['name' => 'May Day / Labor Day', 'holiday_date' => '2026-05-01', 'type' => 'public'],
            ['name' => 'Independence Day', 'holiday_date' => '2026-08-15', 'type' => 'national'],
            ['name' => 'Mahatma Gandhi Jayanti', 'holiday_date' => '2026-10-02', 'type' => 'national'],
            ['name' => 'Dussehra', 'holiday_date' => '2026-10-20', 'type' => 'public'],
            ['name' => 'Diwali (Deepavali)', 'holiday_date' => '2026-11-08', 'type' => 'public'],
            ['name' => 'Christmas Day', 'holiday_date' => '2026-12-25', 'type' => 'public'],
        ];

        foreach ($holidays as $h) {
            HolidayCalendar::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'name' => $h['name'],
                'holiday_date' => $h['holiday_date'],
                'status' => true,
            ]);
        }

        // 9. Document Master & Templates
        $docCatKyc = DocumentCategory::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Identity Proofs & KYC', 'description' => 'Government photo IDs and identity verifications']);
        $docCatAcad = DocumentCategory::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Academic & Qualifications', 'description' => 'Degrees, diplomas and professional certifications']);
        $docCatExp = DocumentCategory::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Prior Employment Records', 'description' => 'Relieving certificates, payslips, experience letters']);
        $docCatLegal = DocumentCategory::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Legal & Company Agreements', 'description' => 'NDAs, signed offer letters, code of conduct']);

        // Employee-Upload Document Masters (Uploaded by employee from profile -> requires Admin/HR verification/approval)
        $masterAadhaar = DocumentMaster::create([
            'tenant_id'             => $tenant->id,
            'document_category_id'  => $docCatKyc->id,
            'name'                  => 'Aadhaar Card',
            'code'                  => 'AADHAAR',
            'description'           => 'Government of India Unique Identification Card (Aadhaar)',
            'is_required'           => true,
            'upload_responsibility' => 'employee',
            'approval_required'     => true,
            'requires_signature'    => false,
            'expiry_applicable'     => false,
            'employee_can_view'     => true,
            'employee_can_download' => true,
            'status'                => 'active',
        ]);

        $masterPan = DocumentMaster::create([
            'tenant_id'             => $tenant->id,
            'document_category_id'  => $docCatKyc->id,
            'name'                  => 'Permanent Account Number (PAN) Card',
            'code'                  => 'PAN',
            'description'           => 'Income Tax Department PAN Identity Document',
            'is_required'           => true,
            'upload_responsibility' => 'employee',
            'approval_required'     => true,
            'requires_signature'    => false,
            'expiry_applicable'     => false,
            'employee_can_view'     => true,
            'employee_can_download' => true,
            'status'                => 'active',
        ]);

        $masterDegree = DocumentMaster::create([
            'tenant_id'             => $tenant->id,
            'document_category_id'  => $docCatAcad->id,
            'name'                  => 'Graduation / Degree Certificate',
            'code'                  => 'DEGREE',
            'description'           => 'Undergraduate / Postgraduate Degree or Diploma Certificate',
            'is_required'           => true,
            'upload_responsibility' => 'employee',
            'approval_required'     => true,
            'requires_signature'    => false,
            'expiry_applicable'     => false,
            'employee_can_view'     => true,
            'employee_can_download' => true,
            'status'                => 'active',
        ]);

        $masterPassport = DocumentMaster::create([
            'tenant_id'             => $tenant->id,
            'document_category_id'  => $docCatKyc->id,
            'name'                  => 'Passport',
            'code'                  => 'PASSPORT',
            'description'           => 'Republic of India International Travel Passport',
            'is_required'           => false,
            'upload_responsibility' => 'employee',
            'approval_required'     => true,
            'requires_signature'    => false,
            'expiry_applicable'     => true,
            'reminder_days_before'  => 60,
            'employee_can_view'     => true,
            'employee_can_download' => true,
            'status'                => 'active',
        ]);

        $masterRelieving = DocumentMaster::create([
            'tenant_id'             => $tenant->id,
            'document_category_id'  => $docCatExp->id,
            'name'                  => 'Previous Employer Relieving Letter',
            'code'                  => 'RELIEVING_LETTER',
            'description'           => 'Official relieving & experience letter from previous employer',
            'is_required'           => false,
            'upload_responsibility' => 'employee',
            'approval_required'     => true,
            'requires_signature'    => false,
            'expiry_applicable'     => false,
            'employee_can_view'     => true,
            'employee_can_download' => true,
            'status'                => 'active',
        ]);

        // HR-Upload / Generated Document Masters (Uploaded or Generated by HR for Employee)
        $masterNda = DocumentMaster::create([
            'tenant_id'             => $tenant->id,
            'document_category_id'  => $docCatLegal->id,
            'name'                  => 'Signed Non-Disclosure Agreement (NDA)',
            'code'                  => 'NDA',
            'description'           => 'Corporate Confidentiality & Intellectual Property Agreement',
            'is_required'           => true,
            'upload_responsibility' => 'hr',
            'approval_required'     => false,
            'requires_signature'    => true,
            'expiry_applicable'     => false,
            'employee_can_view'     => true,
            'employee_can_download' => true,
            'status'                => 'active',
        ]);

        $masterOffer = DocumentMaster::create([
            'tenant_id'             => $tenant->id,
            'document_category_id'  => $docCatLegal->id,
            'name'                  => 'Employment Offer & Appointment Letter',
            'code'                  => 'OFFER_LETTER',
            'description'           => 'Official employment offer and confirmation letter',
            'is_required'           => true,
            'upload_responsibility' => 'hr',
            'approval_required'     => false,
            'requires_signature'    => true,
            'expiry_applicable'     => false,
            'employee_can_view'     => true,
            'employee_can_download' => true,
            'status'                => 'active',
        ]);

        $masterConduct = DocumentMaster::create([
            'tenant_id'             => $tenant->id,
            'document_category_id'  => $docCatLegal->id,
            'name'                  => 'Employee Code of Conduct & Ethics Policy',
            'code'                  => 'CODE_OF_CONDUCT',
            'description'           => 'Company integrity, workplace conduct, and compliance policy',
            'is_required'           => true,
            'upload_responsibility' => 'hr',
            'approval_required'     => false,
            'requires_signature'    => true,
            'expiry_applicable'     => false,
            'employee_can_view'     => true,
            'employee_can_download' => true,
            'status'                => 'active',
        ]);

        $masterAppraisal = DocumentMaster::create([
            'tenant_id'             => $tenant->id,
            'document_category_id'  => $docCatLegal->id,
            'name'                  => 'Annual Appraisal & Compensation Revision Letter',
            'code'                  => 'APPRAISAL_LETTER',
            'description'           => 'Annual performance appraisal and salary revision notification',
            'is_required'           => false,
            'upload_responsibility' => 'hr',
            'approval_required'     => false,
            'requires_signature'    => false,
            'expiry_applicable'     => false,
            'employee_can_view'     => true,
            'employee_can_download' => true,
            'status'                => 'active',
        ]);

        // Document Template
        $templateOffer = DocumentTemplate::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'document_category_id' => $docCatLegal->id,
            'name' => 'Standard Corporate Employment Offer Letter',
            'code' => 'OFFER_LETTER_STD',
            'header_content' => '<div style="text-align:center;"><h2>Warrgyizmorsch Technologies Pvt Ltd</h2><p>Official Offer of Employment</p></div>',
            'body_content' => '<p>Dear <strong>{{candidate_name}}</strong>,</p><p>We are delighted to extend you an offer for the position of <strong>{{job_title}}</strong> at our <strong>{{branch_name}}</strong> location with an annual compensation package of <strong>INR {{annual_ctc}}</strong>.</p><p>Joining Date: <strong>{{joining_date}}</strong></p>',
            'footer_content' => '<p style="font-size:11px; text-align:center;">Warrgyizmorsch Technologies • Confidential & Proprietary</p>',
            'is_default' => true,
            'requires_signature' => true,
            'status' => 'active',
        ]);

        // 10. Fixed Assets: Categories, Items & Assets
        $catLaptops = AssetCategory::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'IT Hardware - Laptops & Desktops', 'description' => 'Laptops, MacBooks, Workstations']);
        $catDisplays = AssetCategory::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'IT Peripherals & Displays', 'description' => '4K Monitors, Docks, Keyboards']);
        $catFurniture = AssetCategory::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Ergonomic Office Furniture', 'description' => 'Ergonomic chairs, standing motorized desks']);
        $catMachinery = AssetCategory::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Plant Machinery & Instruments', 'description' => 'CNC mills, laser cutters, testing apparatus', 'is_production_machinery' => true]);

        $itemMacBook = AssetItem::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'asset_category_id' => $catLaptops->id, 'name' => 'Apple MacBook Pro M3 16"', 'description' => 'Apple M3 Pro 36GB Unified RAM, 1TB SSD']);
        $itemThinkPad = AssetItem::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'asset_category_id' => $catLaptops->id, 'name' => 'Lenovo ThinkPad X1 Carbon Gen 12', 'description' => 'Intel Core Ultra 7, 32GB RAM, 1TB SSD']);
        $itemDell4k = AssetItem::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'asset_category_id' => $catDisplays->id, 'name' => 'Dell UltraSharp 32" 4K Hub Monitor', 'description' => 'U3223QE 4K IPS Black with 90W USB-C Power Delivery']);
        $itemChair = AssetItem::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'asset_category_id' => $catFurniture->id, 'name' => 'Herman Miller Aeron Ergonomic Chair', 'description' => 'Fully adjustable posture fit SL ergonomic chair']);
        $itemCnc = AssetItem::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'asset_category_id' => $catMachinery->id, 'name' => 'Haas VF-2 5-Axis CNC Milling Center', 'description' => 'High-precision production machining center']);

        $assetMac1 = Asset::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'asset_category_id' => $catLaptops->id, 'asset_item_id' => $itemMacBook->id, 'name' => 'MacBook Pro 16" - Dev Unit 01', 'asset_code' => 'AST-MBP-001', 'serial_number' => 'C02G89A4Q05D', 'purchase_cost' => 249900.00, 'purchase_date' => '2026-05-10', 'warranty_end_date' => '2029-05-10', 'status' => 'available']);
        $assetMac2 = Asset::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'asset_category_id' => $catLaptops->id, 'asset_item_id' => $itemMacBook->id, 'name' => 'MacBook Pro 16" - Dev Unit 02', 'asset_code' => 'AST-MBP-002', 'serial_number' => 'C02G89A4Q05E', 'purchase_cost' => 249900.00, 'purchase_date' => '2026-05-10', 'warranty_end_date' => '2029-05-10', 'status' => 'available']);
        $assetThinkPad1 = Asset::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'asset_category_id' => $catLaptops->id, 'asset_item_id' => $itemThinkPad->id, 'name' => 'ThinkPad X1 Carbon - Mgmt 01', 'asset_code' => 'AST-TP-001', 'serial_number' => 'PF49B7Z1', 'purchase_cost' => 175000.00, 'purchase_date' => '2026-06-01', 'warranty_end_date' => '2029-06-01', 'status' => 'available']);
        $assetDell1 = Asset::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'asset_category_id' => $catDisplays->id, 'asset_item_id' => $itemDell4k->id, 'name' => 'Dell 32" 4K Monitor - Desk 101', 'asset_code' => 'AST-MON-001', 'serial_number' => 'CN-0K793P-74445', 'purchase_cost' => 68000.00, 'purchase_date' => '2026-05-15', 'warranty_end_date' => '2029-05-15', 'status' => 'available']);
        $assetChair1 = Asset::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'asset_category_id' => $catFurniture->id, 'asset_item_id' => $itemChair->id, 'name' => 'Herman Miller Aeron - Desk 101', 'asset_code' => 'AST-CHR-001', 'serial_number' => 'HM-AER-98214', 'purchase_cost' => 110000.00, 'purchase_date' => '2026-05-15', 'warranty_end_date' => '2038-05-15', 'status' => 'available']);
        $assetCnc1 = Asset::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'asset_category_id' => $catMachinery->id, 'asset_item_id' => $itemCnc->id, 'name' => 'Haas 5-Axis Milling Machine - Bay 01', 'asset_code' => 'AST-MCH-001', 'serial_number' => 'HAAS-VF2-2026-01', 'purchase_cost' => 4500000.00, 'purchase_date' => '2026-01-15', 'warranty_end_date' => '2028-01-15', 'status' => 'available']);

        // 11. Expense Master, Policies & Workflows
        $expCatTravel = ExpenseCategory::create(['tenant_id' => $tenant->id, 'name' => 'Flight & Inter-City Travel', 'code' => 'TRAVEL', 'status' => true]);
        $expCatHotel = ExpenseCategory::create(['tenant_id' => $tenant->id, 'name' => 'Hotel & Accommodation', 'code' => 'HOTEL', 'status' => true]);
        $expCatMeals = ExpenseCategory::create(['tenant_id' => $tenant->id, 'name' => 'Client Meals & Daily Allowance', 'code' => 'MEALS', 'status' => true]);
        $expCatLocal = ExpenseCategory::create(['tenant_id' => $tenant->id, 'name' => 'Local Cab & Fuel Conveyance', 'code' => 'CAB_FUEL', 'status' => true]);
        $expCatInternet = ExpenseCategory::create(['tenant_id' => $tenant->id, 'name' => 'Mobile & Broadband Reimbursement', 'code' => 'INTERNET', 'status' => true]);

        $expPolicy = ExpensePolicy::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Standard Corporate Travel & Reimbursement Policy',
            'description' => 'Comprehensive corporate travel limits and expense guidelines',
            'status' => true,
        ]);

        ExpensePolicyRule::create(['expense_policy_id' => $expPolicy->id, 'expense_category_id' => $expCatTravel->id, 'max_monthly_limit' => 50000, 'receipt_required' => true]);
        ExpensePolicyRule::create(['expense_policy_id' => $expPolicy->id, 'expense_category_id' => $expCatHotel->id, 'max_daily_limit' => 7500, 'receipt_required' => true]);
        ExpensePolicyRule::create(['expense_policy_id' => $expPolicy->id, 'expense_category_id' => $expCatMeals->id, 'max_daily_limit' => 1500, 'receipt_required' => false]);
        ExpensePolicyRule::create(['expense_policy_id' => $expPolicy->id, 'expense_category_id' => $expCatLocal->id, 'max_monthly_limit' => 10000, 'receipt_required' => true]);
        ExpensePolicyRule::create(['expense_policy_id' => $expPolicy->id, 'expense_category_id' => $expCatInternet->id, 'max_monthly_limit' => 2000, 'receipt_required' => true]);

        ExpenseApprovalWorkflow::create([
            'tenant_id' => $tenant->id,
            'name' => 'Default Departmental 2-Level Expense Workflow',
            'description' => 'Level 1: Reporting Manager, Level 2: Finance for > 10,000 INR',
            'company_id' => $company->id,
            'approval_type' => '2_level',
            'first_approver' => 'reporting_manager',
            'second_approver' => 'finance',
            'amount_threshold_for_2_level' => 10000,
            'is_default' => true,
            'status' => true,
        ]);

        // 12. Create Full Array of Employees Mapped to Real-World Work Modes & Physical GPS Locations
        // Includes balanced work modes: Office-Based (HQ, Plant), Work-from-Home (WFH), and On-Site (Client Visits / Audits)
        $employeeDefinitions = [
            // 1. Super Admin (Corporate HQ - Noida Office)
            [
                'first' => 'Rajesh', 'last' => 'Singhania', 'email' => 'superadmin@warrg.com',
                'role_slug' => 'super_admin', 'job_title' => 'Chief Executive Officer',
                'dept' => $deptExec->id, 'desig' => $desigCeo->id, 'branch' => $branchHq->id, 'bu' => $buCorporate->id,
                'struct' => $structExec->id, 'pay_group' => $payGroupExec->id, 'leave_plan' => $leavePlanExec->id,
                'salary' => 2800000, 'gender' => 'Male', 'marital' => 'Married', 'blood' => 'O+', 'diet' => 'Veg',
                'doj' => '2026-06-01', 'dob' => '1982-04-12', 'exp' => 16.5, 'mobile' => '+91 98111 00101',
                'city' => 'Noida', 'pan' => 'AAACS1234A', 'aadhaar' => '2345 6789 0001', 'acc' => '5010041234001',
                'work_mode' => 'office', 'wfh_lat' => 28.6385, 'wfh_lng' => 77.3620,
            ],
            // 2. Tenant Owner / VP Operations (Noida Office)
            [
                'first' => 'Aarti', 'last' => 'Mehta', 'email' => 'owner@warrg.com',
                'role_slug' => 'tenant_owner', 'job_title' => 'VP of Operations',
                'dept' => $deptExec->id, 'desig' => $desigVpOps->id, 'branch' => $branchHq->id, 'bu' => $buCorporate->id,
                'struct' => $structExec->id, 'pay_group' => $payGroupExec->id, 'leave_plan' => $leavePlanExec->id,
                'salary' => 2200000, 'gender' => 'Female', 'marital' => 'Married', 'blood' => 'B+', 'diet' => 'Veg',
                'doj' => '2026-06-01', 'dob' => '1986-09-18', 'exp' => 12.0, 'mobile' => '+91 98111 00102',
                'city' => 'Noida', 'pan' => 'AAACM5678B', 'aadhaar' => '2345 6789 0002', 'acc' => '5010041234002',
                'work_mode' => 'office', 'wfh_lat' => 28.5708, 'wfh_lng' => 77.3712,
            ],
            // 3. VP HR & Admin (Noida Office)
            [
                'first' => 'Vikram', 'last' => 'Malhotra', 'email' => 'admin@demo.com',
                'role_slug' => 'company_admin', 'job_title' => 'VP of Human Resources & Admin',
                'dept' => $deptHr->id, 'desig' => $desigVpHr->id, 'branch' => $branchHq->id, 'bu' => $buCorporate->id,
                'struct' => $structExec->id, 'pay_group' => $payGroupExec->id, 'leave_plan' => $leavePlanExec->id,
                'salary' => 1800000, 'gender' => 'Male', 'marital' => 'Married', 'blood' => 'A+', 'diet' => 'Non Veg',
                'doj' => '2026-06-01', 'dob' => '1988-11-22', 'exp' => 10.5, 'mobile' => '+91 98111 00103',
                'city' => 'Noida', 'pan' => 'AAACM9012C', 'aadhaar' => '2345 6789 0003', 'acc' => '5010041234003',
                'work_mode' => 'office', 'wfh_lat' => 28.5835, 'wfh_lng' => 77.3148,
            ],
            // 4. Plant Production Manager (Pune Plant Office)
            [
                'first' => 'Vikramaditya', 'last' => 'Shinde', 'email' => 'prod.manager@warrg.com',
                'role_slug' => 'production_manager', 'job_title' => 'Plant Production Manager',
                'dept' => $deptProd->id, 'desig' => $desigProdMgr->id, 'branch' => $branchPune->id, 'bu' => $buMfg->id,
                'struct' => $structTech->id, 'pay_group' => $payGroupTech->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 1350000, 'gender' => 'Male', 'marital' => 'Married', 'blood' => 'O+', 'diet' => 'Non Veg',
                'doj' => '2026-06-15', 'dob' => '1989-03-14', 'exp' => 9.0, 'mobile' => '+91 98111 00104',
                'city' => 'Pune', 'pan' => 'AAACS3456D', 'aadhaar' => '2345 6789 0004', 'acc' => '5010041234004',
                'work_mode' => 'office', 'wfh_lat' => 18.5074, 'wfh_lng' => 73.8077,
            ],
            // 5. Production Engineer (Pune Plant Office)
            [
                'first' => 'Rohan', 'last' => 'Kulkarni', 'email' => 'prod.engineer@warrg.com',
                'role_slug' => 'production_engineer', 'job_title' => 'Senior Production Engineer',
                'dept' => $deptProd->id, 'desig' => $desigProdEng->id, 'branch' => $branchPune->id, 'bu' => $buMfg->id,
                'struct' => $structStaff->id, 'pay_group' => $payGroupStaff->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 720000, 'gender' => 'Male', 'marital' => 'Single', 'blood' => 'B+', 'diet' => 'Veg',
                'doj' => '2026-07-01', 'dob' => '1996-07-19', 'exp' => 4.0, 'mobile' => '+91 98111 00105',
                'city' => 'Pune', 'pan' => 'AAACK7890E', 'aadhaar' => '2345 6789 0005', 'acc' => '5010041234005',
                'work_mode' => 'office', 'wfh_lat' => 18.6224, 'wfh_lng' => 73.8436,
            ],
            // 6. National Sales Manager (On-Site Client Hubs / Field Visits)
            [
                'first' => 'Ananya', 'last' => 'Sen', 'email' => 'sales.manager@warrg.com',
                'role_slug' => 'sales_manager', 'job_title' => 'National Sales Manager',
                'dept' => $deptSales->id, 'desig' => $desigSalesMgr->id, 'branch' => $branchHq->id, 'bu' => $buCorporate->id,
                'struct' => $structTech->id, 'pay_group' => $payGroupTech->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 1400000, 'gender' => 'Female', 'marital' => 'Single', 'blood' => 'AB+', 'diet' => 'Non Veg',
                'doj' => '2026-06-15', 'dob' => '1991-05-25', 'exp' => 8.0, 'mobile' => '+91 98111 00106',
                'city' => 'Noida', 'pan' => 'AAACS1122F', 'aadhaar' => '2345 6789 0006', 'acc' => '5010041234006',
                'work_mode' => 'onsite', 'wfh_lat' => 28.5482, 'wfh_lng' => 77.2343,
            ],
            // 7. Senior Sales Executive (On-Site Client Visits & Customer Demos)
            [
                'first' => 'Devendra', 'last' => 'Verma', 'email' => 'sales.exec@warrg.com',
                'role_slug' => 'sales_executive', 'job_title' => 'Senior Sales Executive',
                'dept' => $deptSales->id, 'desig' => $desigSalesExec->id, 'branch' => $branchHq->id, 'bu' => $buCorporate->id,
                'struct' => $structStaff->id, 'pay_group' => $payGroupStaff->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 600000, 'gender' => 'Male', 'marital' => 'Single', 'blood' => 'O-', 'diet' => 'Veg',
                'doj' => '2026-07-01', 'dob' => '1997-12-08', 'exp' => 3.5, 'mobile' => '+91 98111 00107',
                'city' => 'Noida', 'pan' => 'AAACV3344G', 'aadhaar' => '2345 6789 0007', 'acc' => '5010041234007',
                'work_mode' => 'onsite', 'wfh_lat' => 28.5912, 'wfh_lng' => 77.2942,
            ],
            // 8. Warehouse & Inventory Manager (Pune Plant Office)
            [
                'first' => 'Manish', 'last' => 'Gupta', 'email' => 'inv.manager@warrg.com',
                'role_slug' => 'inventory_manager', 'job_title' => 'Warehouse & Inventory Manager',
                'dept' => $deptScm->id, 'desig' => $desigInvMgr->id, 'branch' => $branchPune->id, 'bu' => $buMfg->id,
                'struct' => $structTech->id, 'pay_group' => $payGroupTech->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 950000, 'gender' => 'Male', 'marital' => 'Married', 'blood' => 'A+', 'diet' => 'Veg',
                'doj' => '2026-06-15', 'dob' => '1990-08-30', 'exp' => 7.5, 'mobile' => '+91 98111 00108',
                'city' => 'Pune', 'pan' => 'AAACG5566H', 'aadhaar' => '2345 6789 0008', 'acc' => '5010041234008',
                'work_mode' => 'office', 'wfh_lat' => 18.5987, 'wfh_lng' => 73.7686,
            ],
            // 9. Procurement & Purchase Manager (On-Site Vendor / Supplier Plant Audits)
            [
                'first' => 'Sandeep', 'last' => 'Tiwari', 'email' => 'purchase.manager@warrg.com',
                'role_slug' => 'purchase_manager', 'job_title' => 'Procurement & Purchase Manager',
                'dept' => $deptScm->id, 'desig' => $desigPurchMgr->id, 'branch' => $branchPune->id, 'bu' => $buMfg->id,
                'struct' => $structTech->id, 'pay_group' => $payGroupTech->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 1100000, 'gender' => 'Male', 'marital' => 'Married', 'blood' => 'B+', 'diet' => 'Non Veg',
                'doj' => '2026-06-15', 'dob' => '1989-10-15', 'exp' => 8.5, 'mobile' => '+91 98111 00109',
                'city' => 'Pune', 'pan' => 'AAACT7788I', 'aadhaar' => '2345 6789 0009', 'acc' => '5010041234009',
                'work_mode' => 'onsite', 'wfh_lat' => 18.6279, 'wfh_lng' => 73.8009,
            ],
            // 10. HR Manager (Noida Office)
            [
                'first' => 'Sneha', 'last' => 'Rao', 'email' => 'hr.manager@warrg.com',
                'role_slug' => 'hr_manager', 'job_title' => 'HR Manager',
                'dept' => $deptHr->id, 'desig' => $desigHrMgr->id, 'branch' => $branchHq->id, 'bu' => $buCorporate->id,
                'struct' => $structTech->id, 'pay_group' => $payGroupTech->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 1050000, 'gender' => 'Female', 'marital' => 'Single', 'blood' => 'O+', 'diet' => 'Veg',
                'doj' => '2026-06-01', 'dob' => '1992-02-14', 'exp' => 6.5, 'mobile' => '+91 98111 00110',
                'city' => 'Noida', 'pan' => 'AAACR9900J', 'aadhaar' => '2345 6789 0010', 'acc' => '5010041234010',
                'work_mode' => 'office', 'wfh_lat' => 28.6200, 'wfh_lng' => 77.3650,
            ],
            // 11. Senior Financial Accountant (Noida Office)
            [
                'first' => 'Amit', 'last' => 'Patel', 'email' => 'accountant@warrg.com',
                'role_slug' => 'accountant', 'job_title' => 'Senior Financial Accountant',
                'dept' => $deptFin->id, 'desig' => $desigAccountant->id, 'branch' => $branchHq->id, 'bu' => $buCorporate->id,
                'struct' => $structTech->id, 'pay_group' => $payGroupTech->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 900000, 'gender' => 'Male', 'marital' => 'Married', 'blood' => 'A+', 'diet' => 'Veg',
                'doj' => '2026-06-15', 'dob' => '1991-06-20', 'exp' => 7.0, 'mobile' => '+91 98111 00111',
                'city' => 'Noida', 'pan' => 'AAACP1133K', 'aadhaar' => '2345 6789 0011', 'acc' => '5010041234011',
                'work_mode' => 'office', 'wfh_lat' => 28.6401, 'wfh_lng' => 77.3689,
            ],
            // 12. Lead Internal Auditor (On-Site Multi-Branch & Warehouse Audits)
            [
                'first' => 'Neha', 'last' => 'Joshi', 'email' => 'auditor@warrg.com',
                'role_slug' => 'auditor', 'job_title' => 'Lead Internal Auditor',
                'dept' => $deptFin->id, 'desig' => $desigAuditor->id, 'branch' => $branchHq->id, 'bu' => $buCorporate->id,
                'struct' => $structTech->id, 'pay_group' => $payGroupTech->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 1250000, 'gender' => 'Female', 'marital' => 'Married', 'blood' => 'B+', 'diet' => 'Veg',
                'doj' => '2026-06-01', 'dob' => '1989-01-11', 'exp' => 9.5, 'mobile' => '+91 98111 00112',
                'city' => 'Noida', 'pan' => 'AAACJ3355L', 'aadhaar' => '2345 6789 0012', 'acc' => '5010041234012',
                'work_mode' => 'onsite', 'wfh_lat' => 28.5670, 'wfh_lng' => 77.3890,
            ],
            // 13. Compliance & Audit Observer (Pune Plant - QC Department)
            [
                'first' => 'Karan', 'last' => 'Kapoor', 'email' => 'readonly@warrg.com',
                'role_slug' => 'read_only', 'job_title' => 'Compliance & Audit Observer',
                'dept' => $deptQc->id, 'desig' => $desigReadOnly->id, 'branch' => $branchPune->id, 'bu' => $buMfg->id,
                'struct' => $structStaff->id, 'pay_group' => $payGroupStaff->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 540000, 'gender' => 'Male', 'marital' => 'Single', 'blood' => 'AB-', 'diet' => 'Non Veg',
                'doj' => '2026-07-15', 'dob' => '1998-04-05', 'exp' => 2.0, 'mobile' => '+91 98111 00113',
                'city' => 'Pune', 'pan' => 'AAACK5577M', 'aadhaar' => '2345 6789 0013', 'acc' => '5010041234013',
                'work_mode' => 'office', 'wfh_lat' => 18.5204, 'wfh_lng' => 73.8567,
            ],
            // 14. Tech Lead & Architect (Bangalore Tech Center - Engineering)
            [
                'first' => 'Priya', 'last' => 'Nair', 'email' => 'priya@warrg.com',
                'role_slug' => 'tech_lead', 'job_title' => 'Tech Lead & Architect',
                'dept' => $deptEng->id, 'desig' => $desigTechLead->id, 'branch' => $branchBlr->id, 'bu' => $buTech->id,
                'struct' => $structTech->id, 'pay_group' => $payGroupTech->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 1600000, 'gender' => 'Female', 'marital' => 'Married', 'blood' => 'O+', 'diet' => 'Non Veg',
                'doj' => '2026-06-01', 'dob' => '1990-07-28', 'exp' => 8.5, 'mobile' => '+91 98111 00114',
                'city' => 'Bangalore', 'pan' => 'AAACN7799N', 'aadhaar' => '2345 6789 0014', 'acc' => '5010041234014',
                'work_mode' => 'wfh', 'wfh_lat' => 12.9352, 'wfh_lng' => 77.6245,
            ],
            // 15. Senior Software Engineer (Bangalore Tech Center - Engineering)
            [
                'first' => 'Rahul', 'last' => 'Sharma', 'email' => 'rahul@warrg.com',
                'role_slug' => 'software_engineer', 'job_title' => 'Senior Software Engineer',
                'dept' => $deptEng->id, 'desig' => $desigSrSwe->id, 'branch' => $branchBlr->id, 'bu' => $buTech->id,
                'struct' => $structTech->id, 'pay_group' => $payGroupTech->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 1150000, 'gender' => 'Male', 'marital' => 'Single', 'blood' => 'A+', 'diet' => 'Veg',
                'doj' => '2026-06-15', 'dob' => '1995-05-16', 'exp' => 4.5, 'mobile' => '+91 98111 00115',
                'city' => 'Bangalore', 'pan' => 'AAACS9911P', 'aadhaar' => '2345 6789 0015', 'acc' => '5010041234015',
                'work_mode' => 'wfh', 'wfh_lat' => 12.9121, 'wfh_lng' => 77.6446,
            ],
            // 16. HR Operations Specialist (Corporate HQ - Human Resources)
            [
                'first' => 'Pooja', 'last' => 'Bhatt', 'email' => 'pooja@warrg.com',
                'role_slug' => 'hr_executive', 'job_title' => 'HR Operations Specialist',
                'dept' => $deptHr->id, 'desig' => $desigHrOps->id, 'branch' => $branchHq->id, 'bu' => $buCorporate->id,
                'struct' => $structStaff->id, 'pay_group' => $payGroupStaff->id, 'leave_plan' => $leavePlanStandard->id,
                'salary' => 500000, 'gender' => 'Female', 'marital' => 'Single', 'blood' => 'B-', 'diet' => 'Veg',
                'doj' => '2026-07-01', 'dob' => '1998-09-02', 'exp' => 2.5, 'mobile' => '+91 98111 00116',
                'city' => 'Noida', 'pan' => 'AAACB2244Q', 'aadhaar' => '2345 6789 0016', 'acc' => '5010041234016',
                'work_mode' => 'office', 'wfh_lat' => 28.6250, 'wfh_lng' => 77.3710,
            ],
        ];

        /** @var Employee[] $employees */
        $employees = [];

        foreach ($employeeDefinitions as $index => $def) {
            $roleModel = $rolesBySlug->get($def['role_slug']);

            // Create or update User
            $user = User::updateOrCreate(
                ['tenant_id' => $tenant->id, 'email' => $def['email']],
                [
                    'company_id' => $company->id,
                    'branch_id' => $def['branch'],
                    'department_id' => $def['dept'],
                    'role_id' => $roleModel?->id,
                    'name' => $def['first'] . ' ' . $def['last'],
                    'phone' => $def['mobile'],
                    'password' => bcrypt('password'),
                    'role' => $def['role_slug'],
                ]
            );

            // Assign UserRole mapping
            if ($roleModel) {
                UserRole::updateOrCreate(
                    ['user_id' => $user->id, 'tenant_id' => $tenant->id],
                    ['role_id' => $roleModel->id]
                );
            }

            // Determine precise organizational reporting manager hierarchy
            $managerId = null;
            if ($index === 1 || $index === 2 || $index === 11 || $index === 13) {
                // VP Operations, VP HR, Lead Auditor, Tech Lead report directly to CEO
                $managerId = $employees[0]->id ?? null;
            } elseif ($index === 3 || $index === 5 || $index === 7 || $index === 8 || $index === 10) {
                // Plant Manager, Sales Manager, Inventory Manager, Purchase Manager, Senior Accountant report to VP Operations
                $managerId = $employees[1]->id ?? null;
            } elseif ($index === 4 || $index === 12) {
                // Production Engineer, QC Observer report to Plant Production Manager
                $managerId = $employees[3]->id ?? null;
            } elseif ($index === 6) {
                // Sales Executive reports to National Sales Manager
                $managerId = $employees[5]->id ?? null;
            } elseif ($index === 9) {
                // HR Manager reports to VP HR & Admin
                $managerId = $employees[2]->id ?? null;
            } elseif ($index === 14) {
                // Senior Software Engineer reports to Tech Lead & Architect
                $managerId = $employees[13]->id ?? null;
            } elseif ($index === 15) {
                // HR Operations Specialist reports to HR Manager
                $managerId = $employees[9]->id ?? null;
            }

            $empCode = 'WRG-' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT);
            $stage = 'Confirmed';
            if ($index === 12 || $index === 15) {
                $stage = 'Probation';
            } elseif ($index === 4) {
                $stage = 'Notice Period';
            }

            $emp = Employee::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'company_id' => $company->id,
                'business_unit_id' => $def['bu'],
                'branch_id' => $def['branch'],
                'department_id' => $def['dept'],
                'designation_id' => $def['desig'],
                'pay_group_id' => $def['pay_group'],
                'salary_structure_id' => $def['struct'],
                'leave_plan_id' => $def['leave_plan'],
                'attendance_penalty_id' => $penaltyLate->id,
                'reporting_manager_id' => $managerId,
                'shift_id' => $def['branch'] === $branchPune->id ? $shiftMorning->id : $shiftDay->id,
                'weekly_pattern' => [
                    0 => 'off',
                ],

                'employee_id' => $empCode,
                'full_name' => $def['first'] . ' ' . $def['last'],
                'nick_name' => $def['first'],
                'blood_group' => $def['blood'],
                'employee_stage' => $stage,
                'job_title' => $def['job_title'],
                'role' => $def['role_slug'],
                'employment_type' => 'Full-time',
                'date_of_joining' => $def['doj'],
                'date_of_birth' => $def['dob'],
                'probation_end_date' => Carbon::parse($def['doj'])->addMonths(3)->format('Y-m-d'),
                'confirmation_date' => $stage === 'Confirmed' ? Carbon::parse($def['doj'])->addMonths(3)->format('Y-m-d') : null,
                'office' => $def['work_mode'] ?? 'office',
                'wfh_latitude' => $def['wfh_lat'] ?? 28.6280,
                'wfh_longitude' => $def['wfh_lng'] ?? 77.3730,
                'gender' => $def['gender'],
                'marital_status' => $def['marital'],
                'diet_preference' => $def['diet'],
                'aadhaar_card_number' => $def['aadhaar'],
                'pan_card_number' => $def['pan'],
                'photo' => null,

                'present_address' => 'Plot #' . (20 + $index) . ', Tech Park Avenue, Sector 62',
                'permanent_address' => 'House #' . (101 + $index) . ', Green Valley Residency',
                'city' => $def['city'],
                'postal_code' => $def['city'] === 'Pune' ? '411057' : ($def['city'] === 'Bangalore' ? '560100' : '201301'),
                'personal_mobile_number' => $def['mobile'],
                'home_phone' => '011-456789' . str_pad((string)$index, 2, '0', STR_PAD_LEFT),
                'personal_email' => strtolower($def['first']) . '.' . strtolower($def['last']) . '@gmail.com',
                'office_email' => $def['email'],

                'experience' => $def['exp'],
                'source_of_hire' => 'Direct Referral / Leadership Search',
                'skill_set' => 'Leadership, System Architecture, Fullstack Development, Operations, Strategic Planning',
                'current_salary' => $def['salary'],
                'qualification' => 'B.Tech / MCA / MBA Graduate',
                'bank_name' => 'HDFC Bank',
                'account_number' => $def['acc'],
                'ifsc_code' => 'HDFC0001234',
                'emergency_contact_name' => 'Spouse / Family Contact of ' . $def['first'],
                'emergency_contact_number' => '+91 99887 766' . str_pad((string)$index, 2, '0', STR_PAD_LEFT),
                'emergency_contact_relation' => $def['marital'] === 'Married' ? 'Spouse' : 'Parent',

                'status' => true,
                'resume_path' => 'resumes/' . $empCode . '.pdf',
            ]);

            $employees[] = $emp;

            // Setup Leave Balances
            $isExecPlan = ($def['leave_plan'] === $leavePlanExec->id);
            $cl = $isExecPlan ? $ltClExec : $ltClStd;
            $sl = $isExecPlan ? $ltSlExec : $ltSlStd;
            $el = $isExecPlan ? $ltElExec : $ltElStd;

            LeaveBalance::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'employee_id' => $emp->id, 'leave_type_id' => $cl->id, 'allocated' => $cl->quota, 'used' => 0, 'encashed' => 0]);
            LeaveBalance::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'employee_id' => $emp->id, 'leave_type_id' => $sl->id, 'allocated' => $sl->quota, 'used' => 0, 'encashed' => 0]);
            LeaveBalance::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'employee_id' => $emp->id, 'leave_type_id' => $el->id, 'allocated' => $el->quota, 'used' => 0, 'encashed' => 0]);

            // Setup Employment History
            EmployeeEmploymentHistory::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $emp->id,
                'company_name' => 'Previous Global Enterprise Solutions Ltd',
                'designation' => 'Senior Specialist',
                'start_date' => Carbon::parse($def['doj'])->subYears(3)->format('Y-m-d'),
                'end_date' => Carbon::parse($def['doj'])->subDays(15)->format('Y-m-d'),
                'job_description' => 'Managed end-to-end deliverables, system scaling, and cross-functional enterprise workflows.',
            ]);

            // Seed Employee Self-Service Uploaded Documents (Requested/Uploaded by employee from their profile)
            // Aadhaar Card
            $aadhaarStatus = 'approved';
            if ($index === 15) {
                $aadhaarStatus = 'uploaded'; // Pooja Bhatt (Probation) - Pending Admin/HR Approval
            } elseif ($index === 6) {
                $aadhaarStatus = 'rejected'; // Devendra Verma - Admin rejected due to blurry photocopy
            }
            Document::create([
                'tenant_id'          => $tenant->id,
                'documentable_id'    => $emp->id,
                'documentable_type'  => Employee::class,
                'document_master_id' => $masterAadhaar->id,
                'name'               => 'Aadhaar Card - ' . $emp->full_name,
                'description'        => 'Self-uploaded government identity proof',
                'file_name'          => 'aadhaar_' . strtolower($def['first']) . '.pdf',
                'file_path'          => 'documents/aadhaar_' . $empCode . '.pdf',
                'file_type'          => 'application/pdf',
                'file_size'          => 245760,
                'has_expiry'         => false,
                'status'             => $aadhaarStatus,
                'requested_by_id'    => $user->id, // Uploaded by employee
            ]);

            // PAN Card
            $panStatus = ($index === 12) ? 'uploaded' : 'approved'; // Kavita Reddy (Probation) - Pending Verification
            Document::create([
                'tenant_id'          => $tenant->id,
                'documentable_id'    => $emp->id,
                'documentable_type'  => Employee::class,
                'document_master_id' => $masterPan->id,
                'name'               => 'PAN Card - ' . $emp->full_name,
                'description'        => 'Self-uploaded income tax permanent account card',
                'file_name'          => 'pan_' . strtolower($def['first']) . '.pdf',
                'file_path'          => 'documents/pan_' . $empCode . '.pdf',
                'file_type'          => 'application/pdf',
                'file_size'          => 189440,
                'has_expiry'         => false,
                'status'             => $panStatus,
                'requested_by_id'    => $user->id, // Uploaded by employee
            ]);

            // Degree Certificate
            $degreeStatus = in_array($index, [4, 15]) ? 'uploaded' : 'approved'; // Rohan Gupta, Pooja Bhatt - Pending Verification
            Document::create([
                'tenant_id'          => $tenant->id,
                'documentable_id'    => $emp->id,
                'documentable_type'  => Employee::class,
                'document_master_id' => $masterDegree->id,
                'name'               => 'Degree Certificate - ' . $emp->full_name,
                'description'        => 'Self-uploaded educational degree and transcript',
                'file_name'          => 'degree_' . strtolower($def['first']) . '.pdf',
                'file_path'          => 'documents/degree_' . $empCode . '.pdf',
                'file_type'          => 'application/pdf',
                'file_size'          => 412000,
                'has_expiry'         => false,
                'status'             => $degreeStatus,
                'requested_by_id'    => $user->id, // Uploaded by employee
            ]);

            // Passport (Uploaded by senior/travel employees)
            if (in_array($index, [0, 1, 2, 5, 13, 14])) {
                Document::create([
                    'tenant_id'          => $tenant->id,
                    'documentable_id'    => $emp->id,
                    'documentable_type'  => Employee::class,
                    'document_master_id' => $masterPassport->id,
                    'name'               => 'Passport - ' . $emp->full_name,
                    'description'        => 'Self-uploaded international travel passport copy',
                    'file_name'          => 'passport_' . strtolower($def['first']) . '.pdf',
                    'file_path'          => 'documents/passport_' . $empCode . '.pdf',
                    'file_type'          => 'application/pdf',
                    'file_size'          => 320000,
                    'has_expiry'         => true,
                    'expiry_date'        => '2032-10-15',
                    'status'             => 'approved',
                    'requested_by_id'    => $user->id, // Uploaded by employee
                ]);
            }

            // Previous Employer Relieving Letter (Uploaded by lateral hires)
            if (in_array($index, [1, 3, 5, 8, 10, 13, 14])) {
                $relievingStatus = ($index === 10) ? 'uploaded' : 'approved'; // Amit Patel - Pending Verification
                Document::create([
                    'tenant_id'          => $tenant->id,
                    'documentable_id'    => $emp->id,
                    'documentable_type'  => Employee::class,
                    'document_master_id' => $masterRelieving->id,
                    'name'               => 'Previous Employer Relieving Letter - ' . $emp->full_name,
                    'description'        => 'Self-uploaded relieving and work experience certificate',
                    'file_name'          => 'relieving_' . strtolower($def['first']) . '.pdf',
                    'file_path'          => 'documents/relieving_' . $empCode . '.pdf',
                    'file_type'          => 'application/pdf',
                    'file_size'          => 280000,
                    'has_expiry'         => false,
                    'status'             => $relievingStatus,
                    'requested_by_id'    => $user->id, // Uploaded by employee
                ]);
            }
        }

        // Seed HR Uploaded & Issued Documents for employees (Official letters, NDAs, Policies, Appraisals)
        $hrManagerUser = $employees[9]->user; // Sneha Roy (HR Manager)
        $hrManagerUserId = $hrManagerUser?->id ?? $employees[0]->user_id;

        foreach ($employees as $index => $emp) {
            $def = $employeeDefinitions[$index];
            $empCode = $emp->employee_id;

            // 1. Official Employment Offer & Appointment Letter (HR Issued & digitally signed)
            Document::create([
                'tenant_id'          => $tenant->id,
                'documentable_id'    => $emp->id,
                'documentable_type'  => Employee::class,
                'document_master_id' => $masterOffer->id,
                'name'               => 'Official Employment Offer & Appointment Letter - ' . $emp->full_name,
                'description'        => 'Official corporate employment offer letter issued by HR Department',
                'file_name'          => 'offer_letter_' . strtolower($def['first']) . '.pdf',
                'file_path'          => 'documents/offer_' . $empCode . '.pdf',
                'file_type'          => 'application/pdf',
                'file_size'          => 285400,
                'has_expiry'         => false,
                'status'             => 'approved',
                'requested_by_id'    => $hrManagerUserId,
                'requires_signature' => true,
                'is_signed'          => true,
                'signed_at'          => Carbon::parse($def['doj'])->subDays(5),
                'signed_by_id'       => $emp->user_id,
            ]);

            // 2. Signed Non-Disclosure Agreement (NDA)
            Document::create([
                'tenant_id'          => $tenant->id,
                'documentable_id'    => $emp->id,
                'documentable_type'  => Employee::class,
                'document_master_id' => $masterNda->id,
                'name'               => 'Corporate Non-Disclosure Agreement (NDA) - ' . $emp->full_name,
                'description'        => 'Proprietary IP and confidentiality agreement signed upon onboarding',
                'file_name'          => 'nda_' . strtolower($def['first']) . '.pdf',
                'file_path'          => 'documents/nda_' . $empCode . '.pdf',
                'file_type'          => 'application/pdf',
                'file_size'          => 194200,
                'has_expiry'         => false,
                'status'             => 'approved',
                'requested_by_id'    => $hrManagerUserId,
                'requires_signature' => true,
                'is_signed'          => true,
                'signed_at'          => Carbon::parse($def['doj'])->addDay(),
                'signed_by_id'       => $emp->user_id,
            ]);

            // 3. Employee Code of Conduct & Workplace Ethics Policy
            $isProbation = in_array($index, [12, 15]); // Kavita, Pooja
            Document::create([
                'tenant_id'          => $tenant->id,
                'documentable_id'    => $emp->id,
                'documentable_type'  => Employee::class,
                'document_master_id' => $masterConduct->id,
                'name'               => 'Code of Conduct & Workplace Ethics Acknowledgment - ' . $emp->full_name,
                'description'        => 'Enterprise compliance, safety, and workplace ethics agreement',
                'file_name'          => 'code_of_conduct_' . strtolower($def['first']) . '.pdf',
                'file_path'          => 'documents/conduct_' . $empCode . '.pdf',
                'file_type'          => 'application/pdf',
                'file_size'          => 152000,
                'has_expiry'         => false,
                'status'             => $isProbation ? 'pending_signature' : 'approved',
                'requested_by_id'    => $hrManagerUserId,
                'requires_signature' => true,
                'is_signed'          => ! $isProbation,
                'signed_at'          => $isProbation ? null : Carbon::parse($def['doj'])->addDays(2),
                'signed_by_id'       => $isProbation ? null : $emp->user_id,
            ]);

            // 4. Annual Performance Appraisal & Salary Revision (for Senior / Tenured Staff)
            if (in_array($index, [0, 1, 2, 3, 5, 13])) {
                Document::create([
                    'tenant_id'          => $tenant->id,
                    'documentable_id'    => $emp->id,
                    'documentable_type'  => Employee::class,
                    'document_master_id' => $masterAppraisal->id,
                    'name'               => 'FY26 Annual Appraisal & Compensation Revision - ' . $emp->full_name,
                    'description'        => 'Annual performance appraisal and revised salary structure band',
                    'file_name'          => 'appraisal_fy26_' . strtolower($def['first']) . '.pdf',
                    'file_path'          => 'documents/appraisal_' . $empCode . '.pdf',
                    'file_type'          => 'application/pdf',
                    'file_size'          => 164000,
                    'has_expiry'         => false,
                    'status'             => 'approved',
                    'requested_by_id'    => $hrManagerUserId,
                    'requires_signature' => false,
                    'is_signed'          => false,
                ]);
            }
        }

        // 13. Asset Allocations & Requests
        // Physical Allocation 1: Rahul Sharma (Senior SWE) - Active
        AssetAllocation::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $assetMac1->id,
            'employee_id' => $employees[14]->id,
            'allocated_at' => '2026-06-16',
            'allocation_condition' => 'excellent',
        ]);
        $assetMac1->update(['status' => 'allocated', 'assigned_employee_id' => $employees[14]->id]);

        // Physical Allocation 2: Priya (Tech Lead) - Active
        AssetAllocation::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $assetMac2->id,
            'employee_id' => $employees[13]->id,
            'allocated_at' => '2026-06-02',
            'allocation_condition' => 'excellent',
        ]);
        $assetMac2->update(['status' => 'allocated', 'assigned_employee_id' => $employees[13]->id]);

        // Physical Allocation 3: Vikram Malhotra (VP HR) - Active
        AssetAllocation::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $assetThinkPad1->id,
            'employee_id' => $employees[2]->id,
            'allocated_at' => '2026-06-02',
            'allocation_condition' => 'excellent',
        ]);
        $assetThinkPad1->update(['status' => 'allocated', 'assigned_employee_id' => $employees[2]->id]);

        // Physical Allocation 4: Priya (Dell Monitor) - Active
        AssetAllocation::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $assetDell1->id,
            'employee_id' => $employees[13]->id,
            'allocated_at' => '2026-06-05',
            'allocation_condition' => 'excellent',
        ]);
        $assetDell1->update(['status' => 'allocated', 'assigned_employee_id' => $employees[13]->id]);

        // Physical Allocation 5: Priya (Ergonomic Chair) - Active
        AssetAllocation::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $assetChair1->id,
            'employee_id' => $employees[13]->id,
            'allocated_at' => '2026-06-05',
            'allocation_condition' => 'excellent',
        ]);
        $assetChair1->update(['status' => 'allocated', 'assigned_employee_id' => $employees[13]->id]);

        // Physical Allocation 6: Returned Asset Example (Rohan returned temporary test laptop upon project completion)
        AssetAllocation::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $assetMac1->id,
            'employee_id' => $employees[4]->id, // Rohan Kulkarni
            'allocated_at' => '2026-06-01',
            'returned_at' => '2026-06-15',
            'allocation_condition' => 'good',
            'return_condition' => 'good',
            'notes' => 'Temporary test device returned in pristine condition after initial staging setup.',
        ]);

        // Asset Request 1: Rahul Sharma - ALLOCATED / FULFILLED (Req: 1, Allocated: 1, Remaining: 0)
        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employees[14]->id, // Rahul
            'asset_category_id' => $catLaptops->id,
            'asset_item_id' => $itemMacBook->id,
            'quantity' => 1,
            'reason' => 'Development workstation for fullstack SaaS platform engineering.',
            'status' => 'allocated',
            'request_date' => '2026-06-15',
            'allocated_asset_id' => $assetMac1->id,
            'admin_notes' => 'Allocated: AST-MBP-001 on 16 Jun, 2026',
        ]);

        // Asset Request 2: Devendra Verma - APPROVED (Awaiting physical serial allocation: Req: 1, Allocated: 0, Remaining: 1)
        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employees[6]->id, // Devendra
            'asset_category_id' => $catLaptops->id,
            'asset_item_id' => $itemThinkPad->id,
            'quantity' => 1,
            'reason' => 'Need ultraportable laptop for continuous client site visits and customer demos.',
            'status' => 'approved',
            'request_date' => '2026-08-01',
        ]);

        // Asset Request 3: Sneha Patel - PENDING REVIEW (Req: 1, Allocated: 0, Remaining: 1)
        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employees[9]->id, // Sneha (HR Exec)
            'asset_category_id' => $catDisplays->id,
            'asset_item_id' => $itemDell4k->id,
            'quantity' => 1,
            'reason' => 'Secondary display screen for ATS candidate pipeline reviews and document processing.',
            'status' => 'pending',
            'request_date' => '2026-08-20',
        ]);

        // Asset Request 4: Manish Gupta - REJECTED (Declined with reason notes)
        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employees[7]->id, // Manish Gupta
            'asset_category_id' => $catFurniture->id,
            'asset_item_id' => $itemChair->id,
            'quantity' => 1,
            'reason' => 'Requesting additional ergonomic executive chair for plant office.',
            'status' => 'rejected',
            'request_date' => '2026-08-10',
            'admin_notes' => 'Declined: Executive chair policy is limited to designated workstation desks; existing seating is compliant.',
        ]);

        // 14. Biometric Device Master
        $bioDevice = BiometricDevice::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $buTech->id,
            'branch_id' => $branchHq->id,
            'name' => 'HQ Main Lobby Biometric Scanner',
            'device_serial' => 'ZKT-ECO-994820',
            'ip_address' => '192.168.10.50',
            'port' => 4370,
            'status' => true,
            'last_ping_at' => now(),
        ]);

        // 15. Shift Rosters Generation from Joining Date to 2026-10-31
        $today = Carbon::parse('2026-09-24');
        $rosterEnd = Carbon::parse('2026-10-31');

        foreach ($employees as $emp) {
            $curr = Carbon::parse($emp->date_of_joining);
            while ($curr->lte($rosterEnd)) {
                $isPlant = ($emp->branch_id === $branchPune->id);
                $isSunday = ($curr->dayOfWeek === 0);

                $assignedShift = null;
                if (! $isSunday) {
                    $assignedShift = $shiftDay->id;
                    if ($isPlant) {
                        $assignedShift = ($curr->weekOfYear % 2 === 0) ? $shiftMorning->id : $shiftEvening->id;
                    }
                }

                ShiftRoster::create([
                    'tenant_id' => $tenant->id,
                    'employee_id' => $emp->id,
                    'shift_id' => $assignedShift,
                    'date' => $curr->format('Y-m-d'),
                    'status' => 'scheduled',
                ]);

                $curr->addDay();
            }
        }

        // Shift Change Request Sample
        ShiftChangeRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employees[4]->id, // Rohan (Production Engineer)
            'type' => 'temporary',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-20',
            'current_shift_id' => $shiftMorning->id,
            'requested_shift_id' => $shiftEvening->id,
            'reason' => 'Swapping shift for machinery maintenance overhaul support',
            'status' => 'approved',
        ]);

        // 16. Attendance & Map Geolocation Generation (from Joining Date to 2026-09-24)
        // Realistic distribution of Office, WFH, and On-Site coordinates with polyline tracking breadcrumbs
        // Week off is strictly Sunday only (Monday - Saturday are working days)
        $holidayDates = HolidayCalendar::where('tenant_id', $tenant->id)->pluck('holiday_date')->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))->toArray();

        // Branch master coordinates
        $branchCoordinates = [
            $branchHq->id   => ['lat' => 28.62790000, 'lng' => 77.37250000], // Noida Sector 62
            $branchPune->id => ['lat' => 18.52040000, 'lng' => 73.85670000], // Pune Mfg Plant
            $branchBlr->id  => ['lat' => 12.97160000, 'lng' => 77.59460000], // Bangalore Tech Center
        ];

        // Specific Approved WFH Days for specific employees
        $wfhApprovedDays = [
            $employees[14]->id => ['2026-08-10', '2026-08-11', '2026-09-07', '2026-09-08', '2026-09-24'], // Rahul (Senior Dev - WFH today!)
            $employees[9]->id  => ['2026-08-14', '2026-09-18'],                                           // Sneha (HR Manager)
            $employees[2]->id  => ['2026-09-04'],                                                         // Vikram (VP HR)
            $employees[13]->id => ['2026-08-21', '2026-09-18'],                                           // Priya (Tech Lead)
            $employees[10]->id => ['2026-09-07'],                                                         // Amit (Senior Accountant)
        ];

        // Specific Approved Leaves
        $approvedLeaves = [
            $employees[10]->id => ['2026-08-25', '2026-08-26'], // Amit Patel (Casual Leave)
            $employees[15]->id => ['2026-08-05'],               // Pooja Bhatt (Sick Leave)
            $employees[6]->id  => ['2026-08-14'],               // Devendra Verma (Casual Leave)
            $employees[9]->id  => ['2026-09-15'],               // Sneha Roy (Earned Leave)
            $employees[1]->id  => ['2026-09-21'],               // Rajesh Sharma (Casual Leave)
            $employees[12]->id => ['2026-08-11'],               // Kavita Reddy (Sick Leave)
            $employees[4]->id  => ['2026-09-24'],               // Rohan Gupta (Sick Leave - Today On Leave!)
        ];

        // Specific Unplanned Absent Days
        $absentDates = [
            $employees[7]->id  => ['2026-08-12', '2026-09-16', '2026-09-24'], // Karan Sharma (Today Absent!)
            $employees[15]->id => ['2026-08-19', '2026-09-24'],               // Pooja Bhatt (Today Absent!)
            $employees[10]->id => ['2026-09-04', '2026-09-18'],               // Amit Patel
            $employees[6]->id  => ['2026-08-28', '2026-09-09'],               // Devendra Verma
            $employees[8]->id  => ['2026-09-01'],                             // Deepak Joshi
            $employees[11]->id => ['2026-08-21', '2026-09-10'],               // Suresh Iyer
            $employees[12]->id => ['2026-09-07'],                             // Kavita Reddy
            $employees[14]->id => ['2026-08-28'],                             // Rahul Sharma
        ];

        // Specific Late Arrival Days
        $lateArrivalDates = [
            $employees[6]->id  => ['2026-09-08', '2026-09-24'], // Devendra Verma (Today Late!)
            $employees[10]->id => ['2026-08-18', '2026-09-11'], // Amit Patel
            $employees[4]->id  => ['2026-08-14', '2026-09-14'], // Rohan Gupta
            $employees[9]->id  => ['2026-09-02', '2026-09-22'], // Sneha Roy
            $employees[7]->id  => ['2026-09-03'],               // Karan Sharma
            $employees[11]->id => ['2026-08-24', '2026-09-17'], // Suresh Iyer
            $employees[13]->id => ['2026-09-05'],               // Priya Nair
            $employees[3]->id  => ['2026-09-15'],               // Vikramaditya Rao
        ];

        // Specific Half-Day Shifts
        $halfDayDates = [
            $employees[10]->id => ['2026-09-12', '2026-09-24'], // Amit Patel (Today Half-Day!)
            $employees[6]->id  => ['2026-08-22', '2026-09-19'], // Devendra Verma
            $employees[15]->id => ['2026-09-11'],               // Pooja Bhatt
            $employees[9]->id  => ['2026-08-29'],               // Sneha Roy
            $employees[12]->id => ['2026-08-08'],               // Kavita Reddy
            $employees[14]->id => ['2026-09-05'],               // Rahul Sharma
            $employees[8]->id  => ['2026-08-27'],               // Deepak Joshi
        ];

        // Specific Overtime Dates
        $overtimeDates = [
            $employees[14]->id => ['2026-08-15', '2026-09-12'], // Rahul Sharma
            $employees[3]->id  => ['2026-08-29', '2026-09-19'], // Vikramaditya Rao
            $employees[13]->id => ['2026-08-20'],               // Priya Nair
            $employees[11]->id => ['2026-09-05'],               // Suresh Iyer
        ];

        foreach ($employees as $empIndex => $emp) {
            $joiningDate = Carbon::parse($emp->date_of_joining);
            $curr = $joiningDate->copy();

            while ($curr->lte($today)) {
                $dateStr = $curr->format('Y-m-d');
                $dayOfWeek = $curr->dayOfWeek; // 0 = Sun, 1 = Mon, ..., 6 = Sat
                $isToday = ($dateStr === $today->format('Y-m-d'));

                // Week off is strictly Sunday only
                if ($dayOfWeek === 0) {
                    $curr->addDay();
                    continue;
                }

                // Check Overtime flag
                $hasOvertime = isset($overtimeDates[$emp->id]) && in_array($dateStr, $overtimeDates[$emp->id]);

                // Check Holiday (unless employee has approved overtime work)
                if (in_array($dateStr, $holidayDates) && ! $hasOvertime) {
                    $curr->addDay();
                    continue;
                }

                // 1. Check Approved Leave
                $empLeaveDays = $approvedLeaves[$emp->id] ?? [];
                if (in_array($dateStr, $empLeaveDays)) {
                    Attendance::create([
                        'tenant_id'        => $tenant->id,
                        'employee_id'      => $emp->id,
                        'date'             => $dateStr,
                        'check_in'         => Carbon::parse($dateStr . ' 00:00:00'),
                        'check_out'        => null,
                        'location_type'    => null,
                        'status'           => 'on_leave',
                        'total_work_hours' => 0.00,
                        'total_break_hours'=> 0.00,
                    ]);
                    $curr->addDay();
                    continue;
                }

                // 2. Check Absent (unplanned absence)
                $empAbsentDays = $absentDates[$emp->id] ?? [];
                if (in_array($dateStr, $empAbsentDays)) {
                    Attendance::create([
                        'tenant_id'        => $tenant->id,
                        'employee_id'      => $emp->id,
                        'date'             => $dateStr,
                        'check_in'         => Carbon::parse($dateStr . ' 00:00:00'),
                        'check_out'        => null,
                        'location_type'    => null,
                        'status'           => 'absent',
                        'total_work_hours' => 0.00,
                        'total_break_hours'=> 0.00,
                    ]);
                    $curr->addDay();
                    continue;
                }

                // 3. Determine effective location mode for this day (Office / WFH / On-Site)
                $primaryMode = $emp->office ?: 'office'; // 'office', 'wfh', 'onsite'
                $locType = $primaryMode;

                // Specific WFH overrides
                if (isset($wfhApprovedDays[$emp->id]) && in_array($dateStr, $wfhApprovedDays[$emp->id])) {
                    $locType = 'wfh';
                }

                // WFH employees visit branch office on 1st and 15th of the month for all-hands/sprint reviews
                if ($primaryMode === 'wfh' && ($curr->day === 1 || $curr->day === 15)) {
                    $locType = 'office';
                }

                // Ananya (Sales Manager) travel to Mumbai/Pune on Aug 18-21
                if ($emp->id === $employees[5]->id && in_array($dateStr, ['2026-08-18', '2026-08-19', '2026-08-20', '2026-08-21'])) {
                    $locType = 'onsite';
                }

                // 4. Timing & Status Logic
                $status = ($locType === 'wfh') ? 'wfh' : 'present';
                $checkInTime = '08:52:00';
                $checkOutTime = '18:12:00';
                $totalWorkHours = 8.33;
                $breakDuration = 60;
                $hasActiveBreakToday = false;

                // Check Late arrival
                if (isset($lateArrivalDates[$emp->id]) && in_array($dateStr, $lateArrivalDates[$emp->id])) {
                    $checkInTime = '09:35:00';
                    $checkOutTime = '18:10:00';
                    $status = 'late';
                    $totalWorkHours = 7.58;
                }

                // Check Half day
                if (isset($halfDayDates[$emp->id]) && in_array($dateStr, $halfDayDates[$emp->id])) {
                    $checkInTime = '08:55:00';
                    $checkOutTime = '13:15:00';
                    $status = 'half_day';
                    $totalWorkHours = 4.33;
                    $breakDuration = 0;
                }

                // Check Overtime
                if (isset($overtimeDates[$emp->id]) && in_array($dateStr, $overtimeDates[$emp->id])) {
                    $checkInTime = '08:48:00';
                    $checkOutTime = '20:45:00';
                    $status = 'present';
                    $totalWorkHours = 10.95;
                }

                // Live dynamic scenarios for TODAY (2026-09-24 afternoon)
                if ($isToday) {
                    if ($status === 'half_day') {
                        // Half day shift is completed
                        $checkInTime = '08:50:00';
                        $checkOutTime = '13:10:00';
                        $totalWorkHours = 4.33;
                        $breakDuration = 0;
                    } elseif ($status === 'late') {
                        // Late arrival today, currently working
                        $checkInTime = '09:42:00';
                        $checkOutTime = null;
                        $totalWorkHours = 6.97;
                    } elseif ($status === 'wfh') {
                        // WFH today, currently working
                        $checkInTime = '08:55:00';
                        $checkOutTime = null;
                        $totalWorkHours = 7.75;
                    } else {
                        // Regular present today, currently working
                        $checkInTime = '08:50:00';
                        $checkOutTime = null;
                        $totalWorkHours = 7.83;

                        // Deepak Joshi is currently on active lunch break today
                        if ($emp->id === $employees[8]->id) {
                            $hasActiveBreakToday = true;
                        }
                    }
                }

                $checkInDt = Carbon::parse($dateStr . ' ' . $checkInTime);
                $checkOutDt = $checkOutTime ? Carbon::parse($dateStr . ' ' . $checkOutTime) : null;

                // Compute real-world GPS coordinates and tracking points
                $seedHash = crc32($emp->id . '_' . $dateStr);
                $jitterLat = (($seedHash % 40) - 20) * 0.000008;
                $jitterLng = ((($seedHash >> 2) % 40) - 20) * 0.000008;

                $checkInLat = null;
                $checkInLng = null;
                $checkOutLat = null;
                $checkOutLng = null;
                $trackingPoints = [];

                if ($locType === 'office') {
                    $baseBranch = $branchCoordinates[$emp->branch_id] ?? ['lat' => 28.6279, 'lng' => 77.3725];
                    $checkInLat = round($baseBranch['lat'] + $jitterLat, 7);
                    $checkInLng = round($baseBranch['lng'] + $jitterLng, 7);
                    $checkOutLat = $checkOutDt ? round($baseBranch['lat'] + ($jitterLat * 0.5), 7) : null;
                    $checkOutLng = $checkOutDt ? round($baseBranch['lng'] + ($jitterLng * 0.5), 7) : null;

                    $trackingPoints[] = ['lat' => $checkInLat, 'lng' => $checkInLng, 'time' => $checkInDt];
                    $trackingPoints[] = ['lat' => round($baseBranch['lat'] + ($jitterLat * 0.2), 7), 'lng' => round($baseBranch['lng'] + ($jitterLng * 0.2), 7), 'time' => Carbon::parse($dateStr . ' 14:15:00')];
                    if ($checkOutDt) {
                        $trackingPoints[] = ['lat' => $checkOutLat, 'lng' => $checkOutLng, 'time' => $checkOutDt];
                    }
                } elseif ($locType === 'wfh') {
                    $homeLat = (float)($emp->wfh_latitude ?: 28.6280);
                    $homeLng = (float)($emp->wfh_longitude ?: 77.3730);

                    $checkInLat = round($homeLat + $jitterLat, 7);
                    $checkInLng = round($homeLng + $jitterLng, 7);
                    $checkOutLat = $checkOutDt ? round($homeLat + ($jitterLat * 0.3), 7) : null;
                    $checkOutLng = $checkOutDt ? round($homeLng + ($jitterLng * 0.3), 7) : null;

                    $trackingPoints[] = ['lat' => $checkInLat, 'lng' => $checkInLng, 'time' => $checkInDt];
                    $trackingPoints[] = ['lat' => round($homeLat + ($jitterLat * 0.1), 7), 'lng' => round($homeLng + ($jitterLng * 0.1), 7), 'time' => Carbon::parse($dateStr . ' 15:30:00')];
                    if ($checkOutDt) {
                        $trackingPoints[] = ['lat' => $checkOutLat, 'lng' => $checkOutLng, 'time' => $checkOutDt];
                    }
                } elseif ($locType === 'onsite') {
                    // On-site field tracking points (Sales, Procurement & Audit visits)
                    if ($emp->department_id === $deptSales->id) {
                        if ($dateStr >= '2026-08-18' && $dateStr <= '2026-08-21') {
                            $c1Lat = 19.0657; $c1Lng = 72.8685; // BKC Enterprise Hub
                            $c2Lat = 19.0720; $c2Lng = 72.8750; // Client Tech Center
                            $c3Lat = 19.0600; $c3Lng = 72.8620; // Nariman Point Office
                        } else {
                            $c1Lat = 28.4950; $c1Lng = 77.0895; // CyberHub Gate 1
                            $c2Lat = 28.5020; $c2Lng = 77.0850; // Client HQ Tower 4
                            $c3Lat = 28.4890; $c3Lng = 77.0930; // Customer Procurement Office
                        }
                    } elseif ($emp->department_id === $deptScm->id) {
                        $c1Lat = 18.7606; $c1Lng = 73.8567; // Chakan MIDC Phase 2
                        $c2Lat = 18.7645; $c2Lng = 73.8612; // Component Supplier Bay 3
                        $c3Lat = 18.7580; $c3Lng = 73.8520; // Tooling Quality Inspection Lab
                    } else {
                        $c1Lat = 28.6279; $c1Lng = 77.3725; // Noida HQ Central Vault
                        $c2Lat = 28.6310; $c2Lng = 77.3800; // Regional Warehouse Hub
                        $c3Lat = 28.6250; $c3Lng = 77.3690; // Secondary Distribution Depot
                    }

                    $checkInLat = round($c1Lat + $jitterLat, 7);
                    $checkInLng = round($c1Lng + $jitterLng, 7);
                    $checkOutLat = $checkOutDt ? round($c3Lat + $jitterLat, 7) : null;
                    $checkOutLng = $checkOutDt ? round($c3Lng + $jitterLng, 7) : null;

                    $trackingPoints[] = ['lat' => $checkInLat, 'lng' => $checkInLng, 'time' => $checkInDt];
                    $trackingPoints[] = ['lat' => round($c2Lat + $jitterLat, 7), 'lng' => round($c2Lng + $jitterLng, 7), 'time' => Carbon::parse($dateStr . ' 12:30:00')];
                    $trackingPoints[] = ['lat' => round($c3Lat + ($jitterLat * 0.8), 7), 'lng' => round($c3Lng + ($jitterLng * 0.8), 7), 'time' => Carbon::parse($dateStr . ' 15:45:00')];
                    if ($checkOutDt) {
                        $trackingPoints[] = ['lat' => $checkOutLat, 'lng' => $checkOutLng, 'time' => $checkOutDt];
                    }
                }

                $att = Attendance::create([
                    'tenant_id'            => $tenant->id,
                    'employee_id'          => $emp->id,
                    'date'                 => $dateStr,
                    'check_in'             => $checkInDt,
                    'check_out'            => $checkOutDt,
                    'location_type'        => $locType,
                    'status'               => $status,
                    'total_work_hours'     => $totalWorkHours,
                    'total_break_hours'    => $hasActiveBreakToday ? 0.00 : ($breakDuration > 0 ? round($breakDuration / 60, 2) : 0.00),
                    'check_in_latitude'    => $checkInLat,
                    'check_in_longitude'   => $checkInLng,
                    'check_out_latitude'   => $checkOutLat,
                    'check_out_longitude'  => $checkOutLng,
                ]);

                // Attendance Break
                if ($hasActiveBreakToday) {
                    AttendanceBreak::create([
                        'attendance_id'    => $att->id,
                        'break_in'         => Carbon::parse($dateStr . ' 13:15:00'),
                        'break_out'        => null,
                        'duration_minutes' => null,
                    ]);
                } elseif ($breakDuration > 0 && (! $isToday || $status === 'half_day')) {
                    AttendanceBreak::create([
                        'attendance_id'    => $att->id,
                        'break_in'         => Carbon::parse($dateStr . ' 13:00:00'),
                        'break_out'        => Carbon::parse($dateStr . ' 14:00:00'),
                        'duration_minutes' => $breakDuration,
                    ]);
                }

                // Attendance Location Tracking Breadcrumbs (for Google Maps in drawer)
                foreach ($trackingPoints as $pt) {
                    AttendanceLocationLog::create([
                        'tenant_id'     => $tenant->id,
                        'attendance_id' => $att->id,
                        'latitude'      => $pt['lat'],
                        'longitude'     => $pt['lng'],
                        'created_at'    => $pt['time'],
                    ]);
                }

                $curr->addDay();
            }
        }

        // Attendance Correction Samples (Approved & Pending)
        $sampleAtt1 = Attendance::where('employee_id', $employees[10]->id)->where('status', 'late')->first();
        if ($sampleAtt1) {
            AttendanceCorrection::create([
                'tenant_id'           => $tenant->id,
                'employee_id'         => $employees[10]->id,
                'attendance_id'       => $sampleAtt1->id,
                'date'                => $sampleAtt1->date,
                'requested_check_in'  => Carbon::parse($sampleAtt1->date)->setTime(8, 55, 0),
                'requested_check_out' => Carbon::parse($sampleAtt1->date)->setTime(18, 5, 0),
                'reason'              => 'Heavy traffic congestion due to expressway waterlogging.',
                'status'              => 'approved',
                'approved_by'         => $user->id,
            ]);
        }

        $sampleAtt2 = Attendance::where('employee_id', $employees[6]->id)->where('status', 'late')->where('date', '2026-09-08')->first();
        if ($sampleAtt2) {
            AttendanceCorrection::create([
                'tenant_id'           => $tenant->id,
                'employee_id'         => $employees[6]->id,
                'attendance_id'       => $sampleAtt2->id,
                'date'                => $sampleAtt2->date,
                'requested_check_in'  => Carbon::parse($sampleAtt2->date)->setTime(9, 0, 0),
                'requested_check_out' => Carbon::parse($sampleAtt2->date)->setTime(18, 15, 0),
                'reason'              => 'Metro blue line technical delay between Sector 52 and Noida Electronic City.',
                'status'              => 'pending',
            ]);
        }

        // 17. Leaves, Encashments, WFH & Overtime Requests
        // WFH Requests
        WfhRequest::create([
            'tenant_id'   => $tenant->id,
            'company_id'  => $company->id,
            'employee_id' => $employees[14]->id, // Rahul
            'start_date'  => '2026-08-10',
            'end_date'    => '2026-08-11',
            'duration'    => 2,
            'reason'      => 'High-speed fiber installation and system maintenance at residence.',
            'status'      => 'approved',
        ]);

        WfhRequest::create([
            'tenant_id'   => $tenant->id,
            'company_id'  => $company->id,
            'employee_id' => $employees[14]->id, // Rahul
            'start_date'  => '2026-09-07',
            'end_date'    => '2026-09-08',
            'duration'    => 2,
            'reason'      => 'Core architecture sprint delivery and remote pair programming.',
            'status'      => 'approved',
        ]);

        WfhRequest::create([
            'tenant_id'   => $tenant->id,
            'company_id'  => $company->id,
            'employee_id' => $employees[9]->id, // Sneha HR
            'start_date'  => '2026-08-14',
            'end_date'    => '2026-08-14',
            'duration'    => 1,
            'reason'      => 'Home maintenance and delivery coordination.',
            'status'      => 'approved',
        ]);

        WfhRequest::create([
            'tenant_id'   => $tenant->id,
            'company_id'  => $company->id,
            'employee_id' => $employees[9]->id, // Sneha HR
            'start_date'  => '2026-09-18',
            'end_date'    => '2026-09-18',
            'duration'    => 1,
            'reason'      => 'Conducting remote candidate final interviews from home studio.',
            'status'      => 'approved',
        ]);

        WfhRequest::create([
            'tenant_id'   => $tenant->id,
            'company_id'  => $company->id,
            'employee_id' => $employees[2]->id, // Vikram VP HR
            'start_date'  => '2026-09-04',
            'end_date'    => '2026-09-04',
            'duration'    => 1,
            'reason'      => 'Executive board deck preparation and remote strategy review.',
            'status'      => 'approved',
        ]);

        WfhRequest::create([
            'tenant_id'   => $tenant->id,
            'company_id'  => $company->id,
            'employee_id' => $employees[13]->id, // Priya Nair
            'start_date'  => '2026-08-21',
            'end_date'    => '2026-08-21',
            'duration'    => 1,
            'reason'      => 'Deep work on microservices refactoring and benchmark profiling.',
            'status'      => 'approved',
        ]);

        // Approved Leaves
        LeaveRequest::create([
            'tenant_id'     => $tenant->id,
            'company_id'    => $company->id,
            'employee_id'   => $employees[10]->id, // Amit
            'leave_type_id' => $ltClStd->id,
            'start_date'    => '2026-08-25',
            'end_date'      => '2026-08-26',
            'duration'      => 2,
            'reason'        => 'Family annual function and religious ceremony.',
            'status'        => 'approved',
        ]);
        LeaveBalance::where('employee_id', $employees[10]->id)->where('leave_type_id', $ltClStd->id)->increment('used', 2);

        LeaveRequest::create([
            'tenant_id'     => $tenant->id,
            'company_id'    => $company->id,
            'employee_id'   => $employees[15]->id, // Pooja
            'leave_type_id' => $ltSlStd->id,
            'start_date'    => '2026-08-05',
            'end_date'      => '2026-08-05',
            'duration'      => 1,
            'reason'        => 'Medical checkup and viral fever recovery.',
            'status'        => 'approved',
        ]);
        LeaveBalance::where('employee_id', $employees[15]->id)->where('leave_type_id', $ltSlStd->id)->increment('used', 1);

        LeaveRequest::create([
            'tenant_id'     => $tenant->id,
            'company_id'    => $company->id,
            'employee_id'   => $employees[6]->id, // Devendra
            'leave_type_id' => $ltClStd->id,
            'start_date'    => '2026-08-14',
            'end_date'      => '2026-08-14',
            'duration'      => 1,
            'reason'        => 'Long weekend family visit.',
            'status'        => 'approved',
        ]);
        LeaveBalance::where('employee_id', $employees[6]->id)->where('leave_type_id', $ltClStd->id)->increment('used', 1);

        LeaveRequest::create([
            'tenant_id'     => $tenant->id,
            'company_id'    => $company->id,
            'employee_id'   => $employees[9]->id, // Sneha
            'leave_type_id' => $ltElStd->id,
            'start_date'    => '2026-09-15',
            'end_date'      => '2026-09-15',
            'duration'      => 1,
            'reason'        => 'Personal legal and property registry documentation.',
            'status'        => 'approved',
        ]);
        LeaveBalance::where('employee_id', $employees[9]->id)->where('leave_type_id', $ltElStd->id)->increment('used', 1);

        LeaveRequest::create([
            'tenant_id'     => $tenant->id,
            'company_id'    => $company->id,
            'employee_id'   => $employees[1]->id, // Rajesh
            'leave_type_id' => $ltClStd->id,
            'start_date'    => '2026-09-21',
            'end_date'      => '2026-09-21',
            'duration'      => 1,
            'reason'        => 'Attending family wedding reception.',
            'status'        => 'approved',
        ]);
        LeaveBalance::where('employee_id', $employees[1]->id)->where('leave_type_id', $ltClStd->id)->increment('used', 1);

        LeaveRequest::create([
            'tenant_id'     => $tenant->id,
            'company_id'    => $company->id,
            'employee_id'   => $employees[12]->id, // Kavita
            'leave_type_id' => $ltSlStd->id,
            'start_date'    => '2026-08-11',
            'end_date'      => '2026-08-11',
            'duration'      => 1,
            'reason'        => 'Dental surgery and post-operative care.',
            'status'        => 'approved',
        ]);
        LeaveBalance::where('employee_id', $employees[12]->id)->where('leave_type_id', $ltSlStd->id)->increment('used', 1);

        LeaveRequest::create([
            'tenant_id'     => $tenant->id,
            'company_id'    => $company->id,
            'employee_id'   => $employees[4]->id, // Rohan (Today On Leave)
            'leave_type_id' => $ltSlStd->id,
            'start_date'    => '2026-09-24',
            'end_date'      => '2026-09-24',
            'duration'      => 1,
            'reason'        => 'Acute migraine and medical rest.',
            'status'        => 'approved',
        ]);
        LeaveBalance::where('employee_id', $employees[4]->id)->where('leave_type_id', $ltSlStd->id)->increment('used', 1);

        // Pending Leave Request for October
        LeaveRequest::create([
            'tenant_id'     => $tenant->id,
            'company_id'    => $company->id,
            'employee_id'   => $employees[9]->id, // Sneha
            'leave_type_id' => $ltElStd->id,
            'start_date'    => '2026-10-05',
            'end_date'      => '2026-10-09',
            'duration'      => 5,
            'reason'        => 'Annual vacation travel with family.',
            'status'        => 'pending',
        ]);

        LeaveEncashment::create([
            'tenant_id'      => $tenant->id,
            'company_id'     => $company->id,
            'employee_id'    => $employees[13]->id, // Priya
            'leave_type_id'  => $ltElStd->id,
            'requested_days' => 5.0,
            'reason'         => 'Annual leave encashment policy benefit.',
            'status'         => 'approved',
            'approved_by'    => $user->id,
            'approved_at'    => '2026-08-30 14:00:00',
        ]);
        LeaveBalance::where('employee_id', $employees[13]->id)->where('leave_type_id', $ltElStd->id)->increment('encashed', 5);

        // Overtime Requests
        OvertimeRequest::create([
            'tenant_id'         => $tenant->id,
            'company_id'        => $company->id,
            'employee_id'       => $employees[14]->id,
            'date'              => '2026-08-15',
            'start_time'        => '09:00:00',
            'end_time'          => '15:00:00',
            'duration_hours'    => 6,
            'compensation_type' => 'payout',
            'reason'            => 'Critical cloud infrastructure database migration and zero-downtime cutover.',
            'status'            => 'approved',
        ]);

        OvertimeRequest::create([
            'tenant_id'         => $tenant->id,
            'company_id'        => $company->id,
            'employee_id'       => $employees[14]->id,
            'date'              => '2026-09-12',
            'start_time'        => '18:00:00',
            'end_time'          => '21:00:00',
            'duration_hours'    => 3,
            'compensation_type' => 'payout',
            'reason'            => 'Release sprint v2.4 deployment and load testing.',
            'status'            => 'approved',
        ]);

        OvertimeRequest::create([
            'tenant_id'         => $tenant->id,
            'company_id'        => $company->id,
            'employee_id'       => $employees[3]->id, // Vikramaditya
            'date'              => '2026-08-29',
            'start_time'        => '18:00:00',
            'end_time'          => '21:00:00',
            'duration_hours'    => 3,
            'compensation_type' => 'payout',
            'reason'            => 'Overtime supervision for emergency CNC spindle maintenance.',
            'status'            => 'approved',
        ]);

        OvertimeRequest::create([
            'tenant_id'         => $tenant->id,
            'company_id'        => $company->id,
            'employee_id'       => $employees[3]->id, // Vikramaditya
            'date'              => '2026-09-19',
            'start_time'        => '18:00:00',
            'end_time'          => '21:00:00',
            'duration_hours'    => 3,
            'compensation_type' => 'payout',
            'reason'            => 'Weekend plant assembly line throughput calibration.',
            'status'            => 'approved',
        ]);

        OvertimeRequest::create([
            'tenant_id'         => $tenant->id,
            'company_id'        => $company->id,
            'employee_id'       => $employees[13]->id, // Priya
            'date'              => '2026-08-20',
            'start_time'        => '18:30:00',
            'end_time'          => '21:30:00',
            'duration_hours'    => 3,
            'compensation_type' => 'payout',
            'reason'            => 'Enterprise security patch rollout and staging cluster validation.',
            'status'            => 'approved',
        ]);

        OvertimeRequest::create([
            'tenant_id'         => $tenant->id,
            'company_id'        => $company->id,
            'employee_id'       => $employees[11]->id, // Suresh
            'date'              => '2026-09-05',
            'start_time'        => '18:00:00',
            'end_time'          => '21:00:00',
            'duration_hours'    => 3,
            'compensation_type' => 'payout',
            'reason'            => 'Quarterly internal financial reconciliation and tax audit prep.',
            'status'            => 'approved',
        ]);

        // 18. Travel & Expense Management
        $travelReq = TravelRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employees[5]->id, // Ananya Sen (Sales Manager)
            'purpose' => 'Q3 Enterprise Client Demonstrations & Contract Negotiations',
            'destination' => 'Mumbai & Pune Regional Offices',
            'start_date' => '2026-08-18',
            'end_date' => '2026-08-21',
            'estimated_budget' => 35000.00,
            'approved_budget' => 35000.00,
            'status' => 'approved',
        ]);

        CashAdvance::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employees[5]->id,
            'travel_request_id' => $travelReq->id,
            'amount' => 15000.00,
            'approved_amount' => 15000.00,
            'purpose' => 'Advance for local conveyance and customer meetings',
            'status' => 'approved',
            'approval_levels' => 1,
            'current_approval_level' => 1,
            'l1_approved_by' => $user->id,
            'l1_approved_at' => '2026-08-16 11:00:00',
        ]);

        $expReport = ExpenseReport::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employees[5]->id,
            'title' => 'Mumbai-Pune Client Visit Travel Reimbursement',
            'status' => 'approved',
            'total_amount' => 28400.00,
            'advance_adjusted' => 15000.00,
            'net_reimbursement' => 13400.00,
            'approved_amount' => 28400.00,
            'approved_net_reimbursement' => 13400.00,
            'payout_channel' => 'monthly_payroll',
        ]);

        ExpenseClaim::create([
            'expense_report_id' => $expReport->id,
            'expense_category_id' => $expCatTravel->id,
            'expense_date' => '2026-08-18',
            'amount' => 14200.00,
            'receipt_path' => 'expenses/flight_tickets_bom.pdf',
            'description' => 'Round trip air tickets DEL-BOM-DEL (Air India)',
            'status' => 'approved',
            'approved_amount' => 14200.00,
        ]);

        ExpenseClaim::create([
            'expense_report_id' => $expReport->id,
            'expense_category_id' => $expCatHotel->id,
            'expense_date' => '2026-08-20',
            'amount' => 10500.00,
            'receipt_path' => 'expenses/hotel_radisson.pdf',
            'description' => 'Hotel stay 2 nights at Radisson Blu Mumbai',
            'status' => 'approved',
            'approved_amount' => 10500.00,
        ]);

        ExpenseClaim::create([
            'expense_report_id' => $expReport->id,
            'expense_category_id' => $expCatMeals->id,
            'expense_date' => '2026-08-20',
            'amount' => 3700.00,
            'receipt_path' => 'expenses/client_dinner.pdf',
            'description' => 'Business working dinner with enterprise procurement committee',
            'status' => 'approved',
            'approved_amount' => 3700.00,
        ]);

        // 19. Performance Improvement Plan (PIP)
        $pipCatDelivery = PipCategory::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Project Delivery & Velocity', 'code' => 'DELIVERY', 'description' => 'Timely milestone execution and sprint throughput', 'status' => 'active']);
        $pipCatQuality = PipCategory::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Code Quality & System Architecture', 'code' => 'QUALITY', 'description' => 'Code hygiene, test coverage, and documentation', 'status' => 'active']);

        PipPolicyTemplate::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Standard 30-Day Engineering Improvement Plan',
            'duration_days' => 30,
            'checkin_frequency' => 'weekly',
            'description' => 'Structured milestone reviews for engineering output acceleration',
            'status' => 'active',
        ]);

        $pipDev = PerformanceImprovementPlan::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pip_number' => 'PIP-2026-001',
            'employee_id' => $employees[6]->id, // Devendra
            'manager_id' => $employees[5]->id,  // Ananya Sen (Sales Mgr)
            'hr_representative_id' => $user->id,
            'pip_category_id' => $pipCatDelivery->id,
            'reason_category' => 'Lead Conversion & Sales Pipeline Shortfall',
            'reason_details' => 'Target conversion rate fell below 15% in Q2. Structured intervention to improve qualified opportunity closures.',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'duration_days' => 30,
            'checkin_frequency' => 'weekly',
            'status' => 'completed_success',
            'final_outcome' => 'successful_completion',
            'final_comments' => 'Devendra demonstrated remarkable dedication, closed 4 new enterprise accounts, and exceeded pipeline recovery benchmarks.',
            'completed_at' => '2026-08-31 17:00:00',
            'employee_signed_at' => '2026-08-01 10:00:00',
            'manager_signed_at' => '2026-08-01 10:30:00',
            'hr_signed_at' => '2026-08-01 11:00:00',
        ]);

        PipObjective::create([
            'tenant_id' => $tenant->id,
            'pip_id' => $pipDev->id,
            'title' => 'Achieve 20 Validated Enterprise Client Demos',
            'description' => 'Schedule and deliver comprehensive ERP module demonstrations to prospective clients.',
            'target_criteria' => 'Minimum 20 completed demo sessions recorded in CRM.',
            'weightage' => 50.00,
            'status' => 'achieved',
            'manager_remarks' => 'Completed 22 demos with high client engagement.',
        ]);

        PipObjective::create([
            'tenant_id' => $tenant->id,
            'pip_id' => $pipDev->id,
            'title' => 'Convert at least 3 Pipeline Accounts to Paid Quotations',
            'description' => 'Execute high-touch negotiations and secure approved purchase orders.',
            'target_criteria' => 'INR 15 Lakhs in converted sales contracts.',
            'weightage' => 50.00,
            'status' => 'achieved',
            'manager_remarks' => 'Converted 3 major contracts totaling INR 18.5 Lakhs.',
        ]);

        PipCheckin::create([
            'tenant_id' => $tenant->id,
            'pip_id' => $pipDev->id,
            'review_date' => '2026-08-08',
            'reviewer_id' => $user->id,
            'rating_status' => 'on_track',
            'manager_comments' => 'Good initial traction. Continue focusing on follow-up velocity.',
            'employee_comments' => 'Completed 6 product demonstrations; 2 quotations submitted.',
            'action_items' => 'Schedule follow-up calls with top 5 enterprise prospects.',
        ]);

        PipCheckin::create([
            'tenant_id' => $tenant->id,
            'pip_id' => $pipDev->id,
            'review_date' => '2026-08-22',
            'reviewer_id' => $user->id,
            'rating_status' => 'exceeding',
            'manager_comments' => 'Outstanding execution and customer engagement. Exceeded revenue targets.',
            'employee_comments' => 'Closed 3 enterprise accounts with total revenue of INR 18.5 Lakhs.',
            'action_items' => 'Prepare onboarding handoff documents for new accounts.',
        ]);

        // 20. Broadcasts & Announcements
        $b1 = Broadcast::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'broadcast_number' => 'BC-2026-001',
            'title' => 'Q3 2026 Company Town Hall & Strategy Update',
            'category' => 'announcement',
            'priority' => 'important',
            'content' => '<p>Team Warrgyizmorsch,</p><p>We are delighted to invite everyone to our <strong>Q3 2026 Virtual Town Hall</strong> this Friday at 4:00 PM IST. We will share key business milestones, product releases, and recognize top contributors.</p>',
            'target_type' => 'all',
            'is_acknowledgement_required' => true,
            'allow_comments' => true,
            'send_email' => true,
            'show_banner' => true,
            'published_at' => '2026-08-10 09:00:00',
            'status' => 'published',
            'created_by_user_id' => $user->id,
        ]);

        $b2 = Broadcast::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'broadcast_number' => 'BC-2026-002',
            'title' => 'Enhanced Health Insurance & Wellness Benefits 2026-27',
            'category' => 'policy_update',
            'priority' => 'normal',
            'content' => '<p>Dear Colleagues,</p><p>We have upgraded our group medical coverage to include comprehensive OPD coverage, parent health benefits, and mental health wellness counseling sessions.</p>',
            'target_type' => 'all',
            'is_acknowledgement_required' => true,
            'allow_comments' => true,
            'send_email' => true,
            'show_banner' => false,
            'published_at' => '2026-08-20 10:00:00',
            'status' => 'published',
            'created_by_user_id' => $user->id,
        ]);

        foreach ($employees as $emp) {
            BroadcastReceipt::create([
                'tenant_id' => $tenant->id,
                'broadcast_id' => $b1->id,
                'employee_id' => $emp->id,
                'read_at' => '2026-08-10 11:30:00',
                'acknowledged_at' => '2026-08-10 11:35:00',
            ]);

            BroadcastReceipt::create([
                'tenant_id' => $tenant->id,
                'broadcast_id' => $b2->id,
                'employee_id' => $emp->id,
                'read_at' => '2026-08-20 14:00:00',
                'acknowledged_at' => '2026-08-20 14:05:00',
            ]);
        }

        BroadcastComment::create([
            'tenant_id' => $tenant->id,
            'broadcast_id' => $b1->id,
            'employee_id' => $employees[13]->id, // Priya
            'comment_text' => 'Looking forward to the architecture updates and demoing our new multi-tenant SaaS features!',
            'status' => 'published',
        ]);

        BroadcastComment::create([
            'tenant_id' => $tenant->id,
            'broadcast_id' => $b2->id,
            'employee_id' => $employees[5]->id, // Ananya
            'comment_text' => 'Fantastic initiative by HR leadership. The OPD benefit is a huge plus for families!',
            'status' => 'published',
        ]);

        // 21. Recruitment & ATS Master Data
        $reqDev = JobRequisition::create([
            'tenant_id' => $tenant->id,
            'requisition_code' => 'REQ-2026-001',
            'job_title' => 'Senior Full Stack Software Engineer (Laravel / Vue)',
            'department_id' => $deptEng->id,
            'designation_id' => $desigSrSwe->id,
            'vacancies' => 2,
            'min_experience_years' => 4,
            'max_experience_years' => 7,
            'work_mode' => 'hybrid',
            'job_location' => 'Bangalore',
            'employment_type' => 'full_time',
            'priority' => 'high',
            'status' => 'approved',
            'requested_by_employee_id' => $employees[13]->id,
            'approved_by_user_id' => $user->id,
            'approved_at' => '2026-07-10 10:00:00',
            'skills_required' => 'PHP 8.3, Laravel 11, PostgreSQL / MySQL, Vue.js 3, REST APIs, Redis, Docker',
            'job_description' => 'Architect and build robust enterprise modules for our scalable multi-tenant SaaS ERP.',
            'target_joining_date' => '2026-10-01',
        ]);

        $cand1 = Candidate::create([
            'tenant_id' => $tenant->id,
            'candidate_code' => 'CAN-2026-001',
            'first_name' => 'Siddharth',
            'last_name' => 'Menon',
            'email' => 'siddharth.menon@techmail.com',
            'phone' => '+91 98450 12345',
            'current_location' => 'Bangalore',
            'current_company' => 'Infosys Technologies',
            'current_designation' => 'Technology Analyst',
            'total_experience_years' => 5,
            'notice_period_days' => 30,
            'resume_path' => 'resumes/siddharth_menon.pdf',
            'source' => 'linkedin',
            'status' => 'active',
        ]);

        $candApp1 = CandidateApplication::create([
            'tenant_id' => $tenant->id,
            'candidate_id' => $cand1->id,
            'job_requisition_id' => $reqDev->id,
            'current_stage' => 'offer_sent',
            'stage_updated_at' => '2026-08-25 15:00:00',
            'notes' => 'Exceptional system design and hands-on coding interview scores. Recommended for offer.',
        ]);

        $candInterview = CandidateInterview::create([
            'tenant_id' => $tenant->id,
            'application_id' => $candApp1->id,
            'round_number' => 1,
            'round_name' => 'Technical Architecture & Live Coding',
            'scheduled_at' => '2026-08-20 14:00:00',
            'interviewer_employee_id' => $employees[13]->id, // Priya (Tech Lead)
            'meeting_link' => 'https://meet.google.com/xyz-tech-round',
            'venue_location' => 'Bangalore Office / Google Meet',
            'status' => 'completed',
            'round_notes' => 'Strong grasp of database indexing, Eloquent ORM performance, and clean hexagonal architecture.',
        ]);

        InterviewScorecard::create([
            'tenant_id' => $tenant->id,
            'interview_id' => $candInterview->id,
            'interviewer_user_id' => $employees[13]->user_id,
            'technical_rating' => 5,
            'communication_rating' => 5,
            'culture_fit_rating' => 5,
            'overall_rating' => 5,
            'recommendation' => 'pass',
            'feedback_notes' => 'Excellent understanding of SaaS tenant boundaries, distributed locking, and event dispatching.',
        ]);

        JobOffer::create([
            'tenant_id' => $tenant->id,
            'application_id' => $candApp1->id,
            'offer_code' => 'OFR-2026-001',
            'offered_designation_id' => $desigSrSwe->id,
            'offered_department_id' => $deptEng->id,
            'offered_annual_ctc' => 1200000.00,
            'joining_date' => '2026-10-01',
            'status' => 'accepted',
            'accepted_at' => '2026-08-28 12:00:00',
        ]);

        // 22. Helpdesk & IT Service Desk Master Data
        $helpCatIt = HelpdeskCategory::create(['tenant_id' => $tenant->id, 'name' => 'IT Hardware & Workstation Support', 'code' => 'IT_HW', 'description' => 'Laptops, displays, monitors, dock issues, chargers', 'is_active' => true]);
        $helpCatSoftware = HelpdeskCategory::create(['tenant_id' => $tenant->id, 'name' => 'Software Access & Cloud Permissions', 'code' => 'SW_ACCESS', 'description' => 'Git repository access, VPN, cloud server permissions', 'is_active' => true]);
        $helpCatPayroll = HelpdeskCategory::create(['tenant_id' => $tenant->id, 'name' => 'Payroll & Tax Deductions Queries', 'code' => 'PAYROLL_TAX', 'description' => 'Form 16, payslip clarification, tax declaration submission', 'is_active' => true]);
        $helpCatFacilities = HelpdeskCategory::create(['tenant_id' => $tenant->id, 'name' => 'Workplace & Facilities Management', 'code' => 'FACILITIES', 'description' => 'ID access card, parking pass, desk setup, cafeteria', 'is_active' => true]);

        HelpdeskKbArticle::create([
            'tenant_id' => $tenant->id,
            'category_id' => $helpCatIt->id,
            'title' => 'Connecting to Warrgyizmorsch Secure Office Wi-Fi & VPN',
            'slug' => 'connecting-to-office-wifi-and-vpn',
            'content' => '<h3>Warrgyizmorsch Office Network Guide</h3><p>Follow these steps to configure the WPA3-Enterprise Wi-Fi profile on your company MacBook or Windows laptop:</p><ol><li>Select <strong>WRG-Corp-Secure</strong> Wi-Fi SSID.</li><li>Enter your official office email and SSO domain password.</li><li>Accept the root SSL security certificate.</li></ol>',
            'view_count' => 142,
            'is_published' => true,
        ]);

        HelpdeskKbArticle::create([
            'tenant_id' => $tenant->id,
            'category_id' => $helpCatPayroll->id,
            'title' => 'How to Submit Income Tax Declarations & Rent Receipts',
            'slug' => 'submitting-income-tax-declarations-and-rent-receipts',
            'content' => '<h3>Annual Tax Declaration Guidelines</h3><p>Log in to your Employee Self Service portal, navigate to <strong>Payroll > My Tax Declarations</strong>, and upload your 80C, 80D, and HRA rent receipts before the 15th of the month.</p>',
            'view_count' => 289,
            'is_published' => true,
        ]);

        $ticket1 = HelpdeskTicket::create([
            'tenant_id' => $tenant->id,
            'ticket_number' => 'TICK-2026-00001',
            'employee_id' => $employees[14]->id, // Rahul Sharma
            'category_id' => $helpCatSoftware->id,
            'priority' => 'medium',
            'status' => 'resolved',
            'subject' => 'Request for AWS Staging Environment S3 Bucket Read/Write Access',
            'description' => 'Need IAM policy attachment to test multi-tenant document generation and storage uploads on staging cluster.',
            'assigned_to' => $employees[13]->id, // Priya (Tech Lead)
            'due_at' => Carbon::parse('2026-08-12 18:00:00'),
            'resolved_at' => Carbon::parse('2026-08-11 16:30:00'),
            'closed_at' => Carbon::parse('2026-08-12 09:00:00'),
            'is_confidential' => false,
        ]);

        HelpdeskTicketReply::create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket1->id,
            'sender_id' => $employees[13]->id, // Priya
            'message' => 'IAM policy `wrg-s3-staging-documents-rw` has been attached to your developer role. Please verify access via AWS CLI.',
            'is_internal_note' => false,
        ]);

        HelpdeskSatisfactionRating::create([
            'ticket_id' => $ticket1->id,
            'rating' => 5,
            'feedback' => 'Instant resolution within 2 hours. Excellent support!',
        ]);

        // 23. Payroll Processing Data
        $monthsToSeed = ['2026-06', '2026-07', '2026-08'];
        foreach ($monthsToSeed as $pm) {
            $carbonMonth = Carbon::parse($pm . '-01');
            $start = $carbonMonth->copy()->startOfMonth()->format('Y-m-d');
            $end = $carbonMonth->copy()->endOfMonth()->format('Y-m-d');

            PayrollRun::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'payroll_month' => $pm,
                'start_date' => $start,
                'end_date' => $end,
                'status' => 'paid',
                'processed_by' => $user->id,
            ]);
        }

        // Active draft run for current month (2026-09)
        PayrollRun::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'payroll_month' => '2026-09',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'draft',
            'processed_by' => $user->id,
        ]);

        // Salary Revision for Rahul Sharma (Promoted from Software Engineer to Senior SWE)
        SalaryRevision::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employees[14]->id,
            'old_salary_structure_id' => $structStaff->id,
            'new_salary_structure_id' => $structTech->id,
            'effective_date' => '2026-08-01',
            'old_ctc' => 950000.00,
            'new_ctc' => 1150000.00,
            'arrears_paid' => true,
        ]);

        // Employee Adhoc Component (Bonus for Q2 sprint delivery)
        EmployeeAdhocComponent::create([
            'employee_id' => $employees[14]->id,
            'salary_component_id' => $compBonus->id,
            'amount' => 25000.00,
            'payroll_month' => '2026-08',
            'status' => 'processed',
            'remarks' => 'Q2 Outstanding Sprint Delivery & Zero-Defect Release Award',
        ]);

        // 24. Employee Lifecycle: Probation Evaluations & Exit / FnF
        EmployeeProbationEvaluation::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employees[14]->id, // Rahul
            'reviewer_id' => $user->id,
            'evaluation_date' => '2026-08-15',
            'performance_rating' => 5,
            'attendance_rating' => 5,
            'culture_rating' => 5,
            'recommendation' => 'confirm',
            'remarks' => 'Outstanding software development velocity and exceptional code quality.',
            'status' => 'completed',
        ]);

        // Employee Exit & Clearance for Rohan Kulkarni (Production Engineer - Notice Period)
        $sampleExit = EmployeeExit::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employees[4]->id, // Rohan
            'separation_type' => 'resignation',
            'resignation_date' => '2026-08-20',
            'preferred_lwd' => '2026-09-30',
            'approved_lwd' => '2026-09-30',
            'notice_period_days' => 40,
            'notice_shortfall_days' => 0,
            'notice_action' => 'serve',
            'reason_category' => 'Higher Education / Relocation',
            'reason_details' => 'Pursuing Advanced Master of Engineering at University of Stuttgart.',
            'status' => 'in_clearance',
            'initiated_by' => 'employee',
            'approved_by' => $user->id,
            'approved_at' => '2026-08-21 10:00:00',
        ]);

        $clearanceChecklist = [
            ['department' => 'it', 'item_name' => 'Hardware Asset Recovery (Laptop, Docks, Keys)', 'status' => 'pending'],
            ['department' => 'it', 'item_name' => 'Email, ERP & Jira System Logins Revocation', 'status' => 'pending'],
            ['department' => 'admin', 'item_name' => 'Company Physical ID Badge & Plant Entry Pass Handover', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
            ['department' => 'admin', 'item_name' => 'Plant Locker & Tool Storage Key Handover', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
            ['department' => 'finance', 'item_name' => 'Verify Open Cash Advances & Expense Claims Reconciliation', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
            ['department' => 'finance', 'item_name' => 'Notice Period Shortfall / Gratuity Verification', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
            ['department' => 'hr', 'item_name' => 'Exit Interview & Knowledge Transfer Feedback Completed', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
            ['department' => 'manager', 'item_name' => 'Production SOPs, Blueprints & Tooling Handover Sign-off', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
        ];

        foreach ($clearanceChecklist as $item) {
            EmployeeExitClearance::create([
                'tenant_id' => $tenant->id,
                'employee_exit_id' => $sampleExit->id,
                'department' => $item['department'],
                'item_name' => $item['item_name'],
                'status' => $item['status'],
                'cleared_by' => $item['cleared_by'] ?? null,
                'cleared_at' => $item['cleared_at'] ?? null,
            ]);
        }

        // Calculate and save FnF Settlement
        $fnfService = new \App\Domains\HRMS\Services\FnFCalculationService();
        $computedFnF = $fnfService->calculateFnF($sampleExit);
        $fnfService->saveSettlement($sampleExit, $computedFnF);

        $this->command?->info('HrmsDemoSeeder successfully completed! All 13 roles, complete employee forms, attendance history, salary structures, leave plans, fixed assets, recruitment, helpdesk, PIP, broadcasts, and travel expenses are seeded with real-world data.');
    }
}
