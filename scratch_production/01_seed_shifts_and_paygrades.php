<?php
/**
 * 01_seed_shifts_and_paygrades.php
 *
 * Creates 7 Production Shifts and 6 External Worker Pay Grades for Tenant 1 THROUGH THE WEB ROUTES
 * (production.shifts.store / update, production.pay-grades.store / update), as a user viewing in INR.
 * Pay-grade hourly rates are typed in ₹ — the controller converts them to base before saving.
 * Keeps the 3 existing shifts (DAY, MRN, EVE). Re-running updates existing records by code.
 *
 * Usage: php scratch_production/01_seed_shifts_and_paygrades.php
 */

require __DIR__ . '/_http.php';

use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\ProductionShift;

$http = scratch_http();
$tenantId = SCRATCH_TENANT_ID;
$failures = 0;

scratch_header('SEEDING PRODUCTION SHIFTS & PAY GRADES');

// ── 1. Production Shifts ──────────────────────────────────────────────────────
$shiftsToSeed = [
    ['code' => 'SH-00-06', 'name' => 'Graveyard Night Shift',          'start_time' => '00:00', 'end_time' => '06:00', 'break_minutes' => 30, 'overtime_allowed' => 1],
    ['code' => 'SH-04-09', 'name' => 'Early Dawn Setup Shift',         'start_time' => '04:00', 'end_time' => '09:00', 'break_minutes' => 15, 'overtime_allowed' => 0],
    ['code' => 'SH-11-18', 'name' => 'Mid-Day Core Shift',             'start_time' => '11:00', 'end_time' => '18:00', 'break_minutes' => 45, 'overtime_allowed' => 1],
    ['code' => 'SH-10-20', 'name' => 'Extended 10-Hour Heavy Shift',   'start_time' => '10:00', 'end_time' => '20:00', 'break_minutes' => 60, 'overtime_allowed' => 1],
    ['code' => 'SH-19-04', 'name' => 'Overnight Cross-Midnight Shift', 'start_time' => '19:00', 'end_time' => '04:00', 'break_minutes' => 45, 'overtime_allowed' => 1],
    ['code' => 'SH-07-19', 'name' => '12-Hour Continuous Run Shift',   'start_time' => '07:00', 'end_time' => '19:00', 'break_minutes' => 60, 'overtime_allowed' => 1],
    ['code' => 'SH-16-20', 'name' => 'Twilight 4-Hour Surge Shift',    'start_time' => '16:00', 'end_time' => '20:00', 'break_minutes' => 15, 'overtime_allowed' => 0],
];

echo "Production Shifts..." . PHP_EOL;

foreach ($shiftsToSeed as $data) {
    $payload = $data + ['active' => 1];
    if (!$payload['overtime_allowed']) {
        unset($payload['overtime_allowed']);   // unchecked checkbox is not sent by the browser
    }

    $existing = ProductionShift::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $data['code'])->first();
    $r = $existing
        ? $http->send('PUT', route('production.shifts.update', $existing->id), $payload)
        : $http->send('POST', route('production.shifts.store'), $payload);

    $id = $existing?->id ?? ProductionShift::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $data['code'])->value('id');
    $r->ok || $failures++;
    scratch_line($existing ? 'UPDATE' : 'CREATE', "Shift {$data['code']} ({$data['start_time']}–{$data['end_time']}, break {$data['break_minutes']}m) -> " . ($r->ok ? "ID {$id}" : $r->summary()));
}

// ── 2. External Worker Pay Grades (₹ typed, controller converts) ─────────────
echo PHP_EOL . "External Worker Pay Grades..." . PHP_EOL;

$payGradesToSeed = [
    ['code' => 'test-pg-1', 'name' => 'Helper / Loader',                  'hourly_rate' => 80,  'description' => 'Material loading, packing, cleanup'],
    ['code' => 'test-pg-2', 'name' => 'Deburrer / Prep Tech',             'hourly_rate' => 120, 'description' => 'Edge cleaning, grinding, sanding'],
    ['code' => 'test-pg-3', 'name' => 'Standard Machine Operator',        'hourly_rate' => 175, 'description' => 'General press, saw & mill operator'],
    ['code' => 'test-pg-4', 'name' => 'Certified Welder',                 'hourly_rate' => 225, 'description' => 'MIG/TIG structural steel welder'],
    ['code' => 'test-pg-5', 'name' => 'Senior CNC Programmer',            'hourly_rate' => 300, 'description' => '5-axis setup, laser cut optimization'],
    ['code' => 'test-pg-6', 'name' => 'Industrial Automation Specialist', 'hourly_rate' => 380, 'description' => 'Robotic cell & thermal calibration'],
];

foreach ($payGradesToSeed as $pg) {
    $payload = $pg + ['active' => 1];
    $existing = ExternalWorkerPayGrade::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $pg['code'])->first();
    $r = $existing
        ? $http->send('PUT', route('production.pay-grades.update', $existing->id), $payload)
        : $http->send('POST', route('production.pay-grades.store'), $payload);

    $saved = ExternalWorkerPayGrade::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $pg['code'])->first();
    $r->ok || $failures++;
    scratch_line($existing ? 'UPDATE' : 'CREATE', "Pay Grade {$pg['code']} typed ₹{$pg['hourly_rate']}/hr -> "
        . ($r->ok && $saved ? "ID {$saved->id}, stored base " . format_decimal((float) $saved->hourly_rate) : $r->summary()));
}

echo PHP_EOL . ($failures ? "Finished with {$failures} failure(s)." : 'Production Shifts & Pay Grades seeding complete!') . PHP_EOL;
exit($failures ? 1 : 0);
