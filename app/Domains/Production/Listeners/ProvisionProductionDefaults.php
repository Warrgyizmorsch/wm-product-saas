<?php

namespace App\Domains\Production\Listeners;

use App\Core\Tenant\Events\TenantProvisioning;
use App\Domains\Production\Models\ProductionShift;

class ProvisionProductionDefaults
{
    /** code => [name, start, end] */
    private const SHIFTS = [
        'DAY' => ['Day Shift', '09:00:00', '18:00:00'],
        'NIGHT' => ['Night Shift', '21:00:00', '06:00:00'],
    ];

    public function handle(TenantProvisioning $event): void
    {
        if (! $event->includes('production')) {
            return;
        }

        foreach (self::SHIFTS as $code => [$name, $start, $end]) {
            ProductionShift::query()->firstOrCreate(
                ['tenant_id' => $event->tenantId, 'code' => $code],
                [
                    'company_id' => $event->companyId,
                    'name' => $name,
                    'start_time' => $start,
                    'end_time' => $end,
                    'break_minutes' => 0,
                    'overtime_allowed' => false,
                    'active' => true,
                ],
            );
        }
    }
}
