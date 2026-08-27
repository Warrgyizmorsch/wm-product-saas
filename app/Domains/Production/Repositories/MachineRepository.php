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

    public function paginateAll(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
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

        return $query->orderBy('name')->paginate($perPage);
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

    public function getDashboardMachines(int $tenantId): Collection
    {
        $machines = Machine::where('tenant_id', $tenantId)
            ->whereIn('status', [Machine::STATUS_ACTIVE, Machine::STATUS_UNDER_MAINTENANCE])
            ->with(['workCenter', 'maintenanceWorkOrders' => function ($q) {
                $q->whereIn('status', ['draft', 'scheduled', 'in_progress'])->orderByDesc('created_at');
            }])
            ->orderBy('name')
            ->get();

        $machineIds = $machines->pluck('id')->toArray();

        $runningOps = \App\Domains\Production\Models\ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereIn('machine_id', $machineIds)
            ->where('status', \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_RUNNING)
            ->with(['schedule.order.product', 'orderOperation'])
            ->get()
            ->keyBy('machine_id');

        $machines->each(function ($machine) use ($runningOps) {
            $machine->currentOp = $runningOps->get($machine->id);
            $machine->activeMaintenanceWo = $machine->maintenanceWorkOrders->first();
        });

        return $machines;
    }

    public function getMachineDashboardDetails(int $machineId): array
    {
        $machine = Machine::with('workCenter')->findOrFail($machineId);

        $currentOp = \App\Domains\Production\Models\ProductionScheduleOperation::with(['schedule.order.product', 'orderOperation', 'workCenter'])
            ->where('machine_id', $machine->id)
            ->where('status', \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_RUNNING)
            ->first();

        $nextOp = \App\Domains\Production\Models\ProductionScheduleOperation::with(['schedule.order.product', 'orderOperation'])
            ->where('machine_id', $machine->id)
            ->where('status', \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_READY)
            ->orderBy('planned_start')
            ->first();

        $history = \App\Domains\Production\Models\ProductionScheduleOperation::with(['schedule.order.product', 'orderOperation'])
            ->where('machine_id', $machine->id)
            ->whereIn('status', [
                \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_COMPLETED,
                \App\Domains\Production\Models\ProductionScheduleOperation::STATUS_CANCELLED,
            ])
            ->orderByDesc('actual_finish')
            ->take(10)
            ->get();

        return compact('machine', 'currentOp', 'nextOp', 'history');
    }
}
