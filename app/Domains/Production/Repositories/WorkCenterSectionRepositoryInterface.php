<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\WorkCenterSection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface WorkCenterSectionRepositoryInterface
{
    public function getAll(array $filters = []): Collection;

    public function paginateAll(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?WorkCenterSection;

    public function create(array $data): WorkCenterSection;

    public function update(int $id, array $data): WorkCenterSection;

    public function delete(int $id): bool;

    public function findByCode(string $code, int $workCenterId, int $tenantId, ?int $ignoreId = null): ?WorkCenterSection;

    public function getByWorkCenter(int $workCenterId, bool $activeOnly = false): Collection;

    public function syncShifts(WorkCenterSection $section, array $shiftIds, ?int $tenantId = null, array $shiftActive = []): void;

    public function syncMachines(WorkCenterSection $section, array $machineIds, int $tenantId): void;
}
