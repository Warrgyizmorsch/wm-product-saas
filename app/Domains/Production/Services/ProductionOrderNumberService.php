<?php

namespace App\Domains\Production\Services;

use App\Core\Branch\BranchContext;
use App\Domains\Production\Models\ProductionOrder;

class ProductionOrderNumberService
{
    /**
     * Generate the next automated Order number for the tenant and branch in the format: ORD-YYYY-XXXXXX.
     */
    public function generateNextNumber(int $tenantId, ?int $branchId = null): string
    {
        $branchId = $branchId ?? branch_id() ?? app(BranchContext::class)->id();
        $year = date('Y');
        $prefix = "ORD-{$year}-";
        
        $query = ProductionOrder::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('order_number', 'like', "{$prefix}%");

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $latestOrder = $query->orderBy('id', 'desc')->first();

        if (!$latestOrder) {
            $num = $prefix . str_pad('1', 6, '0', STR_PAD_LEFT);
        } else {
            $latestNumber = $latestOrder->order_number;
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
        $existsQuery = fn(string $testNum) => ProductionOrder::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
            ->where('order_number', $testNum)
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
