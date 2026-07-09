<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\ActivityLog;
use Illuminate\Database\Eloquent\Collection;

interface ActivityLogRepositoryInterface
{
    public function create(array $data): ActivityLog;

    public function forProject(int $projectId, int $limit = 50): Collection;
}
