<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Services\ActivityLogService;
use App\Domains\Projects\Services\ProjectService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ProjectActivityLogController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly ActivityLogService $activity,
    ) {
    }

    public function index(int $projectId): View
    {
        $project = $this->projects->find($projectId);

        if (!$project) {
            abort(404, 'Project not found.');
        }

        $this->authorize('view', $project);

        return view('modules.projects.activity', [
            'project'    => $project,
            'activities' => $this->activity->forProject($project, 200),
        ]);
    }
}
