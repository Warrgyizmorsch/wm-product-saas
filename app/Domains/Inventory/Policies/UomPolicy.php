<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\Uom;
use App\Models\User;
use App\Services\Access\AccessService;

class UomPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'inventory.uoms.manage', [
            'tenant_id' => $user->tenant_id,
        ]);
    }
}
