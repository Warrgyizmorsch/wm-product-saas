<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\HRMS\Models\Organization;
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
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetAllocation;
use App\Domains\HRMS\Models\AssetRequest;
use App\Domains\HRMS\Models\Document;
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

        // Truncate tables to ensure idempotency and prevent duplicate records
        DB::table('employee_adhoc_components')->truncate();
        DB::table('employee_employment_histories')->truncate();
        DB::table('employee_penalties')->truncate();
        DB::table('shift_rosters')->truncate();
        DB::table('asset_allocations')->truncate();
        DB::table('asset_requests')->truncate();
        DB::table('assets')->truncate();
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
        DB::table('organizations')->truncate();

        Schema::enableForeignKeyConstraints();

        // 1. Fetch Tenant and Admin User
        $tenant = Tenant::where('slug', 'demo')->first();
        if (!$tenant) {
            $tenant = Tenant::create([
                'name' => 'Demo Tenant',
                'slug' => 'demo',
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

        // 2. Organization & Company
        $org = Organization::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'logo' => null,
            'email' => 'contact@acme.com',
            'phone' => '+1234567890',
            'website' => 'https://acme.com',
            'subscription_plan' => 'enterprise',
            'status' => true,
        ]);

        $company = Company::create([
            'organization_id' => $org->id,
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
            'organization_id' => $org->id,
            'company_id' => $company->id,
            'name' => 'Standard Employees Pay Group',
            'description' => 'Default monthly payroll group for general staff.',
            'status' => true,
        ]);

        $payGroupExecutive = PayGroup::create([
            'organization_id' => $org->id,
            'company_id' => $company->id,
            'name' => 'Executive Pay Group',
            'description' => 'Payroll group for management and executives.',
            'status' => true,
        ]);

        // 4. Salary Components
        $basicComp = SalaryComponent::create([
            'organization_id' => $org->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupStandard->id,
            'name' => 'Basic Salary',
            'code' => 'BASIC',
            'type' => 'earning',
            'calculation_type' => 'percentage',
            'default_value' => '50',
            'description' => 'Base salary component',
            'is_adhoc' => false,
            'status' => true,
        ]);

        $hraComp = SalaryComponent::create([
            'organization_id' => $org->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupStandard->id,
            'name' => 'House Rent Allowance',
            'code' => 'HRA',
            'type' => 'earning',
            'calculation_type' => 'percentage',
            'default_value' => '40',
            'description' => 'HRA allowance',
            'is_adhoc' => false,
            'status' => true,
        ]);

        $pfComp = SalaryComponent::create([
            'organization_id' => $org->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupStandard->id,
            'name' => 'Provident Fund',
            'code' => 'PF',
            'type' => 'deduction',
            'calculation_type' => 'fixed',
            'default_value' => '1800',
            'description' => 'Employee Provident Fund contribution',
            'is_adhoc' => false,
            'status' => true,
        ]);

        $bonusComp = SalaryComponent::create([
            'organization_id' => $org->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroupStandard->id,
            'name' => 'Performance Bonus',
            'code' => 'BONUS',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'default_value' => '0',
            'description' => 'Ad-hoc performance bonus',
            'is_adhoc' => true,
            'status' => true,
        ]);

        // 5. Salary Structures & Items
        $salaryStructureStandard = SalaryStructure::create([
            'company_id' => $company->id,
            'pay_group_id' => $payGroupStandard->id,
            'name' => 'Standard Developer Structure',
            'min_ctc' => 300000.00,
            'max_ctc' => 1500000.00,
            'status' => true,
        ]);

        SalaryStructureItem::create([
            'salary_structure_id' => $salaryStructureStandard->id,
            'salary_component_id' => $basicComp->id,
            'calculation_type' => 'percentage_of_ctc',
            'value' => 50.00,
            'sort_order' => 1,
        ]);

        SalaryStructureItem::create([
            'salary_structure_id' => $salaryStructureStandard->id,
            'salary_component_id' => $hraComp->id,
            'calculation_type' => 'percentage_of_basic',
            'value' => 40.00,
            'sort_order' => 2,
        ]);

        SalaryStructureItem::create([
            'salary_structure_id' => $salaryStructureStandard->id,
            'salary_component_id' => $pfComp->id,
            'calculation_type' => 'fixed',
            'value' => 1800.00,
            'sort_order' => 3,
        ]);

        // 6. Leave Plans & Leave Types
        $leavePlan = LeavePlan::create([
            'company_id' => $company->id,
            'name' => 'Standard India Leave Plan 2026',
            'effective_from' => '2026-01-01',
            'description' => 'Default annual leave policy for Indian employees.',
            'status' => true,
        ]);

        $leaveSick = LeaveType::create([
            'leave_plan_id' => $leavePlan->id,
            'name' => 'Sick Leave',
            'code' => 'SL',
            'description' => 'For medical recovery and emergencies.',
            'type' => 'paid',
            'color' => '#ef4444',
            'quota' => 10.0,
            'rules' => ['requires_certificate_after_days' => 2],
            'status' => true,
        ]);

        $leaveCasual = LeaveType::create([
            'leave_plan_id' => $leavePlan->id,
            'name' => 'Casual Leave',
            'code' => 'CL',
            'description' => 'For personal and unplanned events.',
            'type' => 'paid',
            'color' => '#f59e0b',
            'quota' => 8.0,
            'rules' => ['max_consecutive_days' => 3],
            'status' => true,
        ]);

        $leaveUnpaid = LeaveType::create([
            'leave_plan_id' => $leavePlan->id,
            'name' => 'Loss of Pay',
            'code' => 'LOP',
            'description' => 'Unpaid leave when quota is exhausted.',
            'type' => 'unpaid',
            'color' => '#6b7280',
            'quota' => 0.0,
            'rules' => null,
            'status' => true,
        ]);

        // 7. Attendance Penalties
        $attendancePenalty = AttendancePenalty::create([
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

        // 8. Organizational Structure (Initial setup, manager relations set to null)
        $buManufacturing = BusinessUnit::create([
            'company_id' => $company->id,
            'name' => 'Manufacturing Business Unit',
            'code' => 'BU-MFG',
            'description' => 'Core production and supply chain division.',
            'head_employee_id' => null,
            'status' => true,
        ]);

        $buServices = BusinessUnit::create([
            'company_id' => $company->id,
            'name' => 'Professional Services',
            'code' => 'BU-SRV',
            'description' => 'Consulting and client engineering group.',
            'head_employee_id' => null,
            'status' => true,
        ]);

        $branchHQ = Branch::create([
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

        $deptHR = Department::create([
            'branch_id' => $branchHQ->id,
            'company_id' => $company->id,
            'business_unit_id' => $buServices->id,
            'name' => 'Human Resources',
            'code' => 'DEPT-HR',
            'head_employee_id' => null,
            'description' => 'Talent acquisition, payroll, and employee success.',
            'status' => true,
        ]);

        $deptProd = Department::create([
            'branch_id' => $branchFactory->id,
            'company_id' => $company->id,
            'business_unit_id' => $buManufacturing->id,
            'name' => 'Production & Assembly',
            'code' => 'DEPT-PROD',
            'head_employee_id' => null,
            'description' => 'Assembly line workers and shop floor management.',
            'status' => true,
        ]);

        $desigHRManager = Designation::create([
            'department_id' => $deptHR->id,
            'name' => 'HR Manager',
            'level' => 'L4',
            'description' => 'Heads the branch human resources team.',
            'status' => true,
        ]);

        $desigProdLead = Designation::create([
            'department_id' => $deptProd->id,
            'name' => 'Production Lead',
            'level' => 'L3',
            'description' => 'Supervises shop floor shifts and machines.',
            'status' => true,
        ]);

        $desigOperator = Designation::create([
            'department_id' => $deptProd->id,
            'name' => 'Machine Operator',
            'level' => 'L1',
            'description' => 'Runs assembly machines and welds parts.',
            'status' => true,
        ]);

        // Find or create a production shift for reference
        $productionShift = ProductionShift::where('tenant_id', $tenant->id)->first();
        if (!$productionShift) {
            $productionShift = ProductionShift::create([
                'tenant_id' => $tenant->id,
                'name' => 'Day Shift',
                'code' => 'SHIFT-DAY',
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'active' => true,
            ]);
        }

        // 9. Employees
        // HR Manager (reports to none)
        $employeeHR = Employee::create([
            'employee_id' => 'ACM-0001',
            'company_id' => $company->id,
            'business_unit_id' => $buServices->id,
            'branch_id' => $branchHQ->id,
            'department_id' => $deptHR->id,
            'designation_id' => $desigHRManager->id,
            'pay_group_id' => $payGroupExecutive->id,
            'salary_structure_id' => $salaryStructureStandard->id,
            'leave_plan_id' => $leavePlan->id,
            'attendance_penalty_id' => $attendancePenalty->id,
            'reporting_manager_id' => null,
            'shift_id' => $productionShift->id,
            'full_name' => 'Sophia Martinez',
            'nick_name' => 'Sophia',
            'blood_group' => 'A+',
            'employee_stage' => 'Confirmed',
            'job_title' => 'HR Manager',
            'role' => 'HR Administrator',
            'employment_type' => 'Full-time',
            'date_of_joining' => '2022-04-15',
            'date_of_birth' => '1990-08-22',
            'probation_end_date' => '2022-10-15',
            'confirmation_date' => '2022-10-15',
            'office' => 'HQ Floor 5',
            'gender' => 'Female',
            'marital_status' => 'Married',
            'diet_preference' => 'Veg',
            'aadhaar_card_number' => '1111-2222-3333',
            'pan_card_number' => 'ABCDE1234F',
            'present_address' => '45 Residency Rd, Bangalore',
            'permanent_address' => '45 Residency Rd, Bangalore',
            'city' => 'Bangalore',
            'postal_code' => '560025',
            'personal_mobile_number' => '+919888877777',
            'personal_email' => 'sophia.m@example.com',
            'office_email' => 'sophia.martinez@acme.com',
            'experience' => 8.5,
            'source_of_hire' => 'LinkedIn',
            'skill_set' => 'Recruitment, Employee Relations, Payroll, Benefits',
            'current_salary' => 95000.00,
            'qualification' => 'MBA in HR',
            'bank_name' => 'HDFC Bank',
            'account_number' => '50100012345678',
            'ifsc_code' => 'HDFC0000123',
            'emergency_contact_name' => 'Carlos Martinez',
            'emergency_contact_number' => '+919888877778',
            'emergency_contact_relation' => 'Spouse',
            'status' => true,
        ]);

        // Production Lead (reports to HR Manager)
        $employeeLead = Employee::create([
            'employee_id' => 'ACM-0002',
            'company_id' => $company->id,
            'business_unit_id' => $buManufacturing->id,
            'branch_id' => $branchFactory->id,
            'department_id' => $deptProd->id,
            'designation_id' => $desigProdLead->id,
            'pay_group_id' => $payGroupStandard->id,
            'salary_structure_id' => $salaryStructureStandard->id,
            'leave_plan_id' => $leavePlan->id,
            'attendance_penalty_id' => $attendancePenalty->id,
            'reporting_manager_id' => $employeeHR->id,
            'shift_id' => $productionShift->id,
            'full_name' => 'Rajesh Sharma',
            'nick_name' => 'Raj',
            'blood_group' => 'O+',
            'employee_stage' => 'Confirmed',
            'job_title' => 'Production Lead',
            'role' => 'Supervisor',
            'employment_type' => 'Full-time',
            'date_of_joining' => '2023-06-01',
            'date_of_birth' => '1988-12-05',
            'probation_end_date' => '2023-12-01',
            'confirmation_date' => '2023-11-28',
            'office' => 'Factory Cabin A',
            'gender' => 'Male',
            'marital_status' => 'Married',
            'diet_preference' => 'Veg',
            'aadhaar_card_number' => '2222-3333-4444',
            'pan_card_number' => 'FGHIJ5678K',
            'present_address' => '78 Mulund West, Mumbai',
            'permanent_address' => '78 Mulund West, Mumbai',
            'city' => 'Mumbai',
            'postal_code' => '400080',
            'personal_mobile_number' => '+919777766666',
            'personal_email' => 'rajesh.sharma@example.com',
            'office_email' => 'rajesh.sharma@acme.com',
            'experience' => 10.0,
            'source_of_hire' => 'Referral',
            'skill_set' => 'Assembly Operations, Quality Control, Welding Safety',
            'current_salary' => 75000.00,
            'qualification' => 'B.Tech in Mechanical Engineering',
            'bank_name' => 'ICICI Bank',
            'account_number' => '000401234567',
            'ifsc_code' => 'ICIC0000004',
            'emergency_contact_name' => 'Priya Sharma',
            'emergency_contact_number' => '+919777766665',
            'emergency_contact_relation' => 'Spouse',
            'status' => true,
        ]);

        // Standard Operator (reports to Production Lead)
        $employeeOperator = Employee::create([
            'employee_id' => 'ACM-0003',
            'company_id' => $company->id,
            'business_unit_id' => $buManufacturing->id,
            'branch_id' => $branchFactory->id,
            'department_id' => $deptProd->id,
            'designation_id' => $desigOperator->id,
            'pay_group_id' => $payGroupStandard->id,
            'salary_structure_id' => $salaryStructureStandard->id,
            'leave_plan_id' => $leavePlan->id,
            'attendance_penalty_id' => $attendancePenalty->id,
            'reporting_manager_id' => $employeeLead->id,
            'shift_id' => $productionShift->id,
            'full_name' => 'Amit Patel',
            'nick_name' => 'Amit',
            'blood_group' => 'B+',
            'employee_stage' => 'Probation',
            'job_title' => 'Machine Welder',
            'role' => 'Operator',
            'employment_type' => 'Full-time',
            'date_of_joining' => '2026-02-01',
            'date_of_birth' => '1995-03-14',
            'probation_end_date' => '2026-08-01',
            'confirmation_date' => null,
            'office' => 'Shop Floor Section B',
            'gender' => 'Male',
            'marital_status' => 'Single',
            'diet_preference' => 'Non Veg',
            'aadhaar_card_number' => '3333-4444-5555',
            'pan_card_number' => 'KLMNO9012L',
            'present_address' => '12 Thane East, Mumbai',
            'permanent_address' => '12 Thane East, Mumbai',
            'city' => 'Mumbai',
            'postal_code' => '400603',
            'personal_mobile_number' => '+919666655555',
            'personal_email' => 'amit.patel@example.com',
            'office_email' => 'amit.patel@acme.com',
            'experience' => 3.2,
            'source_of_hire' => 'Walk-In',
            'skill_set' => 'Spot Welding, Machine Diagnostics, Assembly Layouts',
            'current_salary' => 35000.00,
            'qualification' => 'ITI Certificate in Welding',
            'bank_name' => 'State Bank of India',
            'account_number' => '20011122233',
            'ifsc_code' => 'SBIN0001234',
            'emergency_contact_name' => 'Ramesh Patel',
            'emergency_contact_number' => '+919666655554',
            'emergency_contact_relation' => 'Father',
            'status' => true,
        ]);

        // 10. Update Circular Manager/Head references in Organization tables
        $buManufacturing->update(['head_employee_id' => $employeeLead->id]);
        $buServices->update(['head_employee_id' => $employeeHR->id]);

        $branchHQ->update(['manager_employee_id' => $employeeHR->id]);
        $branchFactory->update(['manager_employee_id' => $employeeLead->id]);

        $deptHR->update(['head_employee_id' => $employeeHR->id]);
        $deptProd->update(['head_employee_id' => $employeeLead->id]);

        // 11. Employee Logs & Histories
        EmployeeEmploymentHistory::create([
            'employee_id' => $employeeHR->id,
            'company_name' => 'Global Logistics Corp',
            'designation' => 'Senior HR Generalist',
            'start_date' => '2018-01-10',
            'end_date' => '2022-03-31',
            'job_description' => 'Managed end-to-end recruitment pipelines, resolved employee grievances, and oversaw employee benefits program.',
        ]);

        EmployeeEmploymentHistory::create([
            'employee_id' => $employeeOperator->id,
            'company_name' => 'Tata Engineering Works',
            'designation' => 'Apprentice Welder',
            'start_date' => '2024-01-01',
            'end_date' => '2025-12-31',
            'job_description' => 'Assisted senior welders in manufacturing vehicle chassis, followed precision drawings, and maintained safety logs.',
        ]);

        EmployeePenalty::create([
            'employee_id' => $employeeOperator->id,
            'date' => '2026-06-15',
            'rule_type' => 'late_arrival',
            'penalty_amount' => 0.50,
            'status' => 'pending',
            'payroll_month' => '2026-06',
            'remarks' => 'Arrived at 09:45 AM (Grace period ended at 08:15 AM)',
        ]);

        EmployeeAdhocComponent::create([
            'employee_id' => $employeeOperator->id,
            'salary_component_id' => $bonusComp->id,
            'amount' => 5000.00,
            'payroll_month' => '2026-06',
            'status' => 'processed',
            'remarks' => 'Excellent safety record and shift coverage bonus.',
        ]);

        // 12. Shift Rosters
        $rosterDates = ['2026-07-10', '2026-07-11', '2026-07-12', '2026-07-13', '2026-07-14'];
        foreach ($rosterDates as $rDate) {
            ShiftRoster::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employeeOperator->id,
                'shift_id' => $productionShift->id,
                'date' => $rDate,
                'status' => 'approved',
                'notes' => 'Regular roster assignment.',
            ]);

            ShiftRoster::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employeeLead->id,
                'shift_id' => $productionShift->id,
                'date' => $rDate,
                'status' => 'approved',
                'notes' => 'Supervisor roster assignment.',
            ]);
        }

        // 13. Asset Management (Equipment)
        $assetCatElectronics = AssetCategory::create([
            'company_id' => $company->id,
            'name' => 'IT Electronics & Accessories',
            'description' => 'Laptops, mobile devices, external drives, and monitors.',
        ]);

        $assetCatSafety = AssetCategory::create([
            'company_id' => $company->id,
            'name' => 'Safety & Technical Gear',
            'description' => 'Welding masks, fireproof suits, and specialized toolsets.',
        ]);

        $assetLaptop = Asset::create([
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
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
            'company_id' => $company->id,
            'asset_category_id' => $assetCatSafety->id,
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
            'company_id' => $company->id,
            'asset_category_id' => $assetCatElectronics->id,
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

        AssetAllocation::create([
            'asset_id' => $assetLaptop->id,
            'employee_id' => $employeeHR->id,
            'allocated_at' => '2025-05-15',
            'returned_at' => null,
            'allocation_condition' => 'excellent',
            'return_condition' => null,
            'notes' => 'Allocated on onboarding.',
        ]);

        AssetAllocation::create([
            'asset_id' => $assetWeldingMask->id,
            'employee_id' => $employeeOperator->id,
            'allocated_at' => '2026-02-10',
            'returned_at' => null,
            'allocation_condition' => 'brand-new',
            'return_condition' => null,
            'notes' => 'Standard shop floor allocation.',
        ]);

        AssetRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employeeOperator->id,
            'asset_category_id' => $assetCatElectronics->id,
            'reason' => 'Need a computing device to access shift logs, training portals, and payroll slips.',
            'request_date' => '2026-07-08',
            'status' => 'pending',
            'allocated_asset_id' => null,
            'admin_notes' => 'Pending approval from department head Rajesh Sharma.',
        ]);

        AssetRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employeeLead->id,
            'asset_category_id' => $assetCatElectronics->id,
            'reason' => 'Supervisor mobile phone for coordination of shop floor issues.',
            'request_date' => '2026-07-01',
            'status' => 'approved',
            'allocated_asset_id' => null,
            'admin_notes' => 'Request approved. Procurement is preparing a standard factory mobile handset.',
        ]);

        // 14. Morphable Documents
        Document::create([
            'tenant_id' => $tenant->id,
            'documentable_type' => Employee::class,
            'documentable_id' => $employeeOperator->id,
            'name' => 'Aadhaar Card Copy',
            'description' => 'Govt verification ID document.',
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
            'description' => 'Educational credential check.',
            'file_name' => 'mba_degree_sophia.pdf',
            'file_path' => 'uploads/documents/employees/mba_degree_sophia.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 458000,
            'status' => 'approved',
            'has_expiry' => false,
            'expiry_date' => null,
            'requested_by_id' => $adminUser->id,
        ]);
    }
}
