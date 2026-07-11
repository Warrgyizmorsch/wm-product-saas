<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\SalesReturn;
use App\Models\User;
use App\Services\Access\AccessService;

class SalesReturnPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'sales.returns.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, SalesReturn $return): bool
    {
        return $this->access->allows($user, 'sales.returns.view', [
            'tenant_id' => $return->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'sales.returns.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function complete(User $user, SalesReturn $return): bool
    {
        return $this->access->allows($user, 'sales.returns.complete', [
            'tenant_id' => $return->tenant_id,
        ]);
    }
}
