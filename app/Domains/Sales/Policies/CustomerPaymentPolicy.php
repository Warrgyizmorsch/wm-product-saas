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
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function view(User $user, CustomerPayment $payment): bool
    {
        $ownerId = $payment->customer?->crmAccount?->owner_id;
        if ($ownerId !== $user->id && $payment->allocations()->whereHas('salesOrder', fn($soq) => $soq->where('sales_person_id', $user->id))->exists()) {
            $ownerId = $user->id;
        }

        return $this->access->allows($user, 'sales.payments.view', [
            'tenant_id'     => $payment->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $payment->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $payment->company_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'sales.payments.create', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }
}
