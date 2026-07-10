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
        return $project->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'projects.projects.view', [
                'tenant_id' => $project->tenant_id,
                'owner_id' => $project->owner_id,
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
        return $project->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'projects.projects.update', [
                'tenant_id' => $project->tenant_id,
                'owner_id' => $project->owner_id,
            ]);
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'projects.projects.delete', [
                'tenant_id' => $project->tenant_id,
                'owner_id' => $project->owner_id,
            ]);
    }
}
