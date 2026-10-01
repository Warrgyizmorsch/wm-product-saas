<?php

namespace App\Domains\Projects\Policies;

use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Services\Access\AccessService;

class ChangeRequestPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user, Project $project): bool
    {
        return $this->access->allows($user, 'projects.changerequests.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }

    public function view(User $user, ChangeRequest $cr): bool
    {
        $project = $cr->project;

        return $this->access->allows($user, 'projects.changerequests.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->access->allows($user, 'projects.changerequests.create', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }

    public function approve(User $user, ChangeRequest $cr): bool
    {
        $project = $cr->project;

        return $this->access->allows($user, 'projects.changerequests.approve', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }

    public function markImplemented(User $user, ChangeRequest $cr): bool
    {
        $project = $cr->project;

        return $this->access->allows($user, 'projects.changerequests.approve', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }

    public function implement(User $user, ChangeRequest $cr): bool
    {
        return $this->markImplemented($user, $cr);
    }

    public function delete(User $user, ChangeRequest $cr): bool
    {
        $project = $cr->project;

        return $this->access->allows($user, 'projects.changerequests.create', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }
}
