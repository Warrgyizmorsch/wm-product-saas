<?php

namespace App\Domains\CRM\Policies;

use App\Domains\CRM\Models\DealStatus;
use App\Models\User;
use App\Services\Access\AccessService;

class DealStatusPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'crm.deals.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'crm.deals.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, DealStatus $dealStatus): bool
    {
        return $this->access->allows($user, 'crm.deals.update', [
            'tenant_id' => $dealStatus->tenant_id,
        ]);
    }

    public function delete(User $user, DealStatus $dealStatus): bool
    {
        return $this->access->allows($user, 'crm.deals.delete', [
            'tenant_id' => $dealStatus->tenant_id,
        ]);
    }
}
