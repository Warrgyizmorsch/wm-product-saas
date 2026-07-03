<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrder;

class ProductionOrderNumberService
{
    /**
     * Generate the next automated Order number for the tenant in the format: ORD-YYYY-XXXXXX.
     */
    public function generateNextNumber(int $tenantId): string
    {
        $year = date('Y');
        $prefix = "ORD-{$year}-";
        
        $latestOrder = ProductionOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('order_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

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

        // Loop to guarantee absolute uniqueness
        while (ProductionOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('order_number', $num)
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
