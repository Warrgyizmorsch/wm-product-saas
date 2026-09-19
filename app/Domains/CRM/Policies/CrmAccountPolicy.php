<?php

namespace App\Domains\CRM\Policies;

use App\Domains\CRM\Models\CrmAccount;
use App\Models\User;
use App\Services\Access\AccessService;

class CrmAccountPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'crm.customers.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, CrmAccount $account): bool
    {
        return $this->access->allows($user, 'crm.customers.view', [
            'tenant_id' => $account->tenant_id,
            'owner_id'  => $account->owner_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'crm.customers.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, CrmAccount $account): bool
    {
        return $this->access->allows($user, 'crm.customers.update', [
            'tenant_id' => $account->tenant_id,
            'owner_id'  => $account->owner_id,
        ]);
    }

    public function delete(User $user, CrmAccount $account): bool
    {
        return $this->access->allows($user, 'crm.customers.delete', [
            'tenant_id' => $account->tenant_id,
            'owner_id'  => $account->owner_id,
        ]);
    }
}
