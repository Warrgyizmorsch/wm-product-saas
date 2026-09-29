<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\TimeLog;
use Illuminate\Database\Eloquent\Collection;

class TimeLogRepository implements TimeLogRepositoryInterface
{
    public function getForTask(int $taskId): Collection
    {
        return TimeLog::query()
            ->with(['user', 'approver'])
            ->where('task_id', $taskId)
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->get();
    }

    public function getForProject(int $projectId, array $filters = []): Collection
    {
        $query = TimeLog::query()
            ->with(['task', 'user', 'approver'])
            ->where('project_id', $projectId);

        if (!empty($filters['status'])) {
            $query->where('approval_status', $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }

        return $query->orderByDesc('log_date')->orderByDesc('id')->get();
    }

    public function getPendingApprovals(array $filters = []): Collection
    {
        $query = TimeLog::query()
            ->with(['project', 'task', 'user'])
            ->where('approval_status', TimeLog::STATUS_PENDING);

        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->orderBy('log_date')->orderBy('id')->get();
    }

    public function find(int $id): ?TimeLog
    {
        return TimeLog::query()->with(['project', 'task', 'user', 'approver'])->find($id);
    }

    public function create(array $data): TimeLog
    {
        return TimeLog::create($data);
    }

    public function update(int $id, array $data): TimeLog
    {
        $log = TimeLog::findOrFail($id);
        $log->update($data);
        return $log;
    }

    public function delete(int $id): bool
    {
        return (bool) TimeLog::findOrFail($id)->delete();
    }
}
