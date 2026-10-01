<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\ProjectReview;
use Illuminate\Database\Eloquent\Collection;

interface ProjectReviewRepositoryInterface
{
    public function getForProject(int $projectId, array $filters = []): Collection;

    public function find(int $id): ?ProjectReview;

    public function hasPendingReview(int $projectId): bool;

    public function create(array $data): ProjectReview;

    public function update(int $id, array $data): ProjectReview;

    public function delete(int $id): bool;
}
