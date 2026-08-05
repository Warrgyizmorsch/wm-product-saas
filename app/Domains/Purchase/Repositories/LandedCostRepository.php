<?php

namespace App\Domains\Purchase\Repositories;

use App\Domains\Purchase\Models\LandedCostVoucher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LandedCostRepository
{
    public function getPaginatedVouchers(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = LandedCostVoucher::where('tenant_id', $tenantId)
            ->with(['receipts.goodsReceiptNote', 'expenses', 'items.product', 'creator']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('voucher_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('receipts.goodsReceiptNote', function ($grnQuery) use ($search) {
                      $grnQuery->where('grn_number', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('voucher_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('voucher_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function findById(int $tenantId, int $id): ?LandedCostVoucher
    {
        return LandedCostVoucher::where('tenant_id', $tenantId)
            ->with(['receipts.goodsReceiptNote.vendor', 'expenses.vendor', 'items.product.uom', 'creator', 'poster'])
            ->find($id);
    }
}
