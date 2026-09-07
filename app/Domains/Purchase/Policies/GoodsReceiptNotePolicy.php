<?php

namespace App\Domains\Purchase\Policies;

use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Models\User;
use App\Services\Access\AccessService;

class GoodsReceiptNotePolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'grns.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, GoodsReceiptNote $grn): bool
    {
        return $this->access->allows($user, 'grns.view', [
            'tenant_id' => $grn->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'grns.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, GoodsReceiptNote $grn): bool
    {
        return $this->access->allows($user, 'grns.update', [
            'tenant_id' => $grn->tenant_id,
        ]);
    }

    public function delete(User $user, GoodsReceiptNote $grn): bool
    {
        return $this->access->allows($user, 'grns.delete', [
            'tenant_id' => $grn->tenant_id,
        ]);
    }

    public function approve(User $user, GoodsReceiptNote $grn): bool
    {
        return $this->access->allows($user, 'grns.approve', [
            'tenant_id' => $grn->tenant_id,
        ]);
    }
}
