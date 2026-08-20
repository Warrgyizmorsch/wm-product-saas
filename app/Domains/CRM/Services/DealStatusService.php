<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\DealStatus;
use App\Domains\CRM\Repositories\DealStatusRepository;
use Illuminate\Database\Eloquent\Collection;

class DealStatusService
{
    public function __construct(private readonly DealStatusRepository $repo)
    {
    }

    public function getAllStatuses(?int $tenantId = null): Collection
    {
        return $this->repo->getAll($tenantId);
    }

    public function createStatus(array $data): DealStatus
    {
        $tenantId = tenant_id() ?? 1;
        $data['tenant_id'] = $tenantId;
        $data['is_protected'] = false;
        $data['is_active'] = true;

        return $this->repo->create($data);
    }

    public function updateStatus(DealStatus $dealStatus, array $data): array
    {
        if ($dealStatus->isProtected()) {
            // Protected statuses (Won, Lost) cannot change their name
            if (isset($data['name']) && strtolower(trim($data['name'])) !== strtolower(trim($dealStatus->name))) {
                return [
                    'success' => false,
                    'message' => 'Default system status names (Won, Lost) cannot be changed to maintain deal pipeline logic.'
                ];
            }
        }

        $this->repo->update($dealStatus, $data);

        return [
            'success' => true,
            'message' => 'Deal stage updated successfully.'
        ];
    }

    public function deleteStatus(DealStatus $dealStatus): array
    {
        if ($dealStatus->isProtected()) {
            return [
                'success' => false,
                'message' => 'Default system statuses (Won, Lost) cannot be deleted.'
            ];
        }

        // Check if any deals are currently using this stage
        $inUseCount = CrmDeal::query()
            ->where('tenant_id', $dealStatus->tenant_id)
            ->where('stage', $dealStatus->name)
            ->whereNull('deleted_at')
            ->count();

        if ($inUseCount > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete stage '{$dealStatus->name}' because it is currently assigned to {$inUseCount} deal(s)."
            ];
        }

        $this->repo->delete($dealStatus);

        return [
            'success' => true,
            'message' => 'Deal stage deleted successfully.'
        ];
    }

    public function reorderStatuses(array $orderMap): array
    {
        $this->repo->updateOrder($orderMap);

        return [
            'success' => true,
            'message' => 'Deal stages reordered successfully.'
        ];
    }
}
