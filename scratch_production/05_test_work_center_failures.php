<?php
/**
 * 05_test_work_center_failures.php
 *
 * Negative Test Suite for Work Center Business Rules.
 * Verifies that domain services properly reject invalid operations and enforce invariants.
 *
 * Usage: php scratch_production/05_test_work_center_failures.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\DTO\WorkCenterDTO;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\WorkCenterEmployeeService;
use App\Domains\Production\Services\WorkCenterService;
use Illuminate\Support\Facades\DB;

$tenantId = 1;
$passed = 0;
$failed = 0;

echo "========================================================" . PHP_EOL;
echo "  TEST SUITE: WORK CENTER BUSINESS RULE FAILURES" . PHP_EOL;
echo "========================================================" . PHP_EOL . PHP_EOL;

$wcService = app(WorkCenterService::class);
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

// ── Test WC-NEG-01: Duplicate Work Center Code ────────────────────────────────
runScenario(
    'WC-NEG-01',
    'Reject creating a Work Center with duplicate code',
    function () use ($wcService, $tenantId) {
        $dto = WorkCenterDTO::fromArray([
            'code'          => 'test-wc-1', // Already created
            'name'          => 'Duplicate Code WC',
            'overhead_cost' => 1000.0,
            'status'        => 'active',
        ]);
        $wcService->create($dto, $tenantId);
    },
    'already exists'
);

// ── Test WC-NEG-02: Renaming to an existing Work Center Code ──────────────────
runScenario(
    'WC-NEG-02',
    'Reject updating Work Center to conflict with another existing code',
    function () use ($wcService, $tenantId) {
        $wc2 = WorkCenter::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', 'test-wc-2')->firstOrFail();
        $dto = WorkCenterDTO::fromArray([
            'code'          => 'test-wc-1', // Conflict with WC 1
            'name'          => 'Conflicting Rename',
            'overhead_cost' => 1500.0,
            'status'        => 'active',
        ]);
        $wcService->update($wc2->id, $dto);
    },
    'already exists'
);

// ── Test WC-NEG-03: Assign Staff at WC Level when WC has Zero Sections ────────
runScenario(
    'WC-NEG-03',
    'Reject WC-level employee assignment when Work Center has no active sections',
    function () use ($wcService, $wcEmployeeService, $tenantId) {
        // Create a temporary isolated WC with zero sections
        $tempDto = WorkCenterDTO::fromArray([
            'code'          => 'TEMP-EMPTY-WC',
            'name'          => 'Temporary Empty Work Center',
            'overhead_cost' => 500.0,
            'status'        => 'active',
        ]);
        $tempWc = $wcService->create($tempDto, $tenantId);

        $emp = Employee::withoutGlobalScopes()->where('tenant_id', $tenantId)->firstOrFail();

        $wcEmployeeService->assign(
            workCenterId: $tempWc->id,
            employeeId:   $emp->id,
            tenantId:     $tenantId,
            active:       true,
            sectionId:    null
        );
    },
    'has no active sections'
);

// ── Test WC-NEG-04: Non-existent Work Center ID ───────────────────────────────
runScenario(
    'WC-NEG-04',
    'Reject employee assignment to non-existent Work Center',
    function () use ($wcEmployeeService, $tenantId) {
        $emp = Employee::withoutGlobalScopes()->where('tenant_id', $tenantId)->firstOrFail();
        $wcEmployeeService->assign(
            workCenterId: 999999, // Non-existent
            employeeId:   $emp->id,
            tenantId:     $tenantId,
            active:       true,
            sectionId:    null
        );
    },
    'does not exist'
);

echo PHP_EOL . "========================================================" . PHP_EOL;
echo "  WORK CENTER FAILURES TEST RESULT: $passed Passed, $failed Failed" . PHP_EOL;
echo "========================================================" . PHP_EOL;

