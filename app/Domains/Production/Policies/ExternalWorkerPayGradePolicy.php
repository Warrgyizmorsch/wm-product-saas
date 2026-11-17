<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Models\User;

class ExternalWorkerPayGradePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExternalWorkerPayGrade $payGrade): bool
    {
        return (int) $payGrade->tenant_id === (int) $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasProductionPermission('production.work_center.manage');
    }

    public function update(User $user, ExternalWorkerPayGrade $payGrade): bool
    {
        return $user->hasProductionPermission('production.work_center.manage', $payGrade->tenant_id)
            && (int) $payGrade->tenant_id === (int) $user->tenant_id;
    }

    public function delete(User $user, ExternalWorkerPayGrade $payGrade): bool
    {
        return $user->hasProductionPermission('production.work_center.manage', $payGrade->tenant_id)
            && (int) $payGrade->tenant_id === (int) $user->tenant_id;
    }
}
