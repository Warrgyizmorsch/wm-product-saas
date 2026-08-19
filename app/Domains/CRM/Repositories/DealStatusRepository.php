<?php

namespace App\Domains\CRM\Repositories;

use App\Domains\CRM\Models\DealStatus;
use Illuminate\Database\Eloquent\Collection;

class DealStatusRepository
{
    public function getAll(?int $tenantId = null): Collection
    {
        return DealStatus::getOrderedStatuses($tenantId);
    }

    public function find(int $id): ?DealStatus
    {
        return DealStatus::query()->find($id);
    }

    public function create(array $data): DealStatus
    {
        if (!isset($data['sort_order']) || $data['sort_order'] <= 0) {
            $maxOrder = DealStatus::query()
                ->where('tenant_id', $data['tenant_id'] ?? (tenant_id() ?? 1))
                ->max('sort_order');
            $data['sort_order'] = ($maxOrder ?? 0) + 1;
        }

        return DealStatus::query()->create($data);
    }

    public function update(DealStatus $dealStatus, array $data): bool
    {
        return $dealStatus->update($data);
    }

    public function delete(DealStatus $dealStatus): bool
    {
        return $dealStatus->delete();
    }

    public function updateOrder(array $orderMap): void
    {
        foreach ($orderMap as $id => $order) {
            DealStatus::query()->where('id', $id)->update(['sort_order' => (int) $order]);
        }
    }
}
