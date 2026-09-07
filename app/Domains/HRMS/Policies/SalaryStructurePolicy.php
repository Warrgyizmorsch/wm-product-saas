<?php

namespace App\Domains\HRMS\Policies;

use App\Domains\HRMS\Models\SalaryStructure;
use App\Models\User;
use App\Services\Access\AccessService;

class SalaryStructurePolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'hrms.salary_structures.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'hrms.salary_structures.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, mixed $salaryStructure = null): bool
    {
        $tenantId = $salaryStructure instanceof SalaryStructure ? $salaryStructure->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.salary_structures.update', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function delete(User $user, mixed $salaryStructure = null): bool
    {
        $tenantId = $salaryStructure instanceof SalaryStructure ? $salaryStructure->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.salary_structures.delete', [
            'tenant_id' => $tenantId,
        ]);
    }
}
