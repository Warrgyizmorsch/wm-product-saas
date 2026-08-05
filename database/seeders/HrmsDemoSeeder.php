<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\AttendancePenalty;
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Models\SalaryStructure;
use App\Domains\HRMS\Models\SalaryStructureItem;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeEmploymentHistory;
use App\Domains\HRMS\Models\EmployeePenalty;
use App\Domains\HRMS\Models\EmployeeAdhocComponent;
use App\Domains\HRMS\Models\ShiftRoster;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\AssetItem;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetAllocation;
use App\Domains\HRMS\Models\AssetRequest;
use App\Domains\HRMS\Models\Document;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\LeaveEncashment;
use App\Domains\HRMS\Models\AttendanceRule;
use App\Domains\HRMS\Models\WfhRequest;
use App\Domains\HRMS\Models\ShiftChangeRequest;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Domains\Production\Models\ProductionShift;
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
        Schema::disableForeignKeyConstraints();

        DB::table('wfh_requests')->truncate();
        DB::table('shift_change_requests')->truncate();
        DB::table('overtime_requests')->truncate();
        DB::table('leave_encashments')->truncate();
        DB::table('leave_requests')->truncate();
        DB::table('leave_balances')->truncate();
        DB::table('employee_adhoc_components')->truncate();
        DB::table('employee_employment_histories')->truncate();
        DB::table('employee_penalties')->truncate();
        DB::table('shift_rosters')->truncate();
        DB::table('asset_allocations')->truncate();
        DB::table('asset_requests')->truncate();
        DB::table('assets')->truncate();
        DB::table('asset_items')->truncate();
        DB::table('asset_categories')->truncate();
        DB::table('documents')->truncate();
        DB::table('employees')->truncate();
        DB::table('designations')->truncate();
        DB::table('departments')->truncate();
        DB::table('branches')->truncate();
        DB::table('business_units')->truncate();
        DB::table('salary_structure_items')->truncate();
        DB::table('salary_structures')->truncate();
        DB::table('salary_components')->truncate();
        DB::table('pay_groups')->truncate();
        DB::table('attendance_penalties')->truncate();
        DB::table('leave_types')->truncate();
        DB::table('leave_plans')->truncate();
        DB::table('companies')->truncate();

        Schema::enableForeignKeyConstraints();

        // 1. Fetch Tenant and Admin User
        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (!$tenant) {
            $tenant = Tenant::create([
                'name' => 'Demo Tenant',
                'slug' => config('tenancy.local_fallback_slug', 'demo'),
                'status' => Tenant::STATUS_ACTIVE,
                'plan' => Tenant::PLAN_ENTERPRISE,
                'subscription_status' => Tenant::SUBSCRIPTION_ACTIVE,
                'max_users' => 100,
                'max_storage_mb' => 10240,
                'plan_started_at' => now(),
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
                'settings' => [],
            ]);
        }

        $adminUser = User::where('email', 'admin@example.com')->first();
        if (!$adminUser) {
            $adminUser = User::create([
                'tenant_id' => $tenant->id,
                'name' => 'Demo Admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        // 2. Company
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'company_name' => 'Acme India Pvt Ltd',
            'legal_name' => 'Acme India Private Limited',
            'gst_number' => '29AAAAA1111A1Z1',
            'pan_number' => 'AAAAA1111A',
            'cin_number' => 'U11111KA2026PTC111111',
            'registration_number' => '111111',
            'email' => 'india@acme.com',
            'phone' => '+919876543210',
            'website' => 'https://india.acme.com',
            'address' => '123 Acme Tech Park, Outer Ring Road',
            'city' => 'Bangalore',
            'state' => 'Karnataka',
            'country' => 'India',
            'postal_code' => '560103',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'status' => true,
        ]);

        // 3. Pay Groups
        $payGroupStandard = PayGroup::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Standard Employees Pay Group',
            'description' => 'Default monthly payroll group for general staff.',
            'status' => true,
        ]);

        $payGroupExecutive = PayGroup::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Executive Pay Group',
            'description' => 'Payroll group for management and executives.',
            'status' => true,
        ]);

        // 4. Salary Components (12 Components)
        $salaryComponentsData = [
            ['name' => 'Basic Salary', 'code' => 'BASIC', 'type' => 'earning', 'calculation_type' => 'percentage', 'default_value' => '50', 'description' => 'Base salary component', 'is_adhoc' => false],
            ['name' => 'House Rent Allowance', 'code' => 'HRA', 'type' => 'earning', 'calculation_type' => 'percentage', 'default_value' => '40', 'description' => 'HRA allowance', 'is_adhoc' => false],
            ['name' => 'Special Allowance', 'code' => 'SPL', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_value' => '5000', 'description' => 'Special monthly allowance', 'is_adhoc' => false],
            ['name' => 'Conveyance Allowance', 'code' => 'CONV', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_value' => '1600', 'description' => 'Transport conveyance allowance', 'is_adhoc' => false],
            ['name' => 'Medical Allowance', 'code' => 'MED', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_value' => '1250', 'description' => 'Medical reimbursement allowance', 'is_adhoc' => false],
            ['name' => 'Provident Fund', 'code' => 'PF', 'type' => 'deduction', 'calculation_type' => 'fixed', 'default_value' => '1800', 'description' => 'Employee PF contribution', 'is_adhoc' => false],
            ['name' => 'Professional Tax', 'code' => 'PT', 'type' => 'deduction', 'calculation_type' => 'fixed', 'default_value' => '200', 'description' => 'State professional tax', 'is_adhoc' => false],
            ['name' => 'Tax Deducted at Source', 'code' => 'TDS', 'type' => 'deduction', 'calculation_type' => 'fixed', 'default_value' => '1500', 'description' => 'Income tax deduction', 'is_adhoc' => false],
            ['name' => 'Performance Bonus', 'code' => 'BONUS', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_value' => '0', 'description' => 'Ad-hoc performance bonus', 'is_adhoc' => true],
            ['name' => 'Overtime Allowance', 'code' => 'OT', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_value' => '0', 'description' => 'Ad-hoc overtime payment', 'is_adhoc' => true],
            ['name' => 'Shift Differential Allowance', 'code' => 'SHIFT', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_value' => '0', 'description' => 'Night shift bonus', 'is_adhoc' => true],
            ['name' => 'Salary Advance Deduction', 'code' => 'ADV', 'type' => 'deduction', 'calculation_type' => 'fixed', 'default_value' => '0', 'description' => 'Recovery of salary advance', 'is_adhoc' => true],
        ];

        $basicComp = null; $hraComp = null; $pfComp = null; $bonusComp = null;
        foreach ($salaryComponentsData as $sc) {
            $scObj = SalaryComponent::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'pay_group_id' => $payGroupStandard->id,
                'name' => $sc['name'],
                'code' => $sc['code'],
                'type' => $sc['type'],
                'calculation_type' => $sc['calculation_type'],
                'default_value' => $sc['default_value'],
                'description' => $sc['description'],
                'is_adhoc' => $sc['is_adhoc'],
                'status' => true,
            ]);
            if ($sc['code'] === 'BASIC') $basicComp = $scObj;
            if ($sc['code'] === 'HRA') $hraComp = $scObj;
            if ($sc['code'] === 'PF') $pfComp = $scObj;
            if ($sc['code'] === 'BONUS') $bonusComp = $scObj;
        }

        // 5. Salary Structures & Items (12 Structures)
        $salaryStructuresData = [
            ['name' => 'Standard Developer Structure', 'min_ctc' => 300000.00, 'max_ctc' => 1500000.00],
            ['name' => 'Senior Engineering Lead Structure', 'min_ctc' => 1200000.00, 'max_ctc' => 2500000.00],
            ['name' => 'Executive HR Structure', 'min_ctc' => 600000.00, 'max_ctc' => 1800000.00],
            ['name' => 'Plant Supervisor Structure', 'min_ctc' => 400000.00, 'max_ctc' => 1000000.00],
            ['name' => 'Machine Operator Grade 1 Structure', 'min_ctc' => 250000.00, 'max_ctc' => 500000.00],
            ['name' => 'QA Inspector Structure', 'min_ctc' => 350000.00, 'max_ctc' => 800000.00],
            ['name' => 'Enterprise Sales Commission Structure', 'min_ctc' => 500000.00, 'max_ctc' => 2000000.00],
            ['name' => 'Financial Analyst Structure', 'min_ctc' => 450000.00, 'max_ctc' => 1100000.00],
            ['name' => 'IT Admin & Infrastructure Structure', 'min_ctc' => 400000.00, 'max_ctc' => 900000.00],
            ['name' => 'Supply Chain Manager Structure', 'min_ctc' => 600000.00, 'max_ctc' => 1400000.00],
            ['name' => 'Customer Success Specialist Structure', 'min_ctc' => 300000.00, 'max_ctc' => 700000.00],
            ['name' => 'Legal & Compliance Officer Structure', 'min_ctc' => 800000.00, 'max_ctc' => 2200000.00],
        ];

        $salaryStructureStandard = null;
        foreach ($salaryStructuresData as $idx => $ss) {
            $ssObj = SalaryStructure::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'pay_group_id' => ($idx % 2 === 0) ? $payGroupStandard->id : $payGroupExecutive->id,
                'name' => $ss['name'],
                'min_ctc' => $ss['min_ctc'],
                'max_ctc' => $ss['max_ctc'],
                'status' => true,
            ]);
            if ($idx === 0) $salaryStructureStandard = $ssObj;

            SalaryStructureItem::create([
                'tenant_id' => $tenant->id,
                'salary_structure_id' => $ssObj->id,
                'salary_component_id' => $basicComp->id,
                'calculation_type' => 'percentage_of_ctc',
                'value' => 50.00,
                'sort_order' => 1,
            ]);
            SalaryStructureItem::create([
                'tenant_id' => $tenant->id,
                'salary_structure_id' => $ssObj->id,
                'salary_component_id' => $hraComp->id,
                'calculation_type' => 'percentage_of_basic',
                'value' => 40.00,
                'sort_order' => 2,
            ]);
            SalaryStructureItem::create([
                'tenant_id' => $tenant->id,
                'salary_structure_id' => $ssObj->id,
                'salary_component_id' => $pfComp->id,
                'calculation_type' => 'fixed',
                'value' => 1800.00,
                'sort_order' => 3,
            ]);
        }

        // 6. Leave Plans & Leave Types (12 Leave Types)
        $leavePlan = LeavePlan::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Standard India Leave Plan 2026',
            'effective_from' => '2026-01-01',
            'description' => 'Default annual leave policy for Indian employees.',
            'status' => true,
        ]);

        $leaveTypesData = [
            ['name' => 'Sick Leave', 'code' => 'SL', 'description' => 'For medical recovery and emergencies.', 'type' => 'paid', 'color' => '#ef4444', 'quota' => 10.0],
            ['name' => 'Casual Leave', 'code' => 'CL', 'description' => 'For personal and unplanned events.', 'type' => 'paid', 'color' => '#f59e0b', 'quota' => 8.0],
            ['name' => 'Earned / Privilege Leave', 'code' => 'EL', 'description' => 'Annual accrued paid leave.', 'type' => 'paid', 'color' => '#3b82f6', 'quota' => 15.0],
            ['name' => 'Maternity Leave', 'code' => 'ML', 'description' => 'Paid maternity leave for female employees.', 'type' => 'paid', 'color' => '#ec4899', 'quota' => 26.0],
            ['name' => 'Paternity Leave', 'code' => 'PL', 'description' => 'Paid leave for new fathers.', 'type' => 'paid', 'color' => '#8b5cf6', 'quota' => 5.0],
            ['name' => 'Marriage Leave', 'code' => 'MAR', 'description' => 'Special leave for employee wedding.', 'type' => 'paid', 'color' => '#10b981', 'quota' => 5.0],
            ['name' => 'Bereavement Leave', 'code' => 'BL', 'description' => 'Leave during family bereavement.', 'type' => 'paid', 'color' => '#64748b', 'quota' => 5.0],
            ['name' => 'Compensatory Off', 'code' => 'CO', 'description' => 'Compensatory leave for weekend work.', 'type' => 'paid', 'color' => '#06b6d4', 'quota' => 6.0],
            ['name' => 'Study / Exam Leave', 'code' => 'ST', 'description' => 'Leave for higher education exams.', 'type' => 'paid', 'color' => '#84cc16', 'quota' => 7.0],
            ['name' => 'Sabbatical Leave', 'code' => 'SAB', 'description' => 'Extended unpaid career break.', 'type' => 'unpaid', 'color' => '#475569', 'quota' => 30.0],
            ['name' => 'Quarantine Emergency Leave', 'code' => 'QAR', 'description' => 'Special health isolation leave.', 'type' => 'paid', 'color' => '#14b8a6', 'quota' => 14.0],
            ['name' => 'Loss of Pay', 'code' => 'LOP', 'description' => 'Unpaid leave when quota is exhausted.', 'type' => 'unpaid', 'color' => '#94a3b8', 'quota' => 0.0],
        ];

        $leaveSick = null; $leaveCasual = null;
        foreach ($leaveTypesData as $lt) {
            $ltObj = LeaveType::create([
                'tenant_id' => $tenant->id,
                'leave_plan_id' => $leavePlan->id,
                'name' => $lt['name'],
                'code' => $lt['code'],
                'description' => $lt['description'],
                'type' => $lt['type'],
                'color' => $lt['color'],
                'quota' => $lt['quota'],
                'rules' => null,
                'status' => true,
            ]);
            if ($lt['code'] === 'SL') $leaveSick = $ltObj;
            if ($lt['code'] === 'CL') $leaveCasual = $ltObj;
        }

        // 7. Attendance Penalties
        $attendancePenalty = AttendancePenalty::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'rule_type' => 'late_arrival',
            'grace_period_minutes' => 15,
            'threshold_count' => 3,
            'penalty_action' => 'salary_deduction',
            'leave_type_id' => null,
            'penalty_value' => 0.50,
            'penalty_tiers' => [
                ['late_minutes_min' => 16, 'late_minutes_max' => 60, 'penalty' => 0.25],
                ['late_minutes_min' => 61, 'late_minutes_max' => 180, 'penalty' => 0.50],
            ],
            'status' => true,
        ]);

        // 8. Organizational Structure (Business Units, Branches, 12 Departments, 12 Designations)
        $buManufacturing = BusinessUnit::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Manufacturing Business Unit',
            'code' => 'BU-MFG',
            'description' => 'Core production and supply chain division.',
            'head_employee_id' => null,
            'status' => true,
        ]);

        $buServices = BusinessUnit::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Professional Services',
            'code' => 'BU-SRV',
            'description' => 'Consulting and client engineering group.',
            'head_employee_id' => null,
            'status' => true,
        ]);

        $branchHQ = Branch::create([
            'tenant_id' => $tenant->id,
            'business_unit_id' => $buServices->id,
            'company_id' => $company->id,
            'name' => 'Corporate HQ Bangalore',
            'code' => 'BR-BLR-HQ',
            'manager_employee_id' => null,
            'phone' => '+91801234567',
            'email' => 'hq-blr@acme.com',
            'address' => 'Level 5, Acme Tower, Tech Park',
            'city' => 'Bangalore',
            'state' => 'Karnataka',
            'country' => 'India',
            'postal_code' => '560103',
            'status' => true,
        ]);

        $branchFactory = Branch::create([
            'tenant_id' => $tenant->id,
            'business_unit_id' => $buManufacturing->id,
            'company_id' => $company->id,
            'name' => 'Mumbai Factory & Depot',
            'code' => 'BR-BOM-FAC',
            'manager_employee_id' => null,
            'phone' => '+91227654321',
            'email' => 'factory-bom@acme.com',
            'address' => 'Plot 45, MIDC Industrial Area',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'postal_code' => '400059',
            'status' => true,
        ]);

        // 12 Departments
        $deptHR = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchHQ->id, 'company_id' => $company->id, 'business_unit_id' => $buServices->id, 'name' => 'Human Resources', 'code' => 'DEPT-HR', 'head_employee_id' => null, 'description' => 'Talent acquisition, payroll, and employee success.', 'status' => true]);
        $deptProd = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchFactory->id, 'company_id' => $company->id, 'business_unit_id' => $buManufacturing->id, 'name' => 'Production & Assembly', 'code' => 'DEPT-PROD', 'head_employee_id' => null, 'description' => 'Assembly line workers and shop floor management.', 'status' => true]);
        $deptEng = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchHQ->id, 'company_id' => $company->id, 'business_unit_id' => $buServices->id, 'name' => 'Engineering & R&D', 'code' => 'DEPT-ENG', 'head_employee_id' => null, 'description' => 'Product development, architecture, and software engineering.', 'status' => true]);
        $deptQA = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchFactory->id, 'company_id' => $company->id, 'business_unit_id' => $buManufacturing->id, 'name' => 'Quality Assurance & Audit', 'code' => 'DEPT-QA', 'head_employee_id' => null, 'description' => 'Quality control, inspections, and compliance audits.', 'status' => true]);
        $deptSales = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchHQ->id, 'company_id' => $company->id, 'business_unit_id' => $buServices->id, 'name' => 'Sales & Marketing', 'code' => 'DEPT-MKT', 'head_employee_id' => null, 'description' => 'Client acquisition, enterprise sales, and branding.', 'status' => true]);
        $deptFinance = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchHQ->id, 'company_id' => $company->id, 'business_unit_id' => $buServices->id, 'name' => 'Finance & Accounts', 'code' => 'DEPT-FIN', 'head_employee_id' => null, 'description' => 'Financial reporting, tax management, and treasury.', 'status' => true]);
        $deptIT = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchHQ->id, 'company_id' => $company->id, 'business_unit_id' => $buServices->id, 'name' => 'Information Technology', 'code' => 'DEPT-IT', 'head_employee_id' => null, 'description' => 'IT support, server infrastructure, and network security.', 'status' => true]);
        $deptSCM = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchFactory->id, 'company_id' => $company->id, 'business_unit_id' => $buManufacturing->id, 'name' => 'Supply Chain & Logistics', 'code' => 'DEPT-SCM', 'head_employee_id' => null, 'description' => 'Vendor management, warehouse inventory, and shipping.', 'status' => true]);
        $deptSupport = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchHQ->id, 'company_id' => $company->id, 'business_unit_id' => $buServices->id, 'name' => 'Customer Support & Success', 'code' => 'DEPT-CS', 'head_employee_id' => null, 'description' => 'Customer service, technical support desk, and SLAs.', 'status' => true]);
        $deptLegal = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchHQ->id, 'company_id' => $company->id, 'business_unit_id' => $buServices->id, 'name' => 'Legal & Compliance', 'code' => 'DEPT-LGL', 'head_employee_id' => null, 'description' => 'Corporate law, contracts, regulatory compliance, and risk.', 'status' => true]);
        $deptMaint = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchFactory->id, 'company_id' => $company->id, 'business_unit_id' => $buManufacturing->id, 'name' => 'Plant Maintenance', 'code' => 'DEPT-MNT', 'head_employee_id' => null, 'description' => 'Machine repairs, preventive maintenance, and plant safety.', 'status' => true]);
        $deptProc = Department::create(['tenant_id' => $tenant->id, 'branch_id' => $branchFactory->id, 'company_id' => $company->id, 'business_unit_id' => $buManufacturing->id, 'name' => 'Procurement & Sourcing', 'code' => 'DEPT-PROC', 'head_employee_id' => null, 'description' => 'Raw material acquisition, vendor negotiation, and purchase orders.', 'status' => true]);

        // 12 Designations
        $desigHRManager = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptHR->id, 'name' => 'HR Manager', 'level' => 'L4', 'description' => 'Heads human resources operations.', 'status' => true]);
        $desigProdLead = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptProd->id, 'name' => 'Production Lead', 'level' => 'L3', 'description' => 'Supervises shop floor shifts.', 'status' => true]);
        $desigOperator = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptProd->id, 'name' => 'Machine Operator', 'level' => 'L1', 'description' => 'Runs assembly machines and welds parts.', 'status' => true]);
        $desigEngLead = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptEng->id, 'name' => 'Senior Full Stack Lead', 'level' => 'L4', 'description' => 'Architects core software applications.', 'status' => true]);
        $desigQAManager = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptQA->id, 'name' => 'QA Manager', 'level' => 'L4', 'description' => 'Directs quality control procedures.', 'status' => true]);
        $desigSalesExec = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptSales->id, 'name' => 'Enterprise Sales Executive', 'level' => 'L2', 'description' => 'Drives B2B revenue and client deals.', 'status' => true]);
        $desigFinAnalyst = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptFinance->id, 'name' => 'Senior Financial Analyst', 'level' => 'L3', 'description' => 'Manages budgets and payroll audits.', 'status' => true]);
        $desigSysAdmin = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptIT->id, 'name' => 'IT Systems Administrator', 'level' => 'L2', 'description' => 'Maintains corporate IT infrastructure.', 'status' => true]);
        $desigSCMManager = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptSCM->id, 'name' => 'Supply Chain Manager', 'level' => 'L4', 'description' => 'Oversees warehouse logistics and freight.', 'status' => true]);
        $desigCustLead = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptSupport->id, 'name' => 'Customer Success Lead', 'level' => 'L3', 'description' => 'Manages key client relationships.', 'status' => true]);
        $desigLegalCounsel = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptLegal->id, 'name' => 'Legal Counsel', 'level' => 'L4', 'description' => 'Handles corporate legal matters.', 'status' => true]);
        $desigProcExec = Designation::create(['tenant_id' => $tenant->id, 'department_id' => $deptProc->id, 'name' => 'Senior Procurement Executive', 'level' => 'L3', 'description' => 'Negotiates vendor pricing and POs.', 'status' => true]);

        // Production Shifts (12 Shifts)
        $shiftsData = [
            ['name' => 'Day Shift (Standard)', 'code' => 'SHIFT-DAY', 'start_time' => '08:00:00', 'end_time' => '16:00:00'],
            ['name' => 'Morning Shift A', 'code' => 'SHIFT-AM', 'start_time' => '06:00:00', 'end_time' => '14:30:00'],
            ['name' => 'Afternoon Shift B', 'code' => 'SHIFT-PM', 'start_time' => '14:00:00', 'end_time' => '22:30:00'],
            ['name' => 'Night Shift C', 'code' => 'SHIFT-NIGHT', 'start_time' => '22:00:00', 'end_time' => '06:30:00'],
            ['name' => 'Corporate General Shift', 'code' => 'SHIFT-GEN', 'start_time' => '09:00:00', 'end_time' => '18:00:00'],
            ['name' => 'Flexible Executive Shift', 'code' => 'SHIFT-FLEX', 'start_time' => '10:00:00', 'end_time' => '19:00:00'],
            ['name' => 'Early Dispatch Shift', 'code' => 'SHIFT-DISP', 'start_time' => '05:00:00', 'end_time' => '13:30:00'],
            ['name' => 'Plant Maintenance Shift', 'code' => 'SHIFT-MNT', 'start_time' => '07:00:00', 'end_time' => '15:30:00'],
            ['name' => 'QA Overtime Shift', 'code' => 'SHIFT-QA-OT', 'start_time' => '16:00:00', 'end_time' => '00:30:00'],
            ['name' => 'Warehouse Night Duty', 'code' => 'SHIFT-WH-N', 'start_time' => '23:00:00', 'end_time' => '07:30:00'],
            ['name' => 'Weekend Special Shift', 'code' => 'SHIFT-WKND', 'start_time' => '09:00:00', 'end_time' => '21:00:00'],
            ['name' => 'Executive Mid-Day Shift', 'code' => 'SHIFT-MID', 'start_time' => '11:00:00', 'end_time' => '20:00:00'],
        ];

        $productionShift = null;
        foreach ($shiftsData as $idx => $sData) {
            $shiftObj = ProductionShift::create([
                'tenant_id' => $tenant->id,
                'name' => $sData['name'],
                'code' => $sData['code'],
                'start_time' => $sData['start_time'],
                'end_time' => $sData['end_time'],
                'active' => true,
            ]);
            if ($idx === 0) {
                $productionShift = $shiftObj;
            }
        }

        $dbUsers = \App\Models\User::all();
        if ($dbUsers->count() < 12) {
            $needed = 12 - $dbUsers->count();
            for ($i = 1; $i <= $needed; $i++) {
                \App\Models\User::firstOrCreate(
                    [
                        'email' => 'demouser' . $i . '@example.com',
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'name' => 'Demo User ' . $i,
                        'password' => bcrypt('password'),
                    ]
                );
            }
            $dbUsers = \App\Models\User::all()->take(15);
        } else {
            $dbUsers = $dbUsers->take(15);
        }
        $employeesList = [];
        
        $designations = [
            $desigHRManager, $desigProdLead, $desigOperator, $desigEngLead, 
            $desigQAManager, $desigSalesExec, $desigFinAnalyst, $desigSysAdmin,
            $desigSCMManager, $desigCustLead, $desigLegalCounsel, $desigProcExec
        ];

        $departments = [
            $deptHR, $deptProd, $deptProd, $deptEng,
            $deptQA, $deptSales, $deptFinance, $deptIT,
            $deptSCM, $deptSupport, $deptLegal, $deptProc
        ];

        $idx = 0;
        foreach ($dbUsers as $user) {
            $desig = $designations[$idx % count($designations)];
            $dept = $departments[$idx % count($departments)];

            $empObj = Employee::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'employee_id' => 'EMP-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT),
                'company_id' => $company->id,
                'business_unit_id' => ($idx % 2 === 0) ? $buServices->id : $buManufacturing->id,
                'branch_id' => ($idx % 2 === 0) ? $branchHQ->id : $branchFactory->id,
                'department_id' => $dept->id,
                'designation_id' => $desig->id,
                'pay_group_id' => ($idx < 3) ? $payGroupExecutive->id : $payGroupStandard->id,
                'salary_structure_id' => $salaryStructureStandard->id,
                'leave_plan_id' => $leavePlan->id,
                'attendance_penalty_id' => $attendancePenalty->id,
                'reporting_manager_id' => ($idx > 0) ? $employeesList[0]->id : null,
                'shift_id' => $productionShift->id,
                'full_name' => $user->name,
                'nick_name' => explode(' ', $user->name)[0],
                'blood_group' => 'O+',
                'employee_stage' => 'Confirmed',
                'job_title' => $desig->name,
                'role' => 'Employee',
                'employment_type' => 'Full-time',
                'date_of_joining' => '2023-01-15',
                'date_of_birth' => '1992-05-10',
                'probation_end_date' => '2023-07-15',
                'confirmation_date' => '2023-07-15',
                'office' => ($idx % 3 === 0) ? 'office' : (($idx % 3 === 1) ? 'wfh' : 'onsite'),
                'gender' => ($idx % 2 === 0) ? 'Male' : 'Female',
                'marital_status' => 'Single',
                'diet_preference' => 'Veg',
                'aadhaar_card_number' => '1111-2222-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT),
                'pan_card_number' => 'ABCDE' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT) . 'F',
                'present_address' => 'Sample Address ' . ($idx + 1),
                'permanent_address' => 'Sample Address ' . ($idx + 1),
                'city' => 'Bangalore',
                'postal_code' => '560001',
                'personal_mobile_number' => '+9198000000' . str_pad($idx + 1, 2, '0', STR_PAD_LEFT),
                'personal_email' => $user->email,
                'office_email' => $user->email,
                'experience' => 5.0 + $idx,
                'source_of_hire' => 'Internal',
                'skill_set' => 'Management, Operations, Domain Expertise',
                'current_salary' => 50000.00 + ($idx * 5000),
                'qualification' => 'Bachelor Degree',
                'bank_name' => 'HDFC Bank',
                'account_number' => '501000' . str_pad($idx + 1, 8, '0', STR_PAD_LEFT),
                'ifsc_code' => 'HDFC0000123',
                'emergency_contact_name' => 'Emergency Contact ' . ($idx + 1),
                'emergency_contact_number' => '+9198999900' . str_pad($idx + 1, 2, '0', STR_PAD_LEFT),
                'emergency_contact_relation' => 'Spouse',
                'status' => true,
            ]);
            $employeesList[] = $empObj;
            $idx++;
        }

        $employeeHR = $employeesList[0];
        $employeeLead = $employeesList[1] ?? $employeeHR;
        $employeeOperator = $employeesList[2] ?? $employeeHR;

        // Update Circular Manager/Head references in Organization tables
        $buManufacturing->update(['head_employee_id' => $employeeLead->id]);
        $buServices->update(['head_employee_id' => $employeeHR->id]);

        $branchHQ->update(['manager_employee_id' => $employeeHR->id]);
        $branchFactory->update(['manager_employee_id' => $employeeLead->id]);

        $deptHR->update(['head_employee_id' => $employeeHR->id]);
        $deptProd->update(['head_employee_id' => $employeeLead->id]);

        // 11. Employee Logs & Histories
        EmployeeEmploymentHistory::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employeeHR->id,
            'company_name' => 'Global Logistics Corp',
            'designation' => 'Senior HR Generalist',
            'start_date' => '2018-01-10',
            'end_date' => '2022-03-31',
            'job_description' => 'Managed end-to-end recruitment pipelines, resolved employee grievances, and oversaw employee benefits program.',
        ]);

        EmployeeEmploymentHistory::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employeeOperator->id,
            'company_name' => 'Tata Engineering Works',
            'designation' => 'Apprentice Welder',
            'start_date' => '2024-01-01',
            'end_date' => '2025-12-31',
            'job_description' => 'Assisted senior welders in manufacturing vehicle chassis, followed precision drawings, and maintained safety logs.',
        ]);

        // Attendance Penalization History (12 Penalties)
        $penaltiesData = [
            ['emp' => $employeeOperator, 'date' => '2026-06-15', 'rule' => 'late_arrival', 'amount' => 0.50, 'status' => 'processed', 'month' => '2026-06', 'remarks' => 'Arrived at 09:45 AM (Grace period ended at 08:15 AM)'],
            ['emp' => $employeeLead, 'date' => '2026-06-18', 'rule' => 'late_arrival', 'amount' => 0.25, 'status' => 'processed', 'month' => '2026-06', 'remarks' => 'Late arrival by 22 minutes due to traffic.'],
            ['emp' => $employeeHR, 'date' => '2026-06-22', 'rule' => 'early_exit', 'amount' => 0.50, 'status' => 'processed', 'month' => '2026-06', 'remarks' => 'Early exit without approved gate pass.'],
            ['emp' => $employeesList[3], 'date' => '2026-07-02', 'rule' => 'late_arrival', 'amount' => 0.25, 'status' => 'pending', 'month' => '2026-07', 'remarks' => 'Late arrival by 18 minutes.'],
            ['emp' => $employeesList[4], 'date' => '2026-07-03', 'rule' => 'missing_swipe', 'amount' => 1.00, 'status' => 'pending', 'month' => '2026-07', 'remarks' => 'Missing evening out-swipe.'],
            ['emp' => $employeesList[5], 'date' => '2026-07-05', 'rule' => 'late_arrival', 'amount' => 0.50, 'status' => 'pending', 'month' => '2026-07', 'remarks' => 'Third consecutive late arrival.'],
            ['emp' => $employeesList[6], 'date' => '2026-07-08', 'rule' => 'early_exit', 'amount' => 0.25, 'status' => 'pending', 'month' => '2026-07', 'remarks' => 'Exited 30 mins before shift end.'],
            ['emp' => $employeesList[7], 'date' => '2026-07-10', 'rule' => 'late_arrival', 'amount' => 0.25, 'status' => 'pending', 'month' => '2026-07', 'remarks' => 'Late arrival by 20 minutes.'],
            ['emp' => $employeesList[8], 'date' => '2026-07-12', 'rule' => 'missing_swipe', 'amount' => 0.50, 'status' => 'pending', 'month' => '2026-07', 'remarks' => 'Missing morning in-swipe.'],
            ['emp' => $employeesList[9], 'date' => '2026-07-14', 'rule' => 'late_arrival', 'amount' => 0.25, 'status' => 'pending', 'month' => '2026-07', 'remarks' => 'Late arrival by 15 minutes.'],
            ['emp' => $employeesList[10], 'date' => '2026-07-16', 'rule' => 'early_exit', 'amount' => 0.50, 'status' => 'pending', 'month' => '2026-07', 'remarks' => 'Early exit for medical appointment.'],
            ['emp' => $employeesList[11], 'date' => '2026-07-18', 'rule' => 'late_arrival', 'amount' => 0.50, 'status' => 'pending', 'month' => '2026-07', 'remarks' => 'Late arrival by 45 minutes.'],
        ];

        foreach ($penaltiesData as $p) {
            EmployeePenalty::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $p['emp']->id,
                'date' => $p['date'],
                'rule_type' => $p['rule'],
                'penalty_amount' => $p['amount'],
                'status' => $p['status'],
                'payroll_month' => $p['month'],
                'remarks' => $p['remarks'],
            ]);
        }

        EmployeeAdhocComponent::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employeeOperator->id,
            'salary_component_id' => $bonusComp->id,
            'amount' => 5000.00,
            'payroll_month' => '2026-06',
            'status' => 'processed',
            'remarks' => 'Excellent safety record and shift coverage bonus.',
        ]);

        // Leave Balances for Employees
        foreach ($employeesList as $empItem) {
            LeaveBalance::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $empItem->id,
                'leave_type_id' => $leaveSick->id,
                'allocated' => 10.0,
                'used' => 1.0,
                'encashed' => 0.0,
            ]);

            LeaveBalance::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $empItem->id,
                'leave_type_id' => $leaveCasual->id,
                'allocated' => 8.0,
                'used' => 2.0,
                'encashed' => 1.0,
            ]);
        }

        // Leave Applications & History (12 Leave Requests)
        $leaveRequestsData = [
            ['emp' => $employeeOperator, 'type' => $leaveSick, 'start' => '2026-07-05', 'end' => '2026-07-05', 'dur' => 1.0, 'reason' => 'High fever and doctor advised rest.', 'status' => 'approved'],
            ['emp' => $employeeLead, 'type' => $leaveCasual, 'start' => '2026-07-15', 'end' => '2026-07-16', 'dur' => 2.0, 'reason' => 'Personal family event.', 'status' => 'pending'],
            ['emp' => $employeeHR, 'type' => $leaveCasual, 'start' => '2026-06-20', 'end' => '2026-06-20', 'dur' => 1.0, 'reason' => 'Annual health checkup.', 'status' => 'approved'],
            ['emp' => $employeesList[3], 'type' => $leaveSick, 'start' => '2026-07-01', 'end' => '2026-07-02', 'dur' => 2.0, 'reason' => 'Viral infection recovery.', 'status' => 'approved'],
            ['emp' => $employeesList[4], 'type' => $leaveCasual, 'start' => '2026-07-08', 'end' => '2026-07-08', 'dur' => 1.0, 'reason' => 'Urgent domestic work.', 'status' => 'approved'],
            ['emp' => $employeesList[5], 'type' => $leaveSick, 'start' => '2026-07-10', 'end' => '2026-07-10', 'dur' => 1.0, 'reason' => 'Severe dental pain.', 'status' => 'pending'],
            ['emp' => $employeesList[6], 'type' => $leaveCasual, 'start' => '2026-07-12', 'end' => '2026-07-13', 'dur' => 2.0, 'reason' => 'Family travel.', 'status' => 'approved'],
            ['emp' => $employeesList[7], 'type' => $leaveSick, 'start' => '2026-07-14', 'end' => '2026-07-14', 'dur' => 1.0, 'reason' => 'Migraine headache.', 'status' => 'approved'],
            ['emp' => $employeesList[8], 'type' => $leaveCasual, 'start' => '2026-07-18', 'end' => '2026-07-19', 'dur' => 2.0, 'reason' => 'Outstation trip.', 'status' => 'pending'],
            ['emp' => $employeesList[9], 'type' => $leaveSick, 'start' => '2026-07-20', 'end' => '2026-07-20', 'dur' => 1.0, 'reason' => 'Back pain rest.', 'status' => 'approved'],
            ['emp' => $employeesList[10], 'type' => $leaveCasual, 'start' => '2026-07-22', 'end' => '2026-07-22', 'dur' => 1.0, 'reason' => 'Bank work.', 'status' => 'rejected'],
            ['emp' => $employeesList[11], 'type' => $leaveSick, 'start' => '2026-07-24', 'end' => '2026-07-24', 'dur' => 1.0, 'reason' => 'Doctor appointment.', 'status' => 'approved'],
        ];

        foreach ($leaveRequestsData as $lr) {
            LeaveRequest::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $lr['emp']->id,
                'leave_type_id' => $lr['type']->id,
                'start_date' => $lr['start'],
                'end_date' => $lr['end'],
                'duration' => $lr['dur'],
                'start_date_type' => 'full_day',
                'end_date_type' => 'full_day',
                'notified_contacts' => [],
                'reason' => $lr['reason'],
                'status' => $lr['status'],
                'current_level' => ($lr['status'] === 'approved') ? 'approved' : '1',
                'approved_by' => ($lr['status'] === 'approved') ? $employeeHR->id : null,
            ]);
        }

        // Leave Encashments (12 Leave Encashment Records)
        $encashmentsData = [
            ['emp' => $employeeOperator, 'days' => 1.0, 'status' => 'approved', 'reason' => 'Encashment of unutilized casual leave days.'],
            ['emp' => $employeeLead, 'days' => 1.0, 'status' => 'pending', 'reason' => 'Requesting encashment for 1 casual leave day.'],
            ['emp' => $employeeHR, 'days' => 2.0, 'status' => 'approved', 'reason' => 'Annual leave encashment application.'],
            ['emp' => $employeesList[3], 'days' => 1.5, 'status' => 'approved', 'reason' => 'Encashment of accrued casual leave.'],
            ['emp' => $employeesList[4], 'days' => 2.0, 'status' => 'pending', 'reason' => 'Requesting encashment for 2 leave days.'],
            ['emp' => $employeesList[5], 'days' => 1.0, 'status' => 'approved', 'reason' => 'Unused privilege leave encashment.'],
            ['emp' => $employeesList[6], 'days' => 3.0, 'status' => 'approved', 'reason' => 'Quarterly leave encashment.'],
            ['emp' => $employeesList[7], 'days' => 1.0, 'status' => 'pending', 'reason' => 'Casual leave encashment.'],
            ['emp' => $employeesList[8], 'days' => 2.5, 'status' => 'approved', 'reason' => 'Year-end leave encashment.'],
            ['emp' => $employeesList[9], 'days' => 1.0, 'status' => 'rejected', 'reason' => 'Encashment request beyond policy cap.'],
            ['emp' => $employeesList[10], 'days' => 2.0, 'status' => 'approved', 'reason' => 'Encashment of balance leave.'],
            ['emp' => $employeesList[11], 'days' => 1.0, 'status' => 'pending', 'reason' => 'Special leave encashment request.'],
        ];

        foreach ($encashmentsData as $enc) {
            LeaveEncashment::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $enc['emp']->id,
                'leave_type_id' => $leaveCasual->id,
                'requested_days' => $enc['days'],
                'status' => $enc['status'],
                'reason' => $enc['reason'],
                'approved_by' => ($enc['status'] === 'approved') ? $adminUser->id : null,
                'approved_at' => ($enc['status'] === 'approved') ? now() : null,
            ]);
        }

        // Shift Rosters
        $rosterDates = ['2026-07-10', '2026-07-11', '2026-07-12', '2026-07-13', '2026-07-14'];
        foreach ($rosterDates as $rDate) {
            foreach ($employeesList as $empRoster) {
                ShiftRoster::create([
                    'tenant_id' => $tenant->id,
                    'employee_id' => $empRoster->id,
                    'shift_id' => $productionShift->id,
                    'date' => $rDate,
                    'status' => 'approved',
                    'notes' => 'Regular roster assignment.',
                ]);
            }
        }

        // 13. Asset Management (Equipment & Catalog Items)
        $assetCatElectronics = AssetCategory::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'IT Electronics & Workstations',
            'description' => 'Laptops, mobile devices, external drives, and high-performance monitors.',
        ]);

        $assetCatSafety = AssetCategory::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Safety & Technical Gear',
            'description' => 'Welding masks, fireproof suits, protective helmets, and specialized industrial toolsets.',
        ]);

        $assetCatPeripherals = AssetCategory::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Office Peripherals & Accessories',
            'description' => 'Docking stations, mechanical keyboards, ergonomic mice, and headgear.',
        ]);

        // Seed 12 Asset Items (Master Catalog)
        $itemMacBook = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'name' => 'MacBook Pro 16 Inch M3',
            'description' => 'High performance Apple workstation for senior staff and executive administration.',
        ]);

        $itemWeldingMask = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatSafety->id,
            'name' => 'Auto-Darkening Industrial Heavy Duty Welding & Technical Protection Helmet (Grade A Standard)',
            'description' => 'Industrial protective helmet for factory floor welders with automatic lens tinting.',
        ]);

        $itemDellLaptop = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'name' => 'Dell Latitude 5440',
            'description' => 'Enterprise Dell laptop for business & technical team members.',
        ]);

        $itemHPElite = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'name' => 'HP EliteBook 840 G8 Workstation',
            'description' => 'High speed Intel i7 16GB RAM laptop for technical lead and shop floor managers.',
        ]);

        $itemDellMonitor = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'name' => 'Dell P2422H 24-inch Professional IPS Ultra-Thin Bezel Monitor Display',
            'description' => 'FHD IPS LED backlit monitor with adjustable height stand and HDMI/DisplayPort connections.',
        ]);

        $itemMobileHandset = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'name' => 'Samsung Galaxy M34 Factory Duty Smartphone',
            'description' => 'Ruggedized mobile handset for plant floor shift coordination.',
        ]);

        $itemDockingStation = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatPeripherals->id,
            'name' => 'Lenovo ThinkPad Thunderbolt 4 Workstation Dock',
            'description' => 'Dual 4K display output dock with 100W power delivery for engineering laptops.',
        ]);

        $itemErgonomicChair = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatPeripherals->id,
            'name' => 'Featherlite High Back Ergonomic Mesh Chair with Adjustable Lumbar Support & Armrests',
            'description' => 'Ergonomic lumbar support chair for long hours at administrative desks.',
        ]);

        $itemFireSuit = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatSafety->id,
            'name' => 'Nomex Thermal Resistant Heavy Industrial Fireproof Protective Suit Coverall',
            'description' => 'Multi-layer flame retardant suit for boiler room and casting operators.',
        ]);

        $itemHeadset = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatPeripherals->id,
            'name' => 'Jabra Evolve2 65 Noise Canceling Wireless Headset',
            'description' => 'Dual-ear Bluetooth headset with boom microphone for customer support calls.',
        ]);

        $itemToolKit = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatSafety->id,
            'name' => 'Stanley 110-Piece Mechanical & Maintenance Master Tool Kit Box Set',
            'description' => 'Comprehensive chrome vanadium socket and wrench set for plant technicians.',
        ]);

        $itemTablet = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'name' => 'Apple iPad Air 10.9-inch 256GB Wi-Fi',
            'description' => 'Tablet for quality assurance inspectors to conduct digital audit checklists.',
        ]);


        // Seed 15 Physical Assets
        $assetLaptop = Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemMacBook->id,
            'asset_code' => 'AST-LAP-001',
            'name' => 'MacBook Pro 16 Inch M3',
            'brand' => 'Apple',
            'model_number' => 'MBP2025-16',
            'serial_number' => 'C02H41Z0Q05D',
            'purchase_date' => '2025-05-10',
            'purchase_cost' => 199999.00,
            'condition' => 'good',
            'status' => 'allocated',
            'assigned_employee_id' => $employeeHR->id,
            'allocated_at' => '2025-05-15',
            'expected_return_date' => '2028-05-15',
            'notes' => 'Assigned for HR administration use.',
        ]);

        $assetWeldingMask = Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatSafety->id,
            'asset_item_id' => $itemWeldingMask->id,
            'asset_code' => 'AST-SAF-002',
            'name' => 'Auto-Darkening Welding Helmet',
            'brand' => '3M Speedglas',
            'model_number' => 'SG9100',
            'serial_number' => 'WM-90812-B',
            'purchase_date' => '2026-02-05',
            'purchase_cost' => 15000.00,
            'condition' => 'new',
            'status' => 'allocated',
            'assigned_employee_id' => $employeeOperator->id,
            'allocated_at' => '2026-02-10',
            'expected_return_date' => '2027-02-10',
            'notes' => 'Assigned welder mask.',
        ]);

        $assetSpareLaptop = Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemDellLaptop->id,
            'asset_code' => 'AST-LAP-003',
            'name' => 'Dell Latitude 5440',
            'brand' => 'Dell',
            'model_number' => 'LAT-5440',
            'serial_number' => 'D-778899A',
            'purchase_date' => '2026-03-01',
            'purchase_cost' => 65000.00,
            'condition' => 'new',
            'status' => 'available',
            'assigned_employee_id' => null,
            'allocated_at' => null,
            'expected_return_date' => null,
            'notes' => 'Spare laptop in IT storage.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemHPElite->id,
            'asset_code' => 'AST-LAP-004',
            'name' => 'HP EliteBook 840 G8 Workstation',
            'brand' => 'HP',
            'model_number' => 'EB-840-G8',
            'serial_number' => '5CG123456X',
            'purchase_date' => '2026-01-15',
            'purchase_cost' => 88000.00,
            'condition' => 'good',
            'status' => 'allocated',
            'assigned_employee_id' => $employeeLead->id,
            'allocated_at' => '2026-01-20',
            'notes' => 'Assigned to production lead.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemDellMonitor->id,
            'asset_code' => 'AST-MON-005',
            'name' => 'Dell P2422H 24-inch Monitor',
            'brand' => 'Dell',
            'model_number' => 'P2422H',
            'serial_number' => 'CN-048291-72910-112',
            'purchase_date' => '2026-04-10',
            'purchase_cost' => 14500.00,
            'condition' => 'new',
            'status' => 'available',
            'notes' => 'Unassigned monitor in IT lab.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemMobileHandset->id,
            'asset_code' => 'AST-MOB-006',
            'name' => 'Samsung Galaxy M34',
            'brand' => 'Samsung',
            'model_number' => 'SM-M346B',
            'serial_number' => '358920194829102',
            'purchase_date' => '2026-05-01',
            'purchase_cost' => 18000.00,
            'condition' => 'good',
            'status' => 'allocated',
            'assigned_employee_id' => $employeeOperator->id,
            'allocated_at' => '2026-05-05',
            'notes' => 'Shift manager communication phone.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatPeripherals->id,
            'asset_item_id' => $itemDockingStation->id,
            'asset_code' => 'AST-DCK-007',
            'name' => 'Lenovo ThinkPad Thunderbolt 4 Dock',
            'brand' => 'Lenovo',
            'model_number' => '40B00135US',
            'serial_number' => 'Z19A8201',
            'purchase_date' => '2026-02-18',
            'purchase_cost' => 22000.00,
            'condition' => 'good',
            'status' => 'available',
            'notes' => 'Docking station available for request.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatPeripherals->id,
            'asset_item_id' => $itemErgonomicChair->id,
            'asset_code' => 'AST-CHR-008',
            'name' => 'Featherlite High Back Chair',
            'brand' => 'Featherlite',
            'model_number' => 'OPT-MESH-HB',
            'serial_number' => 'FL-CH-9921',
            'purchase_date' => '2025-11-20',
            'purchase_cost' => 12500.00,
            'condition' => 'good',
            'status' => 'allocated',
            'assigned_employee_id' => $employeeHR->id,
            'allocated_at' => '2025-11-25',
            'notes' => 'Office desk chair.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatSafety->id,
            'asset_item_id' => $itemFireSuit->id,
            'asset_code' => 'AST-SAF-009',
            'name' => 'Nomex Thermal Resistant Fireproof Suit',
            'brand' => 'DuPont Nomex',
            'model_number' => 'NX-FR-400',
            'serial_number' => 'SUIT-2026-091',
            'purchase_date' => '2026-03-15',
            'purchase_cost' => 28000.00,
            'condition' => 'new',
            'status' => 'available',
            'notes' => 'Safety gear in warehouse bay B.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatPeripherals->id,
            'asset_item_id' => $itemHeadset->id,
            'asset_code' => 'AST-AUD-010',
            'name' => 'Jabra Evolve2 65 Headset',
            'brand' => 'Jabra',
            'model_number' => 'EV2-65-MS',
            'serial_number' => 'JB-8829103',
            'purchase_date' => '2026-06-10',
            'purchase_cost' => 16000.00,
            'condition' => 'new',
            'status' => 'available',
            'notes' => 'Call center wireless headset.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatSafety->id,
            'asset_item_id' => $itemToolKit->id,
            'asset_code' => 'AST-TLK-011',
            'name' => 'Stanley 110-Piece Mechanical Tool Kit',
            'brand' => 'Stanley',
            'model_number' => 'STMT73795',
            'serial_number' => 'ST-TK-110-88',
            'purchase_date' => '2026-01-10',
            'purchase_cost' => 9500.00,
            'condition' => 'good',
            'status' => 'allocated',
            'assigned_employee_id' => $employeeOperator->id,
            'allocated_at' => '2026-01-12',
            'notes' => 'Maintenance toolset.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemTablet->id,
            'asset_code' => 'AST-TAB-012',
            'name' => 'Apple iPad Air 10.9-inch',
            'brand' => 'Apple',
            'model_number' => 'IPAD-AIR-5',
            'serial_number' => 'DMPZ9810293',
            'purchase_date' => '2026-04-20',
            'purchase_cost' => 54000.00,
            'condition' => 'new',
            'status' => 'available',
            'notes' => 'Quality audit tablet.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemDellLaptop->id,
            'asset_code' => 'AST-LAP-013',
            'name' => 'Dell Latitude 5440 (Unit 2)',
            'brand' => 'Dell',
            'model_number' => 'LAT-5440-B',
            'serial_number' => 'D-991122B',
            'purchase_date' => '2026-03-01',
            'purchase_cost' => 65000.00,
            'condition' => 'good',
            'status' => 'allocated',
            'assigned_employee_id' => $employeeLead->id,
            'allocated_at' => '2026-03-05',
            'notes' => 'Assigned for shop floor reporting.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemDellMonitor->id,
            'asset_code' => 'AST-MON-014',
            'name' => 'Dell P2422H 24-inch Monitor (Unit 2)',
            'brand' => 'Dell',
            'model_number' => 'P2422H',
            'serial_number' => 'CN-048291-72910-115',
            'purchase_date' => '2026-04-10',
            'purchase_cost' => 14500.00,
            'condition' => 'good',
            'status' => 'allocated',
            'assigned_employee_id' => $employeeLead->id,
            'allocated_at' => '2026-04-15',
            'notes' => 'Secondary monitor assigned.',
        ]);

        Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $assetCatSafety->id,
            'asset_item_id' => $itemWeldingMask->id,
            'asset_code' => 'AST-SAF-015',
            'name' => 'Auto-Darkening Welding Helmet (Unit 2)',
            'brand' => '3M Speedglas',
            'model_number' => 'SG9100',
            'serial_number' => 'WM-90812-C',
            'purchase_date' => '2026-02-05',
            'purchase_cost' => 15000.00,
            'condition' => 'new',
            'status' => 'available',
            'notes' => 'Backup welding helmet.',
        ]);


        // Seed 14 Asset Requests (for testing search, pagination, sort, filter, and line breaking)
        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeOperator->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemDellLaptop->id,
            'quantity' => 1,
            'reason' => 'Need a computing device to access shift logs, training portals, and payroll slips.',
            'request_date' => '2026-07-08',
            'status' => 'pending',
            'allocated_asset_id' => null,
            'admin_notes' => 'Pending approval from department head Rajesh Sharma.',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeLead->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemMobileHandset->id,
            'quantity' => 1,
            'reason' => 'Supervisor mobile phone for coordination of shop floor issues.',
            'request_date' => '2026-07-01',
            'status' => 'approved',
            'allocated_asset_id' => null,
            'admin_notes' => 'Request approved. Procurement is preparing a standard factory mobile handset.',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeOperator->id,
            'asset_category_id' => $assetCatSafety->id,
            'asset_item_id' => $itemWeldingMask->id,
            'quantity' => 1,
            'reason' => 'Replacement request for scratched visor welding helmet on assembly line 3.',
            'request_date' => '2026-06-15',
            'status' => 'allocated',
            'allocated_asset_id' => $assetWeldingMask->id,
            'admin_notes' => 'Allocated: AST-SAF-002 on 16 Jun, 2026',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeLead->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemDellMonitor->id,
            'quantity' => 2,
            'reason' => 'Dual monitor setup for multi-screen CAD drawings and production schedule monitoring.',
            'request_date' => '2026-06-20',
            'status' => 'partially_allocated',
            'allocated_asset_id' => null,
            'admin_notes' => 'Allocated: AST-MON-014 on 22 Jun, 2026 | Waiting for stock for second monitor unit.',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeHR->id,
            'asset_category_id' => $assetCatPeripherals->id,
            'asset_item_id' => $itemErgonomicChair->id,
            'quantity' => 1,
            'reason' => 'Requesting an ergonomic lumbar support chair for long hours at administrative desk to address posture guidelines.',
            'request_date' => '2026-05-12',
            'status' => 'allocated',
            'allocated_asset_id' => null,
            'admin_notes' => 'Allocated: AST-CHR-008 on 15 May, 2026',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeOperator->id,
            'asset_category_id' => $assetCatPeripherals->id,
            'asset_item_id' => $itemHeadset->id,
            'quantity' => 1,
            'reason' => 'Noise-canceling wireless headset needed for virtual shift handover briefings with remote engineering lead.',
            'request_date' => '2026-07-12',
            'status' => 'pending',
            'allocated_asset_id' => null,
            'admin_notes' => 'Reviewing stock availability in central warehouse.',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeLead->id,
            'asset_category_id' => $assetCatSafety->id,
            'asset_item_id' => $itemFireSuit->id,
            'quantity' => 1,
            'reason' => 'Thermal resistant protective suit for upcoming furnace area inspection and quality certification audit.',
            'request_date' => '2026-06-05',
            'status' => 'rejected',
            'allocated_asset_id' => null,
            'admin_notes' => 'Rejected: Inspection suits are provided on a temporary sign-out basis from the safety locker room. Permanent individual allocation is not permitted.',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeOperator->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemTablet->id,
            'quantity' => 1,
            'reason' => 'Requesting a tablet for digital quality assurance checklists on assembly line B.',
            'request_date' => '2026-07-14',
            'status' => 'pending',
            'allocated_asset_id' => null,
            'admin_notes' => 'Pending IT security provisioning.',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeHR->id,
            'asset_category_id' => $assetCatPeripherals->id,
            'asset_item_id' => $itemDockingStation->id,
            'quantity' => 1,
            'reason' => 'Thunderbolt 4 docking station for seamless dual-screen hot desking between HR office and interview rooms.',
            'request_date' => '2026-07-10',
            'status' => 'approved',
            'allocated_asset_id' => null,
            'admin_notes' => 'Approved by IT Asset Manager.',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeLead->id,
            'asset_category_id' => $assetCatSafety->id,
            'asset_item_id' => $itemToolKit->id,
            'quantity' => 1,
            'reason' => 'Requesting a 110-piece master maintenance tool kit for machine line calibration tasks.',
            'request_date' => '2026-05-20',
            'status' => 'allocated',
            'allocated_asset_id' => null,
            'admin_notes' => 'Allocated: AST-TLK-011 on 22 May, 2026',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeOperator->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemHPElite->id,
            'quantity' => 1,
            'reason' => 'Ultra-long text test: Requesting a high-performance workstation laptop with discrete GPU capabilities, dual external monitor docking adapter, high-durability carrying case, and noise-canceling headset for remote project coordination and CAD modeling tasks across multiple plant sites.',
            'request_date' => '2026-07-15',
            'status' => 'pending',
            'allocated_asset_id' => null,
            'admin_notes' => 'Evaluating GPU requirement justification with engineering department head.',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeHR->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemMacBook->id,
            'quantity' => 1,
            'reason' => 'Upgrade request to latest M3 workstation for video production and employee onboarding media rendering.',
            'request_date' => '2026-04-10',
            'status' => 'allocated',
            'allocated_asset_id' => $assetLaptop->id,
            'admin_notes' => 'Allocated: AST-LAP-001 on 15 Apr, 2026',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeOperator->id,
            'asset_category_id' => $assetCatSafety->id,
            'asset_item_id' => $itemFireSuit->id,
            'quantity' => 2,
            'reason' => 'Need 2 sets of Nomex flame retardant suits for new furnace line team members.',
            'request_date' => '2026-07-18',
            'status' => 'pending',
            'allocated_asset_id' => null,
            'admin_notes' => 'Waiting for size confirmation from safety officer.',
        ]);

        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeeLead->id,
            'asset_category_id' => $assetCatElectronics->id,
            'asset_item_id' => $itemDellLaptop->id,
            'quantity' => 1,
            'reason' => 'Backup laptop request for shop floor supervisor station during maintenance overhaul week.',
            'request_date' => '2026-06-28',
            'status' => 'rejected',
            'allocated_asset_id' => null,
            'admin_notes' => 'Rejected: Existing unit AST-LAP-013 is fully functional and covers supervisor duties.',
        ]);


        // 14. Morphable Documents (12 Seeded Employee Documents)
        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'Aadhaar Card Copy',
            'description' => 'Govt verification ID document for identity verification.',
            'file_name' => 'aadhaar_amit_patel.pdf',
            'file_path' => 'uploads/documents/employees/aadhaar_amit_patel.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 124500,
            'status' => 'approved',
            'has_expiry' => false,
            'expiry_date' => null,
            'requested_by_id' => $adminUser->id,
        ]);

        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeHR->id,
            'name' => 'MBA Degree Certificate',
            'description' => 'Educational credential check from university.',
            'file_name' => 'mba_degree_sophia.pdf',
            'file_path' => 'uploads/documents/employees/mba_degree_sophia.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 458000,
            'status' => 'approved',
            'has_expiry' => false,
            'expiry_date' => null,
            'requested_by_id' => $adminUser->id,
        ]);

        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'Passport Copy (Front & Back)',
            'description' => 'International travel and identity verification passport pages.',
            'file_name' => 'passport_amit_patel.pdf',
            'file_path' => 'uploads/documents/employees/passport_amit_patel.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 312000,
            'status' => 'approved',
            'has_expiry' => true,
            'expiry_date' => '2028-12-31',
            'requested_by_id' => $adminUser->id,
        ]);

        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'Heavy Equipment Commercial Driving License (Grade A Industrial Forklift Operator)',
            'description' => 'Mandatory industrial forklift & heavy transport vehicle driving permit.',
            'file_name' => 'dl_amit_patel.pdf',
            'file_path' => 'uploads/documents/employees/dl_amit_patel.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 205000,
            'status' => 'approved',
            'has_expiry' => true,
            'expiry_date' => '2026-09-15',
            'requested_by_id' => $adminUser->id,
        ]);

        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'Relieving & Experience Certificate from Previous Employer',
            'description' => 'Service verification and conduct clearance letter from previous industrial employer.',
            'file_name' => 'relieving_letter_previous.pdf',
            'file_path' => 'uploads/documents/employees/relieving_letter_previous.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 512000,
            'status' => 'approved',
            'has_expiry' => false,
            'expiry_date' => null,
            'requested_by_id' => $adminUser->id,
        ]);

        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'PAN Card Scan Copy',
            'description' => 'Permanent Account Number tax verification document.',
            'file_name' => 'pan_amit_patel.pdf',
            'file_path' => 'uploads/documents/employees/pan_amit_patel.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 180000,
            'status' => 'approved',
            'has_expiry' => false,
            'expiry_date' => null,
            'requested_by_id' => $adminUser->id,
        ]);

        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'Annual Tax Exemption Declaration Form 12BB with Supporting HRA Rent Receipts and Investment Certificates',
            'description' => 'Annual tax proof submission detailing home rent allowance receipts, LIC insurance policies, and PPF deposits.',
            'file_name' => 'form12bb_tax_declaration_2026.pdf',
            'file_path' => 'uploads/documents/employees/form12bb_tax_declaration_2026.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 845000,
            'status' => 'requested',
            'has_expiry' => true,
            'expiry_date' => '2027-03-31',
            'requested_by_id' => $adminUser->id,
        ]);

        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'Medical Fitness & Pre-Employment Health Checkup Certificate',
            'description' => 'Certified medical officer clearance for shop floor industrial operations.',
            'file_name' => 'medical_fitness_amit.pdf',
            'file_path' => 'uploads/documents/employees/medical_fitness_amit.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 290000,
            'status' => 'approved',
            'has_expiry' => true,
            'expiry_date' => '2027-01-10',
            'requested_by_id' => $adminUser->id,
        ]);

        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'Bank Account Details & Cancelled Cheque Leaf Copy',
            'description' => 'Salary credit bank account proof for monthly payroll processing.',
            'file_name' => 'cancelled_cheque_hdfc.pdf',
            'file_path' => 'uploads/documents/employees/cancelled_cheque_hdfc.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 195000,
            'status' => 'approved',
            'has_expiry' => false,
            'expiry_date' => null,
            'requested_by_id' => $adminUser->id,
        ]);

        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'Signed Non-Disclosure Agreement (NDA) & Code of Conduct Acknowledgement',
            'description' => 'Legal agreement covering proprietary manufacturing designs and plant safety regulations.',
            'file_name' => 'signed_nda_amit.pdf',
            'file_path' => 'uploads/documents/employees/signed_nda_amit.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 410000,
            'status' => 'approved',
            'has_expiry' => false,
            'expiry_date' => null,
            'requested_by_id' => $adminUser->id,
        ]);

        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'Industrial Safety Training & First Aid Certification Card',
            'description' => 'Plant safety protocol and emergency response training completion certificate.',
            'file_name' => 'safety_cert_amit.pdf',
            'file_path' => 'uploads/documents/employees/safety_cert_amit.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 330000,
            'status' => 'requested',
            'has_expiry' => true,
            'expiry_date' => '2026-08-30',
            'requested_by_id' => $adminUser->id,
        ]);

        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'Educational Higher Secondary School Leaving Certificate (10+2 Standard Marksheet)',
            'description' => 'Secondary education completion marksheet for HR verification records.',
            'file_name' => 'hsc_marksheet_amit.pdf',
            'file_path' => 'uploads/documents/employees/hsc_marksheet_amit.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 275000,
            'status' => 'approved',
            'has_expiry' => false,
            'expiry_date' => null,
            'requested_by_id' => $adminUser->id,
        ]);

        // Seed new HRMS-related tables: WfhRequest, ShiftChangeRequest, OvertimeRequest
        $seederEmployees = Employee::all();
        
        $requestedShift1 = ProductionShift::where('code', 'SHIFT-AM')->first() ?? $productionShift ?? ProductionShift::first();
        $requestedShift2 = ProductionShift::where('code', 'SHIFT-PM')->first() ?? $productionShift ?? ProductionShift::first();

        foreach ($seederEmployees as $emp) {
            // Find another employee to act as the manager/approver (so they don't self-approve)
            $approver = $seederEmployees->where('id', '!=', $emp->id)->first() ?? $emp;

            // 1. Seed 2 WFH Requests for every employee
            WfhRequest::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $emp->id,
                'start_date' => now()->addDays(2)->format('Y-m-d'),
                'end_date' => now()->addDays(4)->format('Y-m-d'),
                'duration' => 3.0,
                'start_date_type' => 'full_day',
                'end_date_type' => 'full_day',
                'reason' => 'Quarterly remote work arrangement.',
                'status' => 'approved',
                'approved_by' => $approver->id,
            ]);

            WfhRequest::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $emp->id,
                'start_date' => now()->addDays(12)->format('Y-m-d'),
                'end_date' => now()->addDays(12)->format('Y-m-d'),
                'duration' => 1.0,
                'start_date_type' => 'full_day',
                'end_date_type' => 'full_day',
                'reason' => 'Broadband upgrade and maintenance.',
                'status' => 'pending',
            ]);

            // 2. Seed 2 Shift Change Requests for every employee
            ShiftChangeRequest::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $emp->id,
                'type' => 'recurring',
                'start_date' => now()->addDays(1)->format('Y-m-d'),
                'end_date' => now()->addDays(10)->format('Y-m-d'),
                'recurring_days' => ['Monday', 'Wednesday', 'Friday'],
                'current_shift_id' => $emp->shift_id ?? ($productionShift->id ?? null),
                'requested_shift_id' => $requestedShift1->id ?? null,
                'reason' => 'Family commitments require morning timings.',
                'status' => 'pending',
            ]);

            ShiftChangeRequest::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $emp->id,
                'type' => 'temporary',
                'start_date' => now()->addDays(15)->format('Y-m-d'),
                'end_date' => now()->addDays(15)->format('Y-m-d'),
                'current_shift_id' => $emp->shift_id ?? ($productionShift->id ?? null),
                'requested_shift_id' => $requestedShift2->id ?? null,
                'reason' => 'Doctor appointment shift swap.',
                'status' => 'approved',
                'approved_by' => $approver->id,
            ]);

            // 3. Seed 2 Overtime Requests for every employee
            OvertimeRequest::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $emp->id,
                'date' => now()->subDays(2)->format('Y-m-d'),
                'start_time' => '18:00:00',
                'end_time' => '21:00:00',
                'duration_hours' => 3.00,
                'approved_duration_hours' => 3.00,
                'compensation_type' => 'pay',
                'reason' => 'Extra support for monthly inventory count.',
                'status' => 'approved',
                'approved_by' => $approver->id,
            ]);

            OvertimeRequest::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $emp->id,
                'date' => now()->subDays(5)->format('Y-m-d'),
                'start_time' => '18:00:00',
                'end_time' => '20:00:00',
                'duration_hours' => 2.00,
                'approved_duration_hours' => 0.00,
                'compensation_type' => 'pay',
                'reason' => 'Unscheduled troubleshooting assistance.',
                'status' => 'rejected',
                'rejection_reason' => 'Not pre-approved by shift lead.',
            ]);
        }
    }
}
