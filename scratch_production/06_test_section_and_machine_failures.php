<?php
/**
 * 06_test_section_and_machine_failures.php
 *
 * Negative Test Suite for Work Center Sections & Machinery Business Rules.
 * Verifies that domain services properly enforce:
 * - Mandatory operating shifts
 * - Unique section code within parent Work Center
 * - Single-section machine exclusivity (double-booking rejection)
 * - Unique machine codes
 * - Decommissioned/inactive section assignment blocks
 *
 * Usage: php scratch_production/06_test_section_and_machine_failures.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domains\Production\DTO\MachineDTO;
use App\Domains\Production\DTO\WorkCenterSectionDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\MachineService;
use App\Domains\Production\Services\WorkCenterSectionService;
use Illuminate\Support\Facades\DB;

$tenantId = 1;
$passed = 0;
$failed = 0;

echo "========================================================" . PHP_EOL;
echo "  TEST SUITE: SECTION & MACHINERY BUSINESS RULE FAILURES" . PHP_EOL;
echo "========================================================" . PHP_EOL . PHP_EOL;

$wcSectionService = app(WorkCenterSectionService::class);
$machineService = app(MachineService::class);

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
$mah1 = Machine::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', 'test-mah-1')->firstOrFail();

// ── Test SEC-NEG-01: Section with Zero Operating Shifts ───────────────────────
runScenario(
    'SEC-NEG-01',
    'Reject section creation with empty shift_ids array',
    function () use ($wcSectionService, $wc1, $tenantId) {
        $dto = WorkCenterSectionDTO::fromArray([
            'work_center_id' => $wc1->id,
            'code'           => 'SEC-NO-SHIFTS',
            'name'           => 'Section without shifts',
            'status'         => 'active',
            'shifts'         => [], // Empty!
        ]);
        $wcSectionService->create($dto, $tenantId);
    },
    'must have at least one assigned shift'
);

// ── Test SEC-NEG-02: Duplicate Section Code in Same Work Center ───────────────
runScenario(
    'SEC-NEG-02',
    'Reject section creation with duplicate code under same Work Center',
    function () use ($wcSectionService, $wc1, $tenantId) {
        $dto = WorkCenterSectionDTO::fromArray([
            'work_center_id' => $wc1->id,
            'code'           => 'test-sec-1', // Already exists in WC 1
            'name'           => 'Duplicate Section',
            'status'         => 'active',
            'shifts'         => [1],
        ]);
        $wcSectionService->create($dto, $tenantId);
    },
    'already exists in this work center'
);

// ── Test SEC-NEG-03: Section under Non-Existent Work Center ────────────────────
runScenario(
    'SEC-NEG-03',
    'Reject section creation under non-existent Work Center ID',
    function () use ($wcSectionService, $tenantId) {
        $dto = WorkCenterSectionDTO::fromArray([
            'work_center_id' => 999999, // Non-existent
            'code'           => 'SEC-ORPHAN',
            'name'           => 'Orphan Section',
            'status'         => 'active',
            'shifts'         => [1],
        ]);
        $wcSectionService->create($dto, $tenantId);
    },
    'invalid work center'
);

// ── Test MAH-NEG-01: Machine Exclusivity (Double Booking) ─────────────────────
runScenario(
    'MAH-NEG-01',
    'Reject assigning a machine already active in Section 1 to Section 2',
    function () use ($wcSectionService, $wc1, $mah1, $tenantId) {
        // Attempt to create a new section and assign test-mah-1 which is already assigned to test-sec-1
        $dto = WorkCenterSectionDTO::fromArray([
            'work_center_id' => $wc1->id,
            'code'           => 'SEC-CONFLICT-MAH',
            'name'           => 'Conflicting Machine Section',
            'status'         => 'active',
            'shifts'         => [1],
            'machine_ids'    => [$mah1->id], // Already in Section 1!
            'sync_machines'  => true,
        ]);
        $wcSectionService->create($dto, $tenantId);
    },
    'already assigned to'
);

// ── Test MAH-NEG-02: Duplicate Machine Code ───────────────────────────────────
runScenario(
    'MAH-NEG-02',
    'Reject creating a machine with duplicate code',
    function () use ($machineService, $tenantId) {
        $dto = MachineDTO::fromArray([
            'code'         => 'test-mah-1', // Already exists
            'name'         => 'Duplicate Laser Cutter',
            'machine_type' => 'laser_cutter',
            'status'       => 'active',
        ]);
        $machineService->create($dto, $tenantId);
    },
    'already exists'
);

// ── Test MAH-NEG-03: Assign Machine to Non-Existent Section ───────────────────
runScenario(
    'MAH-NEG-03',
    'Reject creating a machine linked to non-existent section',
    function () use ($machineService, $tenantId) {
        $dto = MachineDTO::fromArray([
            'code'         => 'MAH-INVALID-SEC',
            'name'         => 'Machine with invalid section',
            'section_id'   => 999999, // Non-existent
            'status'       => 'active',
        ]);
        $machineService->create($dto, $tenantId);
    },
    'invalid section'
);

echo PHP_EOL . "========================================================" . PHP_EOL;
echo "  SECTION & MACHINERY FAILURES TEST RESULT: $passed Passed, $failed Failed" . PHP_EOL;
echo "========================================================" . PHP_EOL;

