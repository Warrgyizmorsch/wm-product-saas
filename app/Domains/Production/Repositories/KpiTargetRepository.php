<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionKpiTarget;
use Illuminate\Support\Collection;

class KpiTargetRepository implements KpiTargetRepositoryInterface
{
    public function getAllForTenant(int $tenantId): Collection
    {
        return ProductionKpiTarget::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get();
    }

    public function updateOrCreate(int $tenantId, string $kpiName, float $targetValue): ProductionKpiTarget
    {
        return ProductionKpiTarget::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'kpi_name'  => $kpiName,
            ],
            [
                'target_value' => $targetValue,
            ]
        );
    }
}
