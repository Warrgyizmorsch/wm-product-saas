<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\WorkCenter;
use Illuminate\Database\Eloquent\Collection;

interface WorkCenterRepositoryInterface
{
    public function getAll(array $filters = []): Collection;

    public function paginateAll(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    public function find(int $id): ?WorkCenter;

    public function create(array $data): WorkCenter;

    public function update(int $id, array $data): WorkCenter;

    public function delete(int $id): bool;

    public function findByCode(string $code, int $tenantId, ?int $ignoreId = null): ?WorkCenter;

    public function getActiveWorkCenters(): Collection;

    public function getActiveShifts(int $tenantId): Collection;

    public function getAllOrderedByName(): Collection;

    public function paginateShifts(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    public function findShift(int $id): ?\App\Domains\Production\Models\ProductionShift;

    public function createShift(array $data): \App\Domains\Production\Models\ProductionShift;

    public function updateShift(int $id, array $data): \App\Domains\Production\Models\ProductionShift;

    public function deleteShift(int $id): bool;

    public function getDashboardWorkCenters(int $tenantId): Collection;
}
