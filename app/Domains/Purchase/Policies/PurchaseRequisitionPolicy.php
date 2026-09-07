<?php

namespace App\Domains\Purchase\Policies;

use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Models\User;
use App\Services\Access\AccessService;

class PurchaseRequisitionPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'purchase.requisitions.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->access->allows($user, 'purchase.requisitions.view', [
            'tenant_id' => $requisition->tenant_id,
            'owner_id' => $requisition->requested_by,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'purchase.requisitions.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, PurchaseRequisition $requisition): bool
    {
        if ($requisition->status !== 'Draft') {
            return false;
        }

        return $this->access->allows($user, 'purchase.requisitions.edit', [
            'tenant_id' => $requisition->tenant_id,
            'owner_id' => $requisition->requested_by,
        ]);
    }

    public function delete(User $user, PurchaseRequisition $requisition): bool
    {
        if ($requisition->status !== 'Draft') {
            return false;
        }

        return $this->access->allows($user, 'purchase.requisitions.delete', [
            'tenant_id' => $requisition->tenant_id,
            'owner_id' => $requisition->requested_by,
        ]);
    }

    public function approve(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->access->allows($user, 'purchase.requisitions.approve', [
            'tenant_id' => $requisition->tenant_id,
        ]) || $this->access->allows($user, 'purchase.approvals.manage', [
            'tenant_id' => $requisition->tenant_id,
        ]);
    }
}
