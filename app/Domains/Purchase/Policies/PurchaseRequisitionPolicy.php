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
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function view(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->access->allows($user, 'purchase.requisitions.view', [
            'tenant_id'     => $requisition->tenant_id,
            'owner_id'      => $requisition->requested_by,
            'branch_id'     => $requisition->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $requisition->company_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'purchase.requisitions.create', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function update(User $user, PurchaseRequisition $requisition): bool
    {
        if ($requisition->status !== 'Draft') {
            return false;
        }

        return $this->access->allows($user, 'purchase.requisitions.edit', [
            'tenant_id'     => $requisition->tenant_id,
            'owner_id'      => $requisition->requested_by,
            'branch_id'     => $requisition->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $requisition->company_id,
        ]);
    }

    public function delete(User $user, PurchaseRequisition $requisition): bool
    {
        if ($requisition->status !== 'Draft') {
            return false;
        }

        return $this->access->allows($user, 'purchase.requisitions.delete', [
            'tenant_id'     => $requisition->tenant_id,
            'owner_id'      => $requisition->requested_by,
            'branch_id'     => $requisition->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $requisition->company_id,
        ]);
    }

    public function approve(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->access->allows($user, 'purchase.requisitions.approve', [
            'tenant_id'     => $requisition->tenant_id,
            'owner_id'      => $requisition->requested_by,
            'branch_id'     => $requisition->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $requisition->company_id,
        ]) || $this->access->allows($user, 'purchase.approvals.manage', [
            'tenant_id'     => $requisition->tenant_id,
            'owner_id'      => $requisition->requested_by,
            'branch_id'     => $requisition->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $requisition->company_id,
        ]);
    }
}
