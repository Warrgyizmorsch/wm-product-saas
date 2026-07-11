<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\SubTask;
use Illuminate\Database\Eloquent\Collection;

interface SubTaskRepositoryInterface
{
    public function getForTask(int $taskId): Collection;

    public function find(int $id): ?SubTask;

    public function create(array $data): SubTask;

    public function update(int $id, array $data): SubTask;

    public function delete(int $id): bool;
}
