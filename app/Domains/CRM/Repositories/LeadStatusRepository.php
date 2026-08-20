<?php

namespace App\Domains\CRM\Repositories;

use App\Domains\CRM\Models\LeadStatus;
use Illuminate\Database\Eloquent\Collection;

class LeadStatusRepository
{
    public function getAll(?int $tenantId = null): Collection
    {
        return LeadStatus::getOrderedStatuses($tenantId);
    }

    public function find(int $id): ?LeadStatus
    {
        return LeadStatus::query()->find($id);
    }

    public function create(array $data): LeadStatus
    {
        if (!isset($data['sort_order']) || $data['sort_order'] <= 0) {
            $maxOrder = LeadStatus::query()
                ->where('tenant_id', $data['tenant_id'] ?? (tenant_id() ?? 1))
                ->max('sort_order');
            $data['sort_order'] = ($maxOrder ?? 0) + 1;
        }

        return LeadStatus::query()->create($data);
    }

    public function update(LeadStatus $leadStatus, array $data): bool
    {
        return $leadStatus->update($data);
    }

    public function delete(LeadStatus $leadStatus): bool
    {
        return $leadStatus->delete();
    }

    public function updateOrder(array $orderMap): void
    {
        foreach ($orderMap as $id => $order) {
            LeadStatus::query()->where('id', $id)->update(['sort_order' => (int) $order]);
        }
    }
}
