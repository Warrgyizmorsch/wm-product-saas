<?php

namespace App\Domains\Purchase\Repositories;

use App\Domains\Purchase\Models\VendorPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VendorPaymentRepository
{
    public function getPaginatedPayments(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $tenantId = require_tenant_id();

        $query = VendorPayment::where('tenant_id', $tenantId)
            ->with(['vendor', 'vendorBill']);

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where('payment_number', 'like', $search);
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['id', 'payment_number', 'payment_date', 'amount'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?VendorPayment
    {
        $tenantId = require_tenant_id();
        return VendorPayment::where('tenant_id', $tenantId)->find($id);
    }

    public function getNextPaymentNumber(int $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = "PAY-{$year}-";
        $lastPay = VendorPayment::where('tenant_id', $tenantId)
            ->where('payment_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();
        $nextNum = 1;
        if ($lastPay) {
            $lastNumStr = str_replace($prefix, '', $lastPay->payment_number);
            $nextNum = ((int) $lastNumStr) + 1;
        }
        return $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }

    public function create(array $data): VendorPayment
    {
        return VendorPayment::create($data);
    }
}
