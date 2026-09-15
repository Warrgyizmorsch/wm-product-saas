<?php

namespace App\Domains\Inventory\Listeners;

use App\Core\Tenant\Events\TenantProvisioning;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;

class ProvisionInventoryDefaults
{
    /** code => [name, category] */
    private const UOMS = [
        'Pcs' => ['Pieces', 'Goods'],
        'Kg' => ['Kilograms', 'Goods'],
        'Mtr' => ['Meters', 'Goods'],
        'Set' => ['Set', 'Goods'],
        'HRS' => ['Hours', 'Service'],
        'DAYS' => ['Days', 'Service'],
        'VST' => ['Visits', 'Service'],
        'JOB' => ['Jobs', 'Service'],
        'SES' => ['Sessions', 'Service'],
        'UNTS' => ['Service Units', 'Service'],
        'MTH' => ['Months', 'Service'],
        'YRS' => ['Years', 'Service'],
    ];

    public function handle(TenantProvisioning $event): void
    {
        foreach (self::UOMS as $code => [$name, $category]) {
            Uom::query()->firstOrCreate(
                ['tenant_id' => $event->tenantId, 'code' => $code],
                ['company_id' => $event->companyId, 'branch_id' => $event->branchId, 'name' => $name, 'category' => $category],
            );
        }

        // One warehouse to receive stock into; a tenant that already has any keeps its own.
        if (! Warehouse::query()->exists()) {
            Warehouse::query()->create([
                'tenant_id' => $event->tenantId,
                'company_id' => $event->companyId,
                'branch_id' => $event->branchId,
                'name' => 'Main Warehouse',
                'code' => 'WH-MAIN',
                'status' => 'active',
                'is_default' => true,
            ]);
        }
    }
}
