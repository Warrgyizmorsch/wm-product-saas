<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductionWipRepository implements ProductionWipRepositoryInterface
{
    public function paginateWip(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionWip::with([
            'order',
            'product',
            'currentRoutingOperation',
            'currentWorkCenter',
            'currentMachine',
            'batch',
        ]);

        if (!empty($filters['production_order_id'])) {
            $query->where('production_order_id', $filters['production_order_id']);
        }

        if (!empty($filters['work_center_id'])) {
            $query->where('current_work_center_id', $filters['work_center_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn($p) => $p->where('name', 'like', $search)->orWhere('sku', 'like', $search))
                  ->orWhereHas('order', fn($o) => $o->where('order_number', 'like', $search));
            });
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function find(int $id): ?ProductionWip
    {
        return ProductionWip::with([
            'order',
            'product',
            'currentRoutingOperation',
            'currentWorkCenter',
            'currentMachine',
            'batch',
            'transactions.fromOperation',
            'transactions.toOperation',
        ])->find($id);
    }

    public function getWipForOrder(int $orderId): Collection
    {
        return ProductionWip::where('production_order_id', $orderId)
            ->with(['currentRoutingOperation', 'currentWorkCenter'])
            ->get();
    }

    public function getTransferableWip(int $batchId, int $operationId): ?ProductionWip
    {
        return ProductionWip::where('production_batch_id', $batchId)
            ->where('current_routing_operation_id', $operationId)
            ->first();
    }

    public function lockWipForTransfer(int $wipId): ProductionWip
    {
        return ProductionWip::where('id', $wipId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function createWip(array $data): ProductionWip
    {
        return ProductionWip::create($data);
    }

    public function updateWip(int $id, array $data): ProductionWip
    {
        $wip = ProductionWip::findOrFail($id);
        $wip->update($data);
        return $wip->fresh();
    }

    public function createTransaction(array $data): ProductionWipTransaction
    {
        return ProductionWipTransaction::create($data);
    }

    public function getUninitializedOrders(int $tenantId): Collection
    {
        return \App\Domains\Production\Models\ProductionOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['released', 'in_progress'])
            ->whereNotExists(function ($query) {
                $query->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('production_wips')
                    ->whereColumn('production_wips.production_order_id', 'production_orders.id');
            })
            ->get();
    }

    public function getWipKpiSummary(int $tenantId): array
    {
        $summary = ProductionWip::where('tenant_id', $tenantId)
            ->selectRaw('
                count(*) as total_count,
                sum(available_quantity) as total_available,
                sum(case when status = "active" then 1 else 0 end) as active_count,
                sum(case when status = "quality_hold" then 1 else 0 end) as hold_count,
                sum(case when status = "rework" then 1 else 0 end) as rework_count,
                sum(case when status = "completed" then 1 else 0 end) as completed_count
            ')
            ->first();

        return [
            'total_count' => (int) ($summary->total_count ?? 0),
            'total_available' => (float) ($summary->total_available ?? 0),
            'active_count' => (int) ($summary->active_count ?? 0),
            'hold_count' => (int) ($summary->hold_count ?? 0),
            'rework_count' => (int) ($summary->rework_count ?? 0),
            'completed_count' => (int) ($summary->completed_count ?? 0),
            'subcontract_count' => ProductionWip::where('tenant_id', $tenantId)->whereHas('currentRoutingOperation', fn($op) => $op->where('is_external', true))->count(),
        ];
    }
}
