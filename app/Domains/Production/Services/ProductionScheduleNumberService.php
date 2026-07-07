<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionSchedule;

class ProductionScheduleNumberService
{
    /**
     * Generate the next schedule number for the tenant in the format: SCH-YYYY-XXXXXX.
     * Collision-safe and tenant-scoped — follows the same pattern as
     * ProductionPlanNumberService and ProductionOrderNumberService.
     */
    public function generateNextNumber(int $tenantId): string
    {
        $year   = date('Y');
        $prefix = "SCH-{$year}-";

        $latest = ProductionSchedule::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('schedule_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if (!$latest) {
            $num = $prefix . str_pad('1', 6, '0', STR_PAD_LEFT);
        } else {
            $numericPart = substr($latest->schedule_number, strlen($prefix));

            if (is_numeric($numericPart)) {
                $nextVal = (int) $numericPart + 1;
                $len     = max(6, strlen($numericPart));
                $num     = $prefix . str_pad((string) $nextVal, $len, '0', STR_PAD_LEFT);
            } else {
                $num = $prefix . mt_rand(100000, 999999);
            }
        }

        // Loop to guarantee absolute uniqueness within tenant
        while (ProductionSchedule::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('schedule_number', $num)
            ->exists()
        ) {
            $numericPart = substr($num, strlen($prefix));
            if (is_numeric($numericPart)) {
                $nextVal = (int) $numericPart + 1;
                $len     = max(6, strlen($numericPart));
                $num     = $prefix . str_pad((string) $nextVal, $len, '0', STR_PAD_LEFT);
            } else {
                $num = $prefix . mt_rand(100000, 999999);
            }
        }

        return $num;
    }
}
