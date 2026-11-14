<?php

namespace App\Domains\CRM\Policies;

use App\Domains\CRM\Models\CrmDeal;
use App\Models\User;
use App\Services\Access\AccessService;

class CrmDealPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'crm.deals.view', [
            'tenant_id' => $user->tenant_id,
            'owner_id' => $user->id,
            'branch_id' => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id' => $user->company_id,
        ]);
    }

    public function view(User $user, CrmDeal $deal): bool
    {
        $isOwnerOrAccountManager = $user->id === $deal->owner_id
            || ($deal->account && $user->id === $deal->account->owner_id);

        return $this->access->allows($user, 'crm.deals.view', [
            'tenant_id' => $deal->tenant_id,
            'owner_id' => $isOwnerOrAccountManager ? $user->id : $deal->owner_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'crm.deals.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, CrmDeal $deal): bool
    {
        $isOwnerOrAccountManager = $user->id === $deal->owner_id
            || ($deal->account && $user->id === $deal->account->owner_id);

        return $this->access->allows($user, 'crm.deals.update', [
            'tenant_id' => $deal->tenant_id,
            'owner_id' => $isOwnerOrAccountManager ? $user->id : $deal->owner_id,
        ]);
    }

    public function delete(User $user, CrmDeal $deal): bool
    {
        $isOwnerOrAccountManager = $user->id === $deal->owner_id
            || ($deal->account && $user->id === $deal->account->owner_id);

        return $this->access->allows($user, 'crm.deals.delete', [
            'tenant_id' => $deal->tenant_id,
            'owner_id' => $isOwnerOrAccountManager ? $user->id : $deal->owner_id,
        ]);
    }
}
