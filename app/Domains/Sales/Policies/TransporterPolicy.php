<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\Transporter;
use App\Models\User;
use App\Services\Access\AccessService;

class TransporterPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'sales.dispatches.view', [
            'tenant_id' => $user->tenant_id,
        ]) || $this->access->allows($user, 'sales.orders.view', [
            'tenant_id' => $user->tenant_id,
        ]) || $this->access->allows($user, 'inventory.dispatches.view', [
            'tenant_id' => $user->tenant_id,
        ]) || $this->access->allows($user, 'inventory.warehouses.manage', [
            'tenant_id' => $user->tenant_id,
        ]) || ($user->is_super_admin ?? false);
    }

    public function view(User $user, Transporter $transporter): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'sales.dispatches.create', [
            'tenant_id' => $user->tenant_id,
        ]) || $this->access->allows($user, 'sales.orders.create', [
            'tenant_id' => $user->tenant_id,
        ]) || $this->access->allows($user, 'inventory.dispatches.create', [
            'tenant_id' => $user->tenant_id,
        ]) || $this->access->allows($user, 'inventory.warehouses.manage', [
            'tenant_id' => $user->tenant_id,
        ]) || ($user->is_super_admin ?? false);
    }

    public function update(User $user, Transporter $transporter): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Transporter $transporter): bool
    {
        return $this->create($user);
    }
}
