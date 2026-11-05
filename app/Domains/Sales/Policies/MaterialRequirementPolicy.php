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
        $context = [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ];

        return $this->access->allows($user, 'sales.material_requirements.view', $context)
            || $this->access->allows($user, 'inventory.products.view', $context);
    }

    public function view(User $user, MaterialRequirement $delivery): bool
    {
        $ownerId = $delivery->salesOrder?->sales_person_id
            ?? $delivery->salesOrder?->customer?->crmAccount?->owner_id;

        $context = [
            'tenant_id'     => $delivery->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $delivery->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $delivery->company_id,
        ];

        return $this->access->allows($user, 'sales.material_requirements.view', $context)
            || $this->access->allows($user, 'inventory.products.view', $context);
    }

    public function create(User $user): bool
    {
        $context = [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ];

        return $this->access->allows($user, 'sales.material_requirements.create', $context);
    }

    public function ship(User $user, MaterialRequirement $delivery): bool
    {
        return $this->access->allows($user, 'sales.material_requirements.ship', ['tenant_id' => $delivery->tenant_id])
            || $this->access->allows($user, 'inventory.products.update', ['tenant_id' => $delivery->tenant_id]);
    }

    public function cancel(User $user, MaterialRequirement $delivery): bool
    {
        return $this->access->allows($user, 'sales.material_requirements.cancel', ['tenant_id' => $delivery->tenant_id]);
    }
}
