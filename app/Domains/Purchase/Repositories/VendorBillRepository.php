<?php

namespace App\Domains\Purchase\Repositories;

use App\Domains\Purchase\Models\VendorBill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VendorBillRepository
{
    public function getPaginatedBills(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $tenantId = require_tenant_id();

        $query = VendorBill::where('tenant_id', $tenantId)
            ->with(['vendor', 'purchaseOrder', 'goodsReceiptNote', 'items.product']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where('bill_number', 'like', $search);
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['id', 'bill_number', 'bill_date', 'due_date', 'grand_total', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?VendorBill
    {
        $tenantId = require_tenant_id();
        return VendorBill::where('tenant_id', $tenantId)->find($id);
    }

    public function findWithDetails(int $id): VendorBill
    {
        $tenantId = require_tenant_id();
        return VendorBill::where('tenant_id', $tenantId)
            ->with([
                'vendor',
                'purchaseOrder',
                'items.product',
                'payments'
            ])
            ->findOrFail($id);
    }

    public function getNextBillNumber(int $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = "BILL-{$year}-";
        $lastBill = VendorBill::where('tenant_id', $tenantId)
            ->where('bill_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();
        $nextNum = 1;
        if ($lastBill) {
            $lastNumStr = str_replace($prefix, '', $lastBill->bill_number);
            $nextNum = ((int) $lastNumStr) + 1;
        }
        return $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }

    public function create(array $data): VendorBill
    {
        return VendorBill::create($data);
    }

    public function update(VendorBill $bill, array $data): bool
    {
        return $bill->update($data);
    }
}
