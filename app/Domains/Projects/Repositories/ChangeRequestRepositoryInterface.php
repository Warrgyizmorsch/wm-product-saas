<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ChangeRequestRepositoryInterface
{
    public function getForProject(int $projectId, array $filters = []): Collection;

    public function find(int $id): ?ChangeRequest;

    public function nextCrNumber(Project $project): string;

    public function create(array $data): ChangeRequest;

    public function update(int $id, array $data): ChangeRequest;

    public function delete(int $id): bool;
}
