<?php

namespace App\Domains\CRM\Policies;

use App\Domains\CRM\Models\Lead;
use App\Models\User;
use App\Services\Access\AccessService;

class LeadPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'crm.leads.view', [
            'tenant_id' => $user->tenant_id,
            'owner_id' => $user->id,
            'branch_id' => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id' => $user->company_id,
        ]);
    }

    public function view(User $user, Lead $lead): bool
    {
        $isOwnerOrAccountManager = $user->id === $lead->lead_owner_id
            || ($lead->crmAccount && $user->id === $lead->crmAccount->owner_id)
            || ($lead->crmDeal?->account && $user->id === $lead->crmDeal->account->owner_id);

        return $this->access->allows($user, 'crm.leads.view', [
            'tenant_id' => $lead->tenant_id,
            'owner_id' => $isOwnerOrAccountManager ? $user->id : $lead->lead_owner_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'crm.leads.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, Lead $lead): bool
    {
        $isOwnerOrAccountManager = $user->id === $lead->lead_owner_id
            || ($lead->crmAccount && $user->id === $lead->crmAccount->owner_id)
            || ($lead->crmDeal?->account && $user->id === $lead->crmDeal->account->owner_id);

        return $this->access->allows($user, 'crm.leads.update', [
            'tenant_id' => $lead->tenant_id,
            'owner_id' => $isOwnerOrAccountManager ? $user->id : $lead->lead_owner_id,
        ]);
    }

    public function delete(User $user, Lead $lead): bool
    {
        $isOwnerOrAccountManager = $user->id === $lead->lead_owner_id
            || ($lead->crmAccount && $user->id === $lead->crmAccount->owner_id)
            || ($lead->crmDeal?->account && $user->id === $lead->crmDeal->account->owner_id);

        return $this->access->allows($user, 'crm.leads.delete', [
            'tenant_id' => $lead->tenant_id,
            'owner_id' => $isOwnerOrAccountManager ? $user->id : $lead->lead_owner_id,
        ]);
    }
}
