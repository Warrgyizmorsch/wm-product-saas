<?php

namespace App\Domains\Production\Services;

use App\Core\Branch\BranchContext;
use App\Domains\Production\Models\ProductionPlan;

class ProductionPlanNumberService
{
    /**
     * Generate the next automated Plan number for the tenant and branch in the format: PLN-YYYY-XXXXXX.
     */
    public function generateNextNumber(int $tenantId, ?int $branchId = null): string
    {
        $branchId = $branchId ?? branch_id() ?? app(BranchContext::class)->id();
        $year = date('Y');
        $prefix = "PLN-{$year}-";
        
        $query = ProductionPlan::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('plan_number', 'like', "{$prefix}%");

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $latestPlan = $query->orderBy('id', 'desc')->first();

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

        // Loop to guarantee absolute uniqueness within tenant and branch
        $existsQuery = fn(string $testNum) => ProductionPlan::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
            ->where('plan_number', $testNum)
            ->exists();

        while ($existsQuery($num)) {
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
