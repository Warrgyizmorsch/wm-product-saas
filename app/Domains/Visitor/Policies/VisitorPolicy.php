<?php

namespace App\Domains\Visitor\Policies;

use App\Domains\Visitor\Models\Visitor;
use App\Models\User;
use App\Services\Access\AccessService;

class VisitorPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'visitor.visitors.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, Visitor $visitor): bool
    {
        return $this->access->allows($user, 'visitor.visitors.view', [
            'tenant_id' => $visitor->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'visitor.visitors.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, Visitor $visitor): bool
    {
        return $this->access->allows($user, 'visitor.visitors.update', [
            'tenant_id' => $visitor->tenant_id,
        ]);
    }

    public function delete(User $user, Visitor $visitor): bool
    {
        return $this->access->allows($user, 'visitor.visitors.delete', [
            'tenant_id' => $visitor->tenant_id,
        ]);
    }
}
