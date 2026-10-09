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
            ->with(['order.product', 'maintenanceWorkOrder.machine.workCenter']);

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('requisition_number', 'like', $search)
                    ->orWhereHas('order', function ($o) use ($search) {
                        $o->where('order_number', 'like', $search);
                    })
                    ->orWhereHas('maintenanceWorkOrder', function ($m) use ($search) {
                        $m->where('work_order_number', 'like', $search)
                          ->orWhereHas('machine', fn($mac) => $mac->where('name', 'like', $search)->orWhere('code', 'like', $search));
                    });
            });
        }

        if (!empty($filters['status'])) {
            $statusVal = $filters['status'];
            if (in_array($statusVal, ['completed', 'fully_issued', 'Fully Issued'])) {
                $query->whereIn('status', ['completed', 'Fully Issued', 'issued']);
            } elseif (in_array($statusVal, ['partial', 'partially_issued', 'Partially Issued'])) {
                $query->whereIn('status', ['partial', 'Partially Issued', 'Reserved']);
            } else {
                $query->where('status', $statusVal);
            }
        }

        return $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();
    }

    public function find(int $tenantId, int $id): ?ProductionRequisitionSlip
    {
        return ProductionRequisitionSlip::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['order.product', 'maintenanceWorkOrder.machine.workCenter', 'items.product', 'items.warehouse'])
            ->find($id);
    }
}
