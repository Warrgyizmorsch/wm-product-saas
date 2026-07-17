<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class MilestoneRepository implements MilestoneRepositoryInterface
{
    public function getForProject(int $projectId): Collection
    {
        return Milestone::query()
            ->with('owner')
            ->withCount([
                'taskLists',
                'tasks',
                'tasks as completed_tasks_count' => function ($query) {
                    $query->where('status', Task::STATUS_COMPLETED);
                },
            ])
            ->where('project_id', $projectId)
            ->latest('id')
            ->get();
    }

    public function paginateAll(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Milestone::query()
            ->with(['project', 'owner']);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('project', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('project_code', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        return $query
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?Milestone
    {
        return Milestone::query()->with(['owner', 'project'])->find($id);
    }

    public function create(array $data): Milestone
    {
        return Milestone::create($data);
    }

    public function update(int $id, array $data): Milestone
    {
        $milestone = Milestone::findOrFail($id);
        $milestone->update($data);

        return $milestone;
    }

    public function delete(int $id): bool
    {
        $milestone = Milestone::findOrFail($id);

        return $milestone->delete();
    }
}
