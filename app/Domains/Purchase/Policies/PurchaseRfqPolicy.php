<?php

namespace App\Domains\Purchase\Policies;

use App\Domains\Purchase\Models\PurchaseRfq;
use App\Models\User;
use App\Services\Access\AccessService;

class PurchaseRfqPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'purchase.rfqs.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, PurchaseRfq $rfq): bool
    {
        return $this->access->allows($user, 'purchase.rfqs.view', [
            'tenant_id' => $rfq->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'purchase.rfqs.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, PurchaseRfq $rfq): bool
    {
        return $this->access->allows($user, 'purchase.rfqs.edit', [
            'tenant_id' => $rfq->tenant_id,
        ]);
    }

    public function delete(User $user, PurchaseRfq $rfq): bool
    {
        return $this->access->allows($user, 'purchase.rfqs.delete', [
            'tenant_id' => $rfq->tenant_id,
        ]);
    }
}
