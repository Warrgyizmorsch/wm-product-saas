<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\WorkCenterSection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class WorkCenterSectionRepository implements WorkCenterSectionRepositoryInterface
{
    public function getAll(array $filters = []): Collection
    {
        $query = WorkCenterSection::query()
            ->with(['workCenter', 'shifts'])
            ->withCount('machines');

        if (!empty($filters['work_center_id'])) {
            $query->where('work_center_id', $filters['work_center_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get();
    }

    public function paginateAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = WorkCenterSection::query()
            ->with(['workCenter', 'shifts'])
            ->withCount('machines');

        if (!empty($filters['work_center_id'])) {
            $query->where('work_center_id', $filters['work_center_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function find(int $id): ?WorkCenterSection
    {
        return WorkCenterSection::with(['workCenter', 'machines', 'shifts'])->find($id);
    }

    public function create(array $data): WorkCenterSection
    {
        return WorkCenterSection::create($data);
    }

    public function update(int $id, array $data): WorkCenterSection
    {
        $section = WorkCenterSection::findOrFail($id);
        $section->update($data);
        return $section->fresh(['workCenter', 'machines', 'shifts']);
    }

    public function delete(int $id): bool
    {
        $section = WorkCenterSection::findOrFail($id);
        return (bool) $section->delete();
    }

    public function findByCode(string $code, int $workCenterId, int $tenantId, ?int $ignoreId = null): ?WorkCenterSection
    {
        $query = WorkCenterSection::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('work_center_id', $workCenterId)
            ->where('code', $code);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->first();
    }

    public function getByWorkCenter(int $workCenterId, bool $activeOnly = false): Collection
    {
        $query = WorkCenterSection::where('work_center_id', $workCenterId)
            ->with(['shifts'])
            ->withCount('machines');

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    public function syncShifts(WorkCenterSection $section, array $shiftIds, int $tenantId): void
    {
        $syncData = [];
        foreach ($shiftIds as $shiftId) {
            $syncData[$shiftId] = ['tenant_id' => $tenantId];
        }

        $section->shifts()->sync($syncData);
    }
}
