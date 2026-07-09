<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionBom;
use Illuminate\Database\Eloquent\Collection;

interface ProductionBomRepositoryInterface
{
    public function getAll(array $filters = []): Collection;

    public function paginateAll(array $filters = [], int $perPage = 10): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    public function find(int $id): ?ProductionBom;

    public function create(array $data): ProductionBom;

    public function update(int $id, array $data): ProductionBom;

    public function delete(int $id): bool;

    public function getActiveBom(int $productId): ?ProductionBom;

    public function getBomWithComponents(int $id): ?ProductionBom;
}
