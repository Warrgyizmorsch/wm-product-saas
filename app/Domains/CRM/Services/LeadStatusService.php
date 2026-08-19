<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadStatus;
use App\Domains\CRM\Repositories\LeadStatusRepository;
use Illuminate\Database\Eloquent\Collection;

class LeadStatusService
{
    public function __construct(private readonly LeadStatusRepository $repo)
    {
    }

    public function getAllStatuses(?int $tenantId = null): Collection
    {
        return $this->repo->getAll($tenantId);
    }

    public function createStatus(array $data): LeadStatus
    {
        $tenantId = tenant_id() ?? 1;
        $data['tenant_id'] = $tenantId;
        $data['is_protected'] = false;
        $data['is_active'] = true;

        return $this->repo->create($data);
    }

    public function updateStatus(LeadStatus $leadStatus, array $data): array
    {
        if ($leadStatus->isProtected()) {
            // Protected statuses (New, Qualified, Won, Lost) cannot change their name
            if (isset($data['name']) && strtolower(trim($data['name'])) !== strtolower(trim($leadStatus->name))) {
                return [
                    'success' => false,
                    'message' => 'Default system status names (New, Qualified, Won, Lost) cannot be changed to maintain CRM logic.'
                ];
            }
        }

        $this->repo->update($leadStatus, $data);

        return [
            'success' => true,
            'message' => 'Lead status updated successfully.'
        ];
    }

    public function deleteStatus(LeadStatus $leadStatus): array
    {
        if ($leadStatus->isProtected()) {
            return [
                'success' => false,
                'message' => 'Default system statuses (New, Qualified, Won, Lost) cannot be deleted.'
            ];
        }

        // Check if any leads are currently using this status
        $inUseCount = Lead::query()
            ->where('tenant_id', $leadStatus->tenant_id)
            ->where('status', $leadStatus->name)
            ->whereNull('deleted_at')
            ->count();

        if ($inUseCount > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete status '{$leadStatus->name}' because it is currently assigned to {$inUseCount} lead(s)."
            ];
        }

        $this->repo->delete($leadStatus);

        return [
            'success' => true,
            'message' => 'Lead status deleted successfully.'
        ];
    }

    public function reorderStatuses(array $orderMap): array
    {
        $this->repo->updateOrder($orderMap);

        return [
            'success' => true,
            'message' => 'Lead statuses reordered successfully.'
        ];
    }
}
