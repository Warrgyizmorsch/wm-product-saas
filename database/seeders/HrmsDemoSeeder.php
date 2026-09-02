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
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Models\SalaryStructure;
use App\Domains\HRMS\Models\SalaryStructureItem;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\AttendanceRule;
use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceBreak;
use App\Domains\HRMS\Models\WfhRequest;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Domains\HRMS\Models\ShiftRoster;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetItem;
use App\Domains\HRMS\Models\AssetAllocation;
use App\Domains\HRMS\Models\AssetRequest;
use App\Domains\HRMS\Models\DocumentCategory;
use App\Domains\HRMS\Models\DocumentMaster;
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

        // Truncate all related tables
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
        DB::table('document_masters')->truncate();
        DB::table('document_categories')->truncate();
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
        DB::table('attendance_location_logs')->truncate();
        DB::table('attendance_breaks')->truncate();
        DB::table('attendances')->truncate();
        DB::table('attendance_rules')->truncate();
        DB::table('production_shifts')->truncate();
        DB::table('holiday_calendars')->truncate();
        DB::table('expense_claims')->truncate();
        DB::table('expense_reports')->truncate();
        DB::table('expense_policy_rules')->truncate();
        DB::table('expense_policies')->truncate();
        DB::table('expense_categories')->truncate();
        DB::table('travel_requests')->truncate();
        DB::table('payroll_runs')->truncate();
        DB::table('salary_revisions')->truncate();
        if (Schema::hasTable('employee_exit_documents')) DB::table('employee_exit_documents')->truncate();
        if (Schema::hasTable('employee_fnf_settlements')) DB::table('employee_fnf_settlements')->truncate();
        if (Schema::hasTable('employee_exit_clearances')) DB::table('employee_exit_clearances')->truncate();
        if (Schema::hasTable('employee_exits')) DB::table('employee_exits')->truncate();
        if (Schema::hasTable('employee_probation_evaluations')) DB::table('employee_probation_evaluations')->truncate();

        Schema::enableForeignKeyConstraints();

        // 1. Fetch Tenant and Admin User
        $tenant = Tenant::first() ?? Tenant::create([
            'name' => 'Demo Tenant',
            'slug' => 'demo',
            'status' => 'active'
        ]);

        $user = User::where('email', 'admin@demo.com')->first() ?? User::create([
            'name' => 'Admin User',
            'email' => 'admin@demo.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id
        ]);

        // 2. Org Structure: Company warrgyizmorsch
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'company_name' => 'Warrgyizmorsch',
            'legal_name' => 'Warrgyizmorsch Pvt Ltd',
            'status' => true
        ]);

        $bu = BusinessUnit::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Technology Business Unit',
            'code' => 'TBU',
            'status' => true
        ]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $bu->id,
            'name' => 'Headquarters',
            'code' => 'HQ',
            'status' => true
        ]);

        // 2 Departments
        $deptEng = Department::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $bu->id,
            'branch_id' => $branch->id,
            'name' => 'Engineering',
            'code' => 'ENG',
            'status' => true
        ]);

        $deptHr = Department::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'business_unit_id' => $bu->id,
            'branch_id' => $branch->id,
            'name' => 'Human Resources',
            'code' => 'HR',
            'status' => true
        ]);

        // 4 Designations (2 in ENG, 2 in HR)
        $desigSe = Designation::create([
            'tenant_id' => $tenant->id,
            'department_id' => $deptEng->id,
            'name' => 'Software Engineer',
            'level' => 'L1',
            'status' => true
        ]);

        $desigTl = Designation::create([
            'tenant_id' => $tenant->id,
            'department_id' => $deptEng->id,
            'name' => 'Tech Lead',
            'level' => 'L3',
            'status' => true
        ]);

        $desigHra = Designation::create([
            'tenant_id' => $tenant->id,
            'department_id' => $deptHr->id,
            'name' => 'HR Associate',
            'level' => 'L1',
            'status' => true
        ]);

        $desigHrm = Designation::create([
            'tenant_id' => $tenant->id,
            'department_id' => $deptHr->id,
            'name' => 'HR Manager',
            'level' => 'L3',
            'status' => true
        ]);

        // 3. Salary Structure: 2 Pay Groups & Packages
        $payGroup1 = PayGroup::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Regular Pay Group (Deductions)',
            'payroll_rules' => [
                'proration_rule' => 'calendar_days',
                'lop_splicing_rule' => 'proportionate_gross',
                'attendance_lock_day' => 25,
                'variable_lock_day' => 25,
                'enable_pf' => true,
                'restrict_pf_ceiling' => true,
                'enable_esi' => true,
                'restrict_esi_threshold' => true
            ],
            'status' => true
        ]);

        $payGroup2 = PayGroup::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Executive Pay Group (No Deductions)',
            'payroll_rules' => [
                'proration_rule' => 'calendar_days',
                'lop_splicing_rule' => 'proportionate_gross',
                'attendance_lock_day' => 25,
                'variable_lock_day' => 25,
                'enable_pf' => false,
                'restrict_pf_ceiling' => false,
                'enable_esi' => false,
                'restrict_esi_threshold' => false
            ],
            'status' => true
        ]);

        // Salary components for Pay Group 1
        $compBasic = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroup1->id,
            'name' => 'Basic Salary',
            'code' => 'BASIC',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'default_value' => '30000',
            'description' => 'Base salary',
            'is_adhoc' => false,
            'status' => true
        ]);

        $compSpl = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroup1->id,
            'name' => 'Special Allowance',
            'code' => 'SPL',
            'type' => 'earning',
            'calculation_type' => 'balancing',
            'default_value' => '0',
            'description' => 'Balancing allowance',
            'is_adhoc' => false,
            'status' => true
        ]);

        $compPf = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroup1->id,
            'name' => 'Provident Fund',
            'code' => 'PF',
            'type' => 'deduction',
            'calculation_type' => 'fixed',
            'default_value' => '1800',
            'description' => 'Provident Fund Deduction',
            'is_adhoc' => false,
            'status' => true
        ]);

        $compEsi = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroup1->id,
            'name' => 'Employee State Insurance',
            'code' => 'ESI',
            'type' => 'deduction',
            'calculation_type' => 'fixed',
            'default_value' => '0',
            'description' => 'ESI Deduction',
            'is_adhoc' => false,
            'status' => true
        ]);

        // Structure 1 (with deductions)
        $structure1 = SalaryStructure::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroup1->id,
            'name' => 'Regular Employee Salary Package',
            'min_ctc' => 0,
            'max_ctc' => 600000,
            'status' => true
        ]);

        SalaryStructureItem::create([
            'tenant_id' => $tenant->id,
            'salary_structure_id' => $structure1->id,
            'salary_component_id' => $compBasic->id,
            'calculation_type' => 'fixed',
            'value' => 30000
        ]);
        SalaryStructureItem::create([
            'tenant_id' => $tenant->id,
            'salary_structure_id' => $structure1->id,
            'salary_component_id' => $compSpl->id,
            'calculation_type' => 'balancing',
            'value' => 10000
        ]);
        SalaryStructureItem::create([
            'tenant_id' => $tenant->id,
            'salary_structure_id' => $structure1->id,
            'salary_component_id' => $compPf->id,
            'calculation_type' => 'fixed',
            'value' => 1800
        ]);
        SalaryStructureItem::create([
            'tenant_id' => $tenant->id,
            'salary_structure_id' => $structure1->id,
            'salary_component_id' => $compEsi->id,
            'calculation_type' => 'fixed',
            'value' => 0
        ]);

        // Salary components for Pay Group 2 (Exec)
        $compBasicExec = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroup2->id,
            'name' => 'Basic Salary Executive',
            'code' => 'BASIC_EXEC',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'default_value' => '60000',
            'description' => 'Base salary executive',
            'is_adhoc' => false,
            'status' => true
        ]);

        $compSplExec = SalaryComponent::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroup2->id,
            'name' => 'Special Allowance Executive',
            'code' => 'SPL_EXEC',
            'type' => 'earning',
            'calculation_type' => 'balancing',
            'default_value' => '0',
            'description' => 'Balancing allowance executive',
            'is_adhoc' => false,
            'status' => true
        ]);

        // Structure 2 (No deductions)
        $structure2 = SalaryStructure::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'pay_group_id' => $payGroup2->id,
            'name' => 'Executive Employee Salary Package',
            'min_ctc' => 0,
            'max_ctc' => 1200000,
            'status' => true
        ]);

        SalaryStructureItem::create([
            'tenant_id' => $tenant->id,
            'salary_structure_id' => $structure2->id,
            'salary_component_id' => $compBasicExec->id,
            'calculation_type' => 'fixed',
            'value' => 60000
        ]);
        SalaryStructureItem::create([
            'tenant_id' => $tenant->id,
            'salary_structure_id' => $structure2->id,
            'salary_component_id' => $compSplExec->id,
            'calculation_type' => 'balancing',
            'value' => 20000
        ]);

        // 4. Leave Structure: Two Leave Plans
        $leavePlanStandard = LeavePlan::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Standard Leave Plan',
            'effective_from' => '2026-08-01',
            'description' => 'Regular corporate leave plan',
            'status' => true
        ]);

        $ltCasualStandard = LeaveType::create([
            'tenant_id' => $tenant->id,
            'leave_plan_id' => $leavePlanStandard->id,
            'name' => 'Casual Leave',
            'code' => 'CL',
            'type' => 'paid',
            'quota' => 12,
            'status' => true
        ]);

        $ltSickStandard = LeaveType::create([
            'tenant_id' => $tenant->id,
            'leave_plan_id' => $leavePlanStandard->id,
            'name' => 'Sick Leave',
            'code' => 'SL',
            'type' => 'paid',
            'quota' => 12,
            'status' => true
        ]);

        $ltEarnedStandard = LeaveType::create([
            'tenant_id' => $tenant->id,
            'leave_plan_id' => $leavePlanStandard->id,
            'name' => 'Earned Leave',
            'code' => 'EL',
            'type' => 'paid',
            'quota' => 18,
            'status' => true
        ]);

        $leavePlanExecutive = LeavePlan::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Executive Leave Plan',
            'effective_from' => '2026-08-01',
            'description' => 'Executive corporate leave plan',
            'status' => true
        ]);

        $ltCasualExecutive = LeaveType::create([
            'tenant_id' => $tenant->id,
            'leave_plan_id' => $leavePlanExecutive->id,
            'name' => 'Casual Leave (Exec)',
            'code' => 'CL-EXE',
            'type' => 'paid',
            'quota' => 15,
            'status' => true
        ]);

        $ltSickExecutive = LeaveType::create([
            'tenant_id' => $tenant->id,
            'leave_plan_id' => $leavePlanExecutive->id,
            'name' => 'Sick Leave (Exec)',
            'code' => 'SL-EXE',
            'type' => 'paid',
            'quota' => 15,
            'status' => true
        ]);

        $ltEarnedExecutive = LeaveType::create([
            'tenant_id' => $tenant->id,
            'leave_plan_id' => $leavePlanExecutive->id,
            'name' => 'Earned Leave (Exec)',
            'code' => 'EL-EXE',
            'type' => 'paid',
            'quota' => 24,
            'status' => true
        ]);

        // 5. Shift Master: 2 Valid Shifts
        $shiftDay = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Day Shift',
            'code' => 'DAY',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'active' => true
        ]);

        $shiftNight = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Night Shift',
            'code' => 'NIGHT',
            'start_time' => '21:00:00',
            'end_time' => '06:00:00',
            'active' => true
        ]);

        // 6. Penalization Policy (Attendance Rules)
        AttendanceRule::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'office_web' => true,
            'status' => true
        ]);

        // 7. Asset Management: 2 Categories & Items
        $catIt = AssetCategory::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'IT Hardware',
            'description' => 'Laptops, monitors, accessories'
        ]);

        $catFurniture = AssetCategory::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Office Furniture',
            'description' => 'Chairs, desks, cabinets'
        ]);

        // Create AssetItems (parent category item definitions)
        $itemLaptop = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $catIt->id,
            'name' => 'MacBook Pro 16"',
            'description' => 'MacBook Pro Laptops'
        ]);

        $itemMonitor = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $catIt->id,
            'name' => 'Dell 27" 4K Monitor',
            'description' => '4K Monitors'
        ]);

        $itemChair = AssetItem::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $catFurniture->id,
            'name' => 'Ergonomic Office Chair',
            'description' => 'Ergonomic Office Chairs'
        ]);

        // Create Assets (physical serialized instances)
        $assetLaptop1 = Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $catIt->id,
            'asset_item_id' => $itemLaptop->id,
            'name' => 'MacBook Pro 16"',
            'asset_code' => 'MBP-001',
            'serial_number' => 'SN-MBP-001',
            'status' => 'available'
        ]);

        $assetLaptop2 = Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $catIt->id,
            'asset_item_id' => $itemLaptop->id,
            'name' => 'MacBook Pro 16"',
            'asset_code' => 'MBP-002',
            'serial_number' => 'SN-MBP-002',
            'status' => 'available'
        ]);

        $assetMonitor1 = Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $catIt->id,
            'asset_item_id' => $itemMonitor->id,
            'name' => 'Dell 27" 4K Monitor',
            'asset_code' => 'MON-001',
            'serial_number' => 'SN-DEL-001',
            'status' => 'available'
        ]);

        $assetChair1 = Asset::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $catFurniture->id,
            'asset_item_id' => $itemChair->id,
            'name' => 'Ergonomic Office Chair',
            'asset_code' => 'CHR-001',
            'serial_number' => 'SN-CHR-001',
            'status' => 'available'
        ]);

        // 8. Document Master: 2 Categories & Documents
        $docCatId = DocumentCategory::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Identity Proofs',
            'description' => 'Government issued identity cards'
        ]);

        $docCatAcad = DocumentCategory::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Academic Credentials',
            'description' => 'Degrees and certificates'
        ]);

        $masterPassport = DocumentMaster::create([
            'tenant_id' => $tenant->id,
            'document_category_id' => $docCatId->id,
            'name' => 'Passport',
            'code' => 'PASSPORT',
            'is_required' => true,
            'status' => 'active'
        ]);

        $masterAadhaar = DocumentMaster::create([
            'tenant_id' => $tenant->id,
            'document_category_id' => $docCatId->id,
            'name' => 'Aadhaar Card',
            'code' => 'AADHAAR',
            'is_required' => true,
            'status' => 'active'
        ]);

        $masterHighSchool = DocumentMaster::create([
            'tenant_id' => $tenant->id,
            'document_category_id' => $docCatAcad->id,
            'name' => 'High School Certificate',
            'code' => 'HIGHSCHOOL',
            'is_required' => false,
            'status' => 'active'
        ]);

        $masterGraduation = DocumentMaster::create([
            'tenant_id' => $tenant->id,
            'document_category_id' => $docCatAcad->id,
            'name' => 'Graduation Degree',
            'code' => 'GRADUATION',
            'is_required' => true,
            'status' => 'active'
        ]);

        // 9. Valid Holidays
        \App\Domains\HRMS\Models\HolidayCalendar::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'New Year Day',
            'holiday_date' => '2026-01-01',
            'status' => true
        ]);
        \App\Domains\HRMS\Models\HolidayCalendar::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Independence Day',
            'holiday_date' => '2026-08-15',
            'status' => true
        ]);
        \App\Domains\HRMS\Models\HolidayCalendar::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Christmas Day',
            'holiday_date' => '2026-12-25',
            'status' => true
        ]);

        // 10. Valid Expense Policies
        $expCatTravel = \App\Domains\HRMS\Models\ExpenseCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Travel',
            'code' => 'TRAVEL',
            'status' => true
        ]);

        $expCatMeals = \App\Domains\HRMS\Models\ExpenseCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Meals',
            'code' => 'MEALS',
            'status' => true
        ]);

        $expPolicy = \App\Domains\HRMS\Models\ExpensePolicy::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Standard Corporate Expense Policy',
            'description' => 'Standard corporate limits',
            'status' => true
        ]);

        \App\Domains\HRMS\Models\ExpensePolicyRule::create([
            'expense_policy_id' => $expPolicy->id,
            'expense_category_id' => $expCatTravel->id,
            'max_monthly_limit' => 15000,
            'receipt_required' => true
        ]);

        \App\Domains\HRMS\Models\ExpensePolicyRule::create([
            'expense_policy_id' => $expPolicy->id,
            'expense_category_id' => $expCatMeals->id,
            'max_daily_limit' => 1000,
            'receipt_required' => false
        ]);

        // 11. Create 5 Employees including Admin User with Realistic Names
        $empNames = [
            ['first' => 'Admin', 'last' => 'User', 'email' => 'admin@demo.com', 'desig' => $desigTl->id, 'dept' => $deptEng->id, 'structure' => $structure2->id, 'gender' => 'Male', 'is_admin' => true],
            ['first' => 'Rahul', 'last' => 'Sharma', 'email' => 'rahul@warrg.com', 'desig' => $desigSe->id, 'dept' => $deptEng->id, 'structure' => $structure1->id, 'gender' => 'Male', 'is_admin' => false],
            ['first' => 'Priya', 'last' => 'Nair', 'email' => 'priya@warrg.com', 'desig' => $desigTl->id, 'dept' => $deptEng->id, 'structure' => $structure2->id, 'gender' => 'Female', 'is_admin' => false],
            ['first' => 'Amit', 'last' => 'Patel', 'email' => 'amit@warrg.com', 'desig' => $desigHra->id, 'dept' => $deptHr->id, 'structure' => $structure1->id, 'gender' => 'Male', 'is_admin' => false],
            ['first' => 'Sneha', 'last' => 'Rao', 'email' => 'sneha@warrg.com', 'desig' => $desigHrm->id, 'dept' => $deptHr->id, 'structure' => $structure2->id, 'gender' => 'Female', 'is_admin' => false],
        ];

        $employeesList = [];
        foreach ($empNames as $index => $item) {
            if ($item['is_admin']) {
                $empUser = $user;
            } else {
                $empUser = User::where('tenant_id', $tenant->id)->where('email', $item['email'])->first() ?? User::create([
                    'name' => $item['first'] . ' ' . $item['last'],
                    'email' => $item['email'],
                    'password' => bcrypt('password'),
                    'tenant_id' => $tenant->id
                ]);
            }

            $isExec = ($item['structure'] == $structure2->id);
            $planId = $isExec ? $leavePlanExecutive->id : $leavePlanStandard->id;
            $clId = $isExec ? $ltCasualExecutive->id : $ltCasualStandard->id;
            $slId = $isExec ? $ltSickExecutive->id : $ltSickStandard->id;
            $clQuota = $isExec ? 15 : 12;
            $slQuota = $isExec ? 15 : 12;

            $desigModel = Designation::find($item['desig']);
            $emp = Employee::create([
                'tenant_id' => $tenant->id,
                'user_id' => $empUser->id,
                'employee_id' => 'WRG-' . str_pad($index, 3, '0', STR_PAD_LEFT),
                'full_name' => $item['first'] . ' ' . $item['last'],
                'nick_name' => $item['first'],
                'job_title' => $desigModel?->name ?? 'Professional',
                'employment_type' => 'Full-time',
                'employee_stage' => 'Probation',
                'personal_email' => $item['email'],
                'office_email' => strtolower($item['first']) . '@company.com',
                'personal_mobile_number' => '+91 98765 ' . str_pad((string)(43210 + $index * 111), 5, '0', STR_PAD_LEFT),
                'home_phone' => '011-2345678' . $index,
                'company_id' => $company->id,
                'business_unit_id' => $bu->id,
                'branch_id' => $branch->id,
                'department_id' => $item['dept'],
                'designation_id' => $item['desig'],
                'reporting_manager_id' => $index > 0 && isset($employeesList[0]) ? $employeesList[0]->id : null,
                'shift_id' => $shiftDay->id,
                'pay_group_id' => $item['structure'] == $structure1->id ? $payGroup1->id : $payGroup2->id,
                'salary_structure_id' => $item['structure'],
                'leave_plan_id' => $planId,
                'current_salary' => $item['structure'] == $structure1->id ? 480000 : 960000,
                'experience' => 3.5 + $index,
                'date_of_joining' => '2026-08-01',
                'date_of_birth' => '1995-0' . min(9, $index + 1) . '-15',
                'probation_end_date' => '2026-11-01',
                'confirmation_date' => '2026-11-01',
                'gender' => $item['gender'],
                'marital_status' => $index % 2 === 0 ? 'Married' : 'Single',
                'blood_group' => ['A+', 'B+', 'O+', 'AB+', 'O-'][$index % 5],
                'diet_preference' => $index % 2 === 0 ? 'Vegetarian' : 'Non-Vegetarian',
                'office' => 'office',
                'present_address' => 'Plot #' . (10 + $index) . ', Tech Park Road, Sector 62',
                'permanent_address' => 'Plot #' . (10 + $index) . ', Tech Park Road, Sector 62',
                'city' => 'Noida',
                'postal_code' => '201301',
                'aadhaar_card_number' => '5432 8765 ' . str_pad((string)(1000 + $index * 111), 4, '0', STR_PAD_LEFT),
                'pan_card_number' => 'ABCDE' . (1234 + $index) . 'F',
                'bank_name' => 'HDFC Bank',
                'account_number' => '5010023456789' . $index,
                'ifsc_code' => 'HDFC0001234',
                'emergency_contact_name' => 'Emergency Contact ' . ($index + 1),
                'emergency_contact_number' => '+91 91234 5678' . $index,
                'emergency_contact_relation' => $index % 2 === 0 ? 'Spouse' : 'Parent',
                'qualification' => 'B.Tech / MCA in Computer Science',
                'source_of_hire' => 'LinkedIn / Direct Referral',
                'skill_set' => 'PHP, Laravel, MySQL, JavaScript, Vue.js, System Architecture',
                'weekly_pattern' => [0 => 'off', 1 => $shiftDay->id, 2 => $shiftDay->id, 3 => $shiftDay->id, 4 => $shiftDay->id, 5 => $shiftDay->id, 6 => 'off'], // Sat & Sun off
                'status' => true
            ]);
            $employeesList[] = $emp;

            // Setup initial leave balances
            LeaveBalance::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $emp->id,
                'leave_type_id' => $clId,
                'allocated' => $clQuota,
                'used' => 0,
                'encashed' => 0
            ]);
            LeaveBalance::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'employee_id' => $emp->id,
                'leave_type_id' => $slId,
                'allocated' => $slQuota,
                'used' => 0,
                'encashed' => 0
            ]);
        }

        // 12. Feed Data in Document according to the Master
        foreach ($employeesList as $emp) {
            Document::create([
                'tenant_id' => $tenant->id,
                'documentable_id' => $emp->id,
                'documentable_type' => Employee::class,
                'document_master_id' => $masterPassport->id,
                'name' => 'Passport',
                'file_name' => 'passport.pdf',
                'file_path' => 'demo/passport.pdf',
                'status' => 'approved'
            ]);
            Document::create([
                'tenant_id' => $tenant->id,
                'documentable_id' => $emp->id,
                'documentable_type' => Employee::class,
                'document_master_id' => $masterAadhaar->id,
                'name' => 'Aadhaar Card',
                'file_name' => 'aadhaar.pdf',
                'file_path' => 'demo/aadhaar.pdf',
                'status' => 'approved'
            ]);
        }

        // 13. Feed Data in Asset: Allocations and Requests
        AssetAllocation::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $assetLaptop1->id,
            'employee_id' => $employeesList[1]->id, // Rahul
            'allocated_at' => '2026-08-02',
            'allocation_condition' => 'good'
        ]);
        $assetLaptop1->update(['status' => 'allocated', 'assigned_employee_id' => $employeesList[1]->id]);

        AssetAllocation::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $assetMonitor1->id,
            'employee_id' => $employeesList[2]->id, // Priya
            'allocated_at' => '2026-08-02',
            'allocation_condition' => 'good'
        ]);
        $assetMonitor1->update(['status' => 'allocated', 'assigned_employee_id' => $employeesList[2]->id]);

        // Asset Request from Amit
        AssetRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeesList[3]->id,
            'asset_category_id' => $catFurniture->id,
            'asset_item_id' => $itemChair->id,
            'quantity' => 1,
            'reason' => 'Need ergonomic chair for home office setup',
            'status' => 'approved',
            'request_date' => '2026-08-05'
        ]);

        // 14. Feed WFH, Leave, Overtime, Travel & Expense
        // Rahul WFH on Aug 10-11
        WfhRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeesList[1]->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-11',
            'duration' => 2,
            'reason' => 'Broadband installation at home',
            'status' => 'approved'
        ]);

        // Amit Leave on Aug 18-19
        LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeesList[3]->id,
            'leave_type_id' => $ltCasualStandard->id,
            'start_date' => '2026-08-18',
            'end_date' => '2026-08-19',
            'duration' => 2,
            'reason' => 'Family event',
            'status' => 'approved'
        ]);
        $bal = LeaveBalance::where('employee_id', $employeesList[3]->id)->where('leave_type_id', $ltCasualStandard->id)->first();
        $bal->update([
            'used' => 2
        ]);

        // Rahul Overtime on Aug 15 (Holiday!)
        OvertimeRequest::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'employee_id' => $employeesList[1]->id,
            'date' => '2026-08-15',
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'duration_hours' => 4,
            'compensation_type' => 'pay',
            'reason' => 'Server maintenance deployment support',
            'status' => 'approved'
        ]);

        // Travel & Expense: Priya
        \App\Domains\HRMS\Models\TravelRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employeesList[2]->id,
            'purpose' => 'Quarterly branch audit',
            'destination' => 'Branch Office',
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-13',
            'estimated_budget' => 5000,
            'status' => 'approved'
        ]);

        $expReport = \App\Domains\HRMS\Models\ExpenseReport::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employeesList[2]->id,
            'title' => 'Branch Audit Travel Expenses',
            'status' => 'approved',
            'total_amount' => 4200.00,
            'advance_adjusted' => 0.00,
            'net_reimbursement' => 4200.00,
            'approved_amount' => 4200.00,
            'approved_net_reimbursement' => 4200.00,
        ]);

        \App\Domains\HRMS\Models\ExpenseClaim::create([
            'expense_report_id' => $expReport->id,
            'expense_category_id' => $expCatTravel->id,
            'expense_date' => '2026-08-14',
            'amount' => 4200,
            'receipt_path' => 'demo/receipt_travel.pdf',
            'description' => 'Travel flight tickets'
        ]);

        // 15. Shift Rosters: Scheduled day shift for Aug 1 to Sep 30
        $current = \Carbon\Carbon::parse('2026-08-01');
        $end = \Carbon\Carbon::parse('2026-09-30');
        while ($current->lte($end)) {
            foreach ($employeesList as $emp) {
                ShiftRoster::create([
                    'tenant_id' => $tenant->id,
                    'employee_id' => $emp->id,
                    'shift_id' => $shiftDay->id,
                    'date' => $current->format('Y-m-d'),
                    'status' => 'scheduled'
                ]);
            }
            $current->addDay();
        }

        // 16. Feed Attendance Logs
        $current = \Carbon\Carbon::parse('2026-08-01');
        $attEnd = \Carbon\Carbon::parse('2026-08-29');
        while ($current->lte($attEnd)) {
            $dateStr = $current->format('Y-m-d');
            $dayOfWeek = $current->dayOfWeek;
            
            $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);
            $isHoliday = ($dateStr === '2026-08-15');
            
            foreach ($employeesList as $emp) {
                $onLeave = ($emp->id === $employeesList[3]->id && ($dateStr === '2026-08-18' || $dateStr === '2026-08-19'));
                
                if ($isWeekend || $isHoliday || $onLeave) {
                    continue;
                }
                
                $locType = 'office';
                $checkIn = '08:55:00';
                $checkOut = '18:05:00';
                $status = 'present';
                
                // Rahul WFH
                if ($emp->id === $employeesList[1]->id && ($dateStr === '2026-08-10' || $dateStr === '2026-08-11')) {
                    $locType = 'wfh';
                }
                
                // Absent on Aug 25
                if ($dateStr === '2026-08-25') {
                    continue;
                }
                
                // Late on Aug 12
                if ($dateStr === '2026-08-12') {
                    $checkIn = '09:25:00';
                    $status = 'late';
                }
                
                // Half day on Aug 20
                if ($dateStr === '2026-08-20') {
                    $checkOut = '13:00:00';
                    $status = 'half_day';
                }
                
                $checkInDt = \Carbon\Carbon::parse($dateStr . ' ' . $checkIn);
                $checkOutDt = \Carbon\Carbon::parse($dateStr . ' ' . $checkOut);
                
                $att = Attendance::create([
                    'tenant_id' => $tenant->id,
                    'employee_id' => $emp->id,
                    'date' => $dateStr,
                    'check_in' => $checkInDt,
                    'check_out' => $checkOutDt,
                    'location_type' => $locType,
                    'status' => $status
                ]);

                AttendanceBreak::create([
                    'attendance_id' => $att->id,
                    'break_in' => \Carbon\Carbon::parse($dateStr . ' 13:00:00'),
                    'break_out' => \Carbon\Carbon::parse($dateStr . ' 14:00:00'),
                ]);
            }
            $current->addDay();
        }

        // 17. Feed Payroll Data (completed payrun for July 2026)
        \App\Domains\HRMS\Models\PayrollRun::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'payroll_month' => '2026-07',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'completed',
            'processed_by' => $user->id
        ]);
        
        \App\Domains\HRMS\Models\SalaryRevision::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employeesList[1]->id,
            'old_salary_structure_id' => $structure1->id,
            'new_salary_structure_id' => $structure1->id,
            'effective_date' => '2026-08-01',
            'old_ctc' => 40000,
            'new_ctc' => 45000,
            'arrears_paid' => false
        ]);

        // 18. Sample Probation Evaluation
        if (class_exists(\App\Domains\HRMS\Models\EmployeeProbationEvaluation::class) && Schema::hasTable('employee_probation_evaluations')) {
            \App\Domains\HRMS\Models\EmployeeProbationEvaluation::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employeesList[1]->id,
                'reviewer_id' => $user->id,
                'evaluation_date' => '2026-08-10',
                'performance_rating' => 4,
                'attendance_rating' => 5,
                'culture_rating' => 4,
                'recommendation' => 'confirm',
                'remarks' => 'Exceptional onboarding velocity and excellent technical contribution.',
                'status' => 'completed',
            ]);
            $employeesList[1]->update([
                'employee_stage' => 'Confirmed',
                'confirmation_date' => '2026-08-10',
            ]);

            // Set Amit (employee 3) in active probation ending in 10 days
            $employeesList[3]->update([
                'employee_stage' => 'Probation',
                'probation_end_date' => \Carbon\Carbon::today()->addDays(10)->format('Y-m-d'),
            ]);
        }

        // 19. Sample Employee Exit & Clearance
        if (class_exists(\App\Domains\HRMS\Models\EmployeeExit::class) && Schema::hasTable('employee_exits')) {
            $sampleExit = \App\Domains\HRMS\Models\EmployeeExit::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employeesList[4]->id, // Sneha
                'separation_type' => 'resignation',
                'resignation_date' => '2026-08-15',
                'preferred_lwd' => '2026-09-15',
                'approved_lwd' => '2026-09-15',
                'notice_period_days' => 30,
                'notice_shortfall_days' => 0,
                'notice_action' => 'serve',
                'reason_category' => 'Better Opportunity',
                'reason_details' => 'Accepted senior leadership role.',
                'status' => 'in_clearance',
                'initiated_by' => 'employee',
                'approved_by' => $user->id,
                'approved_at' => '2026-08-16 10:00:00',
            ]);

            $employeesList[4]->update(['employee_stage' => 'Notice Period']);

            $standardChecklist = [
                ['department' => 'it', 'item_name' => 'Hardware Asset Recovery (Laptop/Accessories)', 'status' => 'pending'],
                ['department' => 'it', 'item_name' => 'Email, Slack & ERP System Logins Deactivation', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
                ['department' => 'it', 'item_name' => 'Cloud Data Backup & File Handover', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
                ['department' => 'admin', 'item_name' => 'Company Physical ID Card & Access Badge Handover', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
                ['department' => 'admin', 'item_name' => 'Office Keys, Drawer Keys & Parking Tag Handover', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
                ['department' => 'finance', 'item_name' => 'Reconcile Open Cash Advances & Loan Accounts', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
                ['department' => 'finance', 'item_name' => 'Verify Pending Travel & Expense Reimbursements', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
                ['department' => 'finance', 'item_name' => 'Notice Period Shortfall / Buyout Verification', 'status' => 'waived', 'cleared_by' => $user->id, 'cleared_at' => now()],
                ['department' => 'hr', 'item_name' => 'Exit Interview & Feedback Questionnaire Completed', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
                ['department' => 'hr', 'item_name' => 'PF, Gratuity & Pension Settlement Verification', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
                ['department' => 'manager', 'item_name' => 'Knowledge Transfer (KT) & Task Handover Sign-off', 'status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now()],
                ['department' => 'manager', 'item_name' => 'Client Contacts, Repo & Credentials Handover', 'status' => 'pending'],
            ];

            foreach ($standardChecklist as $item) {
                \App\Domains\HRMS\Models\EmployeeExitClearance::create([
                    'tenant_id' => $tenant->id,
                    'employee_exit_id' => $sampleExit->id,
                    'department' => $item['department'],
                    'item_name' => $item['item_name'],
                    'status' => $item['status'],
                    'cleared_by' => $item['cleared_by'] ?? null,
                    'cleared_at' => $item['cleared_at'] ?? null,
                ]);
            }

            // Generate initial FnF settlement draft
            $fnfService = new \App\Domains\HRMS\Services\FnFCalculationService();
            $computedFnF = $fnfService->calculateFnF($sampleExit);
            $fnfService->saveSettlement($sampleExit, $computedFnF);
        }
    }
}
