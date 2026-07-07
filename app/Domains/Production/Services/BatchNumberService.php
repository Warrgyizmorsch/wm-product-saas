<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBatch;

class BatchNumberService
{
    /**
     * Generate the next batch number for the tenant in the format: BAT-YYYY-000001.
     * Collision-safe and tenant-scoped.
     */
    public function generateNextNumber(int $tenantId): string
    {
        $year   = date('Y');
        $prefix = "BAT-{$year}-";

        $latest = ProductionBatch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('batch_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if (!$latest) {
            $num = $prefix . str_pad('1', 6, '0', STR_PAD_LEFT);
        } else {
            $numericPart = substr($latest->batch_number, strlen($prefix));

            if (is_numeric($numericPart)) {
                $nextVal = (int) $numericPart + 1;
                $len     = max(6, strlen($numericPart));
                $num     = $prefix . str_pad((string) $nextVal, $len, '0', STR_PAD_LEFT);
            } else {
                $num = $prefix . mt_rand(100000, 999999);
            }
        }

        // Loop to guarantee absolute uniqueness within tenant
        while (ProductionBatch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('batch_number', $num)
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
