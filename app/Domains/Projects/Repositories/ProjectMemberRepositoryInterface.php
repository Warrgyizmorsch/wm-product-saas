<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\ProjectMember;
use Illuminate\Database\Eloquent\Collection;

interface ProjectMemberRepositoryInterface
{
    public function getForProject(int $projectId): Collection;

    public function getActiveForProject(int $projectId): Collection;

    public function find(int $id): ?ProjectMember;

    public function findForProjectAndUser(int $projectId, int $userId): ?ProjectMember;

    public function existingUserIds(int $projectId): array;

    public function create(array $data): ProjectMember;

    public function update(int $id, array $data): ProjectMember;

    public function delete(int $id): bool;
}
