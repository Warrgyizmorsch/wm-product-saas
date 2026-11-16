<?php

namespace App\Domains\Projects\Policies;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Models\User;
use App\Services\Access\AccessService;

class ProjectMemberPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function manage(User $user, Project $project): bool
    {
        $isLead = (int) $user->id === (int) $project->owner_id
            || (int) $user->id === (int) $project->manager_id;

        return $this->access->allows($user, 'projects.members.manage', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $isLead ? $user->id : $project->owner_id,
        ]);
    }

    public function update(User $user, ProjectMember $member): bool
    {
        $project = $member->project;
        $isLead = (int) $user->id === (int) $project?->owner_id
            || (int) $user->id === (int) $project?->manager_id;

        return $this->access->allows($user, 'projects.members.manage', [
            'tenant_id' => $member->tenant_id,
            'owner_id'  => $isLead ? $user->id : $project?->owner_id,
        ]);
    }

    public function delete(User $user, ProjectMember $member): bool
    {
        return $this->update($user, $member);
    }
}
