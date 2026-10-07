<?php

namespace App\Domains\Production\Services;

use App\Core\Branch\BranchContext;
use App\Domains\Production\Models\ProductionSchedule;

class ProductionScheduleNumberService
{
    /**
     * Generate the next schedule number for the tenant and branch in the format: SCH-YYYY-XXXXXX.
     * Collision-safe, branch-scoped and tenant-scoped.
     */
    public function generateNextNumber(int $tenantId, ?int $branchId = null): string
    {
        $branchId = $branchId ?? branch_id() ?? app(BranchContext::class)->id();
        $year   = date('Y');
        $prefix = "SCH-{$year}-";

        $query = ProductionSchedule::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('schedule_number', 'like', "{$prefix}%");

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $latest = $query->orderBy('id', 'desc')->first();

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

        // Loop to guarantee absolute uniqueness within tenant and branch
        $existsQuery = fn(string $testNum) => ProductionSchedule::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
            ->where('schedule_number', $testNum)
            ->exists();

        while ($existsQuery($num)) {
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
