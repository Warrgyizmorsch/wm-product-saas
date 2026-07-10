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
        return WorkCenter::with(['machines'])->find($id);
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
}
