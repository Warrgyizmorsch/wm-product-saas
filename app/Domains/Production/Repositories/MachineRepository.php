<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\Machine;
use Illuminate\Database\Eloquent\Collection;

class MachineRepository implements MachineRepositoryInterface
{
    public function getAll(array $filters = []): Collection
    {
        $query = Machine::query()->with('workCenter');

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
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('machine_type', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get();
    }

    public function find(int $id): ?Machine
    {
        return Machine::with(['workCenter'])->find($id);
    }

    public function create(array $data): Machine
    {
        return Machine::create($data);
    }

    public function update(int $id, array $data): Machine
    {
        $machine = Machine::findOrFail($id);
        $machine->update($data);
        return $machine->fresh();
    }

    public function delete(int $id): bool
    {
        $machine = Machine::findOrFail($id);
        return (bool) $machine->delete(); // SoftDeletes
    }

    public function getByWorkCenter(int $workCenterId, bool $activeOnly = false): Collection
    {
        $query = Machine::where('work_center_id', $workCenterId);

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    public function findByCode(string $code, int $tenantId, ?int $ignoreId = null): ?Machine
    {
        $query = Machine::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->first();
    }
}
