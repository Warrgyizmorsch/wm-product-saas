<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBatch;

class BatchNumberService
{
    /**
     * Generate the next batch number for the tenant in the format: BAT-YYYY-000001.
     * Collision-safe and tenant-scoped.
     */
    /**
     * Generate the next batch number for the tenant in the format: BAT-{ORDER_NUMBER}-01.
     * Collision-safe, order-sequential, and tenant-scoped.
     */
    public function generateNextNumber(int $tenantId, mixed $order = null): string
    {
        if ($order) {
            $orderNumber = null;
            if (is_object($order) && isset($order->order_number)) {
                $orderNumber = $order->order_number;
            } elseif (is_numeric($order)) {
                $orderModel = ProductionBatch::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->find($order)?->order;
                if (!$orderModel) {
                    $orderModel = \App\Domains\Production\Models\ProductionOrder::withoutGlobalScopes()->find($order);
                }
                $orderNumber = $orderModel?->order_number;
            }

            if ($orderNumber) {
                $prefix = "BAT-{$orderNumber}-";

                $count = ProductionBatch::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('batch_number', 'like', "{$prefix}%")
                    ->count();

                $nextVal = $count + 1;
                $num = $prefix . str_pad((string) $nextVal, 2, '0', STR_PAD_LEFT);

                while (ProductionBatch::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('batch_number', $num)
                    ->exists()
                ) {
                    $nextVal++;
                    $num = $prefix . str_pad((string) $nextVal, 2, '0', STR_PAD_LEFT);
                }

                return $num;
            }
        }

        $year   = date('Y');
        $prefix = "BAT-{$year}-";

        $latest = ProductionBatch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('batch_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
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
