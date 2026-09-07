<?php

namespace App\Domains\HRMS\Policies;

use App\Domains\HRMS\Models\EmployeeExit;
use App\Models\User;
use App\Services\Access\AccessService;

class EmployeeExitPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'hrms.employee_exits.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'hrms.employee_exits.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function approve(User $user, mixed $exit = null): bool
    {
        $tenantId = $exit instanceof EmployeeExit ? $exit->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.employee_exits.approve', [
            'tenant_id' => $tenantId,
        ]);
    }
}
