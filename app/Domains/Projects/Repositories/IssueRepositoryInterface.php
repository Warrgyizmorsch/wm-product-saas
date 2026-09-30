<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface IssueRepositoryInterface
{
    public function getForProject(int $projectId, array $filters = []): Collection;

    public function getForTask(int $taskId): Collection;

    public function find(int $id): ?Issue;

    public function nextIssueNumber(Project $project): string;

    public function create(array $data): Issue;

    public function update(int $id, array $data): Issue;

    public function delete(int $id): bool;
}
