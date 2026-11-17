<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\DispatchOrder;
use App\Models\User;
use App\Services\Access\AccessService;

class DispatchOrderPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'sales.dispatches.view', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function view(User $user, DispatchOrder $dispatch): bool
    {
        $ownerId = $dispatch->salesOrder?->sales_person_id
            ?? $dispatch->customer?->crmAccount?->owner_id;

        return $this->access->allows($user, 'sales.dispatches.view', [
            'tenant_id'     => $dispatch->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $dispatch->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $dispatch->company_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'sales.dispatches.create', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function update(User $user, DispatchOrder $dispatch): bool
    {
        return $this->access->allows($user, 'sales.dispatches.edit', [
            'tenant_id' => $dispatch->tenant_id,
        ]) || $this->access->allows($user, 'sales.dispatches.create', [
            'tenant_id' => $dispatch->tenant_id,
        ]) || $user->is_super_admin ?? true;
    }
}
