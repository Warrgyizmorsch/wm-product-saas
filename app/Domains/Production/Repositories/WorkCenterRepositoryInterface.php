<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\WorkCenter;
use Illuminate\Database\Eloquent\Collection;

interface WorkCenterRepositoryInterface
{
    public function getAll(array $filters = []): Collection;

    public function find(int $id): ?WorkCenter;

    public function create(array $data): WorkCenter;

    public function update(int $id, array $data): WorkCenter;

    public function delete(int $id): bool;

    public function findByCode(string $code, int $tenantId, ?int $ignoreId = null): ?WorkCenter;

    public function getActiveWorkCenters(): Collection;
}
