<?php

namespace App\Domains\Projects\Policies;

use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Services\Access\AccessService;

class ProjectPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'projects.projects.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, Project $project): bool
    {
        $ownerId = $this->isMemberOrLead($user, $project) ? $user->id : $project->owner_id;

        return $this->access->allows($user, 'projects.projects.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id' => $ownerId,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'projects.projects.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, Project $project): bool
    {
        $ownerId = $this->isLead($user, $project) ? $user->id : $project->owner_id;

        return $this->access->allows($user, 'projects.projects.update', [
            'tenant_id' => $project->tenant_id,
            'owner_id' => $ownerId,
        ]);
    }

    public function delete(User $user, Project $project): bool
    {
        if ($project->isClosed()) {
            return false;
        }

        $ownerId = $this->isLead($user, $project) ? $user->id : $project->owner_id;

        return $this->access->allows($user, 'projects.projects.delete', [
            'tenant_id' => $project->tenant_id,
            'owner_id' => $ownerId,
        ]);
    }

    public function close(User $user, Project $project): bool
    {
        if ($project->isClosed()) {
            return false;
        }

        $ownerId = $this->isLead($user, $project) ? $user->id : $project->owner_id;

        return $this->access->allows($user, 'projects.projects.close', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $ownerId,
        ]) || $this->access->allows($user, 'projects.projects.update', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $ownerId,
        ]);
    }

    public function viewBilling(User $user, Project $project): bool
    {
        $ownerId = $this->isMemberOrLead($user, $project) ? $user->id : $project->owner_id;

        return $this->access->allows($user, 'projects.billing.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $ownerId,
        ]) || $this->access->allows($user, 'projects.projects.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $ownerId,
        ]);
    }

    public function generateInvoice(User $user, Project $project): bool
    {
        if ($project->isClosed()) {
            return false;
        }

        $ownerId = $this->isLead($user, $project) ? $user->id : $project->owner_id;

        $hasProjectScope = $this->access->allows($user, 'projects.billing.generate_invoice', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $ownerId,
        ]) || $this->access->allows($user, 'projects.projects.update', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $ownerId,
        ]);

        $hasSalesScope = $this->access->allows($user, 'sales.invoices.create', [
            'tenant_id' => $project->tenant_id,
        ]);

        return $hasProjectScope && $hasSalesScope;
    }

    private function isMemberOrLead(User $user, Project $project): bool
    {
        return (int) $user->id === (int) $project->owner_id
            || (int) $user->id === (int) $project->manager_id
            || ($project->relationLoaded('members')
                ? $project->members->where('user_id', $user->id)->where('is_active', true)->isNotEmpty()
                : $project->members()->where('user_id', $user->id)->where('is_active', true)->exists());
    }

    private function isLead(User $user, Project $project): bool
    {
        return (int) $user->id === (int) $project->owner_id
            || (int) $user->id === (int) $project->manager_id;
    }
}
