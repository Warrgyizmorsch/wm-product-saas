<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductionBatchRepositoryInterface
{
    public function paginateBatches(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?ProductionBatch;

    public function findBatchByNumber(string $batchNumber): ?ProductionBatch;

    public function getBatchesForOperation(int $operationId): Collection;

    public function lockBatchForUpdate(int $batchId): ProductionBatch;

    public function create(array $data): ProductionBatch;

    public function update(int $id, array $data): ProductionBatch;

    public function delete(int $id): bool;

    public function getActiveOperatorAssignments(int $operatorId): Collection;
}
