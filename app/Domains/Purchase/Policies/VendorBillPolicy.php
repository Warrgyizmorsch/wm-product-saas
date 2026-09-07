<?php

namespace App\Domains\Purchase\Policies;

use App\Domains\Purchase\Models\VendorBill;
use App\Models\User;
use App\Services\Access\AccessService;

class VendorBillPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'purchase.bills.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, VendorBill $bill): bool
    {
        return $this->access->allows($user, 'purchase.bills.view', [
            'tenant_id' => $bill->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'purchase.bills.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, VendorBill $bill): bool
    {
        return $this->access->allows($user, 'purchase.bills.edit', [
            'tenant_id' => $bill->tenant_id,
        ]);
    }
}
