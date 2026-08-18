<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionCostAdjustment;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Models\ProductionLotTrace;
use App\Domains\Production\Models\ProductionEventTimeline;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductionCostTraceabilityRepository implements ProductionCostTraceabilityRepositoryInterface
{
    public function getAdjustmentsForOrder(int $orderId): Collection
    {
        return ProductionCostAdjustment::where('production_order_id', $orderId)
            ->with(['creator', 'updater'])
            ->latest('adjustment_date')
            ->get();
    }

    public function paginateAdjustmentsForOrder(int $orderId, int $perPage = 10): LengthAwarePaginator
    {
        return ProductionCostAdjustment::where('production_order_id', $orderId)
            ->with(['creator', 'updater'])
            ->latest('adjustment_date')
            ->paginate($perPage, ['*'], 'adjustments_page');
    }

    public function createCostAdjustment(array $data): ProductionCostAdjustment
    {
        return ProductionCostAdjustment::create($data);
    }

    public function findSerialNumber(string $serialNumber): ?ProductionSerialNumber
    {
        return ProductionSerialNumber::where('serial_number', $serialNumber)
            ->with(['order', 'product', 'batch'])
            ->first();
    }

    public function getSerialNumbersForOrder(int $orderId): Collection
    {
        return ProductionSerialNumber::where('production_order_id', $orderId)->get();
    }

    public function getLotTraceability(string $lotNumber): Collection
    {
        return ProductionLotTrace::with(['source', 'target'])
            ->get();
    }

    public function getEventTimelineForOrder(int $orderId): Collection
    {
        return ProductionEventTimeline::where('production_order_id', $orderId)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function createTimelineEvent(array $data): ProductionEventTimeline
    {
        return ProductionEventTimeline::create($data);
    }
}
