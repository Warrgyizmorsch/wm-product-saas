<?php
/**
 * 03_seed_employees.php
 *
 * Seeds 30 dedicated plant employees (test-emp-1 through test-emp-30) in HRMS for Tenant 1.
 *
 * NOT via the web form on purpose: the HRMS employee form also creates a login user per employee and sends a
 * welcome email. HRMS is outside Production, so this script writes the Employee rows directly.
 * CTC is typed in ₹ and saved as BASE (₹ ÷ INR rate) — every money value in the DB is base.
 * Configures realistic annual CTCs, job roles, and assigns their personal master shifts
 * across the 10 production shifts.
 *
 * Usage: php scratch_production/03_seed_employees.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ProductionShift;
use Illuminate\Support\Facades\DB;

$tenantId = 1;
$inrRate = (float) config('currency.currencies.INR.rate', 1);   // base = ₹ ÷ rate

echo "========================================================" . PHP_EOL;
echo "  SEEDING 30 HRMS EMPLOYEES (Tenant ID: $tenantId)" . PHP_EOL;
echo "========================================================" . PHP_EOL . PHP_EOL;

// ── 1. Resolve Reference IDs from Existing Organization Structure ─────────────
$company = DB::table('companies')->where('tenant_id', $tenantId)->first();
$companyId = $company?->id ?? 1;

$branchId = DB::table('branches')->where('tenant_id', $tenantId)->value('id');
$departmentId = DB::table('departments')->where('tenant_id', $tenantId)->where('name', 'LIKE', '%Production%')->value('id')
    ?? DB::table('departments')->where('tenant_id', $tenantId)->value('id');

$designationId = DB::table('designations')->where('tenant_id', $tenantId)->value('id');
$buId = DB::table('business_units')->where('tenant_id', $tenantId)->value('id');
$payGroupId = DB::table('pay_groups')->where('tenant_id', $tenantId)->value('id');
$structId = DB::table('salary_structures')->where('tenant_id', $tenantId)->value('id');
$leavePlanId = DB::table('leave_plans')->where('tenant_id', $tenantId)->value('id');

// Map shifts by code
$shifts = ProductionShift::withoutGlobalScopes()
    ->where('tenant_id', $tenantId)
    ->pluck('id', 'code')
    ->toArray();

$employeesData = [
    ['code' => 'test-emp-1',  'name' => 'Suresh Kumar',   'title' => 'Production Supervisor',      'salary' => 600000.00, 'shift' => 'DAY'],
    ['code' => 'test-emp-2',  'name' => 'Ramesh Verma',    'title' => 'CNC Operator',               'salary' => 420000.00, 'shift' => 'MRN'],
    ['code' => 'test-emp-3',  'name' => 'Vikram Rao',      'title' => 'Senior Press Technician',    'salary' => 480000.00, 'shift' => 'DAY'],
    ['code' => 'test-emp-4',  'name' => 'Sunita Reddy',    'title' => 'Prep Workstation Tech',      'salary' => 360000.00, 'shift' => 'SH-04-09'],
    ['code' => 'test-emp-5',  'name' => 'Anil Sharma',     'title' => 'Toolroom Technician',        'salary' => 420000.00, 'shift' => 'DAY'],
    ['code' => 'test-emp-6',  'name' => 'Deepak Joshi',    'title' => 'Saw Operator',               'salary' => 360000.00, 'shift' => 'MRN'],
    ['code' => 'test-emp-7',  'name' => 'Meena Gupta',     'title' => 'Evening Quality Auditor',    'salary' => 480000.00, 'shift' => 'EVE'],
    ['code' => 'test-emp-8',  'name' => 'Manoj Shinde',    'title' => 'Electro-Mech Maintenance',   'salary' => 540000.00, 'shift' => 'SH-16-20'],
    ['code' => 'test-emp-9',  'name' => 'Kavita Patel',    'title' => 'Assembly Lead',              'salary' => 450000.00, 'shift' => 'DAY'],
    ['code' => 'test-emp-10', 'name' => 'Ravi Shankar',    'title' => 'Robotic Cell Tech',          'salary' => 480000.00, 'shift' => 'DAY'],
    ['code' => 'test-emp-11', 'name' => 'Pooja Mehta',     'title' => 'Morning Paint Inspector',   'salary' => 390000.00, 'shift' => 'MRN'],
    ['code' => 'test-emp-12', 'name' => 'Sanjay Patil',    'title' => 'Senior Assembler',           'salary' => 420000.00, 'shift' => 'MRN'],
    ['code' => 'test-emp-13', 'name' => 'Rahul Nair',      'title' => 'Mechanical Assembler',       'salary' => 360000.00, 'shift' => 'MRN'],
    ['code' => 'test-emp-14', 'name' => 'Sneha Kulkarni',  'title' => 'VMC Specialist',             'salary' => 510000.00, 'shift' => 'DAY'],
    ['code' => 'test-emp-15', 'name' => 'Amit Roy',        'title' => 'Evening VMC Operator',       'salary' => 450000.00, 'shift' => 'EVE'],
    ['code' => 'test-emp-16', 'name' => 'Harish Iyer',     'title' => 'Mid-Day Bench Operator',     'salary' => 420000.00, 'shift' => 'SH-11-18'],
    ['code' => 'test-emp-17', 'name' => 'Alok Mishra',     'title' => 'Master Fabricator',          'salary' => 540000.00, 'shift' => 'DAY'],
    ['code' => 'test-emp-18', 'name' => 'Rohit Chavan',    'title' => 'Fitting Artisan',            'salary' => 420000.00, 'shift' => 'DAY'],
    ['code' => 'test-emp-19', 'name' => 'Arjun Yadav',     'title' => 'Forge Operations Tech',      'salary' => 540000.00, 'shift' => 'SH-10-20'],
    ['code' => 'test-emp-20', 'name' => 'Divya Sen',       'title' => 'Precision Bending Tech',     'salary' => 420000.00, 'shift' => 'DAY'],
    ['code' => 'test-emp-21', 'name' => 'Mahesh Bhatt',    'title' => 'Sub-Assembly Tech',          'salary' => 390000.00, 'shift' => 'MRN'],
    ['code' => 'test-emp-22', 'name' => 'Neha Deshmukh',   'title' => 'Packaging Specialist',       'salary' => 360000.00, 'shift' => 'DAY'],
    ['code' => 'test-emp-23', 'name' => 'Santosh Pandey',  'title' => 'Evening Dispatch Tech',      'salary' => 360000.00, 'shift' => 'EVE'],
    ['code' => 'test-emp-24', 'name' => 'Ganesh Tambe',    'title' => 'Pre-Heat Dawn Tech',         'salary' => 420000.00, 'shift' => 'SH-04-09'],
    ['code' => 'test-emp-25', 'name' => 'Karthik Menon',   'title' => 'Surge Laser Operator',       'salary' => 480000.00, 'shift' => 'SH-16-20'],
    ['code' => 'test-emp-26', 'name' => 'Nitin Gokhale',   'title' => 'Continuous Flow Auditor',   'salary' => 600000.00, 'shift' => 'SH-11-18'],
    ['code' => 'test-emp-27', 'name' => 'Saurabh Gill',    'title' => 'Twilight Surge Tech',        'salary' => 420000.00, 'shift' => 'SH-16-20'],
    ['code' => 'test-emp-28', 'name' => 'Somnath Biswas',  'title' => 'Night Facility Inspector',   'salary' => 450000.00, 'shift' => 'SH-00-06'],
    ['code' => 'test-emp-29', 'name' => 'Sandeep Kadam',   'title' => 'Multi-Discipline Tech',      'salary' => 420000.00, 'shift' => 'DAY'],
    ['code' => 'test-emp-30', 'name' => 'Geeta Salve',     'title' => 'Multi-Discipline Tech',      'salary' => 420000.00, 'shift' => 'MRN'],
];

echo "Creating/Updating 30 Test Employees..." . PHP_EOL;

foreach ($employeesData as $empInfo) {
    $shiftId = $shifts[$empInfo['shift']] ?? reset($shifts);

    $emp = Employee::withoutGlobalScopes()
        ->where('tenant_id', $tenantId)
        ->where('employee_id', $empInfo['code'])
        ->first();

    $data = [
        'tenant_id'              => $tenantId,
        'company_id'             => $companyId,
        'branch_id'              => $branchId,
        'department_id'          => $departmentId,
        'designation_id'         => $designationId,
        'business_unit_id'       => $buId,
        'pay_group_id'           => $payGroupId,
        'salary_structure_id'    => $structId,
        'leave_plan_id'          => $leavePlanId,
        'shift_id'               => $shiftId,
        'employee_id'            => $empInfo['code'],
        'full_name'              => $empInfo['name'],
        'job_title'              => $empInfo['title'],
        'employment_type'        => 'Full-time',
        'employee_stage'         => 'Confirmed',
        'status'                 => true,
        'current_salary'         => round($empInfo['salary'] / $inrRate, 4),
        'date_of_joining'        => '2025-01-15',
        'office'                 => 'office',
        'gender'                 => 'Male',
        'marital_status'         => 'Single',
        'weekly_pattern'         => [0 => 'off'],
        'personal_email'         => strtolower(str_replace(' ', '.', $empInfo['name'])) . '@example.com',
        'personal_mobile_number' => '+91 98765 ' . rand(10000, 99999),
    ];

    if ($emp) {
        $emp->update($data);
        echo "  [UPDATED] {$empInfo['code']} ({$empInfo['name']}) -> Shift: {$empInfo['shift']} (#{$shiftId}), Annual: ₹" . number_format($empInfo['salary']) . " (base " . round($empInfo['salary'] / $inrRate, 4) . "), ID: {$emp->id}" . PHP_EOL;
    } else {
        $emp = Employee::create($data);
        echo "  [CREATED] {$empInfo['code']} ({$empInfo['name']}) -> Shift: {$empInfo['shift']} (#{$shiftId}), Annual: ₹" . number_format($empInfo['salary']) . " (base " . round($empInfo['salary'] / $inrRate, 4) . "), ID: {$emp->id}" . PHP_EOL;
    }
}

echo PHP_EOL . "30 Employees seeded successfully!" . PHP_EOL;

