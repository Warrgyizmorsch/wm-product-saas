<?php

namespace App\Domains\Purchase\Policies;

use App\Domains\Purchase\Models\PurchaseRfq;
use App\Models\User;
use App\Services\Access\AccessService;

class PurchaseRfqPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'purchase.rfqs.view', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function view(User $user, PurchaseRfq $rfq): bool
    {
        return $this->access->allows($user, 'purchase.rfqs.view', [
            'tenant_id'     => $rfq->tenant_id,
            'owner_id'      => $rfq->created_by ?? $user->id,
            'branch_id'     => $rfq->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $rfq->company_id ?? $user->company_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'purchase.rfqs.create', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function update(User $user, PurchaseRfq $rfq): bool
    {
        return $this->access->allows($user, 'purchase.rfqs.edit', [
            'tenant_id'     => $rfq->tenant_id,
            'owner_id'      => $rfq->created_by ?? $user->id,
            'branch_id'     => $rfq->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $rfq->company_id ?? $user->company_id,
        ]);
    }

    public function delete(User $user, PurchaseRfq $rfq): bool
    {
        return $this->access->allows($user, 'purchase.rfqs.delete', [
            'tenant_id'     => $rfq->tenant_id,
            'owner_id'      => $rfq->created_by ?? $user->id,
            'branch_id'     => $rfq->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $rfq->company_id ?? $user->company_id,
        ]);
    }
}
