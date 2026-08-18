<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionCapa;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\ProductionScrapDisposal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductionQualityRepositoryInterface
{
    public function paginateInspections(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findInspection(int $id): ?ProductionQualityInspection;

    public function createInspection(array $data): ProductionQualityInspection;

    public function updateInspection(int $id, array $data): ProductionQualityInspection;

    public function paginateNcrs(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findNcr(int $id): ?ProductionNcr;

    public function createNcr(array $data): ProductionNcr;

    public function paginateCapas(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function createCapa(array $data): ProductionCapa;

    public function paginateReworkOrders(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function createReworkOrder(array $data): ProductionReworkOrder;

    public function paginateScrapDisposals(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function createScrapDisposal(array $data): ProductionScrapDisposal;

    public function findReworkOrder(int $id): ?ProductionReworkOrder;

    public function findScrapDisposal(int $id): ?ProductionScrapDisposal;

    public function findCapa(int $id): ?ProductionCapa;

    public function getQualityDashboardKpis(int $tenantId): array;
}
