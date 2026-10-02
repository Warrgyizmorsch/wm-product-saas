<?php

namespace App\Domains\HRMS\Policies;

use App\Models\User;
use App\Services\Access\AccessService;

class SopPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'hrms.sop.view', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.self_service.use', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.sop.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'hrms.sop.create', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.sop.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->access->allows($user, 'hrms.sop.update', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.sop.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->access->allows($user, 'hrms.sop.delete', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.sop.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function manage(User $user): bool
    {
        return $this->access->allows($user, 'hrms.sop.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }
}
