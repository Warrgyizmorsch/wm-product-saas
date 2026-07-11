<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\TaskList;
use Illuminate\Database\Eloquent\Collection;

interface TaskListRepositoryInterface
{
    public function getForProject(int $projectId): Collection;

    public function find(int $id): ?TaskList;

    public function create(array $data): TaskList;

    public function update(int $id, array $data): TaskList;

    public function delete(int $id): bool;
}
