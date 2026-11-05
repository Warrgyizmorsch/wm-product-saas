<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\SalesReturn;
use App\Models\User;
use App\Services\Access\AccessService;

class SalesReturnPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'sales.returns.view', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function view(User $user, SalesReturn $return): bool
    {
        $ownerId = $return->salesOrder?->sales_person_id
            ?? $return->customer?->crmAccount?->owner_id;

        return $this->access->allows($user, 'sales.returns.view', [
            'tenant_id'     => $return->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $return->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $return->company_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'sales.returns.create', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function update(User $user, SalesReturn $return): bool
    {
        $ownerId = $return->salesOrder?->sales_person_id
            ?? $return->customer?->crmAccount?->owner_id;

        $context = [
            'tenant_id'     => $return->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $return->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $return->company_id,
        ];

        return $this->access->allows($user, 'sales.returns.complete', $context)
            || $this->access->allows($user, 'sales.returns.create', $context);
    }

    public function approve(User $user, SalesReturn $return): bool
    {
        $ownerId = $return->salesOrder?->sales_person_id
            ?? $return->customer?->crmAccount?->owner_id;

        $context = [
            'tenant_id'     => $return->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $return->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $return->company_id,
        ];

        return $this->access->allows($user, 'sales.returns.complete', $context)
            || $this->access->allows($user, 'sales.returns.create', $context);
    }

    public function complete(User $user, SalesReturn $return): bool
    {
        $ownerId = $return->salesOrder?->sales_person_id
            ?? $return->customer?->crmAccount?->owner_id;

        $context = [
            'tenant_id'     => $return->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $return->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $return->company_id,
        ];

        return $this->access->allows($user, 'sales.returns.complete', $context);
    }
}
