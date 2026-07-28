<?php

namespace App\Domains\Purchase\Repositories;

use App\Domains\Purchase\Models\GoodsReceiptNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GoodsReceiptNoteRepository
{
    public function getPaginatedGrns(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $tenantId = require_tenant_id();

        $query = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->with(['purchaseOrder.vendor', 'warehouse']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where('grn_number', 'like', $search);
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['id', 'grn_number', 'received_date', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?GoodsReceiptNote
    {
        $tenantId = require_tenant_id();
        return GoodsReceiptNote::where('tenant_id', $tenantId)->find($id);
    }

    public function findWithDetails(int $id): GoodsReceiptNote
    {
        $tenantId = require_tenant_id();
        return GoodsReceiptNote::where('tenant_id', $tenantId)
            ->with([
                'purchaseOrder.vendor',
                'warehouse',
                'items.product',
                'items.purchaseOrderItem',
                'creator'
            ])
            ->findOrFail($id);
    }

    public function getNextGrnNumber(int $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = "GRN-{$year}-";
        $lastGrn = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->where('grn_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();
        $nextNum = 1;
        if ($lastGrn) {
            $lastNumStr = str_replace($prefix, '', $lastGrn->grn_number);
            $nextNum = ((int) $lastNumStr) + 1;
        }
        return $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }

    public function create(array $data): GoodsReceiptNote
    {
        return GoodsReceiptNote::create($data);
    }

    public function update(GoodsReceiptNote $grn, array $data): bool
    {
        return $grn->update($data);
    }

    public function delete(GoodsReceiptNote $grn): ?bool
    {
        return $grn->delete();
    }
}
