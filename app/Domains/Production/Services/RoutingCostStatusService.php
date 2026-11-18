<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenterSection;

/**
 * RT-IMPL-14 (RT-19 rule 7): "costs outdated" detection for a routing.
 *
 * Snapshot-only, no recalculation: the original resource values stored on each operation (`resource_snapshot`) are
 * compared field by field with the same machine / manual batch in the latest section calculation snapshot.
 * A section recalculated with no real change keeps the routing up to date. Computed when a page is displayed.
 *
 *   machine : run speed, operating cost per hour, operator labor per machine per hour
 *   batch   : run speed, labor cost per hour
 *   a machine / batch that is no longer in the latest section snapshot is outdated too.
 */
class RoutingCostStatusService
{
    public function __construct(
        private readonly SectionCalculationService $sectionCalculationService,
    ) {}

    /**
     * @return array{outdated: bool, not_calculated: bool, action: string, operations: array<int, array{operation_number: string, messages: string[]}>}
     */
    public function check(Routing $routing): array
    {
        $routing->loadMissing('operations');
        $sectionCache = [];
        $result = [];
        $notCalculated = false;

        foreach ($routing->operations as $op) {
            $messages = [];

            if ($op->costsNotCalculated()) {
                $notCalculated = true;
                $messages[] = 'Costs not calculated — open and save the routing.';
            } elseif (!$op->is_external && $op->section_id && ($op->machine_id || $op->section_manual_production_id)) {
                $latest = $this->latestSnapshot((int) $op->section_id, $sectionCache);

                if ($latest && empty($latest['not_calculated'])) {
                    $messages = $this->compare($op, $latest);
                }
            }

            if ($messages !== []) {
                $result[] = ['operation_id' => $op->id, 'operation_number' => $op->operation_number, 'messages' => $messages];
            }
        }

        $outdated = collect($result)->contains(fn($r) => collect($r['messages'])->contains(fn($m) => !str_starts_with($m, 'Costs not calculated')));

        return [
            'outdated'       => $outdated,
            'not_calculated' => $notCalculated,
            'action'         => $routing->isReadOnly()
                ? 'Create a new routing version to update.'
                : 'Open and save the routing to refresh costs.',
            'operations'     => $result,
            // RT-IMPL-18 (RT-29 rule 2): inactive sections / machines / batches — read from the section snapshot only
            'inactive'        => $inactive = $this->inactiveResources($routing, $sectionCache),
            'inactive_header' => $inactive === [] ? null
                : count($inactive) . ' operation(s) use inactive resources — '
                    . ($routing->isReadOnly() ? 'create a new routing version to update.' : 'choose another resource and save.'),
        ];
    }

    /**
     * RT-IMPL-18: "OP-XXX uses section S-1, which is currently inactive." for every operation whose section was
     * deactivated or deleted, or whose machine / manual batch is no longer listed as active in the section snapshot.
     * Taken from the section snapshot (`section_active`, `machines`, `manual_batches`), not from the live work center /
     * machine tables.
     *
     * @param array<int, array|null> $sectionCache latest snapshot per section id, shared with check()
     * @return array<int, array{operation_id:int, operation_number:string, message:string}>
     */
    public function inactiveResources(Routing $routing, array &$sectionCache = []): array
    {
        $routing->loadMissing('operations');
        $out = [];

        foreach ($routing->operations as $op) {
            if ($op->is_external || !$op->section_id) {
                continue;
            }

            $latest = $this->latestSnapshot((int) $op->section_id, $sectionCache);
            $problem = null;

            if ($latest === null) {
                $problem = 'section #' . $op->section_id . ', which has been deleted';
            } elseif (!empty($latest['not_calculated'])) {
                continue; // no snapshot to read yet — shown as "not calculated"
            } elseif (($latest['section_active'] ?? true) === false) {
                $problem = 'section ' . ($latest['section_code'] ?? '#' . $op->section_id) . ', which is currently inactive';
            } elseif ($op->machine_id || $op->section_manual_production_id) {
                // an active machine / batch is listed in the section snapshot; one that is not has left the section
                $isMachine = (bool) $op->machine_id;
                $resourceId = (int) ($isMachine ? $op->machine_id : $op->section_manual_production_id);
                $list = $isMachine ? ($latest['machines'] ?? []) : ($latest['manual_batches'] ?? []);

                if (!collect($list)->contains(fn($r) => (int) ($r['id'] ?? 0) === $resourceId)) {
                    $snap = is_array($op->resource_snapshot) ? $op->resource_snapshot : [];
                    $what = $isMachine
                        ? 'machine ' . ($snap['code'] ?? "#{$resourceId}")
                        : 'manual batch ' . ($snap['batch_name'] ?? "#{$resourceId}");
                    $problem = "{$what}, which is no longer active in section " . ($latest['section_code'] ?? '#' . $op->section_id);
                }
            }

            if ($problem !== null) {
                $out[] = ['operation_id' => $op->id, 'operation_number' => $op->operation_number, 'message' => "{$op->operation_number} uses {$problem}."];
            }
        }

        return $out;
    }

    /** Latest section snapshot figures (cached per call); null when the section no longer exists. */
    private function latestSnapshot(int $sectionId, array &$cache): ?array
    {
        if (!array_key_exists($sectionId, $cache)) {
            $section = WorkCenterSection::find($sectionId);
            $cache[$sectionId] = $section ? $this->sectionCalculationService->getSnapshotResourceCosts($section) : null;
        }

        return $cache[$sectionId];
    }

    /** One message per changed value: stored resource value of the operation vs the same resource in the latest snapshot. */
    private function compare($op, array $latest): array
    {
        $snap = is_array($op->resource_snapshot) ? $op->resource_snapshot : [];
        $sectionCode = $latest['section_code'] ?? '';
        $snapshotNo = $latest['snapshot_number'] ?? '?';
        $isMachine = (bool) $op->machine_id;
        $resourceId = (int) ($isMachine ? $op->machine_id : $op->section_manual_production_id);
        $list = $isMachine ? ($latest['machines'] ?? []) : ($latest['manual_batches'] ?? []);
        $now = collect($list)->first(fn($r) => (int) ($r['id'] ?? 0) === $resourceId);

        if ($now === null) {
            $what = $isMachine
                ? 'Machine ' . ($snap['code'] ?? "#{$resourceId}")
                : 'Manual batch ' . ($snap['batch_name'] ?? "#{$resourceId}");

            return ["{$what} is no longer active in section {$sectionCode} (snapshot #{$snapshotNo})."];
        }

        $speed = $now['speed_minutes_per_unit'] ?? null;
        $checks = $isMachine
            ? [
                ['speed_minutes_per_unit', $speed, 'run speed', 'speed'],
                ['operating_cost_per_hour', $now['operating_cost_per_hour'] ?? null, 'operating cost', 'money'],
                ['operator_labor_cost_per_machine_per_hour', $now['operator_labor_cost_per_hour'] ?? $latest['operator_labor_cost_per_machine_per_hour'] ?? null, 'operator labor', 'money'],
            ]
            : [
                ['speed_minutes_per_unit', $speed, 'run speed', 'speed'],
                ['labor_cost_per_hour', $now['labor_cost_per_hour'] ?? null, 'labor cost', 'money'],
            ];
        $label = $isMachine
            ? 'Machine ' . ($now['code'] ?? "#{$resourceId}")
            : 'Batch ' . ($now['batch_name'] ?? "#{$resourceId}");

        $messages = [];
        foreach ($checks as [$key, $new, $name, $kind]) {
            if ($new === null || !array_key_exists($key, $snap) || !is_numeric($snap[$key])) {
                continue; // nothing stored to compare with
            }
            $old = (float) $snap[$key];
            $new = (float) $new;
            if (abs($old - $new) <= RoutingOperationCostCalculator::TOLERANCE + 1e-9) {
                continue;
            }

            $fmt = fn(float $v) => $kind === 'money' ? format_currency($v, 4) . '/hr' : format_decimal($v) . ' min/unit';
            $messages[] = "Costs outdated — {$label} {$name} changed (was {$fmt($old)}, now {$fmt($new)}) in section {$sectionCode} snapshot #{$snapshotNo}.";
        }

        return $messages;
    }
}
