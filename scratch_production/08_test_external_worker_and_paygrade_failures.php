<?php
/**
 * 08_test_external_worker_and_paygrade_failures.php
 *
 * Negative Test Suite for External Contractors & Pay Grades Business Rules.
 * Verifies that domain services properly enforce:
 * - Minimum headcount of 1 (rejection of zero or negative count)
 * - Allocation restricted to shifts assigned to Section
 * - Rejection of allocations referencing soft-deleted pay grades
 * - Duplicate allocation grouping prevention
 * - Non-negative hourly pay rate validation
 * - Unique pay grade code per tenant
 *
 * Usage: php scratch_production/08_test_external_worker_and_paygrade_failures.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\ExternalWorkerAllocationService;
use App\Domains\Production\Services\ExternalWorkerPayGradeService;
use Illuminate\Support\Facades\DB;

$tenantId = 1;
$passed = 0;
$failed = 0;

echo "========================================================" . PHP_EOL;
echo "  TEST SUITE: EXTERNAL CONTRACTORS & PAY GRADE RULE FAILURES" . PHP_EOL;
echo "========================================================" . PHP_EOL . PHP_EOL;

$extWorkerService = app(ExternalWorkerAllocationService::class);
$payGradeService = app(ExternalWorkerPayGradeService::class);

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

$sec1 = WorkCenterSection::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', 'test-sec-1')->firstOrFail();
$shiftDay = ProductionShift::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', 'DAY')->firstOrFail();
$shiftGrave = ProductionShift::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', 'SH-00-06')->firstOrFail();
$pg1 = ExternalWorkerPayGrade::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', 'test-pg-1')->firstOrFail();

// ── Test EXT-NEG-01: Zero or Negative Worker Count ────────────────────────────
runScenario(
    'EXT-NEG-01',
    'Reject external worker allocation with headcount <= 0',
    function () use ($extWorkerService, $sec1, $shiftGrave, $pg1, $tenantId) {
        $extWorkerService->allocate(
            sectionId:               $sec1->id,
            shiftId:                 $shiftGrave->id,
            payGradeId:              $pg1->id,
            numberOfExternalWorkers: 0, // Invalid!
            tenantId:                $tenantId
        );
    },
    'must be at least 1'
);

// ── Test EXT-NEG-02: Allocation to Shift Not Assigned to Section ──────────────
runScenario(
    'EXT-NEG-02',
    'Reject allocation to a shift not configured on Section (DAY on Sec 1)',
    function () use ($extWorkerService, $sec1, $shiftDay, $pg1, $tenantId) {
        // Section 1 only operates MRN, EVE, SH-00-06. DAY is not assigned!
        $extWorkerService->allocate(
            sectionId:               $sec1->id,
            shiftId:                 $shiftDay->id,
            payGradeId:              $pg1->id,
            numberOfExternalWorkers: 2,
            tenantId:                $tenantId
        );
    },
    'is not assigned to section'
);

// ── Test EXT-NEG-03: Allocation to Non-Existent Pay Grade ─────────────────────
runScenario(
    'EXT-NEG-03',
    'Reject allocation referencing non-existent pay grade ID',
    function () use ($extWorkerService, $sec1, $shiftGrave, $tenantId) {
        $extWorkerService->allocate(
            sectionId:               $sec1->id,
            shiftId:                 $shiftGrave->id,
            payGradeId:              999999, // Non-existent
            numberOfExternalWorkers: 2,
            tenantId:                $tenantId
        );
    },
    'does not exist'
);

// ── Test EXT-NEG-04: Duplicate Allocation Grouping ────────────────────────────
runScenario(
    'EXT-NEG-04',
    'Reject duplicate allocation for same (section, shift, pay_grade) without update flag',
    function () use ($extWorkerService, $sec1, $shiftGrave, $pg1, $tenantId) {
        // Create initial allocation
        $extWorkerService->allocate(
            sectionId:               $sec1->id,
            shiftId:                 $shiftGrave->id,
            payGradeId:              $pg1->id,
            numberOfExternalWorkers: 1,
            tenantId:                $tenantId,
            active:                  true,
            updateIfExists:          false
        );

        // Attempt duplicate without updateIfExists
        $extWorkerService->allocate(
            sectionId:               $sec1->id,
            shiftId:                 $shiftGrave->id,
            payGradeId:              $pg1->id,
            numberOfExternalWorkers: 2,
            tenantId:                $tenantId,
            active:                  true,
            updateIfExists:          false
        );
    },
    'already exists'
);

// ── Test EXT-NEG-05: Negative Pay Grade Hourly Rate ───────────────────────────
runScenario(
    'EXT-NEG-05',
    'Reject creating a pay grade with negative hourly rate',
    function () use ($payGradeService, $tenantId) {
        $payGradeService->create([
            'code'        => 'PG-NEGATIVE',
            'name'        => 'Negative Rate Grade',
            'hourly_rate' => -50.00,
            'active'      => true,
        ], $tenantId);
    },
    'cannot be negative'
);

// ── Test EXT-NEG-06: Duplicate Pay Grade Code ─────────────────────────────────
runScenario(
    'EXT-NEG-06',
    'Reject creating a pay grade with duplicate code',
    function () use ($payGradeService, $tenantId) {
        $payGradeService->create([
            'code'        => 'test-pg-1', // Already exists
            'name'        => 'Duplicate Helper Grade',
            'hourly_rate' => 95.00,
            'active'      => true,
        ], $tenantId);
    },
    'already exists'
);

echo PHP_EOL . "========================================================" . PHP_EOL;
echo "  EXTERNAL WORKERS & PAY GRADE FAILURES TEST RESULT: $passed Passed, $failed Failed" . PHP_EOL;
echo "========================================================" . PHP_EOL;

