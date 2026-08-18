<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionCostAdjustment;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Models\ProductionEventTimeline;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductionCostTraceabilityRepositoryInterface
{
    public function getAdjustmentsForOrder(int $orderId): Collection;

    public function paginateAdjustmentsForOrder(int $orderId, int $perPage = 10): LengthAwarePaginator;

    public function createCostAdjustment(array $data): ProductionCostAdjustment;

    public function findSerialNumber(string $serialNumber): ?ProductionSerialNumber;

    public function getSerialNumbersForOrder(int $orderId): Collection;

    public function getLotTraceability(string $lotNumber): Collection;

    public function getEventTimelineForOrder(int $orderId): Collection;

    public function createTimelineEvent(array $data): ProductionEventTimeline;
}
