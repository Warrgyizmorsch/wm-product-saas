<?php

namespace App\Domains\Purchase\Repositories;

use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseAdvancePaymentRepository
{
    public function getPaginatedAdvances(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $tenantId = require_tenant_id();

        $query = PurchaseAdvancePayment::where('tenant_id', $tenantId)
            ->with(['vendor', 'purchaseOrder']);

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', $search)
                  ->orWhere('reference_number', 'like', $search);
            });
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        if ($sortBy === 'advance_number') {
            $sortBy = 'payment_number';
        }
        $allowedSorts = ['id', 'payment_number', 'payment_date', 'amount'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?PurchaseAdvancePayment
    {
        $tenantId = require_tenant_id();
        return PurchaseAdvancePayment::where('tenant_id', $tenantId)->find($id);
    }

    public function getNextAdvanceNumber(int $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = "ADV-{$year}-";
        $lastAdv = PurchaseAdvancePayment::where('tenant_id', $tenantId)
            ->where('payment_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();
        $nextNum = 1;
        if ($lastAdv && !empty($lastAdv->payment_number)) {
            $lastNumStr = str_replace($prefix, '', $lastAdv->payment_number);
            $nextNum = ((int) $lastNumStr) + 1;
        }
        return $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }

    public function create(array $data): PurchaseAdvancePayment
    {
        return PurchaseAdvancePayment::create($data);
    }
}
