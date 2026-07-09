<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\ActivityLog;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Repositories\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $logs,
    ) {
    }

    public function record(
        Project $project,
        string $eventType,
        string $title,
        ?string $description = null,
        ?Model $subject = null,
        array $metadata = [],
    ): ActivityLog {
        return $this->logs->create([
            'tenant_id'    => $project->tenant_id,
            'project_id'   => $project->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject?->getKey(),
            'event_type'   => $eventType,
            'title'        => $title,
            'description'  => $description,
            'triggered_by' => auth()->id(),
            'metadata'     => $metadata ?: null,
        ]);
    }

    public function forProject(Project $project, int $limit = 50): Collection
    {
        return $this->logs->forProject($project->id, $limit);
    }
}
