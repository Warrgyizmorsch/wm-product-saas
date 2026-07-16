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

    public function forSubjects(int $projectId, array $subjectTypeToIds, int $limit = 50): Collection
    {
        $subjectTypeToIds = array_filter($subjectTypeToIds);

        if ($subjectTypeToIds === []) {
            return new Collection();
        }

        return ActivityLog::query()
            ->with('triggeredBy')
            ->where('project_id', $projectId)
            ->where(function ($query) use ($subjectTypeToIds) {
                foreach ($subjectTypeToIds as $subjectType => $ids) {
                    $query->orWhere(function ($subQuery) use ($subjectType, $ids) {
                        $subQuery->where('subject_type', $subjectType)->whereIn('subject_id', $ids);
                    });
                }
            })
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
