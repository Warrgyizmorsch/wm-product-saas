<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Repositories\KpiTargetRepositoryInterface;
use App\Domains\Production\DTO\KpiTargetDTO;
use Illuminate\Support\Facades\DB;

class KpiTargetService
{
    public function __construct(
        private readonly KpiTargetRepositoryInterface $repository
    ) {}

    /**
     * Bulk update KPI Targets for a tenant.
     *
     * @param int $tenantId
     * @param KpiTargetDTO[] $dtos
     */
    public function updateTargets(int $tenantId, array $dtos): void
    {
        DB::transaction(function () use ($tenantId, $dtos) {
            foreach ($dtos as $dto) {
                $this->repository->updateOrCreate($tenantId, $dto->kpi_name, $dto->target_value);
            }
        });
    }
}
