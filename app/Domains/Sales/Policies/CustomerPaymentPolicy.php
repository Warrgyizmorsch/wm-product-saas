<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\CustomerPayment;
use App\Models\User;
use App\Services\Access\AccessService;

class CustomerPaymentPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'sales.payments.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, CustomerPayment $payment): bool
    {
        return $this->access->allows($user, 'sales.payments.view', [
            'tenant_id' => $payment->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'sales.payments.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }
}
