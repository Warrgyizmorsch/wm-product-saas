<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionKpiTarget;
use Illuminate\Support\Collection;

interface KpiTargetRepositoryInterface
{
    public function getAllForTenant(int $tenantId): Collection;

    public function updateOrCreate(int $tenantId, string $kpiName, float $targetValue): ProductionKpiTarget;
}
