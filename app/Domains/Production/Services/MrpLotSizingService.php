<?php

namespace App\Domains\Production\Services;

class MrpLotSizingService
{
    /**
     * Calculate planned supply quantity and description of lot sizing rule applied.
     *
     * @param float $netRequirement
     * @param float $moq Minimum Order Quantity (0 if unconstrained)
     * @param float $orderMultiple Order Multiple increment (0 if unconstrained)
     * @return array ['planned_supply_qty' => float, 'rule' => string]
     */
    public function calculatePlannedSupply(float $netRequirement, float $moq = 0.0, float $orderMultiple = 0.0): array
    {
        $netRequirement = max(0.0, round($netRequirement, 4));

        if ($netRequirement <= 0.0) {
            return [
                'planned_supply_qty' => 0.0,
                'rule' => 'None',
            ];
        }

        $moq = max(0.0, (float) $moq);
        $orderMultiple = max(0.0, (float) $orderMultiple);

        $qty = $netRequirement;
        $rulesApplied = [];

        if ($moq > 0.0 && $qty < $moq) {
            $qty = $moq;
            $rulesApplied[] = 'MOQ ' . (float) $moq;
        }

        if ($orderMultiple > 0.0) {
            $multiples = (int) ceil(round($qty / $orderMultiple, 8));
            $newQty = round($multiples * $orderMultiple, 4);

            if (empty($rulesApplied) || $newQty > $moq) {
                $rulesApplied[] = 'Multiple ' . (float) $orderMultiple;
            }
            $qty = $newQty;
        }

        $ruleText = !empty($rulesApplied) ? implode(', ', $rulesApplied) : 'Standard';

        return [
            'planned_supply_qty' => round($qty, 4),
            'rule' => $ruleText,
        ];
    }
}
