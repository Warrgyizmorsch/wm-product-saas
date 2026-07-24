<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\ProjectMember;
use Illuminate\Database\Eloquent\Collection;

class ProjectMemberRepository implements ProjectMemberRepositoryInterface
{
    public function getForProject(int $projectId): Collection
    {
        return ProjectMember::query()
            ->with('user')
            ->where('project_id', $projectId)
            ->latest('id')
            ->get();
    }

    public function getActiveForProject(int $projectId): Collection
    {
        return ProjectMember::query()
            ->with('user')
            ->where('project_id', $projectId)
            ->where('is_active', true)
            ->latest('id')
            ->get();
    }

    public function find(int $id): ?ProjectMember
    {
        return ProjectMember::query()->with(['user', 'project'])->find($id);
    }

    public function findForProjectAndUser(int $projectId, int $userId): ?ProjectMember
    {
        return ProjectMember::query()
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();
    }

    public function existingUserIds(int $projectId): array
    {
        return ProjectMember::query()
            ->where('project_id', $projectId)
            ->pluck('user_id')
            ->all();
    }

    public function create(array $data): ProjectMember
    {
        return ProjectMember::create($data);
    }

    public function update(int $id, array $data): ProjectMember
    {
        $member = ProjectMember::findOrFail($id);
        $member->update($data);

        return $member;
    }

    public function delete(int $id): bool
    {
        $member = ProjectMember::findOrFail($id);

        return $member->delete();
    }
}
