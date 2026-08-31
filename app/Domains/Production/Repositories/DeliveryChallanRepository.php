<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\DeliveryChallanItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DeliveryChallanRepository implements DeliveryChallanRepositoryInterface
{
    public function paginate(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DeliveryChallan::where('tenant_id', $tenantId)
            ->with(['productionOrder', 'operation', 'vendor', 'warehouse', 'creator', 'items.warehouse', 'items.product']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('challan_number', 'like', "%{$search}%")
                  ->orWhere('vehicle_number', 'like', "%{$search}%")
                  ->orWhere('lr_number', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function find(int $id, int $tenantId): ?DeliveryChallan
    {
        return DeliveryChallan::where('tenant_id', $tenantId)
            ->with(['productionOrder.product', 'operation', 'vendor', 'warehouse', 'items.product', 'items.warehouse', 'creator'])
            ->find($id);
    }

    public function findOrFail(int $id, int $tenantId): DeliveryChallan
    {
        return DeliveryChallan::where('tenant_id', $tenantId)
            ->with(['productionOrder.product', 'operation', 'vendor', 'warehouse', 'items.product', 'items.warehouse', 'creator'])
            ->findOrFail($id);
    }

    public function getStatusCounts(int $tenantId): array
    {
        return [
            'total' => DeliveryChallan::where('tenant_id', $tenantId)->count(),
            'draft' => DeliveryChallan::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'dispatched' => DeliveryChallan::where('tenant_id', $tenantId)->where('status', 'dispatched')->count(),
            'completed' => DeliveryChallan::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
        ];
    }

    public function create(int $tenantId, array $attributes, array $items): DeliveryChallan
    {
        return DB::transaction(function () use ($tenantId, $attributes, $items) {
            $challanNumber = $this->getNextChallanNumber($tenantId);
            $firstWarehouseId = $attributes['warehouse_id'] ?? ($items[0]['warehouse_id'] ?? null);

            $challan = DeliveryChallan::create([
                'tenant_id' => $tenantId,
                'challan_number' => $challanNumber,
                'type' => 'subcontract_dispatch',
                'production_order_id' => $attributes['production_order_id'] ?? null,
                'production_order_operation_id' => $attributes['production_order_operation_id'] ?? null,
                'vendor_id' => $attributes['vendor_id'],
                'warehouse_id' => $firstWarehouseId,
                'challan_date' => $attributes['challan_date'],
                'expected_return_date' => $attributes['expected_return_date'] ?? null,
                'status' => $attributes['status'],
                'vehicle_number' => $attributes['vehicle_number'] ?? null,
                'transporter_name' => $attributes['transporter_name'] ?? null,
                'lr_number' => $attributes['lr_number'] ?? null,
                'driver_name' => $attributes['driver_name'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $attributes['created_by'] ?? (auth()->id() ?: 1),
            ]);

            foreach ($items as $item) {
                DeliveryChallanItem::create([
                    'tenant_id' => $tenantId,
                    'delivery_challan_id' => $challan->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'quantity' => $item['quantity'],
                    'unit_of_measure' => $item['unit_of_measure'] ?? 'PCS',
                    'batch_number' => $item['batch_number'] ?? null,
                    'serial_number' => $item['serial_number'] ?? null,
                ]);
            }

            return $challan;
        });
    }

    public function updateStatus(DeliveryChallan $challan, string $status): bool
    {
        $challan->status = $status;
        return $challan->save();
    }

    public function getNextChallanNumber(int $tenantId): string
    {
        $lastChallan = DeliveryChallan::where('tenant_id', $tenantId)->latest('id')->first();
        $nextNum = $lastChallan ? ((int) preg_replace('/[^0-9]/', '', $lastChallan->challan_number)) + 1 : 1;

        return 'DC-' . date('Y') . '-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }
}
