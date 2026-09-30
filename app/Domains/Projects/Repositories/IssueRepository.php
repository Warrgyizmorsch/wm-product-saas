<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class IssueRepository implements IssueRepositoryInterface
{
    public function getForProject(int $projectId, array $filters = []): Collection
    {
        $query = Issue::with(['reporter', 'assignee', 'task'])
            ->where('project_id', $projectId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (!empty($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('issue_number', 'like', $term)
                  ->orWhere('title', 'like', $term)
                  ->orWhere('description', 'like', $term);
            });
        }

        return $query->latest('id')->get();
    }

    public function getForTask(int $taskId): Collection
    {
        return Issue::with(['reporter', 'assignee'])
            ->where('task_id', $taskId)
            ->latest('id')
            ->get();
    }

    public function find(int $id): ?Issue
    {
        return Issue::with(['project', 'task', 'reporter', 'assignee', 'documents'])->find($id);
    }

    public function nextIssueNumber(Project $project): string
    {
        $lastIssue = Issue::withTrashed()
            ->where('project_id', $project->id)
            ->latest('id')
            ->first();

        $nextSeq = 1;
        if ($lastIssue && preg_match('/ISS-(\d+)$/', $lastIssue->issue_number, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-ISS-%03d', $project->project_code, $nextSeq);
    }

    public function create(array $data): Issue
    {
        return Issue::create($data);
    }

    public function update(int $id, array $data): Issue
    {
        $issue = Issue::findOrFail($id);
        $issue->update($data);
        return $issue->fresh(['project', 'task', 'reporter', 'assignee']);
    }

    public function delete(int $id): bool
    {
        $issue = Issue::findOrFail($id);
        return (bool) $issue->delete();
    }
}
