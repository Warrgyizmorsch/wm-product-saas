<?php

namespace App\Domains\Purchase\Repositories;

use App\Domains\Purchase\Models\VendorBill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

        $allNumbers = DB::table('vendor_bills')
            ->where('bill_number', 'like', "{$prefix}%")
            ->pluck('bill_number');

        $maxNum = 0;
        foreach ($allNumbers as $bNum) {
            $numStr = str_replace($prefix, '', $bNum);
            $n = (int) $numStr;
            if ($n > $maxNum) {
                $maxNum = $n;
            }
        }

        $nextNum = $maxNum + 1;
        do {
            $billNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
            $exists = DB::table('vendor_bills')->where('bill_number', $billNumber)->exists();
            if ($exists) {
                $nextNum++;
            }
        } while ($exists);

        return $billNumber;
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
