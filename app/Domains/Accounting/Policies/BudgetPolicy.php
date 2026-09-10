<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\Budget;
use App\Models\User;
use App\Services\Access\AccessService;

class BudgetPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'accounting.budgets.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, Budget $budget): bool
    {
        return $this->access->allows($user, 'accounting.budgets.view', [
            'tenant_id' => $budget->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'accounting.budgets.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, Budget $budget): bool
    {
        return $this->access->allows($user, 'accounting.budgets.update', [
            'tenant_id' => $budget->tenant_id,
        ]);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->access->allows($user, 'accounting.budgets.delete', [
            'tenant_id' => $budget->tenant_id,
        ]);
    }

    public function approve(User $user, Budget $budget): bool
    {
        return $this->access->allows($user, 'accounting.budgets.approve', [
            'tenant_id' => $budget->tenant_id,
        ]);
    }
}
