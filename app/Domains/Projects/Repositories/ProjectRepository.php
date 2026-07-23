<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectRepository implements ProjectRepositoryInterface
{
    /**
     * Whitelist of externally-facing sort keys (as used in ?sort=) to the
     * fully-qualified column they map to. Never resolve a sort column from
     * the request directly — always go through this map.
     */
    private const SORTABLE_COLUMNS = [
        'code'       => 'projects.project_code',
        'name'       => 'projects.name',
        'client'     => 'customers.name',
        'owner'      => 'users.name',
        'priority'   => 'projects.priority',
        'status'     => 'projects.status',
        'start_date' => 'projects.start_date',
        'end_date'   => 'projects.end_date',
        'created_at' => 'projects.created_at',
    ];

    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Project::query()->with(['customer', 'owner', 'manager']);

        if (!empty($filters['status'])) {
            $query->where('projects.status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('projects.name', 'like', "%{$search}%")
                  ->orWhere('projects.project_code', 'like', "%{$search}%");
            });
        }

        $this->applySort($query, $filters['sort'] ?? null, $filters['direction'] ?? null);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Applies server-side sorting from a whitelisted column map, joining
     * only the relation table a given sort actually needs. Falls back to
     * newest-first when no (or an unknown) sort column is requested.
     */
    private function applySort(Builder $query, ?string $sort, ?string $direction): void
    {
        if ($sort === null || !array_key_exists($sort, self::SORTABLE_COLUMNS)) {
            $query->orderByDesc('projects.created_at');

            return;
        }

        $direction = strtolower($direction ?? '') === 'desc' ? 'desc' : 'asc';

        $query->select('projects.*');

        if ($sort === 'client') {
            $query->leftJoin('customers', 'customers.id', '=', 'projects.customer_id');
        } elseif ($sort === 'owner') {
            $query->leftJoin('users', 'users.id', '=', 'projects.owner_id');
        }

        $query->orderBy(self::SORTABLE_COLUMNS[$sort], $direction);
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

    public function countAll(): int
    {
        return Project::query()->count();
    }

    public function countByStatus(string $status): int
    {
        return Project::query()->where('status', $status)->count();
    }
}
