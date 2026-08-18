<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductionScheduleRepository implements ProductionScheduleRepositoryInterface
{
    public function paginateSchedules(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionSchedule::with(['order', 'scenarios']);

        if (!empty($filters['production_order_id'])) {
            $query->where('production_order_id', $filters['production_order_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where('schedule_number', 'like', $search);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function find(int $id): ?ProductionSchedule
    {
        return ProductionSchedule::with([
            'order',
            'operations.workCenter',
            'operations.machine',
            'scenarios',
            'changeLogs',
        ])->find($id);
    }

    public function getActiveScheduleForOrder(int $orderId): ?ProductionSchedule
    {
        return ProductionSchedule::where('production_order_id', $orderId)
            ->where('is_active', true)
            ->first();
    }

    public function getScheduledOperationsForRange(string $startDate, string $endDate, ?int $workCenterId = null): Collection
    {
        $query = ProductionScheduleOperation::whereBetween('planned_start_time', [$startDate, $endDate])
            ->with(['schedule.order', 'workCenter', 'machine']);

        if ($workCenterId) {
            $query->where('work_center_id', $workCenterId);
        }

        return $query->orderBy('planned_start_time')->get();
    }

    public function createSchedule(array $data): ProductionSchedule
    {
        return ProductionSchedule::create($data);
    }

    public function updateSchedule(int $id, array $data): ProductionSchedule
    {
        $schedule = ProductionSchedule::findOrFail($id);
        $schedule->update($data);
        return $schedule->fresh();
    }

    public function createScheduleOperation(array $data): ProductionScheduleOperation
    {
        return ProductionScheduleOperation::create($data);
    }
}
