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
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function view(User $user, VendorBill $bill): bool
    {
        return $this->access->allows($user, 'purchase.bills.view', [
            'tenant_id'     => $bill->tenant_id,
            'owner_id'      => $bill->created_by ?? $user->id,
            'branch_id'     => $bill->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $bill->company_id ?? $user->company_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'purchase.bills.create', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function update(User $user, VendorBill $bill): bool
    {
        return $this->access->allows($user, 'purchase.bills.edit', [
            'tenant_id'     => $bill->tenant_id,
            'owner_id'      => $bill->created_by ?? $user->id,
            'branch_id'     => $bill->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $bill->company_id ?? $user->company_id,
        ]);
    }

    /**
     * Release a bill held by 3-way match so it posts and becomes payable.
     * "Not the person who entered it" is enforced in ThreeWayMatchService.
     */
    public function releaseHold(User $user, ?VendorBill $bill = null): bool
    {
        return $this->access->allows($user, 'purchase.bills.release_hold', [
            'tenant_id'     => $bill?->tenant_id ?? $user->tenant_id,
            'owner_id'      => $bill?->created_by ?? $user->id,
            'branch_id'     => $bill?->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $bill?->company_id ?? $user->company_id,
        ]);
    }

    /** Change the 3-way match mode and tolerances for the tenant. */
    public function configureMatching(User $user): bool
    {
        return $this->access->allows($user, 'purchase.bills.match_configure', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }
}
