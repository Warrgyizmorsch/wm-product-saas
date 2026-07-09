<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function getAll(array $filters = []): Collection
    {
        $query = Project::query()->with(['customer', 'owner', 'manager']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('project_code', 'like', "%{$search}%");
            });
        }

        return $query->latest('id')->get();
    }

    public function find(int $id): ?Project
    {
        return Project::query()->with(['customer', 'owner', 'manager'])->find($id);
    }

    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function update(int $id, array $data): Project
    {
        $project = Project::findOrFail($id);
        $project->update($data);

        return $project;
    }

    public function delete(int $id): bool
    {
        $project = Project::findOrFail($id);

        return $project->delete();
    }

    public function latestCode(): ?string
    {
        return Project::query()
            ->withTrashed()
            ->orderByDesc('id')
            ->value('project_code');
    }

    public function countByStatus(string $status): int
    {
        return Project::query()->where('status', $status)->count();
    }
}
