<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Centralized notification dispatcher for Production domain business events.
 *
 * Enforces strict multi-tenant recipient resolution, deduplication, and non-blocking
 * error resilience to ensure production business transactions are never aborted by
 * secondary notification failures.
 */
class ProductionNotificationService
{
    public const TYPE_ANDON_ALERT = 'production_andon_alert';
    public const TYPE_OPERATOR_ASSIGNMENT = 'production_operator_assignment';
    public const TYPE_QUALITY_HOLD = 'production_quality_hold';
    public const TYPE_NCR_CREATED = 'production_ncr_created';
    public const TYPE_MACHINE_BREAKDOWN = 'production_machine_breakdown';
    public const TYPE_MAINTENANCE_SCHEDULED = 'production_maintenance_scheduled';
    public const TYPE_SCHEDULE_RELEASED = 'production_schedule_released';
    public const TYPE_MATERIAL_REQUESTED = 'production_material_requested';
    public const TYPE_SUBCONTRACT_DISPATCHED = 'production_subcontract_challan_dispatched';

    /**
     * 1. MES Andon Alert Notification.
     */
    public function notifyAndonAlert(
        ProductionScheduleOperation $operation,
        string $category,
        string $severity,
        string $reason
    ): void {
        try {
            $tenantId = $operation->schedule?->tenant_id ?? (int) ($operation->tenant_id ?? 1);

            // Deduplication: prevent duplicate notifications for an already-open unread alert on the same schedule operation
            $alreadyNotified = Notification::query()
                ->where('tenant_id', $tenantId)
                ->where('type', self::TYPE_ANDON_ALERT)
                ->where('data->schedule_operation_id', $operation->id)
                ->whereNull('read_at')
                ->where('created_at', '>=', now()->subMinutes(30))
                ->exists();

            if ($alreadyNotified) {
                return;
            }

            $recipientIds = $this->resolveTenantRoleRecipients($tenantId, [
                'production_supervisor',
                'production_manager',
                'plant_head',
            ]);

            if (empty($recipientIds)) {
                return;
            }

            $seq = $operation->sequence ?? $operation->id;
            $title = 'Production Andon Alert';
            $message = "An Andon alert ({$category} - {$severity}) has been raised for sequence #{$seq}: {$reason}.";
            $actionUrl = route('production.mes.dashboard');
            $icon = 'feather-alert-triangle';
            $data = [
                'schedule_operation_id' => $operation->id,
                'category' => $category,
                'severity' => $severity,
                'reason' => $reason,
            ];

            $this->dispatchToUsers($tenantId, $recipientIds, self::TYPE_ANDON_ALERT, $title, $message, $actionUrl, $icon, $data);
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch Production Andon Alert notification', [
                'operation_id' => $operation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 2. Operator Assignment Notification.
     */
    public function notifyOperatorAssignment(
        int $operatorUserId,
        ProductionOrderOperation $operation,
        int $assignedByUserId
    ): void {
        try {
            $tenantId = (int) ($operation->tenant_id ?? $operation->order?->tenant_id ?? 1);
            $validUserId = $this->resolveDirectRecipient($tenantId, $operatorUserId);

            if (!$validUserId) {
                return;
            }

            // Deduplication: do not send if the operator already has an unread assignment alert for this exact operation
            $alreadyNotified = Notification::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $validUserId)
                ->where('type', self::TYPE_OPERATOR_ASSIGNMENT)
                ->where('data->operation_id', $operation->id)
                ->whereNull('read_at')
                ->exists();

            if ($alreadyNotified) {
                return;
            }

            $opNum = $operation->operation_number ?? "OP-{$operation->id}";
            $orderNum = $operation->order?->order_number ?? "PO-{$operation->production_order_id}";
            $title = 'Operation Assigned';
            $message = "You have been assigned to operation [{$opNum}] for Production Order [{$orderNum}].";
            $actionUrl = route('production.mes.operator.execution', [
                'op' => $operation->id,
                'order_operation_id' => $operation->id,
            ]);
            $icon = 'feather-user-check';
            $data = [
                'operation_id' => $operation->id,
                'order_id' => $operation->production_order_id,
                'assigned_by' => $assignedByUserId,
            ];

            $this->dispatchToUsers($tenantId, [$validUserId], self::TYPE_OPERATOR_ASSIGNMENT, $title, $message, $actionUrl, $icon, $data);
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch Operator Assignment notification', [
                'operation_id' => $operation->id,
                'operator_id' => $operatorUserId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 3. Quality Hold Notification.
     */
    public function notifyQualityHold(
        ProductionOrderOperation $operation,
        string $reason,
        ?int $ncrId = null
    ): void {
        try {
            $tenantId = (int) ($operation->tenant_id ?? $operation->order?->tenant_id ?? 1);

            // Deduplication: prevent repeat notifications if this operation has an active unread quality hold notification
            $alreadyNotified = Notification::query()
                ->where('tenant_id', $tenantId)
                ->where('type', self::TYPE_QUALITY_HOLD)
                ->where('data->operation_id', $operation->id)
                ->whereNull('read_at')
                ->where('created_at', '>=', now()->subMinutes(30))
                ->exists();

            if ($alreadyNotified) {
                return;
            }

            $recipientIds = $this->resolveTenantRoleRecipients($tenantId, [
                'quality_manager',
                'production_supervisor',
                'quality_inspector',
            ]);

            if (empty($recipientIds)) {
                return;
            }

            $opNum = $operation->operation_number ?? "OP-{$operation->id}";
            $title = 'Production Quality Hold';
            $message = "Production operation [{$opNum}] has been placed on quality hold. Reason: {$reason}.";
            $actionUrl = \Illuminate\Support\Facades\Route::has('production.quality.inspections.index')
                ? route('production.quality.inspections.index')
                : (\Illuminate\Support\Facades\Route::has('production.inspections.index')
                    ? route('production.inspections.index')
                    : url('/production/quality/inspections'));
            $icon = 'feather-alert-triangle';
            $data = [
                'operation_id' => $operation->id,
                'order_id' => $operation->production_order_id,
                'reason' => $reason,
                'ncr_id' => $ncrId,
            ];

            $this->dispatchToUsers($tenantId, $recipientIds, self::TYPE_QUALITY_HOLD, $title, $message, $actionUrl, $icon, $data);
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch Quality Hold notification', [
                'operation_id' => $operation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 4. NCR Created Notification.
     */
    public function notifyNcrCreated(
        ProductionNcr $ncr
    ): void {
        try {
            $tenantId = (int) $ncr->tenant_id;

            // Deduplication: Exactly 1 notification per NCR record
            $alreadyNotified = Notification::query()
                ->where('tenant_id', $tenantId)
                ->where('type', self::TYPE_NCR_CREATED)
                ->where('data->ncr_id', $ncr->id)
                ->exists();

            if ($alreadyNotified) {
                return;
            }

            $recipientIds = $this->resolveTenantRoleRecipients($tenantId, [
                'quality_manager',
                'quality_inspector',
                'production_manager',
            ]);

            if (empty($recipientIds)) {
                return;
            }

            $title = 'Production NCR Created';
            $contextDesc = $ncr->ncr_number ?: "NCR-{$ncr->id}";
            $message = "A new Non-Conformance Report [{$contextDesc}] has been logged: {$ncr->description}.";
            $actionUrl = \Illuminate\Support\Facades\Route::has('production.quality.ncrs.show')
                ? route('production.quality.ncrs.show', ['ncr' => $ncr->id])
                : (\Illuminate\Support\Facades\Route::has('production.ncrs.show')
                    ? route('production.ncrs.show', ['ncr' => $ncr->id])
                    : url("/production/quality/ncrs/{$ncr->id}"));
            $icon = 'feather-alert-triangle';
            $data = [
                'ncr_id' => $ncr->id,
                'ncr_number' => $ncr->ncr_number,
                'order_id' => $ncr->production_order_id,
                'operation_id' => $ncr->production_order_operation_id,
            ];

            $this->dispatchToUsers($tenantId, $recipientIds, self::TYPE_NCR_CREATED, $title, $message, $actionUrl, $icon, $data);
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch NCR Created notification', [
                'ncr_id' => $ncr->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 5. Machine Breakdown Notification.
     */
    public function notifyMachineBreakdown(
        Machine $machine,
        string $reason,
        ProductionMaintenanceWorkOrder $workOrder
    ): void {
        try {
            $tenantId = (int) ($machine->tenant_id ?? $workOrder->tenant_id);

            // Deduplication: prevent duplicate notifications if an active breakdown alert for this machine is still unread
            $alreadyNotified = Notification::query()
                ->where('tenant_id', $tenantId)
                ->where('type', self::TYPE_MACHINE_BREAKDOWN)
                ->where('data->machine_id', $machine->id)
                ->whereNull('read_at')
                ->where('created_at', '>=', now()->subHours(1))
                ->exists();

            if ($alreadyNotified) {
                return;
            }

            $recipientIds = $this->resolveTenantRoleRecipients($tenantId, [
                'maintenance_engineer',
                'maintenance_technician',
                'plant_head',
            ]);

            if (empty($recipientIds)) {
                return;
            }

            $title = 'Machine Breakdown';
            $machineName = $machine->name ?? "Machine #{$machine->id}";
            $message = "Machine [{$machineName}] has been reported as broken down. Reason: {$reason}.";
            $actionUrl = route('production.maintenance.dashboard');
            $icon = 'feather-tool';
            $data = [
                'machine_id' => $machine->id,
                'work_order_id' => $workOrder->id,
                'work_order_number' => $workOrder->work_order_number,
                'reason' => $reason,
            ];

            $this->dispatchToUsers($tenantId, $recipientIds, self::TYPE_MACHINE_BREAKDOWN, $title, $message, $actionUrl, $icon, $data);
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch Machine Breakdown notification', [
                'machine_id' => $machine->id,
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 6. Maintenance Scheduled Notification.
     */
    public function notifyMaintenanceScheduled(
        ProductionMaintenanceWorkOrder $workOrder
    ): void {
        try {
            $tenantId = (int) $workOrder->tenant_id;
            $technicianId = $workOrder->assigned_technician_id;

            $validUserId = $this->resolveDirectRecipient($tenantId, $technicianId);
            if (!$validUserId) {
                return;
            }

            // Deduplication: do not send repeat notifications for the same work order to the same technician
            $alreadyNotified = Notification::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $validUserId)
                ->where('type', self::TYPE_MAINTENANCE_SCHEDULED)
                ->where('data->work_order_id', $workOrder->id)
                ->whereNull('read_at')
                ->exists();

            if ($alreadyNotified) {
                return;
            }

            $woNumber = $workOrder->work_order_number ?? "WO-{$workOrder->id}";
            $machineName = $workOrder->machine?->name ?? "Machine #{$workOrder->machine_id}";
            $startStr = $workOrder->planned_start ? date('M d, Y H:i', strtotime((string) $workOrder->planned_start)) : 'Upcoming';
            $title = 'Maintenance Scheduled';
            $message = "Maintenance work order [{$woNumber}] has been scheduled for [{$machineName}] starting {$startStr}.";
            $actionUrl = route('production.maintenance.work-orders.show', ['work_order' => $workOrder->id]);
            $icon = 'feather-calendar';
            $data = [
                'work_order_id' => $workOrder->id,
                'machine_id' => $workOrder->machine_id,
                'planned_start' => (string) $workOrder->planned_start,
            ];

            $this->dispatchToUsers($tenantId, [$validUserId], self::TYPE_MAINTENANCE_SCHEDULED, $title, $message, $actionUrl, $icon, $data);
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch Maintenance Scheduled notification', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 7. Production Schedule Released Notification.
     */
    public function notifyScheduleReleased(
        ProductionSchedule $schedule
    ): void {
        try {
            $tenantId = (int) $schedule->tenant_id;

            // Deduplication: exactly 1 release notification per schedule
            $alreadyNotified = Notification::query()
                ->where('tenant_id', $tenantId)
                ->where('type', self::TYPE_SCHEDULE_RELEASED)
                ->where('data->schedule_id', $schedule->id)
                ->exists();

            if ($alreadyNotified) {
                return;
            }

            $recipientIds = $this->resolveTenantRoleRecipients($tenantId, [
                'production_supervisor',
                'mes_operator',
                'production_manager',
            ]);

            if (empty($recipientIds)) {
                return;
            }

            $schedNum = $schedule->schedule_number ?? "SCHED-{$schedule->id}";
            $title = 'Production Schedule Released';
            $message = "Production schedule [{$schedNum}] has been released for execution.";
            $actionUrl = route('production.schedules.show', ['schedule' => $schedule->id]);
            $icon = 'feather-layers';
            $data = [
                'schedule_id' => $schedule->id,
                'production_order_id' => $schedule->production_order_id,
            ];

            $this->dispatchToUsers($tenantId, $recipientIds, self::TYPE_SCHEDULE_RELEASED, $title, $message, $actionUrl, $icon, $data);
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch Production Schedule Released notification', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 8. Additional Material Requested Notification.
     */
    public function notifyAdditionalMaterialRequested(
        ProductionOrder $order,
        float $quantity,
        string $reason,
        int $requestedByUserId
    ): void {
        try {
            $tenantId = (int) $order->tenant_id;

            $recipientIds = $this->resolveTenantRoleRecipients($tenantId, [
                'store_manager',
                'inventory_manager',
                'production_planner',
            ]);

            if (empty($recipientIds)) {
                return;
            }

            $orderNum = $order->order_number ?? "PO-{$order->id}";
            $title = 'Additional Material Requested';
            $qtyFormatted = number_format($quantity, 2);
            $message = "Additional material has been requested for Production Order [{$orderNum}]. Quantity: {$qtyFormatted}. Reason: {$reason}.";
            $actionUrl = route('production.orders.show', ['order' => $order->id]);
            $icon = 'feather-package';
            $data = [
                'order_id' => $order->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'requested_by' => $requestedByUserId,
            ];

            $this->dispatchToUsers($tenantId, $recipientIds, self::TYPE_MATERIAL_REQUESTED, $title, $message, $actionUrl, $icon, $data);
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch Additional Material Requested notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 9. Subcontract Delivery Challan Dispatched Notification.
     */
    public function notifySubcontractChallanDispatched(
        DeliveryChallan $challan
    ): void {
        try {
            $tenantId = (int) $challan->tenant_id;

            // Deduplication: dispatch event notification exactly once per challan
            $alreadyNotified = Notification::query()
                ->where('tenant_id', $tenantId)
                ->where('type', self::TYPE_SUBCONTRACT_DISPATCHED)
                ->where('data->challan_id', $challan->id)
                ->exists();

            if ($alreadyNotified) {
                return;
            }

            $recipientIds = $this->resolveTenantRoleRecipients($tenantId, [
                'subcontract_manager',
                'purchase_manager',
                'store_manager',
            ]);

            if (empty($recipientIds)) {
                return;
            }

            $challanNum = $challan->challan_number ?? "DC-{$challan->id}";
            $title = 'Subcontract Challan Dispatched';
            $message = "Subcontract delivery challan [{$challanNum}] has been dispatched.";
            $actionUrl = route('production.subcontract.delivery-challans.show', ['challan' => $challan->id]);
            $icon = 'feather-truck';
            $data = [
                'challan_id' => $challan->id,
                'challan_number' => $challan->challan_number,
                'vendor_id' => $challan->vendor_id,
            ];

            $this->dispatchToUsers($tenantId, $recipientIds, self::TYPE_SUBCONTRACT_DISPATCHED, $title, $message, $actionUrl, $icon, $data);
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch Subcontract Challan Dispatched notification', [
                'challan_id' => $challan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─── Protected Multi-Tenant Recipient Resolution Helpers ───────────────────

    /**
     * Verify that a direct recipient belongs strictly to the expected tenant.
     */
    public function resolveDirectRecipient(int $tenantId, ?int $userId): ?int
    {
        if (!$userId) {
            return null;
        }

        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->value('id');
    }

    /**
     * Resolve user IDs for a given tenant matching specific roles or tenant admin fallback.
     *
     * SECURITY RULE: Never query users globally. Never fall back to unscoped queries.
     *
     * @param int $tenantId
     * @param string[] $roles
     * @return int[]
     */
    public function resolveTenantRoleRecipients(int $tenantId, array $roles): array
    {
        $userIds = User::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($roles, $tenantId) {
                foreach ($roles as $role) {
                    $slug = strtolower(str_replace(' ', '_', $role));
                    $name = strtolower($role);
                    $query->orWhere('role', 'like', "%{$role}%")
                          ->orWhere('role', 'like', "%{$slug}%")
                          ->orWhereHas('primaryRole', function ($rq) use ($slug, $name, $tenantId) {
                              $rq->where(function ($sub) use ($slug, $name) {
                                  $sub->where('roles.slug', $slug)
                                      ->orWhere('roles.name', 'like', "%{$name}%");
                              })->where(function ($sub) use ($tenantId) {
                                  $sub->where('roles.tenant_id', $tenantId)->orWhere('roles.is_system', true);
                              });
                          })
                          ->orWhereHas('roles', function ($rq) use ($slug, $name, $tenantId) {
                              $rq->where(function ($sub) use ($slug, $name) {
                                  $sub->where('roles.slug', $slug)
                                      ->orWhere('roles.name', 'like', "%{$name}%");
                              })->where(function ($sub) use ($tenantId) {
                                  $sub->where('roles.tenant_id', $tenantId)->orWhere('roles.is_system', true);
                              });
                          });
                }
            })
            ->pluck('id')
            ->all();

        // Strict fallback ONLY to this tenant's admin / owner
        if (empty($userIds)) {
            $userIds = User::query()
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($tenantId) {
                    $query->whereIn('role', ['admin', 'tenant_owner', 'super_admin'])
                          ->orWhereHas('primaryRole', function ($rq) use ($tenantId) {
                              $rq->whereIn('roles.slug', ['admin', 'tenant_owner', 'super_admin'])
                                 ->where(function ($sub) use ($tenantId) {
                                     $sub->where('roles.tenant_id', $tenantId)->orWhere('roles.is_system', true);
                                 });
                          })
                          ->orWhereHas('roles', function ($rq) use ($tenantId) {
                              $rq->whereIn('roles.slug', ['admin', 'tenant_owner', 'super_admin'])
                                 ->where(function ($sub) use ($tenantId) {
                                     $sub->where('roles.tenant_id', $tenantId)->orWhere('roles.is_system', true);
                                 });
                          });
                })
                ->pluck('id')
                ->all();
        }

        // Final safety net: first active user of THIS tenant only
        if (empty($userIds)) {
            $fallbackUser = User::query()->where('tenant_id', $tenantId)->first();
            if ($fallbackUser) {
                $userIds = [$fallbackUser->id];
            }
        }

        return array_values(array_unique(array_filter($userIds)));
    }

    /**
     * Dispatch notification records directly into the centralized notifications table.
     *
     * @param int $tenantId
     * @param int[] $userIds
     * @param string $type
     * @param string $title
     * @param string $message
     * @param string $actionUrl
     * @param string $icon
     * @param array $data
     */
    protected function dispatchToUsers(
        int $tenantId,
        array $userIds,
        string $type,
        string $title,
        string $message,
        string $actionUrl,
        string $icon,
        array $data = []
    ): void {
        $uniqueUserIds = array_unique(array_filter($userIds));

        foreach ($uniqueUserIds as $userId) {
            Notification::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'module' => 'production',
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
                'icon_class' => $icon,
                'data' => $data,
            ]);
        }
    }
}
