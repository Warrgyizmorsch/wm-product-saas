<?php

namespace App\Domains\CRM\Policies;

use App\Domains\CRM\Models\Customer;
use App\Models\User;
use App\Services\Access\AccessService;

class CustomerPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'crm.customers.view', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function view(User $user, Customer $customer): bool
    {
        $ownerId = $customer->crmAccount?->owner_id;

        return $this->access->allows($user, 'crm.customers.view', [
            'tenant_id'     => $customer->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $customer->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $customer->company_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'crm.customers.create', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function update(User $user, Customer $customer): bool
    {
        $ownerId = $customer->crmAccount?->owner_id;

        return $this->access->allows($user, 'crm.customers.update', [
            'tenant_id'     => $customer->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $customer->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $customer->company_id,
        ]);
    }

    public function delete(User $user, Customer $customer): bool
    {
        $ownerId = $customer->crmAccount?->owner_id;

        return $this->access->allows($user, 'crm.customers.delete', [
            'tenant_id'     => $customer->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $customer->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $customer->company_id,
        ]);
    }
}
