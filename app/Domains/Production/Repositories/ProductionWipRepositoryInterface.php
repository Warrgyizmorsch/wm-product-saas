<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductionWipRepositoryInterface
{
    public function paginateWip(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?ProductionWip;

    public function getWipForOrder(int $orderId): Collection;

    public function getTransferableWip(int $batchId, int $operationId): ?ProductionWip;

    public function lockWipForTransfer(int $wipId): ProductionWip;

    public function createWip(array $data): ProductionWip;

    public function updateWip(int $id, array $data): ProductionWip;

    public function createTransaction(array $data): ProductionWipTransaction;

    public function getUninitializedOrders(int $tenantId): Collection;

    public function getWipKpiSummary(int $tenantId): array;
}
