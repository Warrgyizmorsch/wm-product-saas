<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionPlan;

class ProductionPlanNumberService
{
    /**
     * Generate the next automated Plan number for the tenant in the format: PLN-YYYY-XXXXXX.
     */
    public function generateNextNumber(int $tenantId): string
    {
        $year = date('Y');
        $prefix = "PLN-{$year}-";
        
        $latestPlan = ProductionPlan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('plan_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if (!$latestPlan) {
            $num = $prefix . str_pad('1', 6, '0', STR_PAD_LEFT);
        } else {
            $latestNumber = $latestPlan->plan_number;
            $numericPart = substr($latestNumber, strlen($prefix));

            if (is_numeric($numericPart)) {
                $nextVal = (int) $numericPart + 1;
                $len = max(6, strlen($numericPart));
                $num = $prefix . str_pad((string)$nextVal, $len, '0', STR_PAD_LEFT);
            } else {
                $num = $prefix . mt_rand(100000, 999999);
            }
        }

        // Loop to guarantee absolute uniqueness
        while (ProductionPlan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('plan_number', $num)
            ->exists()) {
            $numericPart = substr($num, strlen($prefix));
            if (is_numeric($numericPart)) {
                $nextVal = (int) $numericPart + 1;
                $len = max(6, strlen($numericPart));
                $num = $prefix . str_pad((string)$nextVal, $len, '0', STR_PAD_LEFT);
            } else {
                $num = $prefix . mt_rand(100000, 999999);
            }
        }

        return $num;
    }
}
