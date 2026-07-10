<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Repositories\ProjectMemberRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectMemberService
{
    public function __construct(
        private readonly ProjectMemberRepositoryInterface $members,
    ) {
    }

    public function list(Project $project): Collection
    {
        return $this->members->getForProject($project->id);
    }

    public function find(int $id): ?ProjectMember
    {
        return $this->members->find($id);
    }

    public function add(Project $project, array $data): ProjectMember
    {
        $this->guardAgainstDuplicate($project, (int) $data['user_id']);

        return DB::transaction(function () use ($project, $data) {
            $data['project_id'] = $project->id;
            $data['tenant_id'] = $project->tenant_id;

            return $this->members->create($data);
        });
    }

    public function update(ProjectMember $member, array $data): ProjectMember
    {
        if (isset($data['user_id']) && (int) $data['user_id'] !== $member->user_id) {
            $this->guardAgainstDuplicate($member->project, (int) $data['user_id']);
        }

        return $this->members->update($member->id, $data);
    }

    public function setActive(ProjectMember $member, bool $active): ProjectMember
    {
        return $this->members->update($member->id, ['is_active' => $active]);
    }

    public function remove(ProjectMember $member): bool
    {
        return $this->members->delete($member->id);
    }

    private function guardAgainstDuplicate(Project $project, int $userId): void
    {
        if ($this->members->findForProjectAndUser($project->id, $userId)) {
            throw ValidationException::withMessages([
                'user_id' => __('projects.member_duplicate'),
            ]);
        }
    }
}
