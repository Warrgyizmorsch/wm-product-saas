<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\ActivityLog;
use Illuminate\Database\Eloquent\Collection;

interface ActivityLogRepositoryInterface
{
    public function create(array $data): ActivityLog;

    public function forProject(int $projectId, int $limit = 50): Collection;

    /**
     * Activity for a set of polymorphic subjects within a project, keyed by
     * fully-qualified subject class => array of subject ids.
     *
     * @param array<class-string, array<int>> $subjectTypeToIds
     */
    public function forSubjects(int $projectId, array $subjectTypeToIds, int $limit = 50): Collection;
}
