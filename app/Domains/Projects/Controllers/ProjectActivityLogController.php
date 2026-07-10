<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\ActivityLogService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ProjectActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activity,
    ) {
    }

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('modules.projects.activity', [
            'project'    => $project,
            'activities' => $this->activity->forProject($project, 200),
        ]);
    }
}
