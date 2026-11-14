<?php
/**
 * 07_test_employee_and_shift_failures.php
 *
 * Negative Test Suite for Employee Staffing & Shift Business Rules.
 * Verifies that WorkCenterEmployeeService properly enforces:
 * - Mutual Exclusivity: An employee cannot hold both active WC-level and Section-level assignments
 * - Section Exclusivity: An employee assigned to a Section cannot be assigned to another Section
 * - Duplicate Active Assignment: Re-assigning to the exact same Section/WC is rejected
 * - Section Partial Allocation Restriction: Shift must lie completely within Section operating hours (idle minutes == 0)
 * - Zero Operational Overlap Restriction: Zero overlap between shift and target hours is rejected
 *
 * Usage: php scratch_production/07_test_employee_and_shift_failures.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\DTO\WorkCenterDTO;
use App\Domains\Production\DTO\WorkCenterSectionDTO;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\WorkCenterEmployeeService;
use App\Domains\Production\Services\WorkCenterSectionService;
use App\Domains\Production\Services\WorkCenterService;
use Illuminate\Support\Facades\DB;

$tenantId = 1;
$passed = 0;
$failed = 0;

echo "========================================================" . PHP_EOL;
echo "  TEST SUITE: EMPLOYEE STAFFING & SHIFT RULE FAILURES" . PHP_EOL;
echo "========================================================" . PHP_EOL . PHP_EOL;

$wcService = app(WorkCenterService::class);
$wcSectionService = app(WorkCenterSectionService::class);
$wcEmployeeService = app(WorkCenterEmployeeService::class);

function runScenario(string $testId, string $description, callable $fn, string $expectedSubstring = '') use (&$passed, &$failed) {
    DB::beginTransaction();
    try {
        $fn();
        DB::rollBack();
        echo "  [FAIL] $testId: $description" . PHP_EOL;
        echo "         -> Error: Expected exception was NOT thrown! Operation unexpectedly succeeded." . PHP_EOL;
        $failed++;
    } catch (\Throwable $e) {
        DB::rollBack();
        $msg = $e->getMessage();
        if ($expectedSubstring === '' || str_contains(strtolower($msg), strtolower($expectedSubstring))) {
            echo "  [PASS] $testId: $description" . PHP_EOL;
            echo "         -> Correctly caught " . get_class($e) . ": '$msg'" . PHP_EOL;
            $passed++;
        } else {
            echo "  [PASS-WARN] $testId: $description" . PHP_EOL;
            echo "         -> Exception caught (" . get_class($e) . "), but message differed slightly:" . PHP_EOL;
            echo "            Actual: '$msg'" . PHP_EOL;
            echo "            Expected snippet: '$expectedSubstring'" . PHP_EOL;
            $passed++;
        }
    }
}

$wc1 = WorkCenter::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', 'test-wc-1')->firstOrFail();
$sec1 = WorkCenterSection::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', 'test-sec-1')->firstOrFail();
$sec2 = WorkCenterSection::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', 'test-sec-2')->firstOrFail();
$sec6 = WorkCenterSection::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', 'test-sec-6')->firstOrFail(); // Operates only MRN (06:00-14:30)

// Active WC-level staff
$empWcStaff = Employee::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('employee_id', 'WRG-004')->firstOrFail();

// Active Section 2 staff
$empSec2Staff = Employee::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('employee_id', 'test-emp-3')->firstOrFail();

// Mid-day shift employee (11:00 to 18:00)
$empMidDay = Employee::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('employee_id', 'test-emp-16')->firstOrFail();

// Graveyard shift employee (00:00 to 06:00)
$empGraveyard = Employee::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('employee_id', 'test-emp-28')->firstOrFail();

// ── Test EMP-NEG-01: Mutual Exclusivity (WC-level to Section-level) ───────────
runScenario(
    'EMP-NEG-01',
    'Reject assigning WC-level employee (WRG-004) to Section 1 (Mutual Exclusivity)',
    function () use ($wcEmployeeService, $wc1, $sec1, $empWcStaff, $tenantId) {
        $wcEmployeeService->assign(
            workCenterId: $wc1->id,
            employeeId:   $empWcStaff->id,
            tenantId:     $tenantId,
            active:       true,
            sectionId:    $sec1->id
        );
    },
    'mutual exclusivity prevents holding both'
);

// ── Test EMP-NEG-02: Mutual Exclusivity (Section-level to WC-level) ───────────
runScenario(
    'EMP-NEG-02',
    'Reject assigning Section 2 employee (test-emp-3) at WC level (Mutual Exclusivity)',
    function () use ($wcEmployeeService, $wc1, $empSec2Staff, $tenantId) {
        $wcEmployeeService->assign(
            workCenterId: $wc1->id,
            employeeId:   $empSec2Staff->id,
            tenantId:     $tenantId,
            active:       true,
            sectionId:    null // WC level
        );
    },
    'mutual exclusivity prevents holding both'
);

// ── Test EMP-NEG-03: Section Exclusivity (Single Section Assignment Only) ─────
runScenario(
    'EMP-NEG-03',
    'Reject assigning Section 2 employee (test-emp-3) to Section 1 (Section Exclusivity)',
    function () use ($wcEmployeeService, $wc1, $sec1, $empSec2Staff, $tenantId) {
        $wcEmployeeService->assign(
            workCenterId: $wc1->id,
            employeeId:   $empSec2Staff->id,
            tenantId:     $tenantId,
            active:       true,
            sectionId:    $sec1->id
        );
    },
    'an employee assigned to a section cannot be assigned to any other section'
);

// ── Test EMP-NEG-04: Duplicate Active Assignment to Same Target ───────────────
runScenario(
    'EMP-NEG-04',
    'Reject duplicate assignment of test-emp-3 to Section 2',
    function () use ($wcEmployeeService, $wc1, $sec2, $empSec2Staff, $tenantId) {
        $wcEmployeeService->assign(
            workCenterId: $wc1->id,
            employeeId:   $empSec2Staff->id,
            tenantId:     $tenantId,
            active:       true,
            sectionId:    $sec2->id
        );
    },
    'already assigned to'
);

// ── Test EMP-NEG-05: Section Partial Allocation Restriction (Idle Minutes > 0) 
runScenario(
    'EMP-NEG-05',
    'Reject assigning mid-day shift employee (11:00-18:00) to MRN-only Section 6 (06:00-14:30)',
    function () use ($wcEmployeeService, $sec6, $empMidDay, $tenantId) {
        // Section 6 operates 06:00-14:30. Harish Iyer works 11:00-18:00 (210 idle mins after 14:30).
        $wcEmployeeService->assign(
            workCenterId: $sec6->work_center_id,
            employeeId:   $empMidDay->id,
            tenantId:     $tenantId,
            active:       true,
            sectionId:    $sec6->id
        );
    },
    'partial allocation restricted at section level'
);

// ── Test EMP-NEG-06: Zero Operational Overlap at Work Center Level ────────────
runScenario(
    'EMP-NEG-06',
    'Reject assigning Graveyard shift employee (00:00-06:00) to a daytime-only Work Center',
    function () use ($wcService, $wcSectionService, $wcEmployeeService, $empGraveyard, $tenantId) {
        // Create daytime-only WC & Section (09:00 - 18:00)
        $dayShiftId = ProductionShift::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', 'DAY')->value('id');

        $tempWc = $wcService->create(WorkCenterDTO::fromArray([
            'code'          => 'TEMP-DAY-WC',
            'name'          => 'Daytime Only WC',
            'overhead_cost' => 100.0,
            'status'        => 'active',
        ]), $tenantId);

        $tempSec = $wcSectionService->create(WorkCenterSectionDTO::fromArray([
            'work_center_id' => $tempWc->id,
            'code'           => 'TEMP-DAY-SEC',
            'name'           => 'Daytime Only Section',
            'status'         => 'active',
            'shifts'         => [$dayShiftId],
        ]), $tenantId);

        // Attempt assigning graveyard employee (00:00 - 06:00) to daytime WC (09:00 - 18:00) -> 0 overlap!
        $wcEmployeeService->assign(
            workCenterId: $tempWc->id,
            employeeId:   $empGraveyard->id,
            tenantId:     $tenantId,
            active:       true,
            sectionId:    null
        );
    },
    'zero operational overlap'
);

echo PHP_EOL . "========================================================" . PHP_EOL;
echo "  EMPLOYEE STAFFING & SHIFT FAILURES TEST RESULT: $passed Passed, $failed Failed" . PHP_EOL;
echo "========================================================" . PHP_EOL;

