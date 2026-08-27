<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderSpare;
use App\Domains\Production\Models\ProductionPmSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MaintenanceRepository implements MaintenanceRepositoryInterface
{
    // ─── PM Schedules ─────────────────────────────────────────────────────────

    public function getPmSchedules(int $tenantId, array $filters = []): Collection
    {
        $query = ProductionPmSchedule::where('tenant_id', $tenantId)->with('machine');

        if (!empty($filters['machine_id'])) {
            $query->where('machine_id', $filters['machine_id']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('next_due_date')->get();
    }

    public function paginatePmSchedules(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionPmSchedule::where('tenant_id', $tenantId)->with('machine');

        if (!empty($filters['machine_id'])) {
            $query->where('machine_id', $filters['machine_id']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('next_due_date')->paginate($perPage);
    }

    public function findPmSchedule(int $id, int $tenantId): ?ProductionPmSchedule
    {
        return ProductionPmSchedule::where('tenant_id', $tenantId)->with('machine')->find($id);
    }

    public function createPmSchedule(array $data): ProductionPmSchedule
    {
        return ProductionPmSchedule::create($data);
    }

    public function updatePmSchedule(int $id, int $tenantId, array $data): ProductionPmSchedule
    {
        $schedule = ProductionPmSchedule::where('tenant_id', $tenantId)->findOrFail($id);
        $schedule->update($data);
        return $schedule->fresh(['machine']);
    }

    public function deletePmSchedule(int $id, int $tenantId): bool
    {
        $schedule = ProductionPmSchedule::where('tenant_id', $tenantId)->findOrFail($id);
        return (bool) $schedule->delete();
    }

    public function getDuePmSchedules(int $tenantId, string $dateThreshold): Collection
    {
        return ProductionPmSchedule::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereDate('next_due_date', '<=', $dateThreshold)
            ->with('machine')
            ->get();
    }

    // ─── Maintenance Work Orders ──────────────────────────────────────────────

    public function getWorkOrders(int $tenantId, array $filters = []): Collection
    {
        $query = ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)
            ->with(['machine', 'pmSchedule', 'technician', 'downtime']);

        if (!empty($filters['machine_id'])) {
            $query->where('machine_id', $filters['machine_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['assigned_technician_id'])) {
            $query->where('assigned_technician_id', $filters['assigned_technician_id']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('work_order_number', 'like', "%{$search}%")
                  ->orWhere('problem_description', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function paginateWorkOrders(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)
            ->with(['machine', 'pmSchedule', 'technician', 'downtime']);

        if (!empty($filters['machine_id'])) {
            $query->where('machine_id', $filters['machine_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['assigned_technician_id'])) {
            $query->where('assigned_technician_id', $filters['assigned_technician_id']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('work_order_number', 'like', "%{$search}%")
                  ->orWhere('problem_description', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function findWorkOrder(int $id, int $tenantId): ?ProductionMaintenanceWorkOrder
    {
        return ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)
            ->with(['machine', 'pmSchedule', 'technician', 'downtime', 'spares.product', 'spares.warehouse', 'creator', 'completer'])
            ->find($id);
    }

    public function findWorkOrderForLock(int $id, int $tenantId): ?ProductionMaintenanceWorkOrder
    {
        return ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)
            ->with(['machine', 'spares'])
            ->lockForUpdate()
            ->find($id);
    }

    public function createWorkOrder(array $data): ProductionMaintenanceWorkOrder
    {
        return ProductionMaintenanceWorkOrder::create($data);
    }

    public function updateWorkOrder(int $id, int $tenantId, array $data): ProductionMaintenanceWorkOrder
    {
        $wo = ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)->findOrFail($id);
        $wo->update($data);
        return $wo->fresh(['machine', 'pmSchedule', 'technician', 'downtime', 'spares.product', 'spares.warehouse']);
    }

    public function getMachineWorkOrderHistory(int $machineId, int $tenantId, int $limit = 20): Collection
    {
        return ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)
            ->where('machine_id', $machineId)
            ->with(['technician', 'spares.product'])
            ->orderByDesc('created_at')
            ->take($limit)
            ->get();
    }

    public function getMachineTotalMaintenanceCost(int $machineId, int $tenantId): float
    {
        return (float) ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)
            ->where('machine_id', $machineId)
            ->where('status', ProductionMaintenanceWorkOrder::STATUS_COMPLETED)
            ->sum('total_cost');
    }

    // ─── Spares ───────────────────────────────────────────────────────────────

    public function addWorkOrderSpare(array $data): ProductionMaintenanceWorkOrderSpare
    {
        return ProductionMaintenanceWorkOrderSpare::create($data);
    }

    public function findWorkOrderSpare(int $spareId, int $tenantId): ?ProductionMaintenanceWorkOrderSpare
    {
        return ProductionMaintenanceWorkOrderSpare::where('tenant_id', $tenantId)
            ->with(['product', 'warehouse', 'workOrder'])
            ->find($spareId);
    }

    public function updateWorkOrderSpare(int $spareId, int $tenantId, array $data): ProductionMaintenanceWorkOrderSpare
    {
        $spare = ProductionMaintenanceWorkOrderSpare::where('tenant_id', $tenantId)->findOrFail($spareId);
        $spare->update($data);
        return $spare->fresh(['product', 'warehouse']);
    }
}
