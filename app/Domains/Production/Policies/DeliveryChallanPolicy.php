<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\DeliveryChallan;
use App\Models\User;
use App\Services\Access\AccessService;

class DeliveryChallanPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'production.mes.execute', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, DeliveryChallan $challan): bool
    {
        return $challan->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'production.mes.execute', [
                'tenant_id' => $challan->tenant_id,
            ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'production.mes.execute', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function dispatch(User $user, DeliveryChallan $challan): bool
    {
        return $challan->tenant_id === $user->tenant_id
            && $challan->status !== 'dispatched'
            && $this->access->allows($user, 'production.mes.execute', [
                'tenant_id' => $challan->tenant_id,
            ]);
    }

    public function receive(User $user, DeliveryChallan $challan): bool
    {
        return $challan->tenant_id === $user->tenant_id
            && $challan->status !== 'completed'
            && $this->access->allows($user, 'production.mes.execute', [
                'tenant_id' => $challan->tenant_id,
            ]);
    }
}
