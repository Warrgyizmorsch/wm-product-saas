<?php
/**
 * 04_seed_work_centers_and_sections.php
 *
 * Builds the Work Center structure for Tenant 1 THROUGH THE WEB ROUTES, as a user viewing in INR — the same forms,
 * modals and buttons a user clicks, in the order the UI requires:
 *
 *   1. 15 Machines              POST production.machines.store            (₹ op cost / energy rate → base in controller)
 *   2. 3 Work Centers           POST production.work-centers.store        (₹ overhead per day → base in controller)
 *   3. 10 Sections              POST production.work-center-sections.store (shifts + machine_ids, sync_machines)
 *   4. WC-level staff           POST production.work-center-employees.store (section_id empty)
 *   5. Section staff            POST production.work-center-employees.store (section_id set)
 *   6. External workers         POST production.external-worker-allocations.store
 *   7. Manual batches           POST production.work-center-sections.manual-productions.store
 *                               (links the section staff and external allocations chosen in the batch modal —
 *                                a batch must have at least one worker, so it is created after them)
 *   8. Recalculate              POST production.work-centers.recalculate-now (scope=all, the header button)
 *
 * Every record that already exists (same code) is skipped — run 00_reset first for a clean run.
 *
 * Usage: php scratch_production/04_seed_work_centers_and_sections.php
 */

require __DIR__ . '/_http.php';

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\CountdownCalculationEngine;

$http = scratch_http();
$tenantId = SCRATCH_TENANT_ID;
$failures = 0;
$check = function ($r) use (&$failures) { $r->ok || $failures++; return $r; };

scratch_header('SEEDING WORK CENTERS, SECTIONS & MACHINES');

// ── 0. Lookup Maps ────────────────────────────────────────────────────────────
$shifts    = ProductionShift::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
$payGrades = ExternalWorkerPayGrade::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
$employees = Employee::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id', 'employee_id')->all();

// ── 1. Machines (₹ typed) ─────────────────────────────────────────────────────
echo "1. Machines..." . PHP_EOL;

$machinesData = [
    // 7 normal: op cost = consumption × rate
    ['code' => 'test-mah-1',  'name' => 'Laser Cutter Alpha',           'type' => 'laser_cutter',   'src' => 'electricity', 'cons' => 20, 'rate' => 10, 'cost' => 200, 'cap' => 120],
    ['code' => 'test-mah-2',  'name' => 'CNC Press Brake 100T',         'type' => 'press_brake',    'src' => 'electricity', 'cons' => 12, 'rate' => 10, 'cost' => 120, 'cap' => 90],
    ['code' => 'test-mah-3',  'name' => 'CNC Vertical Milling Center',  'type' => 'cnc_milling',    'src' => 'electricity', 'cons' => 15, 'rate' => 10, 'cost' => 150, 'cap' => 75],
    ['code' => 'test-mah-4',  'name' => 'Robotic Welder Cell',          'type' => 'robotic_welder', 'src' => 'electricity', 'cons' => 14, 'rate' => 10, 'cost' => 140, 'cap' => 110],
    ['code' => 'test-mah-5',  'name' => 'High-Speed Cold Circular Saw', 'type' => 'cold_saw',       'src' => 'electricity', 'cons' => 6,  'rate' => 10, 'cost' => 60,  'cap' => 150],
    ['code' => 'test-mah-6',  'name' => 'Electrostatic Powder Booth',   'type' => 'powder_booth',   'src' => 'electricity', 'cons' => 18, 'rate' => 10, 'cost' => 180, 'cap' => 80],
    ['code' => 'test-mah-7',  'name' => 'Automated Packaging Line',     'type' => 'packaging_line', 'src' => 'electricity', 'cons' => 4,  'rate' => 10, 'cost' => 40,  'cap' => 200],

    // 4 subsidised: op cost below consumption × rate (the form's "Operating Grant / Subsidy")
    ['code' => 'test-mah-8',  'name' => 'CNC Turning Lathe',            'type' => 'cnc_lathe',      'src' => 'electricity', 'cons' => 25, 'rate' => 10, 'cost' => 160, 'cap' => 85],
    ['code' => 'test-mah-9',  'name' => 'High-Definition Plasma Cutter', 'type' => 'plasma_cutter', 'src' => 'electricity', 'cons' => 30, 'rate' => 10, 'cost' => 210, 'cap' => 130],
    ['code' => 'test-mah-10', 'name' => 'Infrared Curing Oven',         'type' => 'curing_oven',    'src' => 'electricity', 'cons' => 40, 'rate' => 10, 'cost' => 260, 'cap' => 100],
    ['code' => 'test-mah-11', 'name' => 'Thermal Induction Pre-Heater', 'type' => 'pre_heater',     'src' => 'electricity', 'cons' => 16, 'rate' => 10, 'cost' => 100, 'cap' => 140],

    // 4 high expense: op cost above consumption × rate (the form's "Additional Expense")
    ['code' => 'test-mah-12', 'name' => 'Precision Laser Drill',        'type' => 'laser_drill',    'src' => 'electricity', 'cons' => 22, 'rate' => 10, 'cost' => 350, 'cap' => 60],
    ['code' => 'test-mah-13', 'name' => '5-Axis High Precision VMC',    'type' => '5axis_vmc',      'src' => 'electricity', 'cons' => 28, 'rate' => 10, 'cost' => 450, 'cap' => 50],
    ['code' => 'test-mah-14', 'name' => 'Heavy Hydraulic Forge Press',  'type' => 'forge_press',    'src' => 'diesel',      'cons' => 5,  'rate' => 90, 'cost' => 600, 'cap' => 70],
    // energy source "gas" is not offered by the form (electricity / petrol / diesel / others) → "others"
    ['code' => 'test-mah-15', 'name' => 'Thermal Fluid Heat Exchanger', 'type' => 'thermal_heater', 'src' => 'others',      'cons' => 8,  'rate' => 80, 'cost' => 800, 'cap' => 90],
];

$machineId = fn(string $code) => Machine::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $code)->value('id');
$machinesByCode = [];

foreach ($machinesData as $m) {
    if ($id = $machineId($m['code'])) {
        $machinesByCode[$m['code']] = $id;
        scratch_line('SKIP', "{$m['code']} exists -> ID {$id}");
        continue;
    }
    $r = $check($http->send('POST', route('production.machines.store'), [
        'name'                    => $m['name'],
        'code'                    => $m['code'],
        'machine_type'            => $m['type'],
        'manufacturer'            => 'Haas / Trumpf Precision Systems',
        'capacity'                => $m['cap'],
        'energy_source'           => $m['src'],
        'consumption_per_hour'    => $m['cons'],
        'energy_rate_per_unit'    => $m['rate'],
        'operating_cost_per_hour' => $m['cost'],
        'status'                  => Machine::STATUS_ACTIVE,
        'installation_date'       => '2025-01-10',
        'maintenance_status'      => 'operational',
    ]));
    $machinesByCode[$m['code']] = $machineId($m['code']);
    scratch_line('MACHINE', "{$m['code']} cap {$m['cap']}/hr, ₹{$m['cost']}/hr -> " . ($r->ok ? "ID {$machinesByCode[$m['code']]}" : $r->summary()));
}

// ── 2. Work Centers (₹ overhead per day) ──────────────────────────────────────
echo PHP_EOL . "2. Work Centers..." . PHP_EOL;

$wcDefs = [
    ['code' => 'test-wc-1', 'name' => 'Fabrication & Forming Hub',      'overhead_cost' => 2500, 'description' => 'Heavy laser cutting, saw profiling, and hydraulic sheet press work center.'],
    ['code' => 'test-wc-2', 'name' => 'Precision Machining & Coating',  'overhead_cost' => 3200, 'description' => '5-axis milling, robotic welding, and automated powder booth work center.'],
    ['code' => 'test-wc-3', 'name' => 'Sub-Assembly & Packaging Line',  'overhead_cost' => 1800, 'description' => 'Thermal processing, structural assembly, packaging, and shipping line.'],
];

$wcFind = fn(string $code) => WorkCenter::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $code)->first();
$workCentersByCode = [];

foreach ($wcDefs as $w) {
    if ($wc = $wcFind($w['code'])) {
        $workCentersByCode[$w['code']] = $wc;
        scratch_line('SKIP', "{$w['code']} exists -> ID {$wc->id}");
        continue;
    }
    $r = $check($http->send('POST', route('production.work-centers.store'), $w + [
        'status'                        => 'active',
        'calculation_countdown_minutes' => 10,
    ]));
    $workCentersByCode[$w['code']] = $wcFind($w['code']);
    scratch_line('WC', "{$w['code']} overhead ₹{$w['overhead_cost']}/day -> " . ($r->ok ? 'ID ' . $workCentersByCode[$w['code']]?->id : $r->summary()));
}

// ── 3. Sections ───────────────────────────────────────────────────────────────
echo PHP_EOL . "3. Sections..." . PHP_EOL;

$sectionDefs = [
    // WC 1
    ['wc' => 'test-wc-1', 'code' => 'test-sec-1',  'name' => 'Primary Cutting Cell',            'shifts' => ['MRN', 'EVE', 'SH-00-06'],      'machines' => ['test-mah-1', 'test-mah-5', 'test-mah-9']],
    ['wc' => 'test-wc-1', 'code' => 'test-sec-2',  'name' => 'Heavy Press & Bending',           'shifts' => ['DAY', 'MRN', 'SH-10-20'],      'machines' => ['test-mah-2', 'test-mah-14']],
    ['wc' => 'test-wc-1', 'code' => 'test-sec-3',  'name' => 'Edge Prep & Deburring Station',   'shifts' => ['DAY', 'SH-04-09', 'SH-11-18'], 'machines' => []],
    // WC 2
    ['wc' => 'test-wc-2', 'code' => 'test-sec-4',  'name' => 'Hardware Staging & Kitting',      'shifts' => ['DAY', 'MRN'],                  'machines' => []],
    ['wc' => 'test-wc-2', 'code' => 'test-sec-5',  'name' => 'Manual Sealing & Gasketing',      'shifts' => ['DAY', 'EVE'],                  'machines' => []],
    ['wc' => 'test-wc-2', 'code' => 'test-sec-6',  'name' => 'Precision Hand Finishing',        'shifts' => ['MRN'],                         'machines' => []],
    ['wc' => 'test-wc-2', 'code' => 'test-sec-7',  'name' => 'High-Tolerance CNC Cell',         'shifts' => ['DAY', 'EVE'],                  'machines' => ['test-mah-3', 'test-mah-13']],
    ['wc' => 'test-wc-2', 'code' => 'test-sec-8',  'name' => 'Automated Powder Coating Booth',  'shifts' => ['DAY', 'SH-10-20'],             'machines' => ['test-mah-4', 'test-mah-6']],
    // WC 3
    ['wc' => 'test-wc-3', 'code' => 'test-sec-9',  'name' => 'Structural Base Assembly',        'shifts' => ['DAY', 'MRN', 'SH-11-18'],      'machines' => ['test-mah-15']],
    ['wc' => 'test-wc-3', 'code' => 'test-sec-10', 'name' => 'Final Inspection & Crating Line', 'shifts' => ['DAY', 'EVE', 'SH-16-20'],      'machines' => ['test-mah-7']],
];
// test-mah-8, 10, 11, 12 stay unassigned on purpose.

$secFind = fn(int $wcId, string $code) => WorkCenterSection::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('work_center_id', $wcId)->where('code', $code)->first();
$sectionsByCode = [];

foreach ($sectionDefs as $s) {
    $wc = $workCentersByCode[$s['wc']] ?? null;
    if (!$wc) { $failures++; scratch_line('SECTION', "{$s['code']} -> skipped, work center {$s['wc']} missing"); continue; }

    if ($sec = $secFind($wc->id, $s['code'])) {
        $sectionsByCode[$s['code']] = $sec;
        scratch_line('SKIP', "{$s['code']} exists -> ID {$sec->id}");
        continue;
    }
    $r = $check($http->send('POST', route('production.work-center-sections.store'), [
        'work_center_id' => $wc->id,
        'code'           => $s['code'],
        'name'           => $s['name'],
        'status'         => WorkCenterSection::STATUS_ACTIVE,
        'shifts'         => array_map(fn($c) => $shifts[$c], $s['shifts']),
        'machine_ids'    => array_map(fn($c) => $machinesByCode[$c], $s['machines']),
        'sync_machines'  => 1,
    ]));
    $sectionsByCode[$s['code']] = $secFind($wc->id, $s['code']);
    scratch_line('SECTION', "{$s['code']} [" . implode(', ', $s['shifts']) . '] machines [' . implode(', ', $s['machines']) . '] -> '
        . ($r->ok ? 'ID ' . $sectionsByCode[$s['code']]?->id : $r->summary()));
}

// ── 4. Work-Center-level staff (section_id empty) ─────────────────────────────
echo PHP_EOL . "4. Work Center staff..." . PHP_EOL;

$wcStaffAssignments = [
    ['wc' => 'test-wc-1', 'emp' => 'WRG-004'],
    ['wc' => 'test-wc-1', 'emp' => 'WRG-005'],
    ['wc' => 'test-wc-2', 'emp' => 'WRG-004'],
    ['wc' => 'test-wc-2', 'emp' => 'WRG-005'],
    ['wc' => 'test-wc-2', 'emp' => 'test-emp-8'],   // 4h surge shift
    ['wc' => 'test-wc-3', 'emp' => 'WRG-004'],
    ['wc' => 'test-wc-3', 'emp' => 'test-emp-8'],
    ['wc' => 'test-wc-3', 'emp' => 'WRG-002'],
    ['wc' => 'test-wc-3', 'emp' => 'test-emp-1'],
    ['wc' => 'test-wc-3', 'emp' => 'test-emp-26'],  // 7h shift
];

foreach ($wcStaffAssignments as $a) {
    $wc = $workCentersByCode[$a['wc']] ?? null;
    $empId = $employees[$a['emp']] ?? null;
    if (!$wc || !$empId) { $failures++; scratch_line('WC STAFF', "{$a['emp']} -> {$a['wc']}: missing work center or employee"); continue; }

    $r = $check($http->send('POST', route('production.work-center-employees.store'), [
        'work_center_id' => $wc->id,
        'section_id'     => '',
        'employee_id'    => $empId,
        'active'         => 1,
    ], true));
    scratch_line('WC STAFF', "{$a['emp']} -> {$a['wc']}: " . $r->summary());
}

// ── 5. Section staff ──────────────────────────────────────────────────────────
echo PHP_EOL . "5. Section staff..." . PHP_EOL;

// 'batch' = the manual batch this person is later selected in (step 7)
$secStaffAssignments = [
    ['sec' => 'test-sec-1',  'emp' => 'test-emp-2',  'batch' => null],
    ['sec' => 'test-sec-1',  'emp' => 'test-emp-25', 'batch' => null],             // 4h surge inside EVE
    ['sec' => 'test-sec-2',  'emp' => 'test-emp-3',  'batch' => null],
    ['sec' => 'test-sec-2',  'emp' => 'test-emp-20', 'batch' => null],
    ['sec' => 'test-sec-2',  'emp' => 'test-emp-6',  'batch' => null],
    ['sec' => 'test-sec-2',  'emp' => 'test-emp-19', 'batch' => null],
    ['sec' => 'test-sec-3',  'emp' => 'test-emp-4',  'batch' => null],
    ['sec' => 'test-sec-4',  'emp' => 'test-emp-9',  'batch' => null],
    ['sec' => 'test-sec-4',  'emp' => 'test-emp-10', 'batch' => null],
    ['sec' => 'test-sec-6',  'emp' => 'test-emp-12', 'batch' => 'test-batch-6'],
    ['sec' => 'test-sec-6',  'emp' => 'test-emp-13', 'batch' => 'test-batch-6'],
    ['sec' => 'test-sec-7',  'emp' => 'test-emp-14', 'batch' => 'test-batch-7'],
    ['sec' => 'test-sec-7',  'emp' => 'test-emp-15', 'batch' => null],
    ['sec' => 'test-sec-9',  'emp' => 'test-emp-17', 'batch' => 'test-batch-9A'],
    ['sec' => 'test-sec-9',  'emp' => 'test-emp-18', 'batch' => 'test-batch-9A'],
    ['sec' => 'test-sec-9',  'emp' => 'test-emp-21', 'batch' => 'test-batch-9B'],
    ['sec' => 'test-sec-10', 'emp' => 'test-emp-22', 'batch' => 'test-batch-10A'],
    ['sec' => 'test-sec-10', 'emp' => 'test-emp-23', 'batch' => 'test-batch-10B'],
    ['sec' => 'test-sec-10', 'emp' => 'test-emp-27', 'batch' => null],
];

$batchEmployeeIds = [];   // batch code => [work_center_employee ids]

foreach ($secStaffAssignments as $sa) {
    $sec = $sectionsByCode[$sa['sec']] ?? null;
    $empId = $employees[$sa['emp']] ?? null;
    if (!$sec || !$empId) { $failures++; scratch_line('SEC STAFF', "{$sa['emp']} -> {$sa['sec']}: missing section or employee"); continue; }

    $r = $check($http->send('POST', route('production.work-center-employees.store'), [
        'work_center_id' => $sec->work_center_id,
        'section_id'     => $sec->id,
        'employee_id'    => $empId,
        'active'         => 1,
    ], true));
    scratch_line('SEC STAFF', "{$sa['emp']} -> {$sa['sec']}: " . $r->summary());

    if ($sa['batch']) {
        $assignmentId = WorkCenterEmployeeAssignment::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('section_id', $sec->id)->where('employee_id', $empId)->value('id');
        if ($assignmentId) {
            $batchEmployeeIds[$sa['batch']][] = $assignmentId;
        }
    }
}

// ── 6. External worker allocations ────────────────────────────────────────────
echo PHP_EOL . "6. External workers..." . PHP_EOL;

$extAllocations = [
    ['sec' => 'test-sec-1',  'shift' => 'MRN',      'pg' => 'test-pg-3', 'count' => 2, 'batch' => null],
    ['sec' => 'test-sec-1',  'shift' => 'EVE',      'pg' => 'test-pg-1', 'count' => 1, 'batch' => null],
    ['sec' => 'test-sec-3',  'shift' => 'DAY',      'pg' => 'test-pg-2', 'count' => 2, 'batch' => 'test-batch-1'],
    ['sec' => 'test-sec-3',  'shift' => 'SH-11-18', 'pg' => 'test-pg-1', 'count' => 1, 'batch' => null],
    ['sec' => 'test-sec-4',  'shift' => 'DAY',      'pg' => 'test-pg-1', 'count' => 2, 'batch' => null],
    ['sec' => 'test-sec-4',  'shift' => 'MRN',      'pg' => 'test-pg-2', 'count' => 1, 'batch' => null],
    ['sec' => 'test-sec-5',  'shift' => 'DAY',      'pg' => 'test-pg-3', 'count' => 3, 'batch' => 'test-batch-5'],
    ['sec' => 'test-sec-5',  'shift' => 'EVE',      'pg' => 'test-pg-1', 'count' => 2, 'batch' => null],
    ['sec' => 'test-sec-8',  'shift' => 'DAY',      'pg' => 'test-pg-4', 'count' => 3, 'batch' => 'test-batch-8'],
    ['sec' => 'test-sec-8',  'shift' => 'SH-10-20', 'pg' => 'test-pg-5', 'count' => 2, 'batch' => null],
    ['sec' => 'test-sec-9',  'shift' => 'DAY',      'pg' => 'test-pg-3', 'count' => 2, 'batch' => 'test-batch-9A'],
    ['sec' => 'test-sec-9',  'shift' => 'MRN',      'pg' => 'test-pg-2', 'count' => 2, 'batch' => 'test-batch-9B'],
    ['sec' => 'test-sec-9',  'shift' => 'SH-11-18', 'pg' => 'test-pg-4', 'count' => 2, 'batch' => null],
    ['sec' => 'test-sec-9',  'shift' => 'DAY',      'pg' => 'test-pg-6', 'count' => 1, 'batch' => null],
    ['sec' => 'test-sec-10', 'shift' => 'DAY',      'pg' => 'test-pg-1', 'count' => 3, 'batch' => 'test-batch-10A'],
    ['sec' => 'test-sec-10', 'shift' => 'EVE',      'pg' => 'test-pg-3', 'count' => 2, 'batch' => 'test-batch-10B'],
    ['sec' => 'test-sec-10', 'shift' => 'SH-16-20', 'pg' => 'test-pg-2', 'count' => 2, 'batch' => null],
];

$batchAllocationIds = [];   // batch code => [allocation ids]

foreach ($extAllocations as $ea) {
    $sec = $sectionsByCode[$ea['sec']] ?? null;
    if (!$sec) { $failures++; scratch_line('EXT ALLOC', "{$ea['sec']}: section missing"); continue; }

    $r = $check($http->send('POST', route('production.external-worker-allocations.store'), [
        'section_id'                 => $sec->id,
        'shift_id'                   => $shifts[$ea['shift']],
        'pay_grade_id'               => $payGrades[$ea['pg']],
        'number_of_external_workers' => $ea['count'],
        'active'                     => 1,
    ], true));
    scratch_line('EXT ALLOC', "{$ea['count']}x {$ea['pg']} on {$ea['sec']} ({$ea['shift']}): " . $r->summary());

    if ($ea['batch']) {
        $allocId = ExternalWorkerAllocation::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('section_id', $sec->id)
            ->where('shift_id', $shifts[$ea['shift']])->where('pay_grade_id', $payGrades[$ea['pg']])->value('id');
        if ($allocId) {
            $batchAllocationIds[$ea['batch']][] = $allocId;
        }
    }
}

// ── 7. Manual batches (the batch modal: name, shift, capacity, workers) ──────
echo PHP_EOL . "7. Manual batches..." . PHP_EOL;

$manualBatchesData = [
    ['code' => 'test-batch-1',   'sec' => 'test-sec-3',  'shift' => 'DAY', 'cap' => 25],
    ['code' => 'test-batch-5',   'sec' => 'test-sec-5',  'shift' => 'DAY', 'cap' => 30],
    ['code' => 'test-batch-6',   'sec' => 'test-sec-6',  'shift' => 'MRN', 'cap' => 20],
    ['code' => 'test-batch-7',   'sec' => 'test-sec-7',  'shift' => 'DAY', 'cap' => 15],
    ['code' => 'test-batch-8',   'sec' => 'test-sec-8',  'shift' => 'DAY', 'cap' => 40],
    ['code' => 'test-batch-9A',  'sec' => 'test-sec-9',  'shift' => 'DAY', 'cap' => 35],
    ['code' => 'test-batch-9B',  'sec' => 'test-sec-9',  'shift' => 'MRN', 'cap' => 20],
    ['code' => 'test-batch-10A', 'sec' => 'test-sec-10', 'shift' => 'DAY', 'cap' => 50],
    ['code' => 'test-batch-10B', 'sec' => 'test-sec-10', 'shift' => 'EVE', 'cap' => 30],
];

foreach ($manualBatchesData as $b) {
    $sec = $sectionsByCode[$b['sec']] ?? null;
    if (!$sec) { $failures++; scratch_line('BATCH', "{$b['code']}: section missing"); continue; }

    $existing = SectionManualProduction::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('section_id', $sec->id)->where('batch_name', $b['code'])->first();
    if ($existing) {
        scratch_line('SKIP', "{$b['code']} exists -> ID {$existing->id}");
        continue;
    }

    $empIds = $batchEmployeeIds[$b['code']] ?? [];
    $extIds = $batchAllocationIds[$b['code']] ?? [];
    $r = $check($http->send('POST', route('production.work-center-sections.manual-productions.store', $sec->id), [
        'batch_name'              => $b['code'],
        'shift_id'                => $shifts[$b['shift']],
        'capacity_per_hour'       => $b['cap'],
        'active'                  => 1,
        'employee_ids'            => $empIds,
        'external_allocation_ids' => $extIds,
    ], true));
    scratch_line('BATCH', "{$b['code']} ({$b['sec']}, {$b['shift']}, {$b['cap']}/hr, " . count($empIds) . ' staff, ' . count($extIds) . ' ext alloc): ' . $r->summary());
}

// ── 8. Recalculate (header "Recalculate now" button = scope all) ─────────────
echo PHP_EOL . "8. Recalculate..." . PHP_EOL;

foreach ($workCentersByCode as $code => $wc) {
    if (!$wc) { continue; }
    $r = $check($http->send('POST', route('production.work-centers.recalculate-now', $wc->id), [
        'scope' => CountdownCalculationEngine::SCOPE_ALL,
    ], true));
    $snap = $r->json['snapshot'] ?? null;
    scratch_line('RECALC', "{$code}: " . ($r->ok && $snap
        ? 'snapshot #' . ($snap['snapshot_number'] ?? $snap['id'] ?? '?') . ', capacity/hr ' . format_decimal((float) ($snap['capacity_per_hour'] ?? 0))
            . ', daily capacity ' . format_decimal((float) ($snap['daily_capacity'] ?? 0))
            . ', cost/unit (base) ' . (isset($snap['cost_per_unit']) ? format_decimal((float) $snap['cost_per_unit']) : 'n/a')
        : $r->summary()));
}

echo PHP_EOL . ($failures ? "Finished with {$failures} failure(s)." : 'Work Centers, Sections, Machines, and Staffing seeded and calculated!') . PHP_EOL;
exit($failures ? 1 : 0);
