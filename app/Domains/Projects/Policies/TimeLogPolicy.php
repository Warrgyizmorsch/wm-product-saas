<?php

namespace App\Domains\Projects\Policies;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\TimeLog;
use App\Models\User;
use App\Services\Access\AccessService;

class TimeLogPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user, Project $project): bool
    {
        return $this->access->allows($user, 'projects.timetracking.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]) || $this->access->allows($user, 'projects.tasks.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->access->allows($user, 'projects.timetracking.log', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]) || $this->access->allows($user, 'projects.tasks.create', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }

    public function view(User $user, TimeLog $timeLog): bool
    {
        return $this->authorizeOnLog($user, $timeLog, 'projects.timetracking.view')
            || $this->authorizeOnLog($user, $timeLog, 'projects.tasks.view');
    }

    public function update(User $user, TimeLog $timeLog): bool
    {
        return $this->authorizeOnLog($user, $timeLog, 'projects.timetracking.log')
            || $this->authorizeOnLog($user, $timeLog, 'projects.tasks.update');
    }

    public function delete(User $user, TimeLog $timeLog): bool
    {
        return $this->authorizeOnLog($user, $timeLog, 'projects.timetracking.log')
            || $this->authorizeOnLog($user, $timeLog, 'projects.tasks.delete');
    }

    public function approve(User $user, TimeLog $timeLog): bool
    {
        $project = $timeLog->project;

        return $this->access->allows($user, 'projects.timetracking.approve', [
            'tenant_id' => $timeLog->tenant_id,
            'owner_id'  => $project?->owner_id,
        ]) || $this->access->allows($user, 'projects.projects.update', [
            'tenant_id' => $timeLog->tenant_id,
            'owner_id'  => $project?->owner_id,
        ]);
    }

    public function approveAny(User $user): bool
    {
        return $this->access->allows($user, 'projects.timetracking.approve', [
            'tenant_id' => require_tenant_id(),
        ]) || $this->access->allows($user, 'projects.projects.update', [
            'tenant_id' => require_tenant_id(),
        ]);
    }

    private function authorizeOnLog(User $user, TimeLog $timeLog, string $permission): bool
    {
        // Try user grant first (user owning the time entry)
        if ($this->access->allows($user, $permission, [
            'tenant_id' => $timeLog->tenant_id,
            'owner_id'  => $timeLog->user_id,
        ])) {
            return true;
        }

        // Fall back to project lead grant (project owner)
        return $this->access->allows($user, $permission, [
            'tenant_id' => $timeLog->tenant_id,
            'owner_id'  => $timeLog->project?->owner_id,
        ]);
    }
}
