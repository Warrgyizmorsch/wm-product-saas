<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\MaterialRequirement;
use App\Models\User;
use App\Services\Access\AccessService;

class MaterialRequirementPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'sales.material_requirements.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, MaterialRequirement $delivery): bool
    {
        return $this->access->allows($user, 'sales.material_requirements.view', [
            'tenant_id' => $delivery->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'sales.material_requirements.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function ship(User $user, MaterialRequirement $delivery): bool
    {
        return $this->access->allows($user, 'sales.material_requirements.ship', [
            'tenant_id' => $delivery->tenant_id,
        ]);
    }

    public function cancel(User $user, MaterialRequirement $delivery): bool
    {
        return $this->access->allows($user, 'sales.material_requirements.cancel', [
            'tenant_id' => $delivery->tenant_id,
        ]);
    }
}
