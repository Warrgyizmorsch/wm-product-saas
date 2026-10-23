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
            ->with(['workCenter'])
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
            ->with(['workCenter'])
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
        return WorkCenterSection::with(['workCenter', 'machines'])->find($id);
    }

    public function create(array $data): WorkCenterSection
    {
        return WorkCenterSection::create($data);
    }

    public function update(int $id, array $data): WorkCenterSection
    {
        $section = WorkCenterSection::findOrFail($id);
        $section->update($data);
        return $section->fresh(['workCenter', 'machines']);
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
            ->withCount('machines');

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    public function syncShifts(WorkCenterSection $section, array $shiftIds, ?int $tenantId = null, array $shiftActive = []): void
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $shiftIds))));
        $section->update(['assigned_shifts' => $normalizedIds]);
        $section->unsetRelation('shifts');
    }

    public function syncMachines(WorkCenterSection $section, array $machineIds, int $tenantId): void
    {
        $currentMachineIds = \App\Domains\Production\Models\Machine::where('tenant_id', $tenantId)
            ->where('section_id', $section->id)
            ->pluck('id')
            ->toArray();

        $toRemove = array_diff($currentMachineIds, $machineIds);
        $toAdd    = array_diff($machineIds, $currentMachineIds);

        if (!empty($toRemove)) {
            \App\Domains\Production\Models\Machine::where('tenant_id', $tenantId)
                ->whereIn('id', $toRemove)
                ->update([
                    'section_id' => null,
                ]);
        }

        if (!empty($toAdd)) {
            \App\Domains\Production\Models\Machine::where('tenant_id', $tenantId)
                ->whereIn('id', $toAdd)
                ->update([
                    'section_id' => $section->id,
                ]);
        }
    }
}
