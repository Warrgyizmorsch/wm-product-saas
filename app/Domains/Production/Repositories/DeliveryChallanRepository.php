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
            $dispatchedWipQty = (float) collect($items)->sum('quantity');

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
                'dispatched_wip_qty' => $dispatchedWipQty,
                'vehicle_number' => $attributes['vehicle_number'] ?? null,
                'transporter_name' => $attributes['transporter_name'] ?? null,
                'lr_number' => $attributes['lr_number'] ?? null,
                'driver_name' => $attributes['driver_name'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $attributes['created_by'] ?? (auth()->id() ?: 1),
            ]);

            $op = !empty($attributes['production_order_operation_id'])
                ? \App\Domains\Production\Models\ProductionOrderOperation::find($attributes['production_order_operation_id'])
                : null;

            foreach ($items as $item) {
                $batchId = $item['production_batch_id'] ?? null;
                $wipId = $item['production_wip_id'] ?? null;
                $orderId = $attributes['production_order_id'] ?? null;

                if ($orderId && (!$wipId || !$batchId)) {
                    if (!$batchId) {
                        $batch = \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $tenantId)
                            ->where('production_order_id', $orderId)
                            ->first();
                        $batchId = $batch?->id;
                    }

                    if (!$wipId) {
                        $wip = \App\Domains\Production\Models\ProductionWip::where('tenant_id', $tenantId)
                            ->where('production_order_id', $orderId)
                            ->first();

                        if (!$wip) {
                            $order = \App\Domains\Production\Models\ProductionOrder::find($orderId);
                            $wip = \App\Domains\Production\Models\ProductionWip::create([
                                'tenant_id' => $tenantId,
                                'production_order_id' => $orderId,
                                'production_batch_id' => $batchId,
                                'product_id' => $item['product_id'] ?? $order?->product_id,
                                'current_routing_operation_id' => $op?->routing_operation_id,
                                'current_work_center_id' => $op?->work_center_id,
                                'quantity' => $order?->quantity_ordered ?? 0,
                                'available_quantity' => 0,
                                'completed_quantity' => 0,
                                'rejected_quantity' => 0,
                                'scrap_quantity' => 0,
                                'rework_quantity' => 0,
                                'status' => 'active',
                            ]);
                        }
                        $wipId = $wip?->id;
                    }
                }

                DeliveryChallanItem::create([
                    'tenant_id' => $tenantId,
                    'delivery_challan_id' => $challan->id,
                    'product_id' => $item['product_id'],
                    'production_batch_id' => $batchId,
                    'production_wip_id' => $wipId,
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
        $nextNum = 1;

        if ($lastChallan && preg_match('/DC-\d{4}-(\d+)/', $lastChallan->challan_number, $matches)) {
            $nextNum = ((int) $matches[1]) + 1;
        }

        return 'DC-' . date('Y') . '-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }
}
