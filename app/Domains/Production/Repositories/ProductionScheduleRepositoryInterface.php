<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductionScheduleRepositoryInterface
{
    public function paginateSchedules(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?ProductionSchedule;

    public function getActiveScheduleForOrder(int $orderId): ?ProductionSchedule;

    public function getScheduledOperationsForRange(string $startDate, string $endDate, ?int $workCenterId = null): Collection;

    public function createSchedule(array $data): ProductionSchedule;

    public function updateSchedule(int $id, array $data): ProductionSchedule;

    public function createScheduleOperation(array $data): ProductionScheduleOperation;
}
