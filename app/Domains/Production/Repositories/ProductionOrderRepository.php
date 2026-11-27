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
        $query = ProductionOrder::with(['product', 'bom', 'routing', 'project', 'projectTask']);

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

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'order_number', 'start_date', 'end_date', 'quantity_ordered', 'status'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage);
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
        // H-08: counts use the statuses the flow really writes (2026-11-27 rework)
        $Op = \App\Domains\Production\Models\ProductionOrderOperation::class;
        $Challan = \App\Domains\Production\Models\DeliveryChallan::class;
        $liveOrder = fn ($q) => $q->whereIn('status', [
            \App\Domains\Production\Models\ProductionOrder::STATUS_RELEASED,
            \App\Domains\Production\Models\ProductionOrder::STATUS_IN_PROGRESS,
        ]);
        $openOp = fn ($q) => $q->where('tenant_id', $tenantId)
            ->where('is_external', true)
            ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
            ->whereHas('order', $liveOrder);

        // Outsourced ops of live orders with no subcontract PO yet (D-4: raised only by button)
        $awaitingProcurement = $openOp($Op::query())
            ->whereNull('purchase_order_id')
            ->count();

        $poAwaitingApproval = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)
            ->where('is_subcontract', true)
            ->whereIn('status', ['Draft', 'Pending Approval', 'draft', 'pending_approval'])
            ->count();

        // Company-material / job-work ops of live orders not yet at a vendor
        $readyForDispatch = $openOp($Op::query())
            ->where(fn ($q) => $q->whereNull('material_supply_type')->orWhere('material_supply_type', '!=', 'vendor_supplied'))
            ->whereNotIn('status', [$Op::STATUS_VENDOR_DISPATCHED, $Op::STATUS_SUBCONTRACT_QC_PENDING])
            ->whereDoesntHave('deliveryChallans', fn ($q) => $q->whereIn('status', $Challan::AT_VENDOR_STATUSES))
            ->count();

        // Dispatch slips still at a vendor (dispatched / partly received)
        $atVendor = $Challan::where('tenant_id', $tenantId)
            ->whereIn('status', $Challan::AT_VENDOR_STATUSES)
            ->count();

        // Slips past their expected-back date
        $vendorDelayed = $Challan::where('tenant_id', $tenantId)
            ->whereIn('status', $Challan::AT_VENDOR_STATUSES)
            ->whereNotNull('expected_return_date')
            ->whereDate('expected_return_date', '<', now()->toDateString())
            ->count();

        $partialReceipt = $Challan::where('tenant_id', $tenantId)
            ->where('status', $Challan::STATUS_PARTIALLY_RECEIVED)
            ->count();

        $qcPending = $Op::where('tenant_id', $tenantId)
            ->where('is_external', true)
            ->where('status', $Op::STATUS_SUBCONTRACT_QC_PENDING)
            ->count();

        // logRework still writes 'pending'; in_progress is the next state
        $vendorRework = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('operation', function ($q) {
                $q->where('is_external', true);
            })
            ->count();

        // Closed slips whose material lines were never fully accounted for (sent − used − returned − scrapped > 0)
        $materialExceptions = $Challan::where('tenant_id', $tenantId)
            ->where('status', $Challan::STATUS_COMPLETED)
            ->whereHas('items', fn ($q) => $q->where(fn ($w) => $w->whereNull('item_type')->orWhere('item_type', 'material'))
                ->whereRaw('(COALESCE(quantity,0) - COALESCE(consumed_qty,0) - COALESCE(returned_qty,0) - COALESCE(scrapped_qty,0)) > 0.0001'))
            ->count();

        return [
            'awaiting_subcontract_pr' => $awaitingProcurement,
            'po_awaiting_approval' => $poAwaitingApproval,
            'ready_for_dispatch' => $readyForDispatch,
            'at_vendor' => $atVendor,
            'vendor_delayed' => $vendorDelayed,
            'partial_receipt' => $partialReceipt,
            'subcontract_qc_pending' => $qcPending,
            'vendor_rework' => $vendorRework,
            'material_exceptions' => $materialExceptions,
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
            'project',
            'projectMilestone',
            'projectTask',
            'creator',
            'releaser',
            'completer',
            'closer',
            'operations.workCenter',
            'operations.machine',
            'operations.vendor',
            'operations.sourceProduct',
            'operations.manualProduction',
            'operations.routingOperation',
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
