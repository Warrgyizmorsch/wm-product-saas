<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductionOrderRepositoryInterface
{
    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function getStatusCounts(): array;

    public function find(int $id): ?ProductionOrder;

    public function findWithDetails(int $id): ?ProductionOrder;

    public function findForExecutionLock(int $id): ProductionOrder;

    public function create(array $data): ProductionOrder;

    public function update(int $id, array $data): ProductionOrder;

    public function delete(int $id): bool;

    public function getPendingRequests(int $tenantId, ?int $requestId = null): Collection;
}
