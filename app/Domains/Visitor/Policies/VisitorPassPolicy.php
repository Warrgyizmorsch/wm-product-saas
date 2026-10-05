<?php

namespace App\Domains\Visitor\Policies;

use App\Domains\Visitor\Models\VisitorPass;
use App\Models\User;
use App\Services\Access\AccessService;

class VisitorPassPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'visitor.passes.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, VisitorPass $pass): bool
    {
        return $this->access->allows($user, 'visitor.passes.view', [
            'tenant_id' => $pass->tenant_id,
            'host_id'   => $pass->host_user_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'visitor.passes.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function checkIn(User $user, VisitorPass $pass): bool
    {
        return $this->access->allows($user, 'visitor.passes.checkin', [
            'tenant_id' => $pass->tenant_id,
        ]);
    }

    public function update(User $user, VisitorPass $pass): bool
    {
        return $this->access->allows($user, 'visitor.passes.create', [
            'tenant_id' => $pass->tenant_id,
        ]) || $this->access->allows($user, 'visitor.passes.view', [
            'tenant_id' => $pass->tenant_id,
            'host_id'   => $pass->host_user_id,
        ]);
    }

    public function checkOut(User $user, VisitorPass $pass): bool
    {
        return $this->access->allows($user, 'visitor.passes.checkout', [
            'tenant_id' => $pass->tenant_id,
        ]);
    }
}
