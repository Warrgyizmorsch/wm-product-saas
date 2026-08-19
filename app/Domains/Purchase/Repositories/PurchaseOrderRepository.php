<?php

namespace App\Domains\Purchase\Repositories;

use App\Domains\Purchase\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseOrderRepository
{
    public function getPaginatedOrders(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $tenantId = require_tenant_id();

        $query = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['vendor', 'items.product', 'reminders.user']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where('purchase_order_number', 'like', $search);
        }

        if (!empty($filters['reminder_date_from'])) {
            $query->whereDate('last_reminded_at', '>=', $filters['reminder_date_from']);
        }

        if (!empty($filters['reminder_date_to'])) {
            $query->whereDate('last_reminded_at', '<=', $filters['reminder_date_to']);
        }

        if (isset($filters['has_reminders']) && $filters['has_reminders'] !== '') {
            if ($filters['has_reminders'] == '1') {
                $query->where('reminder_count', '>', 0);
            } elseif ($filters['has_reminders'] == '0') {
                $query->where('reminder_count', 0);
            }
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['id', 'purchase_order_number', 'date', 'grand_total', 'status', 'last_reminded_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?PurchaseOrder
    {
        $tenantId = require_tenant_id();
        return PurchaseOrder::where('tenant_id', $tenantId)->find($id);
    }

    public function findWithDetails(int $id): PurchaseOrder
    {
        $tenantId = require_tenant_id();
        return PurchaseOrder::where('tenant_id', $tenantId)
            ->with([
                'vendor',
                'items.product',
                'items.warehouse',
                'goodsReceiptNotes',
                'vendorBills',
                'payments'
            ])
            ->findOrFail($id);
    }

    public function getNextPoNumber(int $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = "PO-{$year}-";
        $lastPo = PurchaseOrder::where('tenant_id', $tenantId)
            ->where('purchase_order_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();
        $nextNum = 1;
        if ($lastPo) {
            $lastNumStr = str_replace($prefix, '', $lastPo->purchase_order_number);
            $nextNum = ((int) $lastNumStr) + 1;
        }
        return $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }

    public function create(array $data): PurchaseOrder
    {
        return PurchaseOrder::create($data);
    }

    public function update(PurchaseOrder $order, array $data): bool
    {
        return $order->update($data);
    }

    public function delete(PurchaseOrder $order): ?bool
    {
        return $order->delete();
    }
}
