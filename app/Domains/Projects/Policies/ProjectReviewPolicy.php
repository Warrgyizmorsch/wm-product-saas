<?php

namespace App\Domains\Projects\Policies;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectReview;
use App\Models\User;
use App\Services\Access\AccessService;

class ProjectReviewPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user, Project $project): bool
    {
        return $this->access->allows($user, 'projects.reviews.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }

    public function view(User $user, ProjectReview $review): bool
    {
        $project = $review->project;

        return $this->access->allows($user, 'projects.reviews.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }

    public function create(User $user, Project $project): bool
    {
        if ($project->isClosed()) {
            return false;
        }

        return $this->access->allows($user, 'projects.reviews.create', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }

    public function signoff(User $user, ProjectReview $review): bool
    {
        $project = $review->project;

        if ($project?->isClosed()) {
            return false;
        }

        return $this->access->allows($user, 'projects.reviews.signoff', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }

    public function delete(User $user, ProjectReview $review): bool
    {
        $project = $review->project;

        if ($project?->isClosed()) {
            return false;
        }

        return $this->access->allows($user, 'projects.reviews.signoff', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $project->owner_id,
        ]);
    }
}
