<?php

namespace App\Domains\HRMS\Policies;

use App\Domains\HRMS\Models\PayrollRun;
use App\Models\User;
use App\Services\Access\AccessService;

class PayrollRunPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'hrms.payroll_runs.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'hrms.payroll_runs.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function approve(User $user, mixed $run = null): bool
    {
        $tenantId = $run instanceof PayrollRun ? $run->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.payroll_runs.approve', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function process(User $user, mixed $run = null): bool
    {
        $tenantId = $run instanceof PayrollRun ? $run->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.payroll_runs.process', [
            'tenant_id' => $tenantId,
        ]);
    }
}
