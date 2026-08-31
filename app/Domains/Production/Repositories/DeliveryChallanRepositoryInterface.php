<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\DeliveryChallan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DeliveryChallanRepositoryInterface
{
    public function paginate(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $id, int $tenantId): ?DeliveryChallan;

    public function findOrFail(int $id, int $tenantId): DeliveryChallan;

    public function getStatusCounts(int $tenantId): array;

    public function create(int $tenantId, array $attributes, array $items): DeliveryChallan;

    public function updateStatus(DeliveryChallan $challan, string $status): bool;

    public function getNextChallanNumber(int $tenantId): string;
}
