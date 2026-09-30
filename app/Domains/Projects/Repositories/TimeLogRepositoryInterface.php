<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\TimeLog;
use Illuminate\Database\Eloquent\Collection;

interface TimeLogRepositoryInterface
{
    public function getForTask(int $taskId): Collection;

    public function getForProject(int $projectId, array $filters = []): Collection;

    public function getPendingApprovals(array $filters = []): Collection;

    public function find(int $id): ?TimeLog;

    public function create(array $data): TimeLog;

    public function update(int $id, array $data): TimeLog;

    public function delete(int $id): bool;
}
