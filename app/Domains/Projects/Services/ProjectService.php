<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Repositories\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    /**
     * Allowed forward status transitions. Closed is only reachable via the
     * closure workflow (a later milestone), never through a plain update.
     */
    private const TRANSITIONS = [
        Project::STATUS_DRAFT     => [Project::STATUS_ACTIVE],
        Project::STATUS_ACTIVE    => [Project::STATUS_ON_HOLD, Project::STATUS_COMPLETED],
        Project::STATUS_ON_HOLD   => [Project::STATUS_ACTIVE],
        Project::STATUS_COMPLETED => [],
        Project::STATUS_CLOSED    => [],
    ];

    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly ActivityLogService $activity,
    ) {
    }

    public function list(array $filters = []): Collection
    {
        return $this->projects->getAll($filters);
    }

    public function find(int $id): ?Project
    {
        return $this->projects->find($id);
    }

    public function summary(): array
    {
        return [
            'total'  => $this->projects->getAll()->count(),
            'active' => $this->projects->countByStatus(Project::STATUS_ACTIVE),
        ];
    }

    public function getNextProjectCode(): string
    {
        $latest = $this->projects->latestCode();

        $nextSeq = $latest ? intval(str_replace('PRJ-', '', $latest)) + 1 : 1;

        return 'PRJ-' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $data['project_code'] = $this->getNextProjectCode();

            $project = $this->projects->create($data);

            $this->activity->record(
                $project,
                'project.created',
                "Project {$project->project_code} created",
                "Project '{$project->name}' created with status '{$project->status}'",
                $project,
            );

            return $project;
        });
    }

    public function update(Project $project, array $data): Project
    {
        $oldStatus = $project->status;
        $newStatus = $data['status'] ?? $oldStatus;

        if ($newStatus !== $oldStatus && !in_array($newStatus, self::TRANSITIONS[$oldStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "A project cannot move from '{$oldStatus}' to '{$newStatus}'.",
            ]);
        }

        return DB::transaction(function () use ($project, $data, $oldStatus, $newStatus) {
            $project = $this->projects->update($project->id, $data);

            if ($newStatus !== $oldStatus) {
                $this->activity->record(
                    $project,
                    'project.status_changed',
                    "Project {$project->project_code} status changed",
                    "Status changed from '{$oldStatus}' to '{$newStatus}'",
                    $project,
                    ['old' => $oldStatus, 'new' => $newStatus],
                );
            } else {
                $this->activity->record(
                    $project,
                    'project.updated',
                    "Project {$project->project_code} updated",
                    "Project '{$project->name}' details updated",
                    $project,
                );
            }

            return $project;
        });
    }

    public function delete(Project $project): bool
    {
        return DB::transaction(function () use ($project) {
            $this->activity->record(
                $project,
                'project.deleted',
                "Project {$project->project_code} deleted",
                "Project '{$project->name}' was deleted",
                $project,
            );

            return $this->projects->delete($project->id);
        });
    }
}
