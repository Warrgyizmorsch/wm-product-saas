<?php

namespace App\Domains\Projects\Policies;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Services\Access\AccessService;

class MilestonePolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function manage(User $user, Project $project): bool
    {
        return $this->access->allows($user, 'projects.milestones.manage', [
            'tenant_id' => $project->tenant_id,
            'owner_id' => $project->owner_id,
        ]);
    }

    public function update(User $user, Milestone $milestone): bool
    {
        return $this->access->allows($user, 'projects.milestones.manage', [
            'tenant_id' => $milestone->tenant_id,
            'owner_id' => $milestone->project?->owner_id,
        ]);
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return $this->update($user, $milestone);
    }
}
