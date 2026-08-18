<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\WorkCenter;
use Illuminate\Database\Eloquent\Collection;

class WorkCenterRepository implements WorkCenterRepositoryInterface
{
    public function getAll(array $filters = []): Collection
    {
        $query = WorkCenter::query()->withCount('machines');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['work_center_type'])) {
            $query->where('work_center_type', $filters['work_center_type']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('department_name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get();
    }

    public function paginateAll(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = WorkCenter::query()->withCount('machines');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['work_center_type'])) {
            $query->where('work_center_type', $filters['work_center_type']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('department_name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function find(int $id): ?WorkCenter
    {
        return WorkCenter::with(['machines', 'shifts'])->find($id);
    }

    public function create(array $data): WorkCenter
    {
        return WorkCenter::create($data);
    }

    public function update(int $id, array $data): WorkCenter
    {
        $workCenter = WorkCenter::findOrFail($id);
        $workCenter->update($data);
        return $workCenter->fresh();
    }

    public function delete(int $id): bool
    {
        $workCenter = WorkCenter::findOrFail($id);
        return (bool) $workCenter->delete(); // SoftDeletes
    }

    public function findByCode(string $code, int $tenantId, ?int $ignoreId = null): ?WorkCenter
    {
        $query = WorkCenter::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->first();
    }

    public function getActiveWorkCenters(): Collection
    {
        return WorkCenter::active()->orderBy('name')->get();
    }

    public function getActiveShifts(int $tenantId): Collection
    {
        return \App\Domains\Production\Models\ProductionShift::where('tenant_id', $tenantId)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    public function getAllOrderedByName(): Collection
    {
        return WorkCenter::orderBy('name')->get();
    }

    public function paginateShifts(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = \App\Domains\Production\Models\ProductionShift::query();

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search);
            });
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function findShift(int $id): ?\App\Domains\Production\Models\ProductionShift
    {
        return \App\Domains\Production\Models\ProductionShift::find($id);
    }

    public function createShift(array $data): \App\Domains\Production\Models\ProductionShift
    {
        return \App\Domains\Production\Models\ProductionShift::create($data);
    }

    public function updateShift(int $id, array $data): \App\Domains\Production\Models\ProductionShift
    {
        $shift = \App\Domains\Production\Models\ProductionShift::findOrFail($id);
        $shift->update($data);
        return $shift->fresh();
    }

    public function deleteShift(int $id): bool
    {
        $shift = \App\Domains\Production\Models\ProductionShift::findOrFail($id);
        return (bool) $shift->delete();
    }

    public function getDashboardWorkCenters(int $tenantId): Collection
    {
        $workCenters = WorkCenter::where('tenant_id', $tenantId)
            ->active()
            ->with(['machines'])
            ->orderBy('name')
            ->get();

        $wcIds = $workCenters->pluck('id')->toArray();

        $runningCounts = \App\Domains\Production\Models\ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereIn('work_center_id', $wcIds)
            ->where('status', \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_RUNNING)
            ->selectRaw('work_center_id, count(*) as count')
            ->groupBy('work_center_id')
            ->pluck('count', 'work_center_id');

        $waitingCounts = \App\Domains\Production\Models\ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereIn('work_center_id', $wcIds)
            ->where('status', \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_READY)
            ->selectRaw('work_center_id, count(*) as count')
            ->groupBy('work_center_id')
            ->pluck('count', 'work_center_id');

        $completedTodayCounts = \App\Domains\Production\Models\ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereIn('work_center_id', $wcIds)
            ->where('status', \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_COMPLETED)
            ->whereDate('actual_finish', today())
            ->selectRaw('work_center_id, count(*) as count')
            ->groupBy('work_center_id')
            ->pluck('count', 'work_center_id');

        $workCenters->each(function ($wc) use ($runningCounts, $waitingCounts, $completedTodayCounts) {
            $wc->runningCount   = $runningCounts->get($wc->id, 0);
            $wc->waitingCount   = $waitingCounts->get($wc->id, 0);
            $wc->completedToday = $completedTodayCounts->get($wc->id, 0);
        });

        return $workCenters;
    }
}
