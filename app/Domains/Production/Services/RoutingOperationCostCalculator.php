<?php

namespace App\Domains\Production\Services;

/**
 * RT-IMPL-14 (RT-19): the PHP mirror of the routing form calculator. Used ONLY to check the figures the form
 * (or the API) sends — the backend saves the submitted values as they are when they match.
 *
 * Inputs: the operation as submitted + the resource figures of the latest section snapshot
 * (`SectionCalculationService::getSnapshotResourceCosts()`).
 *
 * Formulas (RT-IMPL-10 / 11 / 12 / 13), rounded exactly like the form (4 decimals at each saved step):
 *   run time (min/unit)   = resource speed_minutes_per_unit
 *   machine rate (/min)   = machine final operating cost per hour ÷ 60
 *   labor rate (/min)     = machine: operator labor per machine per hour ÷ 60 · batch: batch labor per hour ÷ 60
 *   cycle per piece       = setup per piece + run time
 *   yield factor          = 100 ÷ yield %
 *   labor / machine per unit = cycle × rate × yield factor;  cost per unit = labor + machine
 *   setup cost per run    = setup per operation × (labor rate + machine rate)   (no yield)
 *   external              = subcontract cost per unit × yield factor
 */
class RoutingOperationCostCalculator
{
    public const TOLERANCE = 0.0001;

    public const FIELDS = [
        'processing_time_minutes', 'labor_cost_rate', 'machine_cost_rate',
        'labor_cost_per_unit', 'machine_cost_per_unit', 'cost_per_unit', 'setup_cost_per_run',
    ];

    private static function r4($v): float
    {
        return round((float) $v, 4);
    }

    /**
     * @param array      $op        submitted operation
     * @param array|null $resources snapshot resource figures of the operation's section
     * @return array{figures: array<string,float>, resource: ?array, error: ?string}
     */
    public function calculate(array $op, ?array $resources): array
    {
        $yield = (float) ($op['expected_yield_percentage'] ?? 100);
        $yieldFactor = $yield > 0 ? 100 / $yield : 1.0;
        $isExternal = filter_var($op['is_external'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isExternal) {
            $cpu = self::r4((float) ($op['subcontract_cost_per_unit'] ?? 0) * $yieldFactor);

            return ['figures' => [
                'processing_time_minutes' => self::r4($op['processing_time_minutes'] ?? 0),
                'labor_cost_rate'         => 0.0,
                'machine_cost_rate'       => 0.0,
                'labor_cost_per_unit'     => 0.0,
                'machine_cost_per_unit'   => 0.0,
                'cost_per_unit'           => $cpu,
                'setup_cost_per_run'      => 0.0,
            ], 'resource' => null, 'error' => null];
        }

        $resource = null;
        $speed = 0.0;
        $laborRate = 0.0;
        $machineRate = 0.0;

        if (!empty($op['machine_id'])) {
            $resource = collect($resources['machines'] ?? [])->first(fn($m) => (int) $m['id'] === (int) $op['machine_id']);
            if ($resource) {
                $speed = (float) ($resource['speed_minutes_per_unit'] ?? 0);
                $machineRate = self::r4((float) ($resource['operating_cost_per_hour'] ?? 0) / 60);
                $laborRate = self::r4((float) ($resource['operator_labor_cost_per_hour'] ?? $resources['operator_labor_cost_per_machine_per_hour'] ?? 0) / 60);
            }
        } elseif (!empty($op['section_manual_production_id'])) {
            $resource = collect($resources['manual_batches'] ?? [])->first(fn($b) => (int) $b['id'] === (int) $op['section_manual_production_id']);
            if ($resource) {
                $speed = (float) ($resource['speed_minutes_per_unit'] ?? 0);
                $laborRate = self::r4((float) ($resource['labor_cost_per_hour'] ?? 0) / 60);
            }
        }

        if (!$resource) {
            return ['figures' => [], 'resource' => null, 'error' => 'not_in_snapshot'];
        }

        $run = self::r4($speed);
        $cycle = (float) ($op['setup_time_per_piece_minutes'] ?? 0) + $run;
        $laborPerUnit = self::r4($cycle * $laborRate * $yieldFactor);
        $machinePerUnit = self::r4($cycle * $machineRate * $yieldFactor);

        return ['figures' => [
            'processing_time_minutes' => $run,
            'labor_cost_rate'         => $laborRate,
            'machine_cost_rate'       => $machineRate,
            'labor_cost_per_unit'     => $laborPerUnit,
            'machine_cost_per_unit'   => $machinePerUnit,
            'cost_per_unit'           => self::r4($laborPerUnit + $machinePerUnit),
            'setup_cost_per_run'      => self::r4((float) ($op['setup_time_minutes'] ?? 0) * ($laborRate + $machineRate)),
        ], 'resource' => $resource, 'error' => null];
    }

    /** Field names whose submitted value differs from the calculated one (missing counts as 0). */
    public function mismatches(array $op, array $figures): array
    {
        $bad = [];
        foreach (self::FIELDS as $field) {
            $sent = (float) ($op[$field] ?? 0);
            if (abs($sent - (float) ($figures[$field] ?? 0)) > self::TOLERANCE + 1e-9) { // allow a 1-in-the-4th-decimal rounding difference
                $bad[] = $field;
            }
        }

        return $bad;
    }
}
