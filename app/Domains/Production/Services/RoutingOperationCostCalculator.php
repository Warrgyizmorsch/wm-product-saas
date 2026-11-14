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

    /** Money keys of the section resource payload (top level, machines[], manual_batches[]) — DB base in the snapshot. */
    public const MONEY_RESOURCE_KEYS = [
        'operating_cost_per_hour', 'operator_labor_cost_per_hour', 'operator_labor_cost_per_machine_per_hour',
        'non_batch_daily_labor_cost', 'labor_cost_per_unit', 'machine_cost_per_unit', 'cost_per_unit',
        'labor_cost_per_hour', 'total_unassigned_labor_cost_per_hour', 'unassigned_labor_hourly_total',
    ];

    private static function r4($v): float
    {
        return round((float) $v, 4);
    }

    /**
     * Short Full Review Point 2 — UI boundary: copy of the DB-base resource payload with every money key in the
     * active currency (convert_from_base once). Used by the routing form feed and by the web check of what the form sent.
     * Never persist the result — snapshots and saved figures stay DB base.
     */
    public static function resourcesToActiveCurrency(array $resources): array
    {
        $convert = function (array $row): array {
            foreach (self::MONEY_RESOURCE_KEYS as $key) {
                if (array_key_exists($key, $row) && is_numeric($row[$key])) {
                    $row[$key] = convert_from_base((float) $row[$key]);
                }
            }
            return $row;
        };

        $resources = $convert($resources);
        foreach (['machines', 'manual_batches'] as $list) {
            if (isset($resources[$list]) && is_array($resources[$list])) {
                $resources[$list] = array_map(fn ($r) => is_array($r) ? $convert($r) : $r, $resources[$list]);
            }
        }

        return $resources;
    }

    /**
     * DB-base resource snapshot of an operation (same shape the routing form builds), built on the server from the
     * section snapshot so `resource_snapshot` never holds active-currency numbers.
     */
    public function buildResourceSnapshot(array $op, ?array $resource, ?array $meta): ?array
    {
        if (filter_var($op['is_external'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return [
                'type'          => 'outsourced',
                'vendor_id'     => $op['vendor_id'] ?? null,
                'cost_per_unit' => (float) ($op['subcontract_cost_per_unit'] ?? 0),
            ];
        }
        if (!$resource) {
            return null;
        }

        $source = [
            'work_center_id'              => $meta['work_center_id'] ?? ($op['work_center_id'] ?? null),
            'work_center_code'            => $meta['work_center_code'] ?? null,
            'section_id'                  => $meta['section_id'] ?? ($op['section_id'] ?? null),
            'section_code'                => $meta['section_code'] ?? null,
            'section_calculation_id'      => $meta['section_calculation_id'] ?? null,
            'section_snapshot_number'     => $meta['snapshot_number'] ?? null,
            'work_center_calculation_id'  => $meta['work_center_calculation_id'] ?? null,
            'work_center_snapshot_number' => $meta['work_center_snapshot_number'] ?? null,
            'calculated_at'               => $meta['calculated_at'] ?? null,
        ];

        if (!empty($op['machine_id'])) {
            return [
                'type'                                     => 'machine',
                'machine_id'                               => $resource['id'] ?? null,
                'code'                                     => $resource['code'] ?? null,
                'name'                                     => $resource['name'] ?? null,
                'capacity'                                 => (float) ($resource['capacity'] ?? 0),
                'speed_minutes_per_unit'                   => (float) ($resource['speed_minutes_per_unit'] ?? 0),
                'operating_cost_per_hour'                  => (float) ($resource['operating_cost_per_hour'] ?? 0),
                'operator_labor_cost_per_machine_per_hour' => (float) ($resource['operator_labor_cost_per_hour'] ?? $meta['operator_labor_cost_per_machine_per_hour'] ?? 0),
                'non_batch_daily_labor_cost'               => (float) ($resource['non_batch_daily_labor_cost'] ?? 0),
                'section_operating_hours'                  => (float) ($resource['section_operating_hours'] ?? 0),
                'active_machines_count'                    => (int) ($resource['active_machines_count'] ?? 0),
                'run_machine_cost_per_unit'                => (float) ($resource['machine_cost_per_unit'] ?? 0),
                'run_labor_cost_per_unit'                  => (float) ($resource['labor_cost_per_unit'] ?? 0),
                'run_cost_per_unit'                        => (float) ($resource['cost_per_unit'] ?? 0),
                'rate_source'                              => 'operator labor = non-batch daily labor ÷ section operating hours ÷ active machines',
            ] + $source;
        }

        return [
            'type'                    => 'manual_batch',
            'batch_id'                => $resource['id'] ?? null,
            'batch_name'              => $resource['batch_name'] ?? null,
            'shift_name'              => $resource['shift_name'] ?? null,
            'capacity'                => (float) ($resource['capacity_per_hour'] ?? 0),
            'speed_minutes_per_unit'  => (float) ($resource['speed_minutes_per_unit'] ?? 0),
            'labor_cost_per_hour'     => (float) ($resource['labor_cost_per_hour'] ?? 0),
            'run_labor_cost_per_unit' => (float) ($resource['labor_cost_per_unit'] ?? 0),
            'run_cost_per_unit'       => (float) ($resource['cost_per_unit'] ?? 0),
            'rate_source'             => 'batch labor per hour (batch workers)',
        ] + $source;
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
