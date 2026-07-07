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
        ]);
    }

    public function view(User $user, Lead $lead): bool
    {
        return $lead->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'crm.leads.view', [
                'tenant_id' => $lead->tenant_id,
                'owner_id' => $lead->lead_owner_id,
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
        return $lead->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'crm.leads.update', [
                'tenant_id' => $lead->tenant_id,
                'owner_id' => $lead->lead_owner_id,
            ]);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $lead->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'crm.leads.delete', [
                'tenant_id' => $lead->tenant_id,
                'owner_id' => $lead->lead_owner_id,
            ]);
    }
}
