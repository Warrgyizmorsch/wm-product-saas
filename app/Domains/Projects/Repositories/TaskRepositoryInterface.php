<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\Task;
use Illuminate\Database\Eloquent\Collection;

interface TaskRepositoryInterface
{
    public function getForProject(int $projectId, array $filters = []): Collection;

    public function getForTaskList(int $taskListId): Collection;

    public function find(int $id): ?Task;

    public function create(array $data): Task;

    public function update(int $id, array $data): Task;

    public function delete(int $id): bool;

    public function latestCodeForProject(int $projectId): ?string;

    public function deleteAllForTaskList(int $taskListId): void;
}
