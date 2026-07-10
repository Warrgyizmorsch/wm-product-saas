<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Repositories\MilestoneRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MilestoneService
{
    public function __construct(
        private readonly MilestoneRepositoryInterface $milestones,
        private readonly ActivityLogService $activity,
    ) {
    }

    public function list(Project $project): Collection
    {
        return $this->milestones->getForProject($project->id);
    }

    public function paginateAll(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->milestones->paginateAll($filters, $perPage);
    }

    public function find(int $id): ?Milestone
    {
        return $this->milestones->find($id);
    }

    public function create(Project $project, array $data): Milestone
    {
        return DB::transaction(function () use ($project, $data) {
            $data['project_id'] = $project->id;
            $data['tenant_id'] = $project->tenant_id;

            $milestone = $this->milestones->create($data);

            $this->activity->record(
                $project,
                'milestone.created',
                "Milestone '{$milestone->name}' created",
                null,
                $milestone,
            );

            return $milestone;
        });
    }

    public function update(Milestone $milestone, array $data): Milestone
    {
        $oldStatus = $milestone->status;
        $newStatus = $data['status'] ?? $oldStatus;

        return DB::transaction(function () use ($milestone, $data, $oldStatus, $newStatus) {
            $project = $milestone->project;
            $milestone = $this->milestones->update($milestone->id, $data);

            if ($newStatus !== $oldStatus && $newStatus === Milestone::STATUS_COMPLETED) {
                $this->activity->record(
                    $project,
                    'milestone.completed',
                    "Milestone '{$milestone->name}' completed",
                    null,
                    $milestone,
                    ['old_status' => $oldStatus, 'new_status' => $newStatus],
                );
            } else {
                $this->activity->record(
                    $project,
                    'milestone.updated',
                    "Milestone '{$milestone->name}' updated",
                    null,
                    $milestone,
                );
            }

            return $milestone;
        });
    }

    public function delete(Milestone $milestone): bool
    {
        return DB::transaction(function () use ($milestone) {
            $this->activity->record(
                $milestone->project,
                'milestone.deleted',
                "Milestone '{$milestone->name}' deleted",
                null,
                $milestone,
            );

            return $this->milestones->delete($milestone->id);
        });
    }
}
