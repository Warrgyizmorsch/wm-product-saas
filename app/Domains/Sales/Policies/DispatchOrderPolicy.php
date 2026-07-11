<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\DispatchOrder;
use App\Models\User;
use App\Services\Access\AccessService;

class DispatchOrderPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'sales.dispatches.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, DispatchOrder $dispatch): bool
    {
        return $this->access->allows($user, 'sales.dispatches.view', [
            'tenant_id' => $dispatch->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'sales.dispatches.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }
}
