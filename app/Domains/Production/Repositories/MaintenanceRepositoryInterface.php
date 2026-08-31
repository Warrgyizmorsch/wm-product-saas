<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderSpare;
use App\Domains\Production\Models\ProductionPmSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MaintenanceRepositoryInterface
{
    // PM Schedules
    public function getPmSchedules(int $tenantId, array $filters = []): Collection;
    public function paginatePmSchedules(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function findPmSchedule(int $id, int $tenantId): ?ProductionPmSchedule;
    public function createPmSchedule(array $data): ProductionPmSchedule;
    public function updatePmSchedule(int $id, int $tenantId, array $data): ProductionPmSchedule;
    public function deletePmSchedule(int $id, int $tenantId): bool;
    public function getDuePmSchedules(int $tenantId, string $dateThreshold): Collection;

    // Maintenance Work Orders
    public function getWorkOrders(int $tenantId, array $filters = []): Collection;
    public function paginateWorkOrders(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function findWorkOrder(int $id, int $tenantId): ?ProductionMaintenanceWorkOrder;
    public function findWorkOrderForLock(int $id, int $tenantId): ?ProductionMaintenanceWorkOrder;
    public function createWorkOrder(array $data): ProductionMaintenanceWorkOrder;
    public function updateWorkOrder(int $id, int $tenantId, array $data): ProductionMaintenanceWorkOrder;
    public function getMachineWorkOrderHistory(int $machineId, int $tenantId, int $limit = 20): Collection;
    public function getMachineTotalMaintenanceCost(int $machineId, int $tenantId): float;

    // Spares
    public function addWorkOrderSpare(array $data): ProductionMaintenanceWorkOrderSpare;
    public function findWorkOrderSpare(int $spareId, int $tenantId): ?ProductionMaintenanceWorkOrderSpare;
    public function updateWorkOrderSpare(int $spareId, int $tenantId, array $data): ProductionMaintenanceWorkOrderSpare;
}
