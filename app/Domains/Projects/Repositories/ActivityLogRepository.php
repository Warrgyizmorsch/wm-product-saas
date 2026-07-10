<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\ActivityLog;
use Illuminate\Database\Eloquent\Collection;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function create(array $data): ActivityLog
    {
        return ActivityLog::create($data);
    }

    public function forProject(int $projectId, int $limit = 50): Collection
    {
        return ActivityLog::query()
            ->with('triggeredBy')
            ->where('project_id', $projectId)
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
