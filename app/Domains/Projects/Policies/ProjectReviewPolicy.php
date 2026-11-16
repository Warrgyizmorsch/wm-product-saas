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
        $isMemberOrLead = (int) $user->id === (int) $project->owner_id
            || (int) $user->id === (int) $project->manager_id
            || ($project->relationLoaded('members')
                ? $project->members->where('user_id', $user->id)->where('is_active', true)->isNotEmpty()
                : $project->members()->where('user_id', $user->id)->where('is_active', true)->exists());

        return $this->access->allows($user, 'projects.reviews.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $isMemberOrLead ? $user->id : $project->owner_id,
        ]);
    }

    public function view(User $user, ProjectReview $review): bool
    {
        $project = $review->project;
        $isParty = (int) $user->id === (int) $review->reviewer_id
            || (int) $user->id === (int) $project?->owner_id
            || (int) $user->id === (int) $project?->manager_id
            || ($project && ($project->relationLoaded('members')
                ? $project->members->where('user_id', $user->id)->where('is_active', true)->isNotEmpty()
                : $project->members()->where('user_id', $user->id)->where('is_active', true)->exists()));

        return $this->access->allows($user, 'projects.reviews.view', [
            'tenant_id' => $project?->tenant_id ?? $review->tenant_id,
            'owner_id'  => $isParty ? $user->id : $project?->owner_id,
        ]);
    }

    public function create(User $user, Project $project): bool
    {
        if ($project->isClosed()) {
            return false;
        }

        $isLead = (int) $user->id === (int) $project->owner_id
            || (int) $user->id === (int) $project->manager_id;

        return $this->access->allows($user, 'projects.reviews.create', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $isLead ? $user->id : $project->owner_id,
        ]);
    }

    public function signoff(User $user, ProjectReview $review): bool
    {
        $project = $review->project;

        if ($project?->isClosed()) {
            return false;
        }

        $isParty = (int) $user->id === (int) $review->reviewer_id
            || (int) $user->id === (int) $project?->owner_id
            || (int) $user->id === (int) $project?->manager_id;

        return $this->access->allows($user, 'projects.reviews.signoff', [
            'tenant_id' => $project?->tenant_id ?? $review->tenant_id,
            'owner_id'  => $isParty ? $user->id : $project?->owner_id,
        ]);
    }

    public function delete(User $user, ProjectReview $review): bool
    {
        $project = $review->project;

        if ($project?->isClosed()) {
            return false;
        }

        $isLead = (int) $user->id === (int) $project?->owner_id
            || (int) $user->id === (int) $project?->manager_id;

        return $this->access->allows($user, 'projects.reviews.signoff', [
            'tenant_id' => $project?->tenant_id ?? $review->tenant_id,
            'owner_id'  => $isLead ? $user->id : $project?->owner_id,
        ]);
    }
}
