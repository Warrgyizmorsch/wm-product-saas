<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionPmSchedule;

class MaintenanceCodeService
{
    public function generatePmScheduleCode(int $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = "PM-{$year}-";

        $count = ProductionPmSchedule::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', 'like', "{$prefix}%")
            ->count();

        $nextVal = $count + 1;
        $num = $prefix . str_pad((string)$nextVal, 6, '0', STR_PAD_LEFT);

        while (ProductionPmSchedule::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $num)->exists()) {
            $nextVal++;
            $num = $prefix . str_pad((string)$nextVal, 6, '0', STR_PAD_LEFT);
        }

        return $num;
    }

    public function generateWorkOrderNumber(int $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = "MWO-{$year}-";

        $count = ProductionMaintenanceWorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('work_order_number', 'like', "{$prefix}%")
            ->count();

        $nextVal = $count + 1;
        $num = $prefix . str_pad((string)$nextVal, 6, '0', STR_PAD_LEFT);

        while (ProductionMaintenanceWorkOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('work_order_number', $num)->exists()) {
            $nextVal++;
            $num = $prefix . str_pad((string)$nextVal, 6, '0', STR_PAD_LEFT);
        }

        return $num;
    }
}
