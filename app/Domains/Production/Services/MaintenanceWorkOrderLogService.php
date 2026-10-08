<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderLog;
use Illuminate\Support\Facades\DB;

class MaintenanceWorkOrderLogService
{
    public function recordEvent(
        int $tenantId,
        string $eventType,
        string $summary,
        array $details = [],
        ?ProductionMaintenanceWorkOrder $workOrder = null,
        ?int $downtimeId = null,
        ?int $machineId = null,
        ?int $userId = null,
        string $source = 'MaintenanceWorkOrderLogService',
        string $status = 'logged'
    ): ProductionMaintenanceWorkOrderLog {
        return DB::transaction(function () use ($tenantId, $eventType, $summary, $details, $workOrder, $downtimeId, $machineId, $userId, $source, $status) {
            return ProductionMaintenanceWorkOrderLog::create([
                'tenant_id'   => $tenantId,
                'work_order_id' => $workOrder?->id,
                'downtime_id' => $downtimeId ?? $workOrder?->downtime_id,
                'machine_id'  => $machineId ?? $workOrder?->machine_id,
                'user_id'     => $userId,
                'event_type'  => $eventType,
                'summary'     => $summary,
                'details'     => $details,
                'source'      => $source,
                'status'      => $status,
                'logged_at'   => now(),
            ]);
        });
    }

    public function recordWorkOrderCreated(ProductionMaintenanceWorkOrder $workOrder, ?int $userId = null): ProductionMaintenanceWorkOrderLog
    {
        return $this->recordEvent(
            $workOrder->tenant_id,
            'Work Order Created',
            "Maintenance Work Order [{$workOrder->work_order_number}] created.",
            [
                'work_order_number' => $workOrder->work_order_number,
                'type' => $workOrder->type,
                'priority' => $workOrder->priority,
                'machine_id' => $workOrder->machine_id,
            ],
            $workOrder,
            $workOrder->downtime_id,
            $workOrder->machine_id,
            $userId,
            'MaintenanceWorkOrderService'
        );
    }

    public function recordWorkOrderScheduled(ProductionMaintenanceWorkOrder $workOrder, ?int $userId = null, array $details = []): ProductionMaintenanceWorkOrderLog
    {
        return $this->recordEvent(
            $workOrder->tenant_id,
            'Work Order Scheduled',
            "Maintenance Work Order [{$workOrder->work_order_number}] scheduled.",
            array_merge([
                'work_order_number' => $workOrder->work_order_number,
                'machine_id' => $workOrder->machine_id,
                'planned_start' => $workOrder->planned_start?->toDateTimeString(),
                'planned_end' => $workOrder->planned_end?->toDateTimeString(),
            ], $details),
            $workOrder,
            $workOrder->downtime_id,
            $workOrder->machine_id,
            $userId,
            'MaintenanceWorkOrderService'
        );
    }

    public function recordWorkOrderRescheduled(ProductionMaintenanceWorkOrder $workOrder, ?int $userId = null, array $details = []): ProductionMaintenanceWorkOrderLog
    {
        return $this->recordEvent(
            $workOrder->tenant_id,
            'Work Order Rescheduled',
            "Maintenance Work Order [{$workOrder->work_order_number}] rescheduled.",
            array_merge([
                'work_order_number' => $workOrder->work_order_number,
                'machine_id' => $workOrder->machine_id,
                'planned_start' => $workOrder->planned_start?->toDateTimeString(),
                'planned_end' => $workOrder->planned_end?->toDateTimeString(),
            ], $details),
            $workOrder,
            $workOrder->downtime_id,
            $workOrder->machine_id,
            $userId,
            'MaintenanceWorkOrderService'
        );
    }

    public function recordAssignmentCreated(ProductionMaintenanceWorkOrder $workOrder, array $assignmentData, ?int $userId = null): ProductionMaintenanceWorkOrderLog
    {
        return $this->recordEvent(
            $workOrder->tenant_id,
            'Assignment Added',
            "Assignment added to work order [{$workOrder->work_order_number}].",
            array_merge([
                'work_order_number' => $workOrder->work_order_number,
                'machine_id' => $workOrder->machine_id,
            ], $assignmentData),
            $workOrder,
            $workOrder->downtime_id,
            $workOrder->machine_id,
            $userId,
            'MaintenanceWorkOrderService'
        );
    }

    public function recordMachineDowntimeStarted(ProductionMaintenanceWorkOrder $workOrder, int $downtimeId, ?int $userId = null, array $details = []): ProductionMaintenanceWorkOrderLog
    {
        return $this->recordEvent(
            $workOrder->tenant_id,
            'Machine DT Started',
            "Machine downtime started for work order [{$workOrder->work_order_number}].",
            array_merge([
                'downtime_id' => $downtimeId,
                'machine_id' => $workOrder->machine_id,
                'work_order_number' => $workOrder->work_order_number,
            ], $details),
            $workOrder,
            $downtimeId,
            $workOrder->machine_id,
            $userId,
            'MaintenanceWorkOrderService'
        );
    }

    public function recordMaintenanceStarted(ProductionMaintenanceWorkOrder $workOrder, ?int $userId = null, array $details = []): ProductionMaintenanceWorkOrderLog
    {
        return $this->recordEvent(
            $workOrder->tenant_id,
            'Maintenance Started',
            "Maintenance started for work order [{$workOrder->work_order_number}].",
            array_merge([
                'machine_id' => $workOrder->machine_id,
                'work_order_number' => $workOrder->work_order_number,
            ], $details),
            $workOrder,
            $workOrder->downtime_id,
            $workOrder->machine_id,
            $userId,
            'MaintenanceWorkOrderService'
        );
    }

    public function recordWorkOrderCompleted(ProductionMaintenanceWorkOrder $workOrder, ?int $userId = null, array $details = []): ProductionMaintenanceWorkOrderLog
    {
        return $this->recordEvent(
            $workOrder->tenant_id,
            'Work Order Completed',
            "Maintenance work order [{$workOrder->work_order_number}] completed.",
            array_merge([
                'work_order_number' => $workOrder->work_order_number,
                'machine_id' => $workOrder->machine_id,
            ], $details),
            $workOrder,
            $workOrder->downtime_id,
            $workOrder->machine_id,
            $userId,
            'MaintenanceWorkOrderService'
        );
    }

    public function recordWorkOrderCancelled(ProductionMaintenanceWorkOrder $workOrder, ?int $userId = null, array $details = []): ProductionMaintenanceWorkOrderLog
    {
        return $this->recordEvent(
            $workOrder->tenant_id,
            'Work Order Cancelled',
            "Maintenance work order [{$workOrder->work_order_number}] cancelled.",
            array_merge([
                'work_order_number' => $workOrder->work_order_number,
                'machine_id' => $workOrder->machine_id,
            ], $details),
            $workOrder,
            $workOrder->downtime_id,
            $workOrder->machine_id,
            $userId,
            'MaintenanceWorkOrderService'
        );
    }

    public function recordManualLog(ProductionMaintenanceWorkOrder $workOrder, ?int $userId = null, string $action = 'Manual Maintenance Log', string $notes = ''): ProductionMaintenanceWorkOrderLog
    {
        return $this->recordEvent(
            $workOrder->tenant_id,
            'manual_log',
            $action,
            [
                'notes' => $notes,
                'action' => $action,
            ],
            $workOrder,
            $workOrder->downtime_id,
            $workOrder->machine_id,
            $userId,
            'MaintenanceWorkOrderController'
        );
    }

    public function recordSpareRequested(ProductionMaintenanceWorkOrder $workOrder, int $productId, int $warehouseId, float $requestedQty, ?int $userId = null): ProductionMaintenanceWorkOrderLog
    {
        return $this->recordEvent(
            $workOrder->tenant_id,
            'Spare Part Requested',
            "Spare part requested for work order [{$workOrder->work_order_number}].",
            [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'requested_qty' => $requestedQty,
            ],
            $workOrder,
            $workOrder->downtime_id,
            $workOrder->machine_id,
            $userId,
            'MaintenanceSpareService'
        );
    }

    public function recordSpareIssued(ProductionMaintenanceWorkOrder $workOrder, int $productId, int $warehouseId, float $issuedQty, float $totalCost, ?int $userId = null): ProductionMaintenanceWorkOrderLog
    {
        return $this->recordEvent(
            $workOrder->tenant_id,
            'Spare Part Issued',
            "Spare part issued for work order [{$workOrder->work_order_number}].",
            [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'issued_qty' => $issuedQty,
                'total_cost' => $totalCost,
            ],
            $workOrder,
            $workOrder->downtime_id,
            $workOrder->machine_id,
            $userId,
            'MaintenanceSpareService'
        );
    }
}
