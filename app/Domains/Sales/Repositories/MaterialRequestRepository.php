<?php

namespace App\Domains\Sales\Repositories;

use App\Domains\Production\Models\ProductionRequisitionSlip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MaterialRequestRepository
{
    public function getPaginatedSlips(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionRequisitionSlip::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['order.product']);

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('requisition_number', 'like', $search)
                    ->orWhereHas('order', function ($o) use ($search) {
                        $o->where('order_number', 'like', $search);
                    });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();
    }

    public function find(int $tenantId, int $id): ?ProductionRequisitionSlip
    {
        return ProductionRequisitionSlip::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['order.product', 'items.product', 'items.warehouse'])
            ->find($id);
    }
}
