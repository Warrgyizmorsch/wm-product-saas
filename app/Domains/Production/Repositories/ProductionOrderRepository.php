<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductionOrderRepository implements ProductionOrderRepositoryInterface
{
    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionOrder::with(['product', 'bom', 'routing']);

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', $search)
                    ->orWhereHas('product', function ($p) use ($search) {
                        $p->where('name', 'like', $search)->orWhere('sku', 'like', $search);
                    });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['production_mode'])) {
            $query->where('production_mode', $filters['production_mode']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('start_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('end_date', '<=', $filters['end_date']);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function getStatusCounts(): array
    {
        return ProductionOrder::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    public function getSubcontractDashboardMetrics(int $tenantId): array
    {
        $awaitingPr = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('is_external', true)
            ->where('status', 'ready')
            ->count();

        $atVendor = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('is_external', true)
            ->whereIn('status', ['in_process', 'sent_to_vendor'])
            ->count();

        $vendorDelayed = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('is_external', true)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('actual_start_time')
            ->whereDate('actual_start_time', '<', now()->subDays(3))
            ->count();

        $qcPending = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('is_external', true)
            ->where('status', 'subcontract_qc_pending')
            ->count();

        $vendorRework = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->whereHas('operation', function ($q) {
                $q->where('is_external', true);
            })
            ->count();

        return [
            'awaiting_subcontract_pr' => $awaitingPr,
            'at_vendor' => $atVendor,
            'vendor_delayed' => $vendorDelayed,
            'subcontract_qc_pending' => $qcPending,
            'vendor_rework' => $vendorRework,
        ];
    }

    public function find(int $id): ?ProductionOrder
    {
        return ProductionOrder::find($id);
    }

    public function findWithDetails(int $id): ?ProductionOrder
    {
        return ProductionOrder::with([
            'product',
            'bom',
            'routing',
            'creator',
            'releaser',
            'completer',
            'closer',
            'operations.workCenter',
            'operations.machine',
            'operations.vendor',
            'operations.subcontractServiceProduct',
            'operations.purchaseOrder',
            'operations.purchaseOrderItem',
            'operations.scheduleOperation',
            'operations.operatorAssignments.user',
            'reservations.product',
            'reservations.uom',
            'reservations.warehouse',
            'issues.product',
            'issues.user',
            'issues.warehouse',
            'progressLogs.operation',
            'progressLogs.user',
            'progressLogs.machine',
            'receipts.user',
            'receipts.warehouse',
            'scraps.operation',
            'scraps.product',
            'scraps.user',
            'reworks.operation',
            'reworks.user',
            'wips.currentRoutingOperation',
            'wips.currentWorkCenter',
            'wips.transactions.fromOperation',
            'wips.transactions.toOperation',
            'requisitionSlips.items.product',
            'requisitionSlips.items.uom',
            'requisitionSlips.purchaseRequisitions.items',
            'schedules',
            'batches',
            'serialNumbers',
        ])->find($id);
    }

    public function findForExecutionLock(int $id): ProductionOrder
    {
        return ProductionOrder::where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function create(array $data): ProductionOrder
    {
        return ProductionOrder::create($data);
    }

    public function update(int $id, array $data): ProductionOrder
    {
        $order = ProductionOrder::findOrFail($id);
        $order->update($data);
        return $order->fresh();
    }

    public function delete(int $id): bool
    {
        $order = ProductionOrder::findOrFail($id);
        return (bool) $order->delete();
    }

    public function getPendingRequests(int $tenantId, ?int $requestId = null): Collection
    {
        return ProductionOrderRequest::where('tenant_id', $tenantId)
            ->where(function ($q) use ($requestId) {
                $q->where(function ($sub) {
                    $sub->where('status', 'draft')->whereNull('production_order_id');
                });
                if ($requestId) {
                    $q->orWhere('id', $requestId);
                }
            })
            ->with([
                'product',
                'materialRequirementItem.materialRequirement.salesOrder.customer',
                'materialRequirementItem.salesOrderItem.salesOrder',
            ])
            ->orderByDesc('id')
            ->get();
    }
}
