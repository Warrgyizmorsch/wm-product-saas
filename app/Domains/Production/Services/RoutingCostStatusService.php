<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenterSection;

/**
 * RT-IMPL-14 (RT-19 rule 7): "costs outdated" detection for a routing.
 *
 * Compares the figures SAVED on each operation with the latest section calculation snapshot — numbers only,
 * nothing is recalculated or saved. Computed when a page is displayed.
 */
class RoutingCostStatusService
{
    public function __construct(
        private readonly SectionCalculationService $sectionCalculationService,
        private readonly RoutingOperationCostCalculator $calculator,
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
                if (!array_key_exists($op->section_id, $sectionCache)) {
                    $section = WorkCenterSection::find($op->section_id);
                    $sectionCache[$op->section_id] = $section ? $this->sectionCalculationService->getSnapshotResourceCosts($section) : null;
                }
                $latest = $sectionCache[$op->section_id];

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
            // RT-IMPL-18 (RT-29 rule 2): resources that are no longer active — statuses compared only
            'inactive'        => $inactive = $this->inactiveResources($routing),
            'inactive_header' => $inactive === [] ? null
                : count($inactive) . ' operation(s) use inactive resources — '
                    . ($routing->isReadOnly() ? 'create a new routing version to update.' : 'choose another resource and save.'),
        ];
    }

    /**
     * RT-IMPL-18: "OP-XXX uses machine MC-XX, which is currently inactive." for every operation whose work center,
     * section, machine or batch is inactive or deleted.
     *
     * @return array<int, array{operation_id:int, operation_number:string, message:string}>
     */
    public function inactiveResources(Routing $routing): array
    {
        $routing->loadMissing([
            'operations.workCenter' => fn($q) => $q->withTrashed(),
            'operations.section'    => fn($q) => $q->withTrashed(),
            'operations.machine'    => fn($q) => $q->withTrashed(),
            'operations.manualProduction' => fn($q) => $q->withTrashed(),
        ]);
        $out = [];

        foreach ($routing->operations as $op) {
            if ($op->is_external) {
                continue;
            }
            $problems = [];
            if ($op->work_center_id) {
                $wc = $op->workCenter;
                if (!$wc || (method_exists($wc, 'trashed') && $wc->trashed())) {
                    $problems[] = 'work center #' . $op->work_center_id . ', which has been deleted';
                } elseif ($wc->status !== 'active') {
                    $problems[] = "work center {$wc->code}, which is currently {$wc->status}";
                }
            }
            if ($op->section_id) {
                $sec = $op->section;
                if (!$sec || (method_exists($sec, 'trashed') && $sec->trashed())) {
                    $problems[] = 'section #' . $op->section_id . ', which has been deleted';
                } elseif ($sec->status !== 'active') {
                    $problems[] = "section {$sec->code}, which is currently {$sec->status}";
                }
            }
            if ($op->machine_id) {
                $m = $op->machine;
                if (!$m || (method_exists($m, 'trashed') && $m->trashed())) {
                    $problems[] = 'machine #' . $op->machine_id . ', which has been deleted';
                } elseif ($m->status !== \App\Domains\Production\Models\Machine::STATUS_ACTIVE) {
                    $problems[] = "machine {$m->code}, which is currently " . str_replace('_', ' ', $m->status);
                }
            }
            if ($op->section_manual_production_id) {
                $b = $op->manualProduction;
                if (!$b || $b->trashed()) {
                    $problems[] = 'manual batch #' . $op->section_manual_production_id . ', which has been deleted';
                } elseif (!$b->active) {
                    $problems[] = "manual batch {$b->batch_name}, which is currently inactive";
                }
            }
            foreach ($problems as $problem) {
                $out[] = ['operation_id' => $op->id, 'operation_number' => $op->operation_number, 'message' => "{$op->operation_number} uses {$problem}."];
            }
        }

        return $out;
    }

    private function compare($op, array $latest): array
    {
        $messages = [];
        $snap = is_array($op->resource_snapshot) ? $op->resource_snapshot : [];
        $code = $latest['section_code'] ?? '';

        $inputs = [
            'is_external'                  => false,
            'machine_id'                   => $op->machine_id,
            'section_manual_production_id' => $op->section_manual_production_id,
            'setup_time_minutes'           => $op->setup_time_minutes,
            'setup_time_per_piece_minutes' => $op->setup_time_per_piece_minutes,
            'expected_yield_percentage'    => $op->expected_yield_percentage,
        ];
        $calc = $this->calculator->calculate($inputs, $latest);

        if ($calc['error'] === 'not_in_snapshot') {
            $what = $op->machine_id ? ('Machine ' . ($snap['code'] ?? $op->machine?->code ?? "#{$op->machine_id}")) : ('Manual batch ' . ($snap['batch_name'] ?? "#{$op->section_manual_production_id}"));
            return ["{$what} is no longer active in section {$code} (snapshot #{$latest['snapshot_number']})."];
        }

        $saved = [
            'processing_time_minutes' => $op->processing_time_minutes,
            'labor_cost_rate'         => $op->labor_cost_rate,
            'machine_cost_rate'       => $op->machine_cost_rate,
            'labor_cost_per_unit'     => $op->labor_cost_per_unit,
            'machine_cost_per_unit'   => $op->machine_cost_per_unit,
            'cost_per_unit'           => $op->cost_per_unit,
            'setup_cost_per_run'      => $op->setup_cost_per_run,
        ];
        $differs = $this->calculator->mismatches($saved, $calc['figures']) !== [];
        $newer = (int) ($latest['section_calculation_id'] ?? 0) !== (int) ($op->section_calculation_id ?? 0);

        if ($differs) {
            if ($newer) {
                $messages[] = "Costs outdated — section {$code} has a newer calculation (snapshot #{$latest['snapshot_number']}).";
            } else {
                $messages[] = "Costs outdated — saved figures differ from section {$code} snapshot #{$latest['snapshot_number']}.";
            }

            $res = $calc['resource'] ?? [];
            if ($op->machine_id) {
                $oldCost = (float) ($snap['operating_cost_per_hour'] ?? 0);
                $newCost = (float) ($res['operating_cost_per_hour'] ?? 0);
                if (abs($oldCost - $newCost) > RoutingOperationCostCalculator::TOLERANCE) {
                    $messages[] = 'Machine ' . ($res['code'] ?? '') . ' cost changed (was ' . format_currency($oldCost, 4) . '/hr, now ' . format_currency($newCost, 4) . '/hr).';
                }
                $oldLabor = (float) ($snap['operator_labor_cost_per_machine_per_hour'] ?? 0);
                $newLabor = (float) ($res['operator_labor_cost_per_hour'] ?? 0);
                if (abs($oldLabor - $newLabor) > RoutingOperationCostCalculator::TOLERANCE) {
                    $messages[] = 'Operator labor changed (was ' . format_currency($oldLabor, 4) . '/hr, now ' . format_currency($newLabor, 4) . '/hr).';
                }
            } else {
                $oldLabor = (float) ($snap['labor_cost_per_hour'] ?? 0);
                $newLabor = (float) ($res['labor_cost_per_hour'] ?? 0);
                if (abs($oldLabor - $newLabor) > RoutingOperationCostCalculator::TOLERANCE) {
                    $messages[] = 'Batch ' . ($res['batch_name'] ?? '') . ' labor changed (was ' . format_currency($oldLabor, 4) . '/hr, now ' . format_currency($newLabor, 4) . '/hr).';
                }
            }
            $oldCap = (float) ($snap['capacity'] ?? 0);
            $newCap = (float) ($res['capacity'] ?? $res['capacity_per_hour'] ?? 0);
            if ($oldCap > 0 && abs($oldCap - $newCap) > RoutingOperationCostCalculator::TOLERANCE) {
                $messages[] = 'Capacity changed (was ' . format_decimal($oldCap) . ' u/h, now ' . format_decimal($newCap) . ' u/h).';
            }
        }

        return $messages;
    }
}
