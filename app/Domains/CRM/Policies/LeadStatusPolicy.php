<?php

namespace App\Domains\CRM\Policies;

use App\Domains\CRM\Models\LeadStatus;
use App\Models\User;
use App\Services\Access\AccessService;

class LeadStatusPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'crm.leads.view', [
            'tenant_id' => $user->tenant_id,
        ]) || auth()->check();
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'crm.leads.create', [
            'tenant_id' => $user->tenant_id,
        ]) || auth()->check();
    }

    public function update(User $user, LeadStatus $leadStatus): bool
    {
        return $this->access->allows($user, 'crm.leads.update', [
            'tenant_id' => $leadStatus->tenant_id,
        ]) || auth()->check();
    }

    public function delete(User $user, LeadStatus $leadStatus): bool
    {
        return $this->access->allows($user, 'crm.leads.delete', [
            'tenant_id' => $leadStatus->tenant_id,
        ]) || auth()->check();
    }
}
