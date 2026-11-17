<?php

namespace Tests\Concerns;

use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Models\WorkCenterSectionCalculation;
use App\Domains\Production\Services\CountdownCalculationEngine;
use App\Domains\Production\Services\RoutingOperationCostCalculator;
use App\Domains\Production\Services\SectionCalculationService;

/**
 * Test helper for RT-IMPL-14: does what the routing form does before saving — makes sure each section has a
 * calculation snapshot (recalculates its Work Center once if not) and fills every operation figure from the
 * one calculator, plus the snapshot reference. The backend check then accepts the payload.
 */
trait FillsRoutingFigures
{
    protected function withRoutingFigures(array $payload): array
    {
        if (empty($payload['operations']) || !is_array($payload['operations'])) {
            return $payload;
        }

        $calculator = app(RoutingOperationCostCalculator::class);
        $sections = app(SectionCalculationService::class);

        foreach ($payload['operations'] as $i => $op) {
            $isExternal = filter_var($op['is_external'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $resources = null;

            if (!$isExternal && !empty($op['section_id'])) {
                $section = WorkCenterSection::find($op['section_id']);
                if (!$section) {
                    continue;
                }
                if (!WorkCenterSectionCalculation::where('section_id', $section->id)->exists()) {
                    app(CountdownCalculationEngine::class)->recalculateNow(
                        WorkCenter::find($section->work_center_id), 'manual', CountdownCalculationEngine::SCOPE_ALL
                    );
                }
                $resources = $sections->getSnapshotResourceCosts($section->fresh());
            } elseif (!$isExternal) {
                continue; // structural validation will report it
            }

            $calc = $calculator->calculate($op, $resources);
            if ($calc['error'] !== null) {
                continue;
            }
            foreach ($calc['figures'] as $k => $v) {
                $op[$k] = number_format((float) $v, 4, '.', '');
            }
            if ($resources) {
                $op['section_calculation_id'] = $resources['section_calculation_id'];
                $op['section_snapshot_number'] = $resources['snapshot_number'];
            }
            $payload['operations'][$i] = $op;
        }

        return $payload;
    }
}
